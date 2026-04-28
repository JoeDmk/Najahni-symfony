<?php

namespace App\Tests\Service;

use App\Entity\InvestmentOffer;
use App\Entity\InvestmentOpportunity;
use App\Entity\Projet;
use App\Entity\User;
use App\Service\Investment\InvestmentOfferValidator;
use PHPUnit\Framework\TestCase;

class InvestmentOfferValidatorTest extends TestCase
{
    private InvestmentOfferValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new InvestmentOfferValidator();
    }

    public function testValidOffer(): void
    {
        $investor = $this->createUserWithId(1);
        $opportunity = $this->createOpportunity(100000, 2, 20000);

        self::assertTrue($this->validator->validate($investor, $opportunity, 50000));
    }

    public function testOfferAmountIsZero(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Le montant de l'offre doit être supérieur à zéro");

        $investor = $this->createUserWithId(1);
        $opportunity = $this->createOpportunity(100000, 2);

        $this->validator->validate($investor, $opportunity, 0);
    }

    public function testOfferAmountIsNegative(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Le montant de l'offre doit être supérieur à zéro");

        $investor = $this->createUserWithId(1);
        $opportunity = $this->createOpportunity(100000, 2);

        $this->validator->validate($investor, $opportunity, -500);
    }

    public function testOfferExceedsRemainingFunding(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le montant dépasse le financement restant disponible');

        $investor = $this->createUserWithId(1);
        $opportunity = $this->createOpportunity(20000, 2, 10000);

        $this->validator->validate($investor, $opportunity, 50000);
    }

    public function testInvestorIsProjectOwner(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Un entrepreneur ne peut pas investir dans son propre projet');

        $investor = $this->createUserWithId(7);
        $opportunity = $this->createOpportunity(100000, 7);

        $this->validator->validate($investor, $opportunity, 1000);
    }

    private function createOpportunity(float $targetAmount, int $ownerId, float $totalAlreadyFunded = 0): InvestmentOpportunity
    {
        $owner = $this->createUserWithId($ownerId);
        $project = (new Projet())
            ->setUser($owner)
            ->setTitre('Projet test')
            ->setSecteur('Technologie');

        $opportunity = (new InvestmentOpportunity())
            ->setProject($project)
            ->setTargetAmount(number_format($targetAmount, 2, '.', ''));

        if ($totalAlreadyFunded > 0) {
            $fundedOffer = (new InvestmentOffer())
                ->setOpportunity($opportunity)
                ->setInvestor($this->createUserWithId(99))
                ->setProposedAmount(number_format($totalAlreadyFunded, 2, '.', ''))
                ->setPaid(true);

            $opportunity->getOffers()->add($fundedOffer);
        }

        return $opportunity;
    }

    private function createUserWithId(int $id): User
    {
        $user = (new User())
            ->setFirstname('Test')
            ->setLastname('User')
            ->setEmail(sprintf('user%d@example.com', $id))
            ->setPassword('secret');

        $this->setPrivateProperty($user, 'id', $id);

        return $user;
    }

    private function setPrivateProperty(object $object, string $property, mixed $value): void
    {
        $reflection = new \ReflectionProperty($object, $property);
        $reflection->setValue($object, $value);
    }
}