<?php

namespace App\Service\Investment;

use App\Entity\InvestmentContract;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\Writer\PngWriter;

class ContractQrCodeService
{
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

    private function buildPayload(InvestmentContract $contract, string $verifyUrl): string
    {
        if ($this->isPublicVerifyUrl($verifyUrl)) {
            return $verifyUrl;
        }

        $projectTitle = $contract->getOffer()?->getOpportunity()?->getProject()?->getTitre() ?? 'Projet';
        $amount = $contract->getOffer()?->getProposedAmount();

        return implode("\n", array_filter([
            'Najahni - Verification contrat d\'investissement',
            'Contrat: #' . $contract->getId(),
            'Statut: ' . ($contract->isFullySigned() ? 'VALIDE - signe par les deux parties' : 'NON VALIDE'),
            'Projet: ' . $projectTitle,
            $amount !== null ? 'Montant: ' . number_format((float) $amount, 0, ',', ' ') . ' DT' : null,
            'Investisseur: ' . ($contract->getInvestor()?->getFullName() ?? 'N/A'),
            'Entrepreneur: ' . ($contract->getEntrepreneur()?->getFullName() ?? 'N/A'),
            'Signature investisseur: ' . ($contract->getInvestorSignedAt()?->format('d/m/Y H:i') ?? 'N/A'),
            'Signature entrepreneur: ' . ($contract->getEntrepreneurSignedAt()?->format('d/m/Y H:i') ?? 'N/A'),
            'Empreinte SHA-256: ' . $contract->getTermsDigest(),
            'Lien web public indisponible sur cette instance locale.',
        ]));
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