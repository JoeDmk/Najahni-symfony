<?php
namespace App\Repository;
use App\Entity\InvestmentOpportunity;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
class InvestmentOpportunityRepository extends ServiceEntityRepository {
    public function __construct(ManagerRegistry $registry) { parent::__construct($registry, InvestmentOpportunity::class); }
    public function findOpen(): array { return $this->findBy(['status' => 'OPEN'], ['createdAt' => 'DESC']); }
    public function findByProject($project): array {
        return $this->findBy(['project' => $project], ['id' => 'DESC']);
    }
}
