<?php

namespace App\Service\Investment;

use App\Entity\InvestmentOpportunity;
use App\Entity\User;

class InvestmentOfferValidator
{
    public function validate(User $investor, InvestmentOpportunity $opportunity, float $amount): bool
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException("Le montant de l'offre doit être supérieur à zéro");
        }

        $remainingFunding = (float) $opportunity->getTargetAmount() - $opportunity->getTotalFunded();
        if ($amount > $remainingFunding) {
            throw new \InvalidArgumentException('Le montant dépasse le financement restant disponible');
        }

        $ownerId = $opportunity->getProject()?->getUser()?->getId();
        $investorId = $investor->getId();
        if ($ownerId !== null && $investorId !== null && $investorId === $ownerId) {
            throw new \InvalidArgumentException('Un entrepreneur ne peut pas investir dans son propre projet');
        }

        return true;
    }
}