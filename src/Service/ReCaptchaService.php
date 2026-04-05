<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ReCaptchaService
{
    private const VERIFY_URL = 'https://www.google.com/recaptcha/api/siteverify';
    private const PLACEHOLDER_VALUES = [
        '',
        'your_recaptcha_secret_key',
        'your_recaptcha_site_key',
    ];

    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        private string $secretKey,
        private float $scoreThreshold = 0.5,
        private bool $enabled = true,
    ) {}

    public function verify(string $token, ?string $remoteIp = null): bool
    {
        if (!$this->isEnabled()) {
            return true;
        }

        if (empty($token)) {
            return false;
        }

        try {
            $params = [
                'secret' => $this->secretKey,
                'response' => $token,
            ];

            if ($remoteIp) {
                $params['remoteip'] = $remoteIp;
            }

            $response = $this->httpClient->request('POST', self::VERIFY_URL, [
                'body' => $params,
            ]);

            $data = $response->toArray();

            if (empty($data['success'])) {
                $this->logger->warning('reCAPTCHA verification failed', [
                    'error-codes' => $data['error-codes'] ?? [],
                ]);
                return false;
            }

            if (isset($data['score']) && $data['score'] < $this->scoreThreshold) {
                $this->logger->warning('reCAPTCHA score too low', [
                    'score' => $data['score'],
                    'threshold' => $this->scoreThreshold,
                ]);
                return false;
            }

            return true;
        } catch (\Throwable $e) {
            $this->logger->error('reCAPTCHA verification error: ' . $e->getMessage());
            // Fail open in case of API issues to not block users
            return true;
        }
    }

    public function isEnabled(): bool
    {
        return $this->enabled && !$this->isPlaceholderValue($this->secretKey);
    }

    private function isPlaceholderValue(string $value): bool
    {
        return in_array(trim($value), self::PLACEHOLDER_VALUES, true);
    }
}
