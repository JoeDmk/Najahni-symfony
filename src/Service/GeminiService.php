<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class GeminiService
{
    private const GROQ_ENDPOINT = 'https://api.groq.com/openai/v1/chat/completions';
    private const GROQ_MODEL = 'llama-3.3-70b-versatile';

    private ?string $groqApiKey;
    private ?string $geminiApiKey;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        string $geminiApiKey,
        string $groqApiKey,
    ) {
        $this->geminiApiKey = $geminiApiKey ?: null;
        $this->groqApiKey = $groqApiKey ?: null;
    }

    public function isConfigured(): bool
    {
        return $this->groqApiKey !== null && $this->groqApiKey !== 'your_groq_api_key';
    }

    public function generate(string $prompt, float $temperature = 0.7): ?string
    {
        if (!$this->isConfigured()) {
            $this->logger->warning('GeminiService: Groq API key not configured');
            return null;
        }

        try {
            $response = $this->httpClient->request('POST', self::GROQ_ENDPOINT, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->groqApiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => self::GROQ_MODEL,
                    'temperature' => $temperature,
                    'max_tokens' => 4096,
                    'messages' => [
                        ['role' => 'system', 'content' => 'Tu es un expert en entrepreneuriat et business en Tunisie. Réponds toujours en français.'],
                        ['role' => 'user', 'content' => $prompt],
                    ],
                ],
                'timeout' => 30,
            ]);

            $statusCode = $response->getStatusCode();
            $data = $response->toArray(false);

            if ($statusCode < 200 || $statusCode >= 300) {
                $this->logger->error('Groq API error', [
                    'status' => $statusCode,
                    'error' => $data['error']['message'] ?? json_encode($data),
                ]);
                return null;
            }

            $content = $data['choices'][0]['message']['content'] ?? null;

            return is_string($content) && trim($content) !== '' ? trim($content) : null;
        } catch (\Throwable $e) {
            $this->logger->error('Groq API exception: ' . $e->getMessage());
            return null;
        }
    }

    public function generateJson(string $prompt, float $temperature = 0.3): ?array
    {
        $result = $this->generate($prompt, $temperature);
        if ($result === null) {
            return null;
        }

        $result = trim($result);
        if (str_starts_with($result, '```json')) {
            $result = substr($result, 7);
        }
        if (str_starts_with($result, '```')) {
            $result = substr($result, 3);
        }
        if (str_ends_with($result, '```')) {
            $result = substr($result, 0, -3);
        }

        $decoded = json_decode(trim($result), true);

        return is_array($decoded) ? $decoded : null;
    }

    public function analyzeSentiment(string $text): ?array
    {
        $prompt = <<<PROMPT
Analyse le sentiment du texte suivant d'un projet entrepreneurial. Retourne un JSON avec:
- "sentiment": "positif", "neutre" ou "négatif"
- "score": nombre entre -1.0 (très négatif) et 1.0 (très positif)
- "confiance": nombre entre 0 et 1
- "emotions": tableau des émotions détectées (ex: "optimisme", "ambition", "prudence", "inquiétude")
- "tonalite": description courte du ton général (1 phrase)
- "mots_cles_positifs": tableau des mots/expressions positifs trouvés
- "mots_cles_negatifs": tableau des mots/expressions négatifs trouvés

Texte à analyser:
"""
{$text}
"""

Retourne UNIQUEMENT le JSON, rien d'autre.
PROMPT;

        return $this->generateJson($prompt, 0.2);
    }
}
