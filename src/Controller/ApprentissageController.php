<?php

namespace App\Controller;

use App\Entity\CoursComment;
use App\Entity\Progression;
use App\Repository\BadgeRepository;
use App\Repository\CoursCommentRepository;
use App\Repository\CoursRepository;
use App\Repository\ProgressionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/apprentissage')]
#[IsGranted('ROLE_USER')]
class ApprentissageController extends AbstractController
{
    #[Route('/cours', name: 'app_apprentissage_cours')]
    public function cours(CoursRepository $repo, Request $request): Response
    {
        $search = $request->query->get('q', '');
        $cours = $search ? $repo->findBySearch($search)->getQuery()->getResult() : $repo->findBy(['actif' => true]);
        return $this->render('front/apprentissage/cours.html.twig', ['cours' => $cours, 'search' => $search]);
    }

    #[Route('/cours/{id}', name: 'app_apprentissage_cours_show', requirements: ['id' => '\d+'])]
    public function showCours(\App\Entity\Cours $cours, ProgressionRepository $progRepo, CoursCommentRepository $commentRepo): Response
    {
        $progression = $progRepo->findOneBy(['user' => $this->getUser(), 'cours' => $cours]);
        $comments = $commentRepo->findByCours($cours);

        return $this->render('front/apprentissage/cours_show.html.twig', [
            'cours' => $cours,
            'progression' => $progression,
            'comments' => $comments,
        ]);
    }

    #[Route('/cours/{id}/enroll', name: 'app_apprentissage_enroll', methods: ['POST'])]
    public function enroll(\App\Entity\Cours $cours, EntityManagerInterface $em, ProgressionRepository $repo): Response
    {
        $existing = $repo->findOneBy(['user' => $this->getUser(), 'cours' => $cours]);
        if (!$existing) {
            $prog = new Progression();
            $prog->setUser($this->getUser());
            $prog->setCours($cours);
            $prog->setPourcentage(0);
            $prog->setPointsXp(0);
            $prog->setNiveau(1);
            $prog->setEtat('EN_COURS');
            $prog->setDateDebut(new \DateTime());
            $em->persist($prog);
            $em->flush();
            $this->addFlash('success', 'Inscription au cours réussie !');
        }
        return $this->redirectToRoute('app_apprentissage_cours_show', ['id' => $cours->getId()]);
    }

    #[Route('/cours/{id}/progress', name: 'app_apprentissage_progress_update', methods: ['POST'])]
    public function updateProgress(\App\Entity\Cours $cours, Request $request, EntityManagerInterface $em, ProgressionRepository $repo): Response
    {
        $prog = $repo->findOneBy(['user' => $this->getUser(), 'cours' => $cours]);
        if ($prog) {
            $newPct = min(100, (int) $request->request->get('pourcentage', $prog->getPourcentage()));
            $prog->setPourcentage($newPct);
            if ($newPct >= 100) {
                $prog->setEtat('COMPLETE');
                $prog->setDateObtention(new \DateTime());
                $prog->setPointsXp($cours->getPointsXp());
            }
            $em->flush();
            $this->addFlash('success', 'Progression mise à jour !');
        }
        return $this->redirectToRoute('app_apprentissage_cours_show', ['id' => $cours->getId()]);
    }

    #[Route('/cours/{id}/comment', name: 'app_apprentissage_comment', methods: ['POST'])]
    public function comment(\App\Entity\Cours $cours, Request $request, EntityManagerInterface $em): Response
    {
        $comment = new CoursComment();
        $comment->setCours($cours);
        $comment->setUser($this->getUser());
        $comment->setContenu($request->request->get('contenu'));
        $comment->setRating((float) $request->request->get('rating', 0));

        $em->persist($comment);
        $em->flush();
        $this->addFlash('success', 'Avis ajouté !');
        return $this->redirectToRoute('app_apprentissage_cours_show', ['id' => $cours->getId()]);
    }

    #[Route('/progression', name: 'app_apprentissage_progression')]
    public function progression(ProgressionRepository $repo): Response
    {
        $progressions = $repo->findByUser($this->getUser());
        return $this->render('front/apprentissage/progression.html.twig', ['progressions' => $progressions]);
    }

    #[Route('/badges', name: 'app_apprentissage_badges')]
    public function badges(BadgeRepository $repo): Response
    {
        $badges = $repo->findActifs();
        return $this->render('front/apprentissage/badges.html.twig', ['badges' => $badges]);
    }
}
