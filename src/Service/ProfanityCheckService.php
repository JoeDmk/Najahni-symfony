<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ProfanityCheckService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly string $rapidApiKey,
        private readonly string $rapidApiHost,
        private readonly string $rapidApiUrl
    ) {
    }

    public function containsProfanity(string $text): bool
    {
        $text = trim($text);
        if ($text === '') {
            return false;
        }

        $response = $this->httpClient->request('GET', $this->rapidApiUrl, [
            'query' => ['text' => $text],
            'headers' => [
                'Content-Type' => 'application/json',
                'x-rapidapi-host' => $this->rapidApiHost,
                'x-rapidapi-key' => $this->rapidApiKey,
            ],
            'timeout' => 10,
            'verify_peer' => false,
            'verify_host' => false,
        ]);

        $payload = $response->toArray(false);

        if (isset($payload['has_profanity'])) {
            return (bool) $payload['has_profanity'];
        }

        if (isset($payload['is_profane'])) {
            return (bool) $payload['is_profane'];
        }

        if (isset($payload['profanity']) && is_array($payload['profanity'])) {
            return count($payload['profanity']) > 0;
        }

        $this->logger->warning('Unexpected profanity API payload.', ['payload' => $payload]);

        return false;
    }
}
