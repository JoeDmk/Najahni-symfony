<?php

namespace App\Controller;

use App\Entity\MentorAvailability;
use App\Entity\MentorshipRequest;
use App\Entity\MentorshipSession;
use App\Repository\MentorAvailabilityRepository;
use App\Repository\MentorshipRequestRepository;
use App\Repository\MentorshipSessionRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/mentorat')]
#[IsGranted('ROLE_USER')]
class MentoratController extends AbstractController
{
    #[Route('/requests', name: 'app_mentorat_requests')]
    public function requests(MentorshipRequestRepository $repo): Response
    {
        $user = $this->getUser();
        $sentRequests = $repo->findBy(['entrepreneur' => $user], ['date' => 'DESC']);
        $receivedRequests = $repo->findBy(['mentor' => $user], ['date' => 'DESC']);

        return $this->render('front/mentorat/requests.html.twig', [
            'sentRequests' => $sentRequests,
            'receivedRequests' => $receivedRequests,
        ]);
    }

    #[Route('/mentors', name: 'app_mentorat_mentors')]
    public function mentors(UserRepository $userRepo): Response
    {
        $mentors = $userRepo->findBy(['role' => 'MENTOR', 'isBanned' => false, 'isActive' => true]);
        return $this->render('front/mentorat/mentors.html.twig', ['mentors' => $mentors]);
    }

    #[Route('/request/{mentorId}', name: 'app_mentorat_request_new', methods: ['GET', 'POST'])]
    public function newRequest(int $mentorId, Request $request, EntityManagerInterface $em, UserRepository $userRepo): Response
    {
        $mentor = $userRepo->find($mentorId);
        if (!$mentor || $mentor->getRole() !== 'MENTOR') {
            throw $this->createNotFoundException('Mentor non trouvé');
        }

        if ($request->isMethod('POST')) {
            $req = new MentorshipRequest();
            $req->setEntrepreneur($this->getUser());
            $req->setMentor($mentor);
            $req->setDate(new \DateTime($request->request->get('date')));
            $req->setTime($request->request->get('time'));
            $req->setMotivation($request->request->get('motivation'));
            $req->setGoals($request->request->get('goals'));
            $req->setStatus('PENDING');

            $projectId = $request->request->get('project_id');
            if ($projectId) {
                $project = $em->getRepository(\App\Entity\Projet::class)->find($projectId);
                $req->setProject($project);
            }

            $em->persist($req);
            $em->flush();
            $this->addFlash('success', 'Demande de mentorat envoyée !');
            return $this->redirectToRoute('app_mentorat_requests');
        }

        $projets = $em->getRepository(\App\Entity\Projet::class)->findByUser($this->getUser());
        return $this->render('front/mentorat/request_form.html.twig', [
            'mentor' => $mentor,
            'projets' => $projets,
        ]);
    }

    #[Route('/requests/{id}/respond', name: 'app_mentorat_request_respond', methods: ['POST'])]
    public function respondRequest(MentorshipRequest $req, Request $request, EntityManagerInterface $em): Response
    {
        if ($req->getMentor() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $action = $request->request->get('action');
        $req->setStatus($action === 'accept' ? 'ACCEPTED' : 'REJECTED');

        if ($action === 'accept') {
            $session = new MentorshipSession();
            $session->setMentorshipRequest($req);
            $session->setScheduledAt($req->getDate());
            $session->setDurationMinutes(60);
            $session->setStatus('scheduled');
            $em->persist($session);
        }

        $em->flush();
        $this->addFlash('success', 'Demande ' . ($action === 'accept' ? 'acceptée' : 'refusée') . '.');
        return $this->redirectToRoute('app_mentorat_requests');
    }

    #[Route('/availability', name: 'app_mentorat_availability')]
    public function availability(MentorAvailabilityRepository $repo): Response
    {
        $availabilities = $repo->findByMentor($this->getUser());
        return $this->render('front/mentorat/availability.html.twig', ['availabilities' => $availabilities]);
    }

    #[Route('/availability/new', name: 'app_mentorat_availability_new', methods: ['POST'])]
    public function newAvailability(Request $request, EntityManagerInterface $em): Response
    {
        $avail = new MentorAvailability();
        $avail->setMentor($this->getUser());
        $avail->setDate(new \DateTime($request->request->get('date')));
        $avail->setStartTime($request->request->get('start_time'));
        $avail->setEndTime($request->request->get('end_time'));
        $avail->setCreatedAt(new \DateTime());

        $em->persist($avail);
        $em->flush();
        $this->addFlash('success', 'Disponibilité ajoutée !');
        return $this->redirectToRoute('app_mentorat_availability');
    }

    #[Route('/sessions', name: 'app_mentorat_sessions')]
    public function sessions(MentorshipSessionRepository $repo): Response
    {
        $sessions = $repo->findAll();
        return $this->render('front/mentorat/sessions.html.twig', ['sessions' => $sessions]);
    }

    #[Route('/sessions/{id}/feedback', name: 'app_mentorat_session_feedback', methods: ['POST'])]
    public function sessionFeedback(MentorshipSession $session, Request $request, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        $mentorshipReq = $session->getMentorshipRequest();

        if ($mentorshipReq->getMentor() === $user) {
            $session->setMentorFeedback($request->request->get('feedback'));
            $session->setMentorRating((int) $request->request->get('rating'));
        } elseif ($mentorshipReq->getEntrepreneur() === $user) {
            $session->setEntrepreneurFeedback($request->request->get('feedback'));
            $session->setEntrepreneurRating((int) $request->request->get('rating'));
        }

        $em->flush();
        $this->addFlash('success', 'Feedback envoyé !');
        return $this->redirectToRoute('app_mentorat_sessions');
    }
}
