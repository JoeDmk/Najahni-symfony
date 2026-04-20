<?php

namespace App\Service\Investment;

use App\Entity\InvestmentContract;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\Writer\PngWriter;

class ContractQrCodeService
{
    public function __construct(
        private readonly string $publicBaseUrl = '',
    ) {
    }

    public function buildResult(InvestmentContract $contract, string $verifyUrl, int $size = 300, int $margin = 10): object
    {
        return (new Builder(
            writer: new PngWriter(),
            data: $this->buildPayload($contract, $verifyUrl),
            encoding: new Encoding('UTF-8'),
            size: $size,
            margin: $margin,
        ))->build();
    }

    public function buildDataUri(InvestmentContract $contract, string $verifyUrl, int $size = 200, int $margin = 6): string
    {
        return $this->buildResult($contract, $verifyUrl, $size, $margin)->getDataUri();
    }

    public function resolveVerificationUrl(string $verifyUrl): string
    {
        $normalizedBaseUrl = rtrim(trim($this->publicBaseUrl), '/');
        if ($normalizedBaseUrl === '') {
            return $verifyUrl;
        }

        $path = parse_url($verifyUrl, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            return $normalizedBaseUrl;
        }

        $resolvedUrl = $normalizedBaseUrl . $path;
        $query = parse_url($verifyUrl, PHP_URL_QUERY);
        if (is_string($query) && $query !== '') {
            $resolvedUrl .= '?' . $query;
        }

        return $resolvedUrl;
    }

    public function isPublicVerificationUrl(string $verifyUrl): bool
    {
        return $this->isPublicVerifyUrl($this->resolveVerificationUrl($verifyUrl));
    }

    private function buildPayload(InvestmentContract $contract, string $verifyUrl): string
    {
        return $this->resolveVerificationUrl($verifyUrl);
    }

    private function isPublicVerifyUrl(string $verifyUrl): bool
    {
        $host = parse_url($verifyUrl, PHP_URL_HOST);
        if (!is_string($host) || $host === '') {
            return false;
        }

        $normalizedHost = strtolower($host);
        if ($normalizedHost === 'localhost' || $normalizedHost === '127.0.0.1' || $normalizedHost === '::1' || str_ends_with($normalizedHost, '.local')) {
            return false;
        }

        if (filter_var($normalizedHost, FILTER_VALIDATE_IP)) {
            return filter_var($normalizedHost, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
        }

        return true;
    }
}