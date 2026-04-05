<?php
namespace App\Repository;

use App\Entity\Projet;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ProjetRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry) { parent::__construct($registry, Projet::class); }

    public function findByUser($user): array
    {
        return $this->findBy(['user' => $user], ['dateCreation' => 'DESC']);
    }

    public function findBySearch(string $q)
    {
        return $this->createQueryBuilder('p')
            ->where('p.titre LIKE :q OR p.secteur LIKE :q')
            ->setParameter('q', '%'.$q.'%')
            ->orderBy('p.dateCreation', 'DESC');
    }
}
