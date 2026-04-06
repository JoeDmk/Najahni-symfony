<?php
namespace App\Repository;
use App\Entity\Post;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
class PostRepository extends ServiceEntityRepository {
    public function __construct(ManagerRegistry $registry) { parent::__construct($registry, Post::class); }

    public function findFeed(): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.user', 'u')->addSelect('u')
            ->leftJoin('p.reactions', 'r')->addSelect('r')
            ->leftJoin('r.user', 'reactionUser')->addSelect('reactionUser')
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
