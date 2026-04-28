<?php

namespace App\Tests\Entity;

use App\Entity\InvestmentOffer;
use App\Entity\InvestmentOpportunity;
use App\Entity\InvestorProfile;
use App\Entity\Projet;
use PHPUnit\Framework\TestCase;

class InvestorProfileTest extends TestCase
{
    public function testAdaptFromInvestmentLearnsFromConfirmedBehavior(): void
    {
        $profile = (new InvestorProfile())
            ->setPreferredSectors('tech')
            ->setRiskTolerance(4)
            ->setBudgetMin('1000.00')
            ->setBudgetMax('8000.00')
            ->setHorizonMonths(12);

        $project = (new Projet())
            ->setTitre('AgriTech Vision')
            ->setSecteur('agritech')
            ->setDescription('Plateforme de suivi intelligent pour cultures.');

        $opportunity = (new InvestmentOpportunity())
            ->setProject($project)
            ->setTargetAmount('50000')
            ->setRiskScore(70.0)
            ->setDeadline(new \DateTimeImmutable('+6 months'));

        $offer = (new InvestmentOffer())
            ->setOpportunity($opportunity)
            ->setProposedAmount('12000');

        $updatedBefore = $profile->getUpdatedAt()->getTimestamp();

        $profile->adaptFromInvestment($offer, $opportunity);

        self::assertContains('agritech', $profile->getSectorArray());
        self::assertGreaterThan(4, $profile->getRiskTolerance());
        self::assertGreaterThan(1000.0, (float) $profile->getBudgetMin());
        self::assertGreaterThan(8000.0, (float) $profile->getBudgetMax());
        self::assertSame('Modere', $profile->getImpliedRiskTolerance());
        self::assertGreaterThanOrEqual($updatedBefore, $profile->getUpdatedAt()->getTimestamp());
    }
}