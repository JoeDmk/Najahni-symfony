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
use Symfony\Component\HttpFoundation\JsonResponse;
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
        $filters = $this->getOpportunityFilters($request);
        $qb = $repo->buildAdminQuery($filters);
        $pagination = $paginator->paginate($qb, $request->query->getInt('page', 1), 15);

        if ($request->isXmlHttpRequest()) {
            return $this->render('admin/investment/_opportunities_results.html.twig', [
                'pagination' => $pagination,
            ]);
        }

        // Statistics via DQL repository methods
        $oppCounts = $repo->countByStatus();
        $offerCounts = $offerRepo->countByStatus();
        $totalRaised = $offerRepo->sumAcceptedAmounts();

        return $this->render('admin/investment/index.html.twig', [
            'pagination' => $pagination,
            'filters' => $filters,
            'totalOpportunities' => $oppCounts['total'],
            'openCount' => $oppCounts['open'],
            'fundedCount' => $oppCounts['funded'],
            'totalOffers' => $offerCounts['total'],
            'pendingOffers' => $offerCounts['pending'],
            'totalRaised' => $totalRaised,
        ]);
    }

    #[Route('/ajax/opportunities', name: 'admin_invest_opportunities_ajax', methods: ['GET'])]
    public function opportunitiesAjax(Request $request, InvestmentOpportunityRepository $repo, PaginatorInterface $paginator): Response
    {
        $pagination = $paginator->paginate($repo->buildAdminQuery($this->getOpportunityFilters($request)), $request->query->getInt('page', 1), 15);

        return $this->render('admin/investment/_opportunities_results.html.twig', [
            'pagination' => $pagination,
        ]);
    }

    #[Route('/ajax/offers', name: 'admin_invest_offers_ajax', methods: ['GET'])]
    public function offersAjax(Request $request, InvestmentOfferRepository $repo, PaginatorInterface $paginator): Response
    {
        $pagination = $paginator->paginate($repo->buildFilteredQuery($this->getOfferFilters($request)), $request->query->getInt('page', 1), 15);

        return $this->render('admin/investment/_offers_results.html.twig', [
            'pagination' => $pagination,
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

            $targetAmount = $request->request->get('target_amount');
            if (!is_numeric($targetAmount) || (float) $targetAmount < 100) {
                $this->addFlash('danger', 'Le montant cible doit être au minimum 100 DT.');
                return $this->render('admin/investment/create.html.twig', ['projets' => $projets]);
            }

            $deadlineStr = $request->request->get('deadline');
            if (!$deadlineStr || strtotime($deadlineStr) === false) {
                $this->addFlash('danger', 'La date limite est invalide.');
                return $this->render('admin/investment/create.html.twig', ['projets' => $projets]);
            }

            $deadline = new \DateTime($deadlineStr);
            if ($deadline <= new \DateTime('today')) {
                $this->addFlash('danger', 'La date limite doit être dans le futur.');
                return $this->render('admin/investment/create.html.twig', ['projets' => $projets]);
            }

            $description = trim($request->request->get('description', ''));
            if (mb_strlen($description) < 10) {
                $this->addFlash('danger', 'La description doit contenir au moins 10 caractères.');
                return $this->render('admin/investment/create.html.twig', ['projets' => $projets]);
            }

            $opp = new InvestmentOpportunity();
            $opp->setProject($project);
            $opp->setTargetAmount((string) (float) $targetAmount);
            $opp->setDescription($description);
            $opp->setDeadline($deadline);
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

            $targetAmount = $request->request->get('target_amount');
            if (!is_numeric($targetAmount) || (float) $targetAmount < 100) {
                $this->addFlash('danger', 'Le montant cible doit être au minimum 100 DT.');
                return $this->render('admin/investment/edit.html.twig', ['opportunity' => $opp]);
            }

            $description = trim($request->request->get('description', ''));
            if (mb_strlen($description) < 10) {
                $this->addFlash('danger', 'La description doit contenir au moins 10 caractères.');
                return $this->render('admin/investment/edit.html.twig', ['opportunity' => $opp]);
            }

            $deadlineStr = $request->request->get('deadline');
            if (!$deadlineStr || strtotime($deadlineStr) === false) {
                $this->addFlash('danger', 'La date limite est invalide.');
                return $this->render('admin/investment/edit.html.twig', ['opportunity' => $opp]);
            }

            $deadline = new \DateTime($deadlineStr);
            if ($deadline <= new \DateTime('today')) {
                $this->addFlash('danger', 'La date limite doit être dans le futur.');
                return $this->render('admin/investment/edit.html.twig', ['opportunity' => $opp]);
            }

            $opp->setTargetAmount((string) (float) $targetAmount);
            $opp->setDescription($description);
            $opp->setDeadline($deadline);

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
        $filters = $this->getOfferFilters($request);
        $qb = $repo->buildFilteredQuery($filters);
        $pagination = $paginator->paginate($qb, $request->query->getInt('page', 1), 15);

        if ($request->isXmlHttpRequest()) {
            return $this->render('admin/investment/_offers_results.html.twig', [
                'pagination' => $pagination,
            ]);
        }

        return $this->render('admin/investment/offers.html.twig', [
            'pagination' => $pagination,
            'filters' => $filters,
        ]);
    }

    private function getOpportunityFilters(Request $request): array
    {
        $this->normalizeLegacySortQuery($request);

        return [
            'q' => trim((string) $request->query->get('q', '')),
            'status' => (string) $request->query->get('status', ''),
            'entrepreneur' => trim((string) $request->query->get('entrepreneur', '')),
            'sector' => trim((string) $request->query->get('sector', '')),
            'deadline_state' => (string) $request->query->get('deadline_state', ''),
            'amount_min' => $request->query->get('amount_min', ''),
            'amount_max' => $request->query->get('amount_max', ''),
            'order' => (string) $request->query->get('order', 'recent'),
        ];
    }

    private function getOfferFilters(Request $request): array
    {
        $this->normalizeLegacySortQuery($request);

        return [
            'q' => trim((string) $request->query->get('q', '')),
            'status' => (string) $request->query->get('status', ''),
            'investor' => trim((string) $request->query->get('investor', '')),
            'entrepreneur' => trim((string) $request->query->get('entrepreneur', '')),
            'paid' => (string) $request->query->get('paid', ''),
            'amount_min' => $request->query->get('amount_min', ''),
            'amount_max' => $request->query->get('amount_max', ''),
            'created_from' => (string) $request->query->get('created_from', ''),
            'created_to' => (string) $request->query->get('created_to', ''),
            'order' => (string) $request->query->get('order', 'recent'),
        ];
    }

    private function normalizeLegacySortQuery(Request $request): void
    {
        if ($request->query->has('sort') && !$request->query->has('order')) {
            $request->query->set('order', (string) $request->query->get('sort', 'recent'));
        }

        if ($request->query->has('sort')) {
            $request->query->remove('sort');
        }
    }

    #[Route('/offers/{id}/accept', name: 'admin_invest_offer_accept', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function acceptOffer(InvestmentOffer $offer, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('offer_action_' . $offer->getId(), $request->request->get('_token'))) {
            $this->addFlash('danger', 'Jeton de sécurité invalide.');
            return $this->redirectToRoute('admin_invest_show', ['id' => $offer->getOpportunity()->getId()]);
        }

        if ($offer->getStatus() !== InvestmentOffer::STATUS_PENDING) {
            return $this->json(['success' => false, 'message' => 'Cette offre a déjà été traitée.'], 409);
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

        if ($offer->getStatus() !== InvestmentOffer::STATUS_PENDING) {
            return $this->json(['success' => false, 'message' => 'Cette offre a déjà été traitée.'], 409);
        }

        $offer->setStatus('REJECTED');
        $em->flush();
        $this->addFlash('success', 'Offre rejetée.');
        return $this->redirectToRoute('admin_invest_show', ['id' => $offer->getOpportunity()->getId()]);
    }
}
