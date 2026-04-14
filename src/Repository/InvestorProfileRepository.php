<?php

namespace App\Repository;

use App\Entity\InvestorProfile;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class InvestorProfileRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InvestorProfile::class);
    }

    public function findByUser(User $user): ?InvestorProfile
    {
        return $this->findOneBy(['user' => $user]);
    }
}
