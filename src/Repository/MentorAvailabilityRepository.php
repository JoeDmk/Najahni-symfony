<?php
namespace App\Repository;
use App\Entity\MentorAvailability;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
class MentorAvailabilityRepository extends ServiceEntityRepository {
    public function __construct(ManagerRegistry $registry) { parent::__construct($registry, MentorAvailability::class); }
    public function findByMentor(User $mentor): array { return $this->findBy(['mentor' => $mentor], ['date' => 'ASC']); }

    public function hasOverlappingAvailability(User $mentor, \DateTimeInterface $date, \DateTimeInterface $startTime, \DateTimeInterface $endTime, ?int $excludeId = null): bool
    {
        $qb = $this->createQueryBuilder('a')
            ->where('a.mentor = :mentor')
            ->andWhere('a.date = :date')
            ->andWhere('a.startTime < :endTime AND a.endTime > :startTime')
            ->setParameter('mentor', $mentor)
            ->setParameter('date', $date)
            ->setParameter('startTime', $startTime)
            ->setParameter('endTime', $endTime);

        if ($excludeId) {
            $qb->andWhere('a.id != :excludeId')
               ->setParameter('excludeId', $excludeId);
        }

        return count($qb->getQuery()->getResult()) > 0;
    }
}
