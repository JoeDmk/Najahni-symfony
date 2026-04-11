<?php

namespace App\Repository;

use App\Entity\ContractMilestone;
use App\Entity\InvestmentContract;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ContractMilestone>
 */
class ContractMilestoneRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ContractMilestone::class);
    }

    /** @return ContractMilestone[] */
    public function findByContract(InvestmentContract $contract): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.contract = :contract')
            ->setParameter('contract', $contract)
            ->orderBy('m.position', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function sumReleasedAmount(InvestmentContract $contract): float
    {
        $result = $this->createQueryBuilder('m')
            ->select('SUM(m.amount)')
            ->andWhere('m.contract = :contract')
            ->andWhere('m.status = :status')
            ->setParameter('contract', $contract)
            ->setParameter('status', ContractMilestone::STATUS_RELEASED)
            ->getQuery()
            ->getSingleScalarResult();

        return (float) ($result ?? 0);
    }
}
