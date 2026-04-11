<?php

namespace App\Service\Investment;

use App\Entity\InvestmentContract;
use App\Entity\InvestmentOffer;
use App\Entity\User;

class ContractSignatureService
{
    public function refreshDigest(InvestmentContract $contract): string
    {
        $snapshot = [
            'offerId' => $contract->getOffer()?->getId(),
            'amount' => $contract->getOffer()?->getProposedAmount(),
            'project' => $contract->getOffer()?->getOpportunity()?->getProject()?->getTitre(),
            'title' => trim($contract->getTitle()),
            'terms' => trim($contract->getTerms()),
            'equity' => $contract->getEquityPercentage(),
            'consideration' => trim((string) $contract->getConsideration()),
            'milestones' => trim((string) $contract->getMilestones()),
        ];

        $digest = hash('sha256', json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        $contract->setTermsDigest($digest);

        return $digest;
    }

    public function sign(
        InvestmentContract $contract,
        User $user,
        string $signatureName,
        ?string $ipAddress,
        ?string $userAgent,
    ): string {
        if (!$contract->belongsTo($user)) {
            throw new \InvalidArgumentException('Utilisateur non autorise a signer ce contrat.');
        }

        $signedAt = new \DateTime();
        $digest = $this->refreshDigest($contract);
        $role = $contract->getInvestor()?->getId() === $user->getId() ? 'INVESTOR' : 'ENTREPRENEUR';

        $evidence = implode('|', [
            $digest,
            $role,
            trim($signatureName),
            (string) $user->getEmail(),
            $signedAt->format(DATE_ATOM),
            trim((string) $ipAddress),
            trim(substr((string) $userAgent, 0, 255)),
        ]);

        $signatureHash = hash('sha256', $evidence);
        $contract->markSignedBy($user, trim($signatureName), $signatureHash, $signedAt);

        return $signatureHash;
    }

    public function createDefaultTerms(InvestmentOffer $offer): string
    {
        $projectTitle = $offer->getOpportunity()?->getProject()?->getTitre() ?? 'Projet';

        return implode("\n\n", [
            '1. Objet du contrat',
            'Ce contrat formalise la proposition d\'investissement entre l\'entrepreneur et l\'investisseur pour le projet ' . $projectTitle . '.',
            '2. Montant de l\'investissement',
            'L\'investisseur s\'engage a financer le montant accepte de ' . number_format((float) $offer->getProposedAmount(), 2, '.', '') . ' DT, sous reserve de la signature des deux parties.',
            '3. Contreparties et droits',
            'Les parties definissent ci-dessous les contreparties, notamment une participation au capital, un acces prioritaire au produit ou tout autre avantage negocie.',
            '4. Execution',
            'Toute modification des termes annule les signatures precedentes et necessite une nouvelle signature SHA-256 des deux parties.',
        ]);
    }
}