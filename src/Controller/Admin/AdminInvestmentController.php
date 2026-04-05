<?php

namespace App\Controller\Admin;

use App\Entity\InvestmentOpportunity;
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
    public function index(Request $request, InvestmentOpportunityRepository $repo, PaginatorInterface $paginator): Response
    {
        $qb = $repo->createQueryBuilder('o')->orderBy('o.deadline', 'DESC');
        $pagination = $paginator->paginate($qb, $request->query->getInt('page', 1), 15);

        return $this->render('admin/investment/index.html.twig', ['pagination' => $pagination]);
    }

    #[Route('/{id}', name: 'admin_invest_show')]
    public function show(InvestmentOpportunity $opp): Response
    {
        return $this->render('admin/investment/show.html.twig', ['opportunity' => $opp]);
    }

    #[Route('/{id}/close', name: 'admin_invest_close', methods: ['POST'])]
    public function close(InvestmentOpportunity $opp, EntityManagerInterface $em): Response
    {
        $opp->setStatus('CLOSED');
        $em->flush();
        $this->addFlash('success', 'Opportunité fermée.');
        return $this->redirectToRoute('admin_invest_opportunities');
    }

    #[Route('/{id}/delete', name: 'admin_invest_delete', methods: ['POST'])]
    public function delete(InvestmentOpportunity $opp, EntityManagerInterface $em): Response
    {
        $em->remove($opp);
        $em->flush();
        $this->addFlash('success', 'Opportunité supprimée.');
        return $this->redirectToRoute('admin_invest_opportunities');
    }
}
