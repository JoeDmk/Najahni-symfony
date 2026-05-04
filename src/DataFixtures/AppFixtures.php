<?php

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        // Création d'un utilisateur admin pour les tests
        $admin = new \App\Entity\User();
        $admin->setFirstname('Admin');
        $admin->setLastname('Test');
        $admin->setEmail('admin@najahni.tn');
        // Mot de passe hashé "adminpass" (à adapter selon ton encodeur)
        $admin->setPassword('$2y$13$wQwQwQwQwQwQwQwQwQwQwOQwQwQwQwQwQwQwQwQwQwQwQwQwQwQwQwQwQwQwQ');
        $admin->setRole('ADMIN');
        $admin->setVerified(true);
        $admin->setIsActive(true);
        $admin->setCreatedAt(new \DateTime());
        $admin->setUpdatedAt(new \DateTime());
        $manager->persist($admin);

        $manager->flush();
    }
}
