<?php
namespace App\Tests\Entity;

use App\Entity\Projet;
use PHPUnit\Framework\TestCase;

class ProjetTest extends TestCase
{
    public function testProjetEntityGettersSetters(): void
    {
        $projet = new Projet();
        $projet->setTitre('Plateforme de Mentorat Digital');
        $projet->setDescription('Une plateforme web mettant en relation des entrepreneurs tunisiens avec des mentors expérimentés.');
        $projet->setSecteur('Éducation');
        $projet->setEtape('Prototype');
        $projet->setStatut('En cours');
        $projet->setStatutProjet(Projet::STATUT_SOUMIS);
        $projet->setScoreGlobal(85.5);

        $this->assertEquals('Plateforme de Mentorat Digital', $projet->getTitre());
        $this->assertEquals('Une plateforme web mettant en relation des entrepreneurs tunisiens avec des mentors expérimentés.', $projet->getDescription());
        $this->assertEquals('Éducation', $projet->getSecteur());
        $this->assertEquals('Prototype', $projet->getEtape());
        $this->assertEquals('En cours', $projet->getStatut());
        $this->assertEquals(Projet::STATUT_SOUMIS, $projet->getStatutProjet());
        $this->assertEquals(85.5, $projet->getScoreGlobal());
    }
}
