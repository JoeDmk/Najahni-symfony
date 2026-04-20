<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class ExchangeRateService
{
    private const API_URL = 'https://api.exchangerate-api.com/v4/latest/TND';
    private const CACHE_KEY = 'exchange_rates_tnd';
    private const CACHE_TTL = 3600; // 1 hour

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly CacheInterface $cache,
    ) {}

    public function getRates(): array
    {
        try {
            return $this->cache->get(self::CACHE_KEY, function (ItemInterface $item): array {
                $item->expiresAfter(self::CACHE_TTL);

                $response = $this->httpClient->request('GET', self::API_URL, [
                    'timeout' => 10,
                ]);

                $data = $response->toArray(false);

                if (!isset($data['rates'])) {
                    throw new \RuntimeException('Invalid exchange rate response');
                }

                return [
                    'EUR' => $data['rates']['EUR'] ?? null,
                    'USD' => $data['rates']['USD'] ?? null,
                    'GBP' => $data['rates']['GBP'] ?? null,
                    'base' => 'TND',
                    'date' => $data['date'] ?? date('Y-m-d'),
                ];
            });
        } catch (\Throwable $e) {
            $this->logger->error('Exchange rate API error: ' . $e->getMessage());
            return [
                'EUR' => null,
                'USD' => null,
                'GBP' => null,
                'base' => 'TND',
                'date' => date('Y-m-d'),
            ];
        }
    }

    public function convert(float $amountDT, string $currency = 'EUR'): ?float
    {
        $rates = $this->getRates();
        $rate = $rates[$currency] ?? null;

        if ($rate === null || $rate == 0) {
            return null;
        }

        return round($amountDT * $rate, 2);
    }
}
