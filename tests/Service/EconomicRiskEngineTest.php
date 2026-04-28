<?php

namespace App\Tests\Service;

use App\Service\Investment\EconomicRiskEngine;
use PHPUnit\Framework\TestCase;

class EconomicRiskEngineTest extends TestCase
{
    private EconomicRiskEngine $engine;

    protected function setUp(): void
    {
        $this->engine = new EconomicRiskEngine();
    }

    public function testLowRiskScore(): void
    {
        $score = $this->engine->calculateFullRisk(
            5000,
            new \DateTimeImmutable('+365 days'),
            [
                'dataAvailable' => true,
                'exchangeRateEurUsd' => 1.10,
                'gdpBillions' => 2500.0,
                'inflationRate' => 2.0,
            ]
        );

        self::assertLessThan(40, $score);
    }

    public function testHighRiskScore(): void
    {
        $score = $this->engine->calculateFullRisk(
            100000,
            new \DateTimeImmutable('+30 days'),
            [
                'dataAvailable' => true,
                'exchangeRateEurUsd' => 0.60,
                'gdpBillions' => 10.0,
                'inflationRate' => 18.0,
            ]
        );

        self::assertGreaterThan(65, $score);
    }

    public function testScoreIsWithinBounds(): void
    {
        $score = $this->engine->calculateFullRisk(
            20000,
            new \DateTimeImmutable('+120 days'),
            [
                'dataAvailable' => true,
                'exchangeRateEurUsd' => 0.95,
                'gdpBillions' => 300.0,
                'inflationRate' => 6.0,
            ]
        );

        self::assertGreaterThanOrEqual(0, $score);
        self::assertLessThanOrEqual(100, $score);
    }

    public function testDeterministicVerdict(): void
    {
        $economicData = [
            'countryName' => 'Tunisie',
            'inflationRate' => 4.5,
            'gdpBillions' => 55.0,
        ];
        $deadline = new \DateTimeImmutable('+180 days');

        $firstVerdict = $this->engine->buildDeterministicVerdict(48, $economicData, 30000, $deadline);
        $secondVerdict = $this->engine->buildDeterministicVerdict(48, $economicData, 30000, $deadline);

        self::assertSame($firstVerdict, $secondVerdict);
    }

    public function testEconomicFactorDominatesScore(): void
    {
        $score = $this->engine->calculateFullRisk(
            5000,
            new \DateTimeImmutable('+365 days'),
            [
                'dataAvailable' => true,
                'exchangeRateEurUsd' => 0.50,
                'gdpBillions' => 1.0,
                'inflationRate' => 25.0,
            ]
        );

        self::assertGreaterThanOrEqual(50, $score);
    }
}