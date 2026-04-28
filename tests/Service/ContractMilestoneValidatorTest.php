<?php

namespace App\Tests\Service;

use App\Entity\ContractMilestone;
use App\Service\Investment\ContractMilestoneValidator;
use PHPUnit\Framework\TestCase;

class ContractMilestoneValidatorTest extends TestCase
{
    private ContractMilestoneValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new ContractMilestoneValidator();
    }

    public function testValidMilestones(): void
    {
        $milestones = [
            $this->createMilestone(30),
            $this->createMilestone(40),
            $this->createMilestone(30),
        ];

        self::assertTrue($this->validator->validate($milestones));
    }

    public function testTooFewMilestones(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le nombre de jalons doit être compris entre 2 et 4');

        $this->validator->validate([$this->createMilestone(100)]);
    }

    public function testTooManyMilestones(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le nombre de jalons doit être compris entre 2 et 4');

        $this->validator->validate([
            $this->createMilestone(20),
            $this->createMilestone(20),
            $this->createMilestone(20),
            $this->createMilestone(20),
            $this->createMilestone(20),
        ]);
    }

    public function testPercentageTooLow(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Chaque jalon doit représenter entre 5% et 80% du montant total');

        $this->validator->validate([
            $this->createMilestone(3),
            $this->createMilestone(47),
            $this->createMilestone(50),
        ]);
    }

    public function testPercentageTooHigh(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Chaque jalon doit représenter entre 5% et 80% du montant total');

        $this->validator->validate([
            $this->createMilestone(85),
            $this->createMilestone(10),
            $this->createMilestone(5),
        ]);
    }

    public function testPercentagesDontSumToHundred(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La somme des pourcentages des jalons doit être égale à 100%');

        $this->validator->validate([
            $this->createMilestone(30),
            $this->createMilestone(30),
            $this->createMilestone(30),
        ]);
    }

    public function testValidTwoMilestones(): void
    {
        $milestones = [
            $this->createMilestone(60),
            $this->createMilestone(40),
        ];

        self::assertTrue($this->validator->validate($milestones));
    }

    private function createMilestone(float $percentage): ContractMilestone
    {
        return (new ContractMilestone())
            ->setLabel('Jalon test')
            ->setPercentage(number_format($percentage, 2, '.', ''));
    }
}