<?php
namespace App\Repository;

use App\Entity\InvestmentOffer;
use App\Entity\InvestmentOpportunity;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

class InvestmentOfferRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InvestmentOffer::class);
    }

    public function findByInvestor($user): array
    {
        return $this->findBy(['investor' => $user], ['id' => 'DESC']);
    }

    public function findUnpaidByInvestor(User $user): array
    {
        return $this->createQueryBuilder('o')
            ->where('o.investor = :investor')
            ->andWhere('o.paid = :paid')
            ->setParameter('investor', $user)
            ->setParameter('paid', false)
            ->orderBy('o.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findPaidByInvestor(User $user): array
    {
        return $this->createQueryBuilder('o')
            ->where('o.investor = :investor')
            ->andWhere('o.paid = :paid')
            ->setParameter('investor', $user)
            ->setParameter('paid', true)
            ->orderBy('o.paidAt', 'DESC')
            ->addOrderBy('o.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByOpportunity($opp): array
    {
        return $this->findBy(['opportunity' => $opp], ['id' => 'DESC']);
    }

    /**
     * DQL: Check if an investor already has an offer for a given opportunity.
     */
    public function findExistingOffer(User $investor, InvestmentOpportunity $opportunity): ?InvestmentOffer
    {
        return $this->createQueryBuilder('o')
            ->where('o.investor = :investor')
            ->andWhere('o.opportunity = :opp')
            ->setParameter('investor', $investor)
            ->setParameter('opp', $opportunity)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * DQL: Sum of all accepted offers (total raised).
     */
    public function sumAcceptedAmounts(): float
    {
        $result = $this->createQueryBuilder('o')
            ->select('COALESCE(SUM(o.proposedAmount), 0)')
            ->where('o.status = :s')
            ->setParameter('s', 'ACCEPTED')
            ->getQuery()
            ->getSingleScalarResult();

        return (float) $result;
    }

    /**
     * DQL: Build a filterable query for admin offers list.
     */
    public function buildFilteredQuery(string $statusFilter = ''): QueryBuilder
    {
        $qb = $this->createQueryBuilder('o')
            ->orderBy('o.createdAt', 'DESC');

        if ($statusFilter !== '' && in_array($statusFilter, ['PENDING', 'ACCEPTED', 'REJECTED'])) {
            $qb->andWhere('o.status = :st')->setParameter('st', $statusFilter);
        }

        return $qb;
    }

    /**
     * DQL: Count offers grouped by status.
     * @return array{total: int, pending: int, accepted: int, rejected: int}
     */
    public function countByStatus(): array
    {
        $rows = $this->createQueryBuilder('o')
            ->select('o.status, COUNT(o.id) AS cnt')
            ->groupBy('o.status')
            ->getQuery()
            ->getResult();

        $counts = ['total' => 0, 'pending' => 0, 'accepted' => 0, 'rejected' => 0];
        foreach ($rows as $row) {
            $counts[strtolower($row['status'])] = (int) $row['cnt'];
            $counts['total'] += (int) $row['cnt'];
        }
        return $counts;
    }
}
