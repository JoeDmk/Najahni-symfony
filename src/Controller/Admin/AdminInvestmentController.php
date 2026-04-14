<?php

namespace App\Controller\Admin;

use App\Entity\InvestmentOffer;
use App\Entity\InvestmentOpportunity;
use App\Entity\Projet;
use App\Repository\InvestmentOfferRepository;
use App\Repository\InvestmentOpportunityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/investissement')]
#[IsGranted('ROLE_ADMIN')]
class AdminInvestmentController extends AbstractController
{
    #[Route('', name: 'admin_invest_opportunities')]
    public function index(Request $request, InvestmentOpportunityRepository $repo, InvestmentOfferRepository $offerRepo, PaginatorInterface $paginator): Response
    {
        $search = trim($request->query->get('q', ''));
        $statusFilter = $request->query->get('status', '');

        $qb = $repo->buildAdminQuery($search, $statusFilter);
        $pagination = $paginator->paginate($qb, $request->query->getInt('page', 1), 15);

        // Statistics via DQL repository methods
        $oppCounts = $repo->countByStatus();
        $offerCounts = $offerRepo->countByStatus();
        $totalRaised = $offerRepo->sumAcceptedAmounts();

        return $this->render('admin/investment/index.html.twig', [
            'pagination' => $pagination,
            'search' => $search,
            'statusFilter' => $statusFilter,
            'totalOpportunities' => $oppCounts['total'],
            'openCount' => $oppCounts['open'],
            'fundedCount' => $oppCounts['funded'],
            'totalOffers' => $offerCounts['total'],
            'pendingOffers' => $offerCounts['pending'],
            'totalRaised' => $totalRaised,
        ]);
    }

    #[Route('/create', name: 'admin_invest_create', methods: ['GET', 'POST'])]
    public function create(Request $request, EntityManagerInterface $em): Response
    {
        $projets = $em->getRepository(Projet::class)->findAll();

        if ($request->isMethod('POST')) {
            $token = $request->request->get('_token');
            if (!$this->isCsrfTokenValid('admin_create_opportunity', $token)) {
                $this->addFlash('danger', 'Jeton de sécurité invalide.');
                return $this->render('admin/investment/create.html.twig', ['projets' => $projets]);
            }

            $project = $em->getRepository(Projet::class)->find($request->request->get('project_id'));
            if (!$project) {
                $this->addFlash('danger', 'Projet introuvable.');
                return $this->render('admin/investment/create.html.twig', ['projets' => $projets]);
            }

            $fieldErrors = [];
            $targetAmount = $request->request->get('target_amount');
            if (!is_numeric($targetAmount) || (float) $targetAmount < 100) {
                $fieldErrors['target_amount'] = 'Le montant cible doit être au minimum 100 DT.';
            }

            $deadlineStr = $request->request->get('deadline');
            if (!$deadlineStr || strtotime($deadlineStr) === false) {
                $fieldErrors['deadline'] = 'La date limite est invalide.';
            }

            $description = trim($request->request->get('description', ''));
            if (mb_strlen($description) < 10) {
                $fieldErrors['description'] = 'La description doit contenir au moins 10 caractères.';
            }

            if (!empty($fieldErrors)) {
                return $this->render('admin/investment/create.html.twig', ['projets' => $projets, 'fieldErrors' => $fieldErrors]);
            }

            $opp = new InvestmentOpportunity();
            $opp->setProject($project);
            $opp->setTargetAmount((string) (float) $targetAmount);
            $opp->setDescription($description);
            $opp->setDeadline(new \DateTime($deadlineStr));
            $opp->setStatus('OPEN');

            $em->persist($opp);
            $em->flush();

            $this->addFlash('success', 'Opportunité créée.');
            return $this->redirectToRoute('admin_invest_opportunities');
        }

        return $this->render('admin/investment/create.html.twig', ['projets' => $projets]);
    }

    #[Route('/{id}', name: 'admin_invest_show', requirements: ['id' => '\d+'])]
    public function show(InvestmentOpportunity $opp): Response
    {
        return $this->render('admin/investment/show.html.twig', ['opportunity' => $opp]);
    }

