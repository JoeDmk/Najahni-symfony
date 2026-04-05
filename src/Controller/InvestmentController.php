<?php

namespace App\Controller;

use App\Entity\InvestmentOffer;
use App\Entity\InvestmentOpportunity;
use App\Entity\Projet;
use App\Repository\InvestmentOfferRepository;
use App\Repository\InvestmentOpportunityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/investissement')]
#[IsGranted('ROLE_USER')]
class InvestmentController extends AbstractController
{
    #[Route('/opportunities', name: 'app_invest_opportunities')]
    public function opportunities(Request $request, InvestmentOpportunityRepository $repo): Response
    {
        $search = trim($request->query->get('q', ''));
        $sort = $request->query->get('sort', 'recent');

        $opportunities = $repo->searchOpen($search, $sort);

        return $this->render('front/investment/opportunities.html.twig', [
            'opportunities' => $opportunities,
            'search' => $search,
            'sort' => $sort,
        ]);
    }

    #[Route('/opportunities/{id}', name: 'app_invest_opportunity_show', requirements: ['id' => '\d+'])]
    public function showOpportunity(InvestmentOpportunity $opp, InvestmentOfferRepository $offerRepo): Response
    {
        $offers = $offerRepo->findByOpportunity($opp);
        $myOffer = $offerRepo->findOneBy(['opportunity' => $opp, 'investor' => $this->getUser()]);

        return $this->render('front/investment/show.html.twig', [
            'opportunity' => $opp,
            'offers' => $offers,
            'myOffer' => $myOffer,
        ]);
    }

    #[Route('/opportunities/{id}/offer', name: 'app_invest_offer', methods: ['POST'])]
    #[IsGranted('ROLE_INVESTISSEUR')]
    public function makeOffer(InvestmentOpportunity $opp, Request $request, EntityManagerInterface $em, InvestmentOfferRepository $offerRepo, ValidatorInterface $validator): Response
    {
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('invest_offer_' . $opp->getId(), $token)) {
            $this->addFlash('danger', 'Jeton de sécurité invalide.');
            return $this->redirectToRoute('app_invest_opportunity_show', ['id' => $opp->getId()]);
        }

        if ($opp->getStatus() !== InvestmentOpportunity::STATUS_OPEN) {
            $this->addFlash('danger', 'Cette opportunité n\'est plus ouverte.');
            return $this->redirectToRoute('app_invest_opportunity_show', ['id' => $opp->getId()]);
        }

        // Prevent entrepreneur from investing in their own project
        if ($opp->getProject() && $opp->getProject()->getUser() === $this->getUser()) {
            $this->addFlash('danger', 'Vous ne pouvez pas investir dans votre propre projet.');
            return $this->redirectToRoute('app_invest_opportunity_show', ['id' => $opp->getId()]);
        }

        // Prevent duplicate offers (same investor + same opportunity)
        $existingOffer = $offerRepo->findExistingOffer($this->getUser(), $opp);
        if ($existingOffer) {
            $this->addFlash('danger', 'Vous avez déjà soumis une offre pour cette opportunité.');
            return $this->redirectToRoute('app_invest_opportunity_show', ['id' => $opp->getId()]);
        }

        $amount = $request->request->get('amount');
        if (!is_numeric($amount) || (float) $amount <= 0) {
            $this->addFlash('danger', 'Le montant doit être un nombre positif.');
            return $this->redirectToRoute('app_invest_opportunity_show', ['id' => $opp->getId()]);
        }

        $remaining = (float) $opp->getTargetAmount() - $opp->getTotalFunded();
        if ((float) $amount > $remaining && $remaining > 0) {
            $this->addFlash('danger', 'Le montant ne peut pas dépasser le reste à financer (' . number_format($remaining, 2, ',', ' ') . ' DT).');
            return $this->redirectToRoute('app_invest_opportunity_show', ['id' => $opp->getId()]);
        }

