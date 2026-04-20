<?php

namespace App\Repository;

use App\Entity\InvestmentContract;
use App\Entity\InvestmentOffer;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class InvestmentContractRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InvestmentContract::class);
    }

    public function findOneByOffer(InvestmentOffer $offer): ?InvestmentContract
    {
        return $this->findOneBy(['offer' => $offer]);
    }

    public function findRecentForUser(User $user, int $limit = 20): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.investor = :user OR c.entrepreneur = :user')
            ->setParameter('user', $user)
            ->orderBy('c.lastMessageAt', 'DESC')
            ->addOrderBy('c.updatedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}