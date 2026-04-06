<?php
namespace App\Repository;
use App\Entity\Thread;
use App\Entity\Group;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
class ThreadRepository extends ServiceEntityRepository {
    public function __construct(ManagerRegistry $registry) { parent::__construct($registry, Thread::class); }

    public function findByGroup(Group $group): array
    {
        return $this->createQueryBuilder('t')
            ->leftJoin('t.user', 'u')->addSelect('u')
            ->leftJoin('t.comments', 'c')->addSelect('c')
            ->leftJoin('c.user', 'commentUser')->addSelect('commentUser')
            ->where('t.group = :group')
            ->setParameter('group', $group)
            ->orderBy('t.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
