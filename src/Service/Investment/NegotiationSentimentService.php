<?php

namespace App\Service\Investment;

use App\Entity\InvestmentContract;
use App\Entity\InvestmentContractMessage;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class NegotiationSentimentService
{
    private const MODEL_URL = 'https://api-inference.huggingface.co/models/nlptown/bert-base-multilingual-uncased-sentiment';
    private const CACHE_TTL = 60;
    private const MESSAGE_LIMIT = 10;
    private const MESSAGE_LENGTH_LIMIT = 220;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly CacheInterface $cache,
        private readonly LoggerInterface $logger,
        private readonly string $hfToken,
    ) {
    }

    /**
     * @param list<InvestmentContractMessage> $messages
     */
    public function analyze(InvestmentContract $contract, array $messages): array
    {
        $conversationMessages = array_values(array_filter(
            $messages,
            static fn (InvestmentContractMessage $message): bool => !$message->isSystemMessage() && trim($message->getBody()) !== ''
        ));

        $latestMessage = $conversationMessages !== [] ? $conversationMessages[array_key_last($conversationMessages)] : null;
        $latestMessageId = $latestMessage?->getId();

        if ($conversationMessages === []) {
            return $this->buildFallbackPayload(0, $latestMessageId, 'Pas assez de messages utilisateur pour analyser la tonalite de la negociation.');
        }

        $cacheKey = sprintf(
            'contract_sentiment_%d_%d_%d',
            $contract->getId() ?? 0,
            $latestMessageId ?? 0,
            count($conversationMessages)
        );

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($contract, $conversationMessages, $latestMessageId): array {
            $item->expiresAfter(self::CACHE_TTL);

            return $this->runInference($contract, $conversationMessages, $latestMessageId);
        });
    }

    public function isConfigured(): bool
    {
        return trim($this->hfToken) !== '' && trim($this->hfToken) !== 'your_huggingface_token_here';
    }

    /**
     * @param list<InvestmentContractMessage> $messages
     */
    private function runInference(InvestmentContract $contract, array $messages, ?int $latestMessageId): array
    {
        if (!$this->isConfigured()) {
            return $this->buildFallbackPayload(count($messages), $latestMessageId, 'Analyse IA indisponible. La tonalite est affichee en mode neutre par defaut.');
        }

        try {
            $response = $this->httpClient->request('POST', self::MODEL_URL, [
                'timeout' => 20,
                'verify_peer' => false,
                'verify_host' => false,
                'headers' => [
                    'Authorization' => 'Bearer ' . trim($this->hfToken),
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
                'json' => [
                    'inputs' => $this->buildConversationInput($contract, $messages),
                    'parameters' => [
                        'top_k' => 5,
                    ],
                    'options' => [
                        'wait_for_model' => true,
                    ],
                ],
            ]);

            if ($response->getStatusCode() !== 200) {
                return $this->buildFallbackPayload(count($messages), $latestMessageId, 'Analyse IA temporairement indisponible.');
            }

            $data = $response->toArray(false);
            $score = $this->extractSignedScore($data);

            return $this->buildPayload($score, count($messages), $latestMessageId, 'hf');
        } catch (\Throwable $exception) {
            $this->logger->warning('Negotiation sentiment inference failed.', [
                'contractId' => $contract->getId(),
                'messageCount' => count($messages),
                'exception' => $exception,
            ]);

            return $this->buildFallbackPayload(count($messages), $latestMessageId, 'Analyse IA temporairement indisponible.');
        }
    }

    /**
     * @param list<InvestmentContractMessage> $messages
     */
    private function buildConversationInput(InvestmentContract $contract, array $messages): string
    {
        $lines = [];

        foreach (array_slice($messages, -self::MESSAGE_LIMIT) as $message) {
            $role = 'participant';
            if ($message->getSender()?->getId() === $contract->getInvestor()?->getId()) {
                $role = 'investisseur';
            } elseif ($message->getSender()?->getId() === $contract->getEntrepreneur()?->getId()) {
                $role = 'entrepreneur';
            }

            $body = preg_replace('/\s+/', ' ', trim($message->getBody()));
            $body = mb_substr((string) $body, 0, self::MESSAGE_LENGTH_LIMIT);
            $lines[] = $role . ': ' . $body;
        }

        return implode("\n", $lines);
    }

    private function extractSignedScore(array $rawResponse): float
    {
        $predictions = $this->normalizePredictions($rawResponse);
        $weightedStars = 0.0;
        $totalWeight = 0.0;

        foreach ($predictions as $prediction) {
            if (!is_array($prediction)) {
                continue;
            }

            $stars = $this->extractStars((string) ($prediction['label'] ?? ''));
            $weight = (float) ($prediction['score'] ?? 0.0);
            if ($stars === null || $weight <= 0) {
                continue;
            }

            $weightedStars += $stars * $weight;
            $totalWeight += $weight;
        }

        if ($totalWeight <= 0) {
            return 0.0;
        }

        $averageStars = $weightedStars / $totalWeight;
        $signedScore = (($averageStars - 3.0) / 2.0) * 100.0;

        return max(-100.0, min(100.0, $signedScore));
    }

    private function normalizePredictions(array $rawResponse): array
    {
        if (array_is_list($rawResponse) && isset($rawResponse[0]) && is_array($rawResponse[0]) && array_is_list($rawResponse[0])) {
            return $rawResponse[0];
        }

        return array_is_list($rawResponse) ? $rawResponse : [];
    }

    private function extractStars(string $label): ?int
    {
        if (preg_match('/([1-5])/', $label, $matches) !== 1) {
            return null;
        }

        return (int) $matches[1];
    }

    private function buildPayload(float $signedScore, int $messageCount, ?int $latestMessageId, string $source): array
    {
        $label = 'neutral';
        if ($signedScore > 20) {
            $label = 'positive';
        } elseif ($signedScore < -20) {
            $label = 'negative';
        }

        $summary = match ($label) {
            'positive' => 'Tonalite cooperative detectee sur les derniers echanges.',
            'negative' => 'Des signes de tension apparaissent dans les derniers echanges.',
            default => 'La conversation reste globalement mesuree et prudente.',
        };

        return [
            'label' => $label,
            'score' => (int) round($signedScore),
            'normalizedScore' => (int) round(($signedScore + 100.0) / 2.0),
            'messageCount' => $messageCount,
            'latestMessageId' => $latestMessageId,
            'summary' => $summary,
            'source' => $source,
        ];
    }

    private function buildFallbackPayload(int $messageCount, ?int $latestMessageId, string $summary): array
    {
        return [
            'label' => 'neutral',
            'score' => 0,
            'normalizedScore' => 50,
            'messageCount' => $messageCount,
            'latestMessageId' => $latestMessageId,
            'summary' => $summary,
            'source' => 'fallback',
        ];
    }
}