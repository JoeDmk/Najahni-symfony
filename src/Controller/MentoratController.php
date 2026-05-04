<?php

namespace App\Controller;

use App\Entity\MentorAvailability;
use App\Entity\MentorshipRequest;
use App\Entity\MentorshipSession;
use App\Repository\MentorAvailabilityRepository;
use App\Repository\MentorshipRequestRepository;
use App\Repository\MentorshipSessionRepository;
use App\Repository\UserRepository;
use App\Service\CommunityAiService;
use App\Service\MentoratMatchingService;
use Doctrine\ORM\EntityManagerInterface;
use Dompdf\Dompdf;
use Dompdf\Options;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/mentorat')]
#[IsGranted('ROLE_USER')]
class MentoratController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private MentoratMatchingService $matchingService,
    ) {}

    // ──────────────────────────────────────────────
    //  REQUESTS TAB
    // ──────────────────────────────────────────────

    #[Route('/requests', name: 'app_mentorat_requests')]
    public function requests(MentorshipRequestRepository $repo): Response
    {
        $user = $this->getUser();
        $sentRequests = $repo->findBy(['entrepreneur' => $user], ['createdAt' => 'DESC']);
        $receivedRequests = $repo->findBy(['mentor' => $user], ['createdAt' => 'DESC']);

        return $this->render('front/mentorat/requests.html.twig', [
            'sentRequests' => $sentRequests,
            'receivedRequests' => $receivedRequests,
        ]);
    }

    #[Route('/mentors', name: 'app_mentorat_mentors')]
    public function mentors(MentorAvailabilityRepository $availRepo): Response
    {
        // Entrepreneur sees all mentor availability slots (future dates)
        $availabilities = $availRepo->createQueryBuilder('a')
            ->join('a.mentor', 'm')
            ->where('a.date >= :today')
            ->andWhere('m.isBanned = false')
            ->andWhere('m.isActive = true')
            ->setParameter('today', new \DateTime('today'))
            ->orderBy('a.date', 'ASC')
            ->addOrderBy('a.startTime', 'ASC')
            ->getQuery()->getResult();

        $projets = $this->em->getRepository(\App\Entity\Projet::class)->findByUser($this->getUser());

        return $this->render('front/mentorat/mentors.html.twig', [
            'availabilities' => $availabilities,
            'projets' => $projets,
        ]);
    }

    #[Route('/request/new/{mentorId}', name: 'app_mentorat_request_new', methods: ['GET', 'POST'])]
    public function newRequest(int $mentorId, Request $request, UserRepository $userRepo): Response
    {
        $mentor = $userRepo->find($mentorId);
        if (!$mentor || $mentor->getRole() !== 'MENTOR') {
            throw $this->createNotFoundException('Mentor non trouvé');
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('mentorat_request', $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Jeton CSRF invalide.');
            }

            $req = new MentorshipRequest();
            $req->setEntrepreneur($this->getUser());
            $req->setMentor($mentor);
            $req->setDate(new \DateTime($request->request->get('date')));
            $req->setTime($request->request->get('time'));
            $req->setMotivation($request->request->get('motivation'));
            $req->setGoals($request->request->get('goals'));

            $projectSecteur = null;
            $projectId = $request->request->get('project_id');
            if ($projectId) {
                $project = $this->em->getRepository(\App\Entity\Projet::class)->find($projectId);
                if ($project) {
                    $req->setProject($project);
                    $projectSecteur = $project->getSecteur();
                }
            }

            // Calculate matching score and potentially auto-accept
            $this->matchingService->processRequest($req, $projectSecteur);

            $this->em->persist($req);

            // If auto-accepted, create a session immediately
            if ($req->getStatus() === MentorshipRequest::STATUS_AUTO_ACCEPTED) {
                $session = new MentorshipSession();
                $session->setMentorshipRequest($req);
                $session->setScheduledAt($req->getDate());
                $session->setDurationMinutes(60);
                $session->setStatus(MentorshipSession::STATUS_SCHEDULED);
                $this->em->persist($session);
                $this->addFlash('success', 'Demande auto-acceptée (match ' . $req->getMatchScore() . '%) ! Session créée.');
            } else {
                $this->addFlash('success', 'Demande envoyée (match ' . $req->getMatchScore() . '%). En attente de réponse du mentor.');
            }

            $this->em->flush();
            return $this->redirectToRoute('app_mentorat_requests');
        }

        // Pre-fill date/time from query string (passed from availability card)
        $prefillDate = $request->query->get('date', '');
        $prefillTime = $request->query->get('time', '');

        $projets = $this->em->getRepository(\App\Entity\Projet::class)->findByUser($this->getUser());
        return $this->render('front/mentorat/request_form.html.twig', [
            'mentor' => $mentor,
            'projets' => $projets,
            'prefillDate' => $prefillDate,
            'prefillTime' => $prefillTime,
        ]);
    }

    #[Route('/requests/{id}/edit', name: 'app_mentorat_request_edit', methods: ['GET', 'POST'])]
    public function editRequest(MentorshipRequest $req, Request $request): Response
    {
        if ($req->getEntrepreneur() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }
        if (!in_array($req->getStatus(), [MentorshipRequest::STATUS_PENDING, MentorshipRequest::STATUS_CANCELLED])) {
            $this->addFlash('warning', 'Cette demande ne peut plus être modifiée.');
            return $this->redirectToRoute('app_mentorat_requests');
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('mentorat_request_edit_' . $req->getId(), $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Jeton CSRF invalide.');
            }

            $req->setDate(new \DateTime($request->request->get('date')));
            $req->setTime($request->request->get('time'));
            $req->setMotivation($request->request->get('motivation'));
            $req->setGoals($request->request->get('goals'));
            $req->setStatus(MentorshipRequest::STATUS_PENDING);

            $projectSecteur = null;
            $projectId = $request->request->get('project_id');
            if ($projectId) {
                $project = $this->em->getRepository(\App\Entity\Projet::class)->find($projectId);
                $req->setProject($project);
                $projectSecteur = $project?->getSecteur();
            } else {
                $req->setProject(null);
            }

            $this->matchingService->processRequest($req, $projectSecteur);
            if ($req->getStatus() === MentorshipRequest::STATUS_AUTO_ACCEPTED) {
                $session = new MentorshipSession();
                $session->setMentorshipRequest($req);
                $session->setScheduledAt($req->getDate());
                $session->setDurationMinutes(60);
                $session->setStatus(MentorshipSession::STATUS_SCHEDULED);
                $this->em->persist($session);
            }

            $this->em->flush();
            $this->addFlash('success', 'Demande modifiée.');
            return $this->redirectToRoute('app_mentorat_requests');
        }

        $projets = $this->em->getRepository(\App\Entity\Projet::class)->findByUser($this->getUser());
        return $this->render('front/mentorat/request_edit.html.twig', [
            'req' => $req,
            'projets' => $projets,
        ]);
    }

    #[Route('/requests/{id}/cancel', name: 'app_mentorat_request_cancel', methods: ['POST'])]
    public function cancelRequest(MentorshipRequest $req, Request $request): Response
    {
        if ($req->getEntrepreneur() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }
        if (!$this->isCsrfTokenValid('mentorat_cancel_' . $req->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $req->setStatus(MentorshipRequest::STATUS_CANCELLED);
        $this->em->flush();
        $this->addFlash('success', 'Demande annulée.');
        return $this->redirectToRoute('app_mentorat_requests');
    }

    #[Route('/requests/{id}/delete', name: 'app_mentorat_request_delete', methods: ['POST'])]
    public function deleteRequest(MentorshipRequest $req, Request $request): Response
    {
        if ($req->getEntrepreneur() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }
        if (!$this->isCsrfTokenValid('mentorat_delete_req_' . $req->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $this->em->remove($req);
        $this->em->flush();
        $this->addFlash('success', 'Demande supprimée.');
        return $this->redirectToRoute('app_mentorat_requests');
    }

    #[Route('/requests/{id}/respond', name: 'app_mentorat_request_respond', methods: ['POST'])]
    public function respondRequest(MentorshipRequest $req, Request $request): Response
    {
        if ($req->getMentor() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }
        if (!$this->isCsrfTokenValid('mentorat_respond_' . $req->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $action = $request->request->get('action');
        if ($action === 'accept') {
            $req->setStatus(MentorshipRequest::STATUS_ACCEPTED);
            $session = new MentorshipSession();
            $session->setMentorshipRequest($req);
            $session->setScheduledAt($req->getDate());
            $session->setDurationMinutes(60);
            $session->setStatus(MentorshipSession::STATUS_SCHEDULED);
            $this->em->persist($session);
        } else {
            $req->setStatus(MentorshipRequest::STATUS_REJECTED);
        }

        $this->em->flush();
        $this->addFlash('success', 'Demande ' . ($action === 'accept' ? 'acceptée' : 'refusée') . '.');
        return $this->redirectToRoute('app_mentorat_requests');
    }

    // ──────────────────────────────────────────────
    //  SESSIONS TAB
    // ──────────────────────────────────────────────

    #[Route('/sessions', name: 'app_mentorat_sessions')]
    public function sessions(MentorshipSessionRepository $repo): Response
    {
        $sessions = $repo->findByUser($this->getUser());
        return $this->render('front/mentorat/sessions.html.twig', ['sessions' => $sessions]);
    }

    #[Route('/sessions/new', name: 'app_mentorat_session_new', methods: ['GET', 'POST'])]
    public function newSession(Request $request, MentorshipRequestRepository $reqRepo): Response
    {
        $user = $this->getUser();
        if ($user->getRole() !== 'MENTOR') {
            throw $this->createAccessDeniedException('Seul un mentor peut créer une session.');
        }
        $acceptedRequests = $reqRepo->createQueryBuilder('r')
            ->where('(r.mentor = :u OR r.entrepreneur = :u)')
            ->andWhere('r.status IN (:statuses)')
            ->setParameter('u', $user)
            ->setParameter('statuses', [MentorshipRequest::STATUS_ACCEPTED, MentorshipRequest::STATUS_AUTO_ACCEPTED])
            ->orderBy('r.date', 'DESC')
            ->getQuery()->getResult();

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('mentorat_session_new', $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Jeton CSRF invalide.');
            }

            $reqId = $request->request->get('request_id');
            $mentorshipReq = $reqRepo->find($reqId);
            if (!$mentorshipReq || ($mentorshipReq->getMentor() !== $user && $mentorshipReq->getEntrepreneur() !== $user)) {
                throw $this->createAccessDeniedException();
            }

            $session = new MentorshipSession();
            $session->setMentorshipRequest($mentorshipReq);
            $session->setScheduledAt(new \DateTime($request->request->get('scheduled_at')));
            $session->setDurationMinutes((int) $request->request->get('duration', 60));
            $session->setMeetingLink($request->request->get('meeting_link'));
            $session->setStatus(MentorshipSession::STATUS_SCHEDULED);

            $this->em->persist($session);
            $this->em->flush();
            $this->addFlash('success', 'Session créée.');
            return $this->redirectToRoute('app_mentorat_sessions');
        }

        return $this->render('front/mentorat/session_form.html.twig', [
            'acceptedRequests' => $acceptedRequests,
            'session' => null,
        ]);
    }

    #[Route('/sessions/{id}/edit', name: 'app_mentorat_session_edit', methods: ['GET', 'POST'])]
    public function editSession(MentorshipSession $session, Request $request, MentorshipRequestRepository $reqRepo): Response
    {
        $user = $this->getUser();
        $mentorshipReq = $session->getMentorshipRequest();
        if (!$mentorshipReq || $mentorshipReq->getMentor() !== $user) {
            throw $this->createAccessDeniedException('Seul le mentor peut modifier cette session.');
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('mentorat_session_edit_' . $session->getId(), $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Jeton CSRF invalide.');
            }

            $session->setScheduledAt(new \DateTime($request->request->get('scheduled_at')));
            $session->setDurationMinutes((int) $request->request->get('duration', 60));
            $session->setMeetingLink($request->request->get('meeting_link'));
            $session->setStatus($request->request->get('status', MentorshipSession::STATUS_SCHEDULED));

            $this->em->flush();
            $this->addFlash('success', 'Session modifiée.');
            return $this->redirectToRoute('app_mentorat_sessions');
        }

        $acceptedRequests = $reqRepo->createQueryBuilder('r')
            ->where('(r.mentor = :u OR r.entrepreneur = :u)')
            ->andWhere('r.status IN (:statuses)')
            ->setParameter('u', $user)
            ->setParameter('statuses', [MentorshipRequest::STATUS_ACCEPTED, MentorshipRequest::STATUS_AUTO_ACCEPTED])
            ->orderBy('r.date', 'DESC')
            ->getQuery()->getResult();

        return $this->render('front/mentorat/session_form.html.twig', [
            'acceptedRequests' => $acceptedRequests,
            'session' => $session,
        ]);
    }

    #[Route('/sessions/{id}/delete', name: 'app_mentorat_session_delete', methods: ['POST'])]
    public function deleteSession(MentorshipSession $session, Request $request): Response
    {
        $user = $this->getUser();
        $mentorshipReq = $session->getMentorshipRequest();
        if (!$mentorshipReq || $mentorshipReq->getMentor() !== $user) {
            throw $this->createAccessDeniedException('Seul le mentor peut supprimer cette session.');
        }
        if (!$this->isCsrfTokenValid('mentorat_session_del_' . $session->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $this->em->remove($session);
        $this->em->flush();
        $this->addFlash('success', 'Session supprimée.');
        return $this->redirectToRoute('app_mentorat_sessions');
    }

    #[Route('/sessions/{id}/feedback', name: 'app_mentorat_session_feedback', methods: ['POST'])]
    public function sessionFeedback(MentorshipSession $session, Request $request, CommunityAiService $communityAiService): Response
    {
        if (!$this->isCsrfTokenValid('mentorat_feedback_' . $session->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $user = $this->getUser();
        $mentorshipReq = $session->getMentorshipRequest();

        if ($mentorshipReq->getMentor() === $user) {
            $session->setMentorFeedback($request->request->get('feedback'));
            $session->setMentorRating((int) $request->request->get('rating'));
        } elseif ($mentorshipReq->getEntrepreneur() === $user) {
            $session->setEntrepreneurFeedback($request->request->get('feedback'));
            $session->setEntrepreneurRating((int) $request->request->get('rating'));
        }

        $this->em->flush();

        if ($communityAiService->isConfigured()) {
            $summary = $communityAiService->summarizeSessionFeedback($session);
            if (is_string($summary) && trim($summary) !== '') {
                $session->setSessionSummary(trim($summary));
                $this->em->flush();
            }
        }

        $this->addFlash('success', 'Feedback envoyé !');
        return $this->redirectToRoute('app_mentorat_sessions');
    }

    // ──────────────────────────────────────────────
    //  EXPORT — PDF & EXCEL
    // ──────────────────────────────────────────────

    #[Route('/sessions/export/pdf', name: 'app_mentorat_sessions_export_pdf')]
    public function exportSessionsPdf(MentorshipSessionRepository $repo): Response
    {
        $sessions = $repo->findByUser($this->getUser());
        $userRole = $this->getUser()->getRole();
        $html = $this->renderView('front/mentorat/sessions_pdf.html.twig', ['sessions' => $sessions, 'userRole' => $userRole]);

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'Helvetica');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        return new Response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="sessions_mentorat.pdf"',
        ]);
    }

    #[Route('/sessions/export/excel', name: 'app_mentorat_sessions_export_excel')]
    public function exportSessionsExcel(MentorshipSessionRepository $repo): StreamedResponse
    {
        $sessions = $repo->findByUser($this->getUser());
        $userRole = $this->getUser()->getRole();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Sessions Mentorat');

        $otherLabel = $userRole === 'MENTOR' ? 'Entrepreneur' : 'Mentor';
        $headers = ['#', $otherLabel, 'Date', 'Durée (min)', 'Statut', 'Lien', 'Mon feedback', 'Ma note'];
        foreach ($headers as $col => $h) {
            $sheet->setCellValue([$col + 1, 1], $h);
            $sheet->getStyle([$col + 1, 1])->getFont()->setBold(true);
        }

        $row = 2;
        foreach ($sessions as $s) {
            $r = $s->getMentorshipRequest();
            $otherName = '-';
            if ($r) {
                $otherName = $userRole === 'MENTOR'
                    ? $r->getEntrepreneur()->getFirstname() . ' ' . $r->getEntrepreneur()->getLastname()
                    : $r->getMentor()->getFirstname() . ' ' . $r->getMentor()->getLastname();
            }
            $sheet->setCellValue([1, $row], $s->getId());
            $sheet->setCellValue([2, $row], $otherName);
            $sheet->setCellValue([3, $row], $s->getScheduledAt()?->format('d/m/Y H:i') ?? '-');
            $sheet->setCellValue([4, $row], $s->getDurationMinutes() ?? '-');
            $sheet->setCellValue([5, $row], $s->getStatus());
            $sheet->setCellValue([6, $row], $s->getMeetingLink() ?? '-');
            if ($userRole === 'MENTOR') {
                $sheet->setCellValue([7, $row], $s->getMentorFeedback() ?? '-');
                $sheet->setCellValue([8, $row], $s->getMentorRating() ? $s->getMentorRating() . '/5' : '-');
            } else {
                $sheet->setCellValue([7, $row], $s->getEntrepreneurFeedback() ?? '-');
                $sheet->setCellValue([8, $row], $s->getEntrepreneurRating() ? $s->getEntrepreneurRating() . '/5' : '-');
            }
            $row++;
        }

        foreach (range(1, 8) as $col) {
            $sheet->getColumnDimensionByColumn($col)->setAutoSize(true);
        }

        $response = new StreamedResponse(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        });

        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', 'attachment; filename="sessions_mentorat.xlsx"');
        return $response;
    }

    // ──────────────────────────────────────────────
    //  AVAILABILITY TAB
    // ──────────────────────────────────────────────

    #[Route('/availability', name: 'app_mentorat_availability')]
    public function availability(MentorAvailabilityRepository $repo): Response
    {
        $availabilities = $repo->findByMentor($this->getUser());
        return $this->render('front/mentorat/availability.html.twig', ['availabilities' => $availabilities]);
    }

    #[Route('/availability/new', name: 'app_mentorat_availability_new', methods: ['POST'])]
    public function newAvailability(Request $request, MentorAvailabilityRepository $availRepo): Response
    {
        if (!$this->isCsrfTokenValid('mentorat_availability', $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $date = new \DateTime($request->request->get('date'));
        $startTime = new \DateTime($request->request->get('start_time'));
        $endTime = new \DateTime($request->request->get('end_time'));

        if ($availRepo->hasOverlappingAvailability($this->getUser(), $date, $startTime, $endTime)) {
            $this->addFlash('error', 'Cette disponibilité chevauche une autre disponibilité existante.');
            return $this->redirectToRoute('app_mentorat_availability');
        }

        $avail = new MentorAvailability();
        $avail->setMentor($this->getUser());
        $avail->setDate($date);
        $avail->setStartTime($startTime);
        $avail->setEndTime($endTime);

        $this->em->persist($avail);
        $this->em->flush();
        $this->addFlash('success', 'Disponibilité ajoutée !');
        return $this->redirectToRoute('app_mentorat_availability');
    }

    #[Route('/availability/{id}/edit', name: 'app_mentorat_availability_edit', methods: ['POST'])]
    public function editAvailability(MentorAvailability $avail, Request $request, MentorAvailabilityRepository $availRepo): Response
    {
        if ($avail->getMentor() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }
        if (!$this->isCsrfTokenValid('mentorat_avail_edit_' . $avail->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $date = new \DateTime($request->request->get('date'));
        $startTime = new \DateTime($request->request->get('start_time'));
        $endTime = new \DateTime($request->request->get('end_time'));

        if ($availRepo->hasOverlappingAvailability($this->getUser(), $date, $startTime, $endTime, $avail->getId())) {
            $this->addFlash('error', 'Cette disponibilité chevauche une autre disponibilité existante.');
            return $this->redirectToRoute('app_mentorat_availability');
        }

        $avail->setDate($date);
        $avail->setStartTime($startTime);
        $avail->setEndTime($endTime);

        $this->em->flush();
        $this->addFlash('success', 'Disponibilité modifiée.');
        return $this->redirectToRoute('app_mentorat_availability');
    }

    #[Route('/availability/{id}/delete', name: 'app_mentorat_availability_delete', methods: ['POST'])]
    public function deleteAvailability(MentorAvailability $avail, Request $request): Response
    {
        if ($avail->getMentor() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }
        if (!$this->isCsrfTokenValid('mentorat_avail_del_' . $avail->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $this->em->remove($avail);
        $this->em->flush();
        $this->addFlash('success', 'Disponibilité supprimée.');
        return $this->redirectToRoute('app_mentorat_availability');
    }

    // ──────────────────────────────────────────────
    //  CALENDAR — Entrepreneur view
    // ──────────────────────────────────────────────

    #[Route('/calendar', name: 'app_mentorat_calendar')]
    public function calendar(Request $request, MentorAvailabilityRepository $availRepo): Response
    {
        $year  = (int) $request->query->get('year', date('Y'));
        $month = (int) $request->query->get('month', date('n'));

        if ($month < 1)  { $month = 12; $year--; }
        if ($month > 12) { $month = 1;  $year++; }

        $firstDay = new \DateTime("$year-$month-01");
        $lastDay  = (clone $firstDay)->modify('last day of this month');

        // Fetch all mentor availabilities for this month
        $availabilities = $availRepo->createQueryBuilder('a')
            ->join('a.mentor', 'm')
            ->where('a.date BETWEEN :start AND :end')
            ->andWhere('m.isBanned = false')
            ->andWhere('m.isActive = true')
            ->setParameter('start', $firstDay)
            ->setParameter('end', $lastDay)
            ->orderBy('a.date', 'ASC')
            ->addOrderBy('a.startTime', 'ASC')
            ->getQuery()->getResult();

        // Group availabilities by date string (Y-m-d)
        $availByDate = [];
        foreach ($availabilities as $a) {
            $key = $a->getDate()->format('Y-m-d');
            $availByDate[$key][] = $a;
        }

        return $this->render('front/mentorat/calendar.html.twig', [
            'year'        => $year,
            'month'       => $month,
            'firstDay'    => $firstDay,
            'lastDay'     => $lastDay,
            'availByDate' => $availByDate,
        ]);
    }

    // ──────────────────────────────────────────────
    //  MY CALENDAR — Mentor's own availability view
    // ──────────────────────────────────────────────

    #[Route('/my-calendar', name: 'app_mentorat_my_calendar')]
    public function myCalendar(Request $request, MentorAvailabilityRepository $availRepo): Response
    {
        $user = $this->getUser();

        $year  = (int) $request->query->get('year', date('Y'));
        $month = (int) $request->query->get('month', date('n'));

        if ($month < 1)  { $month = 12; $year--; }
        if ($month > 12) { $month = 1;  $year++; }

        $firstDay = new \DateTime("$year-$month-01");
        $lastDay  = (clone $firstDay)->modify('last day of this month');

        // Fetch only THIS mentor's availabilities for the month
        $availabilities = $availRepo->createQueryBuilder('a')
            ->where('a.mentor = :mentor')
            ->andWhere('a.date BETWEEN :start AND :end')
            ->setParameter('mentor', $user)
            ->setParameter('start', $firstDay)
            ->setParameter('end', $lastDay)
            ->orderBy('a.date', 'ASC')
            ->addOrderBy('a.startTime', 'ASC')
            ->getQuery()->getResult();

        $availByDate = [];
        foreach ($availabilities as $a) {
            $key = $a->getDate()->format('Y-m-d');
            $availByDate[$key][] = $a;
        }

        return $this->render('front/mentorat/my_calendar.html.twig', [
            'year'        => $year,
            'month'       => $month,
            'firstDay'    => $firstDay,
            'lastDay'     => $lastDay,
            'availByDate' => $availByDate,
        ]);
    }

    // ──────────────────────────────────────────────
    //  CHATBOT TAB
    // ──────────────────────────────────────────────

    #[Route('/chatbot', name: 'app_mentorat_chatbot')]
    public function chatbot(CommunityAiService $communityAiService): Response
    {
        return $this->render('front/mentorat/chatbot.html.twig', [
            'aiAvailable' => $communityAiService->isConfigured(),
        ]);
    }

    #[Route('/chatbot/api', name: 'app_mentorat_chatbot_api', methods: ['POST'])]
    public function chatbotApi(Request $request, CommunityAiService $communityAiService): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $question = trim($data['question'] ?? '');

        if (!$question) {
            return $this->json(['answer' => 'Veuillez poser une question.']);
        }

        return $this->json([
            'answer' => $this->findChatbotAnswer($question, $communityAiService),
        ]);
    }

    private function findChatbotAnswer(string $text, ?CommunityAiService $communityAiService = null): string
    {
        if ($communityAiService !== null && $communityAiService->isConfigured()) {
            $aiAnswer = $communityAiService->answerQuestion($text);
            if (is_string($aiAnswer) && trim($aiAnswer) !== '') {
                return trim($aiAnswer);
            }
        }

        $lower = $this->normalizeText($text);

        $knowledgeBase = [
            [
                'keywords' => ['najahni', 'plateforme', 'platform', 'cest quoi', 'what is', 'a propos', 'about'],
                'answer' => 'Najahni est une plateforme digitale conçue pour favoriser l\'entrepreneuriat et le mentorat en connectant des fondateurs dynamiques avec des mentors expérimentés, des investisseurs et des ressources essentielles pour construire et développer des projets innovants.',
            ],
            [
                'keywords' => ['qui a cree', 'qui a fait', 'createur', 'origine', 'esprit'],
                'answer' => 'Najahni est un projet PIDEV 3A réalisé par des étudiants d\'Esprit, Ariana, Tunisie. C\'est une plateforme tunisienne qui connecte entrepreneurs, mentors et investisseurs.',
            ],
            [
                'keywords' => ['meilleur mentor', 'best mentor', 'top mentor', 'recommand'],
                'answer' => 'Les meilleurs mentors dépendent de vos besoins. Les super mentors actifs sur Najahni ont généralement de solides compétences en startups tech, gestion stratégique et levée de fonds. Consultez le tableau de bord pour voir les mentors "Top Rated" basés sur les retours de la communauté.',
            ],
            [
                'keywords' => ['trouver mentor', 'find mentor', 'chercher mentor', 'mentor disponible', 'available mentor'],
                'answer' => 'Vous pouvez naviguer vers l\'onglet "Trouver un mentor" pour voir les créneaux disponibles. Vous pouvez également consulter le Calendrier de disponibilité pour voir les dates colorées en vert où des mentors sont libres.',
            ],
            [
                'keywords' => ['calendrier', 'calendar', 'disponibilite mentor', 'planning'],
                'answer' => 'Le calendrier de disponibilité affiche les dates où les mentors sont disponibles avec des cases vertes. Cliquez sur une date verte pour voir les créneaux et envoyer directement une demande. Accédez-y via Mentorat → Trouver un mentor → Calendrier.',
            ],
            [
                'keywords' => ['reserver', 'book', 'prendre rendez', 'demande mentorat', 'request'],
                'answer' => 'Pour réserver une session : trouvez un créneau disponible dans l\'onglet "Trouver un mentor" ou le Calendrier, puis envoyez une demande de mentorat. Une fois approuvée par le mentor (ou auto-acceptée si le matching est ≥ 70%), la session apparaîtra dans votre onglet "Sessions".',
            ],
            [
                'keywords' => ['demande', 'envoyer demande', 'nouvelle demande', 'creer demande'],
                'answer' => 'Pour créer une demande : allez dans "Trouver un mentor", choisissez un mentor et remplissez le formulaire avec votre motivation, vos objectifs et éventuellement un projet lié. Le score de matching sera calculé automatiquement.',
            ],
            [
                'keywords' => ['annuler', 'cancel', 'supprimer demande'],
                'answer' => 'Vous pouvez annuler une demande en attente depuis l\'onglet "Demandes" en cliquant sur le bouton d\'annulation. Les demandes annulées peuvent aussi être modifiées et renvoyées.',
            ],
            [
                'keywords' => ['modifier', 'edit', 'changer demande', 'mettre a jour'],
                'answer' => 'Les demandes en attente ou annulées peuvent être modifiées. Cliquez sur l\'icône crayon (✏️) dans la colonne Actions de l\'onglet "Demandes".',
            ],
            [
                'keywords' => ['statut', 'status', 'etat'],
                'answer' => 'Les statuts possibles pour une demande sont : En attente (PENDING), Accepté (ACCEPTED), Auto-accepté, Refusé (REJECTED), Annulé (CANCELLED). Pour les sessions : Planifiée, Terminée, Annulée, No-show.',
            ],
            [
                'keywords' => ['session', 'seance', 'rendez-vous', 'meeting'],
                'answer' => 'Les sessions sont créées automatiquement lorsqu\'une demande est acceptée. Les mentors peuvent aussi en créer manuellement. Chaque session a une date, durée, lien de réunion et un système de feedback.',
            ],
            [
                'keywords' => ['feedback', 'avis', 'note', 'rating', 'evaluation'],
                'answer' => 'Après qu\'une session est terminée, le mentor et l\'entrepreneur peuvent laisser un feedback et une note de 1 à 5 étoiles. Cela contribue au classement des mentors sur la plateforme.',
            ],
            [
                'keywords' => ['export', 'pdf', 'excel', 'telecharger'],
                'answer' => 'Vous pouvez exporter vos sessions en PDF ou Excel depuis l\'onglet "Sessions" en cliquant sur les boutons d\'export en haut de la page. Le PDF est au format A4 paysage.',
            ],
            [
                'keywords' => ['matching', 'score', 'auto-accept', 'compatibilite'],
                'answer' => 'Le score de matching est calculé automatiquement en fonction de votre profil, secteur d\'activité, bio et vérification du compte. Un score ≥ 70% entraîne l\'auto-acceptation de la demande et la création automatique d\'une session.',
            ],
            [
                'keywords' => ['disponibilite', 'creneau', 'horaire', 'slot', 'availability'],
                'answer' => 'En tant que mentor, gérez vos créneaux dans l\'onglet "Disponibilités" : ajoutez, modifiez ou supprimez vos horaires. Votre calendrier personnel ("Mon Calendrier") affiche toutes vos disponibilités publiées avec les dates en vert.',
            ],
            [
                'keywords' => ['chat en direct', 'real-time', 'messagerie', 'message direct', 'direct message'],
                'answer' => 'Actuellement, l\'application supporte la planification et la gestion des sessions de mentorat. Vous pouvez aussi exporter les détails des sessions en PDF. La messagerie directe pourrait être disponible dans de futures versions.',
            ],
            [
                'keywords' => ['investissement', 'investment', 'investisseur', 'investor', 'financement', 'funding'],
                'answer' => 'Le module Investissement de Najahni permet aux entrepreneurs de publier des opportunités et aux investisseurs de faire des offres. Accédez-y via le menu "Investissement" dans la barre de navigation.',
            ],
            [
                'keywords' => ['projet', 'project', 'startup'],
                'answer' => 'Le module Projets vous permet de créer et gérer vos projets entrepreneuriaux. Vous pouvez lier un projet à une demande de mentorat pour que le mentor comprenne mieux vos besoins.',
            ],
            [
                'keywords' => ['communaute', 'community', 'groupe', 'group', 'event', 'evenement', 'post'],
                'answer' => 'Le module Communauté comprend les posts, groupes et événements. Partagez vos idées, rejoignez des groupes thématiques et participez aux événements organisés par la communauté Najahni.',
            ],
            [
                'keywords' => ['apprentissage', 'cours', 'learning', 'formation', 'badge', 'xp'],
                'answer' => 'Le module Apprentissage propose des cours classés par niveau (Débutant à Expert). Complétez des cours pour gagner des points XP et débloquer des badges (Commun, Rare, Épique, Légendaire).',
            ],
            [
                'keywords' => ['profil', 'compte', 'profile', 'account', 'parametre', 'settings'],
                'answer' => 'Gérez votre profil depuis le menu en haut à droite → "Mon Profil". Vous pouvez modifier vos informations, votre photo, bio, entreprise, et préférences de langue/thème/devise.',
            ],
            [
                'keywords' => ['bonjour', 'salut', 'hello', 'hi', 'bonsoir', 'hey', 'salam'],
                'answer' => 'Bonjour ! 👋 Je suis l\'assistant Najahni. Comment puis-je vous aider aujourd\'hui ? Vous pouvez me poser des questions sur le mentorat, les sessions, le calendrier, les projets, l\'investissement et plus encore !',
            ],
            [
                'keywords' => ['merci', 'thanks', 'thank you', 'شكرا'],
                'answer' => 'Avec plaisir ! N\'hésitez pas si vous avez d\'autres questions. 😊',
            ],
            [
                'keywords' => ['aide', 'help', 'comment', 'how'],
                'answer' => 'Je peux vous aider avec : 🔹 Trouver un mentor et consulter le calendrier\n🔹 Créer, modifier ou annuler des demandes\n🔹 Gérer vos sessions et laisser un feedback\n🔹 Exporter vos données (PDF/Excel)\n🔹 Comprendre le matching et l\'auto-acceptation\n🔹 Naviguer dans les modules : Projets, Investissement, Communauté, Apprentissage\n\nPosez simplement votre question !',
            ],
        ];

        $bestAnswer = null;
        $bestScore = 0;

        foreach ($knowledgeBase as $entry) {
            $score = 0;
            foreach ($entry['keywords'] as $kw) {
                if (str_contains($lower, $kw)) {
                    $score += mb_strlen($kw);
                }
            }
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestAnswer = $entry['answer'];
            }
        }

        if ($bestAnswer) {
            return $bestAnswer;
        }

        return 'Je ne dispose pas d\'information sur ce sujet pour le moment. Essayez de me poser des questions sur Najahni : mentorat, calendrier, demandes, sessions, projets, investissement, communauté ou apprentissage.';
    }

    private function normalizeText(string $text): string
    {
        $normalized = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        if ($normalized === false) {
            $normalized = $text;
        }

        $normalized = mb_strtolower($normalized);
        return preg_replace('/[^a-z0-9 ]/', ' ', $normalized);
    }
}
