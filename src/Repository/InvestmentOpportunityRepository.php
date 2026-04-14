<?php
namespace App\Repository;

use App\Entity\InvestmentOpportunity;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

class InvestmentOpportunityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InvestmentOpportunity::class);
    }

    public function findOpen(): array
    {
        return $this->findBy(['status' => 'OPEN'], ['createdAt' => 'DESC']);
    }

    public function findByProject($project): array
    {
        return $this->findBy(['project' => $project], ['id' => 'DESC']);
    }

    /**
     * DQL: Search open opportunities with full-text search and dynamic sort.
     */
    public function searchOpen(string $search = '', string $sort = 'recent'): array
    {
        return $this->searchOpenQuery($search, $sort)->getQuery()->getResult();
    }

    /**
     * Returns a QueryBuilder for open opportunities (paginatable).
     */
    public function searchOpenQuery(string $search = '', string $sort = 'recent'): QueryBuilder
    {
        $qb = $this->createQueryBuilder('o')
            ->leftJoin('o.project', 'p')
            ->where('o.status = :status')
            ->setParameter('status', 'OPEN');

        if ($search !== '') {
            $qb->andWhere('p.titre LIKE :q OR o.description LIKE :q')
               ->setParameter('q', '%' . $search . '%');
        }

        match ($sort) {
            'amount_asc' => $qb->orderBy('o.targetAmount', 'ASC'),
            'amount_desc' => $qb->orderBy('o.targetAmount', 'DESC'),
            'deadline' => $qb->orderBy('o.deadline', 'ASC'),
            default => $qb->orderBy('o.createdAt', 'DESC'),
        };

        return $qb;
    }

    /**
     * DQL: Admin search with status filter, returns QueryBuilder for pagination.
     */
    public function buildAdminQuery(string $search = '', string $statusFilter = ''): QueryBuilder
    {
        $qb = $this->createQueryBuilder('o')
            ->leftJoin('o.project', 'p')
            ->orderBy('o.createdAt', 'DESC');

        if ($search !== '') {
            $qb->andWhere('p.titre LIKE :q OR o.description LIKE :q')
               ->setParameter('q', '%' . $search . '%');
        }

        if ($statusFilter !== '' && in_array($statusFilter, ['OPEN', 'CLOSED', 'FUNDED'])) {
            $qb->andWhere('o.status = :st')->setParameter('st', $statusFilter);
        }

        return $qb;
    }

    /**
     * DQL: Count opportunities by status.
     * @return array{total: int, open: int, closed: int, funded: int}
     */
    public function countByStatus(): array
    {
        $rows = $this->createQueryBuilder('o')
            ->select('o.status, COUNT(o.id) AS cnt')
            ->groupBy('o.status')
            ->getQuery()
            ->getResult();

        $counts = ['total' => 0, 'open' => 0, 'closed' => 0, 'funded' => 0];
        foreach ($rows as $row) {
            $counts[strtolower($row['status'])] = (int) $row['cnt'];
            $counts['total'] += (int) $row['cnt'];
        }
        return $counts;
    }

    /**
     * DQL: Get opportunities expiring within N days.
     */
    public function findExpiringSoon(int $days = 7): array
    {
        return $this->createQueryBuilder('o')
            ->where('o.status = :status')
            ->andWhere('o.deadline BETWEEN :now AND :limit')
            ->setParameter('status', 'OPEN')
            ->setParameter('now', new \DateTime('today'))
            ->setParameter('limit', new \DateTime("+{$days} days"))
            ->orderBy('o.deadline', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Count open opportunities grouped by risk score bracket.
     * @return array{total: int, low: int, medium: int, high: int}
     */
    public function countOpenByRiskBracket(): array
    {
        $rows = $this->createQueryBuilder('o')
            ->select('o.riskScore')
            ->where('o.status = :status')
            ->setParameter('status', 'OPEN')
            ->getQuery()
            ->getResult();

        $counts = ['total' => 0, 'low' => 0, 'medium' => 0, 'high' => 0];
        foreach ($rows as $row) {
            $score = (int) ($row['riskScore'] ?? 50);
            $counts['total']++;
            if ($score <= 33) {
                $counts['low']++;
            } elseif ($score <= 66) {
                $counts['medium']++;
            } else {
                $counts['high']++;
            }
        }
        return $counts;
    }

    /**
     * Count open opportunities grouped by project sector.
     * @return array<string, int>  sector => count
     */
    public function countOpenBySector(): array
    {
        $rows = $this->createQueryBuilder('o')
            ->select('p.secteur AS sector, COUNT(o.id) AS cnt')
            ->leftJoin('o.project', 'p')
            ->where('o.status = :status')
            ->setParameter('status', 'OPEN')
            ->groupBy('p.secteur')
            ->orderBy('cnt', 'DESC')
            ->getQuery()
            ->getResult();

        $result = [];
        foreach ($rows as $row) {
            $sector = $row['sector'] ?: 'Non defini';
            $result[$sector] = (int) $row['cnt'];
        }
        return $result;
    }

    /**
     * Count open opportunities with short deadlines (< 6 months), useful as "cross-border" proxy.
     */
    public function countOpenShortDeadline(int $months = 6): int
    {
        return (int) $this->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->where('o.status = :status')
            ->andWhere('o.deadline IS NOT NULL')
            ->andWhere('o.deadline <= :limit')
            ->setParameter('status', 'OPEN')
            ->setParameter('limit', new \DateTime("+{$months} months"))
            ->getQuery()
            ->getSingleScalarResult();
    }
}
