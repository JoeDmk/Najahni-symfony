<?php

namespace App\Controller;

use App\Entity\DonneesBusiness;
use App\Entity\Projet;
use App\Repository\ProjetRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/projets')]
#[IsGranted('ROLE_USER')]
class ProjetController extends AbstractController
{
    #[Route('', name: 'app_projet_index')]
    public function index(ProjetRepository $repo): Response
    {
        $projets = $repo->findByUser($this->getUser());
        return $this->render('front/projet/index.html.twig', ['projets' => $projets]);
    }

    #[Route('/new', name: 'app_projet_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        if ($request->isMethod('POST')) {
            $projet = new Projet();
            $projet->setUser($this->getUser());
            $this->hydrateProjet($projet, $request);
            $projet->setDateCreation(new \DateTime());
            $projet->setStatutProjet('BROUILLON');

            $donnees = new DonneesBusiness();
            $this->hydrateDonnees($donnees, $request);
            $donnees->setProjet($projet);
            $projet->setDonneesBusiness($donnees);

            $em->persist($projet);
            $em->persist($donnees);
            $em->flush();

            $this->addFlash('success', 'Projet créé avec succès !');
            return $this->redirectToRoute('app_projet_index');
        }

        return $this->render('front/projet/form.html.twig', ['projet' => null]);
    }

    #[Route('/{id}', name: 'app_projet_show', requirements: ['id' => '\d+'])]
    public function show(Projet $projet): Response
    {
        return $this->render('front/projet/show.html.twig', ['projet' => $projet]);
    }

    #[Route('/{id}/edit', name: 'app_projet_edit', methods: ['GET', 'POST'])]
    public function edit(Projet $projet, Request $request, EntityManagerInterface $em): Response
    {
        if ($projet->getUser() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException();
        }

        if ($request->isMethod('POST')) {
            $this->hydrateProjet($projet, $request);
            if ($projet->getDonneesBusiness()) {
                $this->hydrateDonnees($projet->getDonneesBusiness(), $request);
            }
            $em->flush();
            $this->addFlash('success', 'Projet modifié avec succès !');
            return $this->redirectToRoute('app_projet_show', ['id' => $projet->getId()]);
        }

        return $this->render('front/projet/form.html.twig', ['projet' => $projet]);
    }

    #[Route('/{id}/delete', name: 'app_projet_delete', methods: ['POST'])]
    public function delete(Projet $projet, EntityManagerInterface $em): Response
    {
        if ($projet->getUser() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException();
        }
        $em->remove($projet);
        $em->flush();
        $this->addFlash('success', 'Projet supprimé.');
        return $this->redirectToRoute('app_projet_index');
    }

    private function hydrateProjet(Projet $p, Request $r): void
    {
        $p->setTitre($r->request->get('titre'));
        $p->setDescription($r->request->get('description'));
        $p->setSecteur($r->request->get('secteur'));
        $p->setEtape($r->request->get('etape'));
        $p->setStatut($r->request->get('statut'));
    }

    private function hydrateDonnees(DonneesBusiness $d, Request $r): void
    {
        $d->setTailleMarche((float) $r->request->get('taille_marche', 0));
        $d->setModeleRevenu($r->request->get('modele_revenu'));
        $d->setCoutsEstimes((float) $r->request->get('couts_estimes', 0));
        $d->setRevenusAttendus((float) $r->request->get('revenus_attendus', 0));
        $d->setNiveauRisque($r->request->get('niveau_risque'));
        $d->setForceEquipe((int) $r->request->get('force_equipe', 0));
    }
}
