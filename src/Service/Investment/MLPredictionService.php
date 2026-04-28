<?php

namespace App\Service\Investment;

use App\Entity\InvestmentOpportunity;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class MLPredictionService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $predictionUrl,
    ) {
    }

    public function predictSuccessProbability(InvestmentOpportunity $opportunity, float $economicScore, int $offerCount): ?array
    {
        try {
            $response = $this->httpClient->request('POST', $this->buildUrl('/predict'), [
                'timeout' => 3,
                'headers' => [
                    'Accept' => 'application/json',
                ],
                'json' => [
                    'sector' => $this->encodeSector($opportunity->getProject()?->getSecteur()),
                    'amount' => (float) $opportunity->getTargetAmount(),
                    'duration_days' => $this->resolveDurationDays($opportunity),
                    'economic_score' => round($economicScore, 2),
                    'offer_count' => max(0, $offerCount),
                ],
            ]);

            if ($response->getStatusCode() !== 200) {
                return null;
            }

            $data = $response->toArray(false);
            if (!is_array($data) || !isset($data['success_probability']) || !is_numeric($data['success_probability'])) {
                return null;
            }

            return $data;
        } catch (ExceptionInterface|\Throwable) {
            return null;
        }
    }

    public function isAvailable(): bool
    {
        try {
            $response = $this->httpClient->request('GET', $this->buildUrl('/health'), [
                'timeout' => 1,
                'headers' => [
                    'Accept' => 'application/json',
                ],
            ]);

            if ($response->getStatusCode() !== 200) {
                return false;
            }

            $data = $response->toArray(false);

            return is_array($data)
                && ($data['status'] ?? null) === 'ok'
                && ($data['model_loaded'] ?? false) === true;
        } catch (ExceptionInterface|\Throwable) {
            return false;
        }
    }

    private function buildUrl(string $path): string
    {
        return rtrim($this->predictionUrl, '/') . $path;
    }

    private function resolveDurationDays(InvestmentOpportunity $opportunity): int
    {
        $deadline = $opportunity->getDeadline();
        if ($deadline === null) {
            return 90;
        }

        $days = (int) $opportunity->getCreatedAt()->diff($deadline)->format('%r%a');

        return max(1, $days);
    }

    private function encodeSector(?string $sector): int
    {
        $normalized = mb_strtolower(trim((string) $sector));
        if ($normalized === '') {
            return 6;
        }

        $mapping = [
            1 => ['tech', 'technologie', 'software', 'saas', 'digital', 'data', 'intelligence artificielle', 'ia'],
            2 => ['health', 'sante', 'med', 'medical', 'biotech', 'diagnostic', 'pharma'],
            3 => ['agri', 'agriculture', 'food', 'agro', 'alimentaire'],
            4 => ['energie', 'energy', 'renewable', 'clean', 'solar', 'climate', 'environnement'],
            5 => ['fin', 'finance', 'service', 'tourisme', 'education', 'commerce', 'retail'],
            6 => ['industrie', 'industrial', 'logistique', 'logistics', 'textile', 'manufacturing'],
        ];

        foreach ($mapping as $encoded => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($normalized, $keyword)) {
                    return $encoded;
                }
            }
        }

        return 6;
    }
}