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

        // Try direct PurgoMalum API first (free, no key needed)
        $response = $this->httpClient->request('GET', 'https://www.purgomalum.com/service/containsprofanity', [
            'query' => ['text' => $text],
            'timeout' => 10,
            'verify_peer' => false,
            'verify_host' => false,
        ]);

        $result = trim($response->getContent(false));

        return strtolower($result) === 'true';
    }
}