    #[Route('/{id}/edit', name: 'admin_invest_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(InvestmentOpportunity $opp, Request $request, EntityManagerInterface $em): Response
    {
        if ($request->isMethod('POST')) {
            $token = $request->request->get('_token');
            if (!$this->isCsrfTokenValid('admin_edit_opportunity_' . $opp->getId(), $token)) {
                $this->addFlash('danger', 'Jeton de sécurité invalide.');
                return $this->render('admin/investment/edit.html.twig', ['opportunity' => $opp]);
            }

            $fieldErrors = [];
            $targetAmount = $request->request->get('target_amount');
            if (!is_numeric($targetAmount) || (float) $targetAmount < 100) {
                $fieldErrors['target_amount'] = 'Le montant cible doit être au minimum 100 DT.';
            }

            $description = trim($request->request->get('description', ''));
            if (mb_strlen($description) < 10) {
                $fieldErrors['description'] = 'La description doit contenir au moins 10 caractères.';
            }

            if (!empty($fieldErrors)) {
                return $this->render('admin/investment/edit.html.twig', ['opportunity' => $opp, 'fieldErrors' => $fieldErrors]);
            }

            $opp->setTargetAmount((string) (float) $targetAmount);
            $opp->setDescription($description);
            $opp->setDeadline(new \DateTime($request->request->get('deadline')));

            $status = $request->request->get('status', $opp->getStatus());
            if (in_array($status, ['OPEN', 'CLOSED', 'FUNDED'])) {
                $opp->setStatus($status);
            }

            $em->flush();

            $this->addFlash('success', 'Opportunité mise à jour.');
            return $this->redirectToRoute('admin_invest_show', ['id' => $opp->getId()]);
        }

        return $this->render('admin/investment/edit.html.twig', ['opportunity' => $opp]);
    }

    #[Route('/{id}/close', name: 'admin_invest_close', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function close(InvestmentOpportunity $opp, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('close_opportunity_' . $opp->getId(), $request->request->get('_token'))) {
            $this->addFlash('danger', 'Jeton de sécurité invalide.');
            return $this->redirectToRoute('admin_invest_opportunities');
        }
        $opp->setStatus('CLOSED');
        $em->flush();
        $this->addFlash('success', 'Opportunité fermée.');
        return $this->redirectToRoute('admin_invest_opportunities');
    }

    #[Route('/{id}/delete', name: 'admin_invest_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(InvestmentOpportunity $opp, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('delete_opportunity_' . $opp->getId(), $request->request->get('_token'))) {
            $this->addFlash('danger', 'Jeton de sécurité invalide.');
            return $this->redirectToRoute('admin_invest_opportunities');
        }
        $em->remove($opp);
        $em->flush();
        $this->addFlash('success', 'Opportunité supprimée.');
        return $this->redirectToRoute('admin_invest_opportunities');
    }

    #[Route('/offers', name: 'admin_invest_offers')]
    public function offers(Request $request, InvestmentOfferRepository $repo, PaginatorInterface $paginator): Response
    {
        $statusFilter = $request->query->get('status', '');

        $qb = $repo->buildFilteredQuery($statusFilter);
        $pagination = $paginator->paginate($qb, $request->query->getInt('page', 1), 15);

        return $this->render('admin/investment/offers.html.twig', [
            'pagination' => $pagination,
            'statusFilter' => $statusFilter,
        ]);
    }

    #[Route('/offers/{id}/accept', name: 'admin_invest_offer_accept', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function acceptOffer(InvestmentOffer $offer, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('offer_action_' . $offer->getId(), $request->request->get('_token'))) {
            $this->addFlash('danger', 'Jeton de sécurité invalide.');
            return $this->redirectToRoute('admin_invest_show', ['id' => $offer->getOpportunity()->getId()]);
        }
        $offer->setStatus('ACCEPTED');
        $em->flush();
        $this->addFlash('success', 'Offre acceptée.');
        return $this->redirectToRoute('admin_invest_show', ['id' => $offer->getOpportunity()->getId()]);
    }

    #[Route('/offers/{id}/reject', name: 'admin_invest_offer_reject', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function rejectOffer(InvestmentOffer $offer, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('offer_action_' . $offer->getId(), $request->request->get('_token'))) {
            $this->addFlash('danger', 'Jeton de sécurité invalide.');
            return $this->redirectToRoute('admin_invest_show', ['id' => $offer->getOpportunity()->getId()]);
        }
        $offer->setStatus('REJECTED');
        $em->flush();
        $this->addFlash('success', 'Offre rejetée.');
        return $this->redirectToRoute('admin_invest_show', ['id' => $offer->getOpportunity()->getId()]);
    }
}