        $offer = new InvestmentOffer();
        $offer->setOpportunity($opp);
        $offer->setInvestor($this->getUser());
        $offer->setProposedAmount((string) (float) $amount);
        $offer->setStatus('PENDING');
        $offer->setPaid(false);

        $errors = $validator->validate($offer);
        if (count($errors) > 0) {
            $this->addFlash('danger', (string) $errors->get(0)->getMessage());
            return $this->redirectToRoute('app_invest_opportunity_show', ['id' => $opp->getId()]);
        }

        $em->persist($offer);
        $em->flush();

        $this->addFlash('success', 'Offre soumise avec succes !');
        return $this->redirectToRoute('app_invest_opportunity_show', ['id' => $opp->getId()]);
    }

    #[Route('/my-offers', name: 'app_invest_my_offers')]
    #[IsGranted('ROLE_INVESTISSEUR')]
    public function myOffers(InvestmentOfferRepository $repo): Response
    {
        $offers = $repo->findByInvestor($this->getUser());
        return $this->render('front/investment/my_offers.html.twig', ['offers' => $offers]);
    }

    #[Route('/create-opportunity', name: 'app_invest_create_opportunity', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ENTREPRENEUR')]
    public function createOpportunity(Request $request, EntityManagerInterface $em, ValidatorInterface $validator): Response
    {
        $projets = $em->getRepository(Projet::class)->findByUser($this->getUser());

        if ($request->isMethod('POST')) {
            $token = $request->request->get('_token');
            if (!$this->isCsrfTokenValid('create_opportunity', $token)) {
                $this->addFlash('danger', 'Jeton de sécurité invalide.');
                return $this->render('front/investment/create_opportunity.html.twig', ['projets' => $projets]);
            }

            $project = $em->getRepository(Projet::class)->find($request->request->get('project_id'));
            if (!$project || $project->getUser() !== $this->getUser()) {
                $this->addFlash('danger', 'Vous ne pouvez créer une opportunité que pour vos propres projets.');
                return $this->render('front/investment/create_opportunity.html.twig', ['projets' => $projets]);
            }

            $targetAmount = $request->request->get('target_amount');
            if (!is_numeric($targetAmount) || (float) $targetAmount < 100) {
                $this->addFlash('danger', 'Le montant cible doit être au minimum 100 DT.');
                return $this->render('front/investment/create_opportunity.html.twig', ['projets' => $projets]);
            }

            $deadlineStr = $request->request->get('deadline');
            if (!$deadlineStr || strtotime($deadlineStr) === false) {
                $this->addFlash('danger', 'La date limite est invalide.');
                return $this->render('front/investment/create_opportunity.html.twig', ['projets' => $projets]);
            }
            $deadline = new \DateTime($deadlineStr);
            if ($deadline <= new \DateTime('today')) {
                $this->addFlash('danger', 'La date limite doit être dans le futur.');
                return $this->render('front/investment/create_opportunity.html.twig', ['projets' => $projets]);
            }

            $description = trim($request->request->get('description', ''));
            if (mb_strlen($description) < 10) {
                $this->addFlash('danger', 'La description doit contenir au moins 10 caractères.');
                return $this->render('front/investment/create_opportunity.html.twig', ['projets' => $projets]);
            }

            $opp = new InvestmentOpportunity();
            $opp->setProject($project);
            $opp->setTargetAmount((string) (float) $targetAmount);
            $opp->setDescription($description);
            $opp->setDeadline($deadline);
            $opp->setStatus('OPEN');

            $errors = $validator->validate($opp);
            if (count($errors) > 0) {
                $this->addFlash('danger', (string) $errors->get(0)->getMessage());
                return $this->render('front/investment/create_opportunity.html.twig', ['projets' => $projets]);
            }

            $em->persist($opp);
            $em->flush();

            $this->addFlash('success', 'Opportunité créée avec succes !');
            return $this->redirectToRoute('app_invest_opportunities');
        }

        return $this->render('front/investment/create_opportunity.html.twig', ['projets' => $projets]);
    }
}
