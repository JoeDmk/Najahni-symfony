<?php

namespace App\Controller\Admin;

use App\Repository\MentorshipRequestRepository;
use App\Repository\MentorshipSessionRepository;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/mentorat')]
#[IsGranted('ROLE_ADMIN')]
class AdminMentoratController extends AbstractController
{
    #[Route('', name: 'admin_mentorat_requests')]
    public function requests(Request $request, MentorshipRequestRepository $repo, PaginatorInterface $paginator): Response
    {
        $qb = $repo->createQueryBuilder('r')->orderBy('r.date', 'DESC');
        $pagination = $paginator->paginate($qb, $request->query->getInt('page', 1), 15);
        return $this->render('admin/mentorat/requests.html.twig', ['pagination' => $pagination]);
    }

    #[Route('/sessions', name: 'admin_mentorat_sessions')]
    public function sessions(Request $request, MentorshipSessionRepository $repo, PaginatorInterface $paginator): Response
    {
        $qb = $repo->createQueryBuilder('s')->orderBy('s.scheduledAt', 'DESC');
        $pagination = $paginator->paginate($qb, $request->query->getInt('page', 1), 15);
        return $this->render('admin/mentorat/sessions.html.twig', ['pagination' => $pagination]);
    }
}
