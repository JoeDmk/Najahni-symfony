<?php
namespace App\Repository;
use App\Entity\InvestmentOffer;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
class InvestmentOfferRepository extends ServiceEntityRepository {
    public function __construct(ManagerRegistry $registry) { parent::__construct($registry, InvestmentOffer::class); }
    public function findByInvestor($user): array { return $this->findBy(['investor' => $user], ['id' => 'DESC']); }
    public function findByOpportunity($opp): array { return $this->findBy(['opportunity' => $opp], ['id' => 'DESC']); }
}
