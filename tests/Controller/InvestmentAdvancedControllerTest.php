<?php

namespace App\Tests\Controller;

use App\Controller\InvestmentAdvancedController;
use App\Entity\InvestmentOpportunity;
use App\Entity\Projet;
use PHPUnit\Framework\TestCase;

class InvestmentAdvancedControllerTest extends TestCase
{
    public function testBuild30DayOutlookClassifiesDefensiveWindow(): void
    {
        $controller = new InvestmentAdvancedController();
        $method = new \ReflectionMethod($controller, 'build30DayOutlook');

        $projectOne = (new Projet())->setSecteur('Technologie');
        $projectTwo = (new Projet())->setSecteur('Technologie');

        $opportunityOne = (new InvestmentOpportunity())
            ->setProject($projectOne)
            ->setTargetAmount('50000')
            ->setRiskScore(80.0)
            ->setDeadline(new \DateTimeImmutable('+10 days'));

        $opportunityTwo = (new InvestmentOpportunity())
            ->setProject($projectTwo)
            ->setTargetAmount('65000')
            ->setRiskScore(55.0)
            ->setDeadline(new \DateTimeImmutable('+45 days'));

        $outlook = $method->invoke($controller, [
            'inflationRate' => 8.5,
            'gdpBillions' => 46.7,
            'exchangeRateEurUsd' => 1.01,
        ], [$opportunityOne, $opportunityTwo]);

        self::assertSame('defensive', $outlook['tone']);
        self::assertSame('Defensif', $outlook['toneLabel']);
        self::assertSame('Technologie', $outlook['dominantSector']);
        self::assertSame(1, $outlook['expiringSoon']);
        self::assertSame(1, $outlook['highRiskCount']);
        self::assertCount(3, $outlook['signals']);
    }
}