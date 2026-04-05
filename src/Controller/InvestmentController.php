<?php

namespace App\Controller;

use App\Entity\InvestmentOffer;
use App\Entity\InvestmentOpportunity;
use App\Repository\InvestmentOfferRepository;
use App\Repository\InvestmentOpportunityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/investissement')]
#[IsGranted('ROLE_USER')]
class InvestmentController extends AbstractController
{
    #[Route('/opportunities', name: 'app_invest_opportunities')]
    public function opportunities(InvestmentOpportunityRepository $repo): Response
    {
        $opportunities = $repo->findOpen();
        return $this->render('front/investment/opportunities.html.twig', ['opportunities' => $opportunities]);
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
    public function makeOffer(InvestmentOpportunity $opp, Request $request, EntityManagerInterface $em): Response
    {
        $offer = new InvestmentOffer();
        $offer->setOpportunity($opp);
        $offer->setInvestor($this->getUser());
        $offer->setProposedAmount((float) $request->request->get('amount'));
        $offer->setStatus('PENDING');
        $offer->setPaid(false);

        $em->persist($offer);
        $em->flush();

        $this->addFlash('success', 'Offre soumise avec succès !');
        return $this->redirectToRoute('app_invest_opportunity_show', ['id' => $opp->getId()]);
    }

    #[Route('/my-offers', name: 'app_invest_my_offers')]
    public function myOffers(InvestmentOfferRepository $repo): Response
    {
        $offers = $repo->findByInvestor($this->getUser());
        return $this->render('front/investment/my_offers.html.twig', ['offers' => $offers]);
    }

    #[Route('/create-opportunity', name: 'app_invest_create_opportunity', methods: ['GET', 'POST'])]
    public function createOpportunity(Request $request, EntityManagerInterface $em): Response
    {
        if ($request->isMethod('POST')) {
            $opp = new InvestmentOpportunity();
            $opp->setProject($em->getRepository(\App\Entity\Projet::class)->find($request->request->get('project_id')));
            $opp->setTargetAmount((float) $request->request->get('target_amount'));
            $opp->setDescription($request->request->get('description'));
            $opp->setDeadline(new \DateTime($request->request->get('deadline')));
            $opp->setStatus('OPEN');

            $em->persist($opp);
            $em->flush();

            $this->addFlash('success', 'Opportunité créée avec succès !');
            return $this->redirectToRoute('app_invest_opportunities');
        }

        $projets = $em->getRepository(\App\Entity\Projet::class)->findByUser($this->getUser());
        return $this->render('front/investment/create_opportunity.html.twig', ['projets' => $projets]);
    }
}
