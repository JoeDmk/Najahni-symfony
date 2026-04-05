<?php

namespace App\Repository;

use App\Entity\LoginHistory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class LoginHistoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LoginHistory::class);
    }

    public function findByUser(int $userId, int $limit = 50): array
    {
        return $this->createQueryBuilder('lh')
            ->andWhere('lh.user = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('lh.loginTime', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findAllWithUsers(int $limit = 100): array
    {
        return $this->createQueryBuilder('lh')
            ->join('lh.user', 'u')
            ->addSelect('u')
            ->orderBy('lh.loginTime', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countRecentFailedAttempts(int $userId, int $minutes = 15): int
    {
        $since = new \DateTime("-{$minutes} minutes");
        return (int) $this->createQueryBuilder('lh')
            ->select('COUNT(lh.id)')
            ->andWhere('lh.user = :userId')
            ->andWhere('lh.success = false')
            ->andWhere('lh.loginTime >= :since')
            ->setParameter('userId', $userId)
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getLastSuccessfulLogin(int $userId): ?LoginHistory
    {
        return $this->createQueryBuilder('lh')
            ->andWhere('lh.user = :userId')
            ->andWhere('lh.success = true')
            ->setParameter('userId', $userId)
            ->orderBy('lh.loginTime', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
