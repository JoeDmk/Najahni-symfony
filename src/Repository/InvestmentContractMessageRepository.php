<?php

namespace App\Repository;

use App\Entity\InvestmentContract;
use App\Entity\InvestmentContractMessage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class InvestmentContractMessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InvestmentContractMessage::class);
    }

    public function findChronological(InvestmentContract $contract): array
    {
        return $this->createQueryBuilder('m')
            ->where('m.contract = :contract')
            ->setParameter('contract', $contract)
            ->orderBy('m.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findAfterId(InvestmentContract $contract, int $afterId): array
    {
        return $this->createQueryBuilder('m')
            ->where('m.contract = :contract')
            ->andWhere('m.id > :afterId')
            ->setParameter('contract', $contract)
            ->setParameter('afterId', $afterId)
            ->orderBy('m.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}