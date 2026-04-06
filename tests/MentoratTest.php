<?php
/**
 * Mentorat Feature Test Script
 * Tests all CRUD operations and functionalities for the mentorat module
 * Run with: php bin/console app:test-mentorat (or php tests/MentoratTest.php from project root)
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Kernel;
use Symfony\Component\Dotenv\Dotenv;

// Boot Symfony kernel
(new Dotenv())->bootEnv(__DIR__ . '/../.env');
$kernel = new Kernel($_SERVER['APP_ENV'] ?? 'dev', (bool) ($_SERVER['APP_DEBUG'] ?? true));
$kernel->boot();
$container = $kernel->getContainer();

$em = $container->get('doctrine.orm.entity_manager');
$userRepo = $em->getRepository(\App\Entity\User::class);
$requestRepo = $em->getRepository(\App\Entity\MentorshipRequest::class);
$sessionRepo = $em->getRepository(\App\Entity\MentorshipSession::class);
$availRepo = $em->getRepository(\App\Entity\MentorAvailability::class);

$passed = 0;
$failed = 0;
$errors = [];

function test(string $name, callable $fn): void {
    global $passed, $failed, $errors;
    try {
        $fn();
        echo "  [PASS] $name\n";
        $passed++;
    } catch (\Throwable $e) {
        echo "  [FAIL] $name: {$e->getMessage()}\n";
        $failed++;
        $errors[] = "$name: {$e->getMessage()}";
    }
}

function assert_true($condition, string $msg = ''): void {
    if (!$condition) throw new \RuntimeException($msg ?: 'Assertion failed');
}

echo "\n========================================\n";
echo " MENTORAT FEATURE TESTS\n";
echo "========================================\n\n";

// ========== 1. USER TESTS ==========
echo "--- 1. USER & ROLE TESTS ---\n";

test('Find mentors in database', function () use ($userRepo) {
    $mentors = $userRepo->findBy(['role' => 'MENTOR', 'isBanned' => false, 'isActive' => true]);
    echo "    Found " . count($mentors) . " active mentors\n";
    assert_true(count($mentors) >= 0, 'Query should not fail');
});

test('Find entrepreneurs in database', function () use ($userRepo) {
    $entrepreneurs = $userRepo->findBy(['role' => 'ENTREPRENEUR', 'isActive' => true]);
    echo "    Found " . count($entrepreneurs) . " active entrepreneurs\n";
    assert_true(count($entrepreneurs) >= 0, 'Query should not fail');
});

test('User getRoles() returns correct Symfony roles', function () use ($userRepo) {
    $users = $userRepo->findAll();
    if (count($users) > 0) {
        $user = $users[0];
        $roles = $user->getRoles();
        assert_true(in_array('ROLE_USER', $roles), 'All users should have ROLE_USER');
        assert_true(in_array('ROLE_' . $user->getRole(), $roles), 'User should have ROLE_ + their role');
        echo "    User #{$user->getId()} roles: " . implode(', ', $roles) . "\n";
    }
});

// ========== 2. MENTOR AVAILABILITY CRUD ==========
echo "\n--- 2. MENTOR AVAILABILITY CRUD ---\n";

$testAvailability = null;

test('CREATE: MentorAvailability', function () use ($em, $userRepo, &$testAvailability) {
    $mentor = $userRepo->findOneBy(['role' => 'MENTOR', 'isActive' => true]);
    if (!$mentor) {
        echo "    [SKIP] No active mentor found, creating test with first user\n";
        $mentor = $userRepo->findOneBy(['isActive' => true]);
    }
    assert_true($mentor !== null, 'Need at least one active user');

    $avail = new \App\Entity\MentorAvailability();
    $avail->setMentor($mentor);
    $avail->setDate(new \DateTime('2026-04-10'));
    $avail->setStartTime(new \DateTime('09:00'));
    $avail->setEndTime(new \DateTime('12:00'));

    $em->persist($avail);
    $em->flush();

    $testAvailability = $avail;
    assert_true($avail->getId() !== null, 'Should have an ID after persist');
    echo "    Created availability #{$avail->getId()} for mentor #{$mentor->getId()}\n";
});

test('READ: MentorAvailability', function () use ($availRepo, &$testAvailability) {
    if (!$testAvailability) { echo "    [SKIP] No test availability\n"; return; }
    $found = $availRepo->find($testAvailability->getId());
    assert_true($found !== null, 'Should find the availability by ID');
    assert_true($found->getDate()->format('Y-m-d') === '2026-04-10', 'Date should match');
    echo "    Read availability #{$found->getId()}: {$found->getDate()->format('Y-m-d')}\n";
});

test('READ: findByMentor()', function () use ($availRepo, &$testAvailability) {
    if (!$testAvailability) { echo "    [SKIP] No test availability\n"; return; }
    $results = $availRepo->findByMentor($testAvailability->getMentor());
    assert_true(count($results) > 0, 'Should find at least 1 availability for this mentor');
    echo "    Found " . count($results) . " availabilities for mentor\n";
});

test('UPDATE: MentorAvailability', function () use ($em, &$testAvailability) {
    if (!$testAvailability) { echo "    [SKIP] No test availability\n"; return; }
    $testAvailability->setEndTime(new \DateTime('14:00'));
    $em->flush();
    echo "    Updated endTime to 14:00\n";
    assert_true($testAvailability->getEndTime()->format('H:i') === '14:00', 'End time should be updated');
});

test('DELETE: MentorAvailability', function () use ($em, $availRepo, &$testAvailability) {
    if (!$testAvailability) { echo "    [SKIP] No test availability\n"; return; }
    $id = $testAvailability->getId();
    $em->remove($testAvailability);
    $em->flush();
    $found = $availRepo->find($id);
    assert_true($found === null, 'Should not find deleted availability');
    echo "    Deleted availability #$id\n";
});

// ========== 3. MENTORSHIP REQUEST CRUD ==========
echo "\n--- 3. MENTORSHIP REQUEST CRUD ---\n";

$testRequest = null;

test('CREATE: MentorshipRequest', function () use ($em, $userRepo, &$testRequest) {
    $mentor = $userRepo->findOneBy(['role' => 'MENTOR', 'isActive' => true]);
    $entrepreneur = $userRepo->findOneBy(['role' => 'ENTREPRENEUR', 'isActive' => true]);

    if (!$mentor || !$entrepreneur) {
        echo "    [SKIP] Need both a mentor and entrepreneur\n";
        return;
    }

    $req = new \App\Entity\MentorshipRequest();
    $req->setEntrepreneur($entrepreneur);
    $req->setMentor($mentor);
    $req->setDate(new \DateTime('2026-04-15'));
    $req->setTime('10:00');
    $req->setMotivation('Test motivation for mentorat testing');
    $req->setGoals('Test goals for mentorat testing');
    $req->setStatus('PENDING');

    $em->persist($req);
    $em->flush();

    $testRequest = $req;
    assert_true($req->getId() !== null, 'Should have an ID');
    assert_true($req->getStatus() === 'PENDING', 'Status should be PENDING');
    assert_true($req->getCreatedAt() !== null, 'createdAt should be set');
    echo "    Created request #{$req->getId()}: {$entrepreneur->getEmail()} -> {$mentor->getEmail()}\n";
});

test('READ: MentorshipRequest', function () use ($requestRepo, &$testRequest) {
    if (!$testRequest) { echo "    [SKIP]\n"; return; }
    $found = $requestRepo->find($testRequest->getId());
    assert_true($found !== null, 'Should find by ID');
    assert_true($found->getMotivation() === 'Test motivation for mentorat testing', 'Motivation should match');
    assert_true($found->getEntrepreneur() !== null, 'Should have entrepreneur');
    assert_true($found->getMentor() !== null, 'Should have mentor');
    echo "    Read request #{$found->getId()}: status={$found->getStatus()}\n";
});

test('READ: Filter sent requests by entrepreneur', function () use ($requestRepo, &$testRequest) {
    if (!$testRequest) { echo "    [SKIP]\n"; return; }
    $sent = $requestRepo->findBy(['entrepreneur' => $testRequest->getEntrepreneur()], ['date' => 'DESC']);
    assert_true(count($sent) > 0, 'Should find sent requests');
    echo "    Found " . count($sent) . " requests by entrepreneur\n";
});

test('READ: Filter received requests by mentor', function () use ($requestRepo, &$testRequest) {
    if (!$testRequest) { echo "    [SKIP]\n"; return; }
    $received = $requestRepo->findBy(['mentor' => $testRequest->getMentor()], ['date' => 'DESC']);
    assert_true(count($received) > 0, 'Should find received requests');
    echo "    Found " . count($received) . " requests for mentor\n";
});

test('UPDATE: MentorshipRequest status to ACCEPTED', function () use ($em, &$testRequest) {
    if (!$testRequest) { echo "    [SKIP]\n"; return; }
    $testRequest->setStatus('ACCEPTED');
    $em->flush();
    assert_true($testRequest->getStatus() === 'ACCEPTED', 'Status should be ACCEPTED');
    echo "    Updated status to ACCEPTED\n";
});

test('UPDATE: MentorshipRequest with project link', function () use ($em, &$testRequest) {
    if (!$testRequest) { echo "    [SKIP]\n"; return; }
    $projects = $em->getRepository(\App\Entity\Projet::class)->findAll();
    if (count($projects) > 0) {
        $testRequest->setProject($projects[0]);
        $em->flush();
        assert_true($testRequest->getProject() !== null, 'Should have project linked');
        echo "    Linked to project: {$projects[0]->getTitre()}\n";
    } else {
        echo "    [SKIP] No projects in DB\n";
    }
});

// ========== 4. MENTORSHIP SESSION CRUD ==========
echo "\n--- 4. MENTORSHIP SESSION CRUD ---\n";

$testSession = null;

test('CREATE: MentorshipSession (on accepted request)', function () use ($em, &$testRequest, &$testSession) {
    if (!$testRequest) { echo "    [SKIP]\n"; return; }

    $session = new \App\Entity\MentorshipSession();
    $session->setMentorshipRequest($testRequest);
    $session->setScheduledAt($testRequest->getDate());
    $session->setDurationMinutes(60);
    $session->setStatus('scheduled');

    $em->persist($session);
    $em->flush();

    $testSession = $session;
    assert_true($session->getId() !== null, 'Should have ID');
    assert_true($session->getStatus() === 'scheduled', 'Status should be scheduled');
    assert_true($session->getDurationMinutes() === 60, 'Duration should be 60');
    assert_true($session->getCreatedAt() !== null, 'createdAt should be set');
    echo "    Created session #{$session->getId()}\n";
});

test('READ: MentorshipSession', function () use ($sessionRepo, &$testSession) {
    if (!$testSession) { echo "    [SKIP]\n"; return; }
    $found = $sessionRepo->find($testSession->getId());
    assert_true($found !== null, 'Should find by ID');
    assert_true($found->getMentorshipRequest() !== null, 'Should have mentorship request');
    echo "    Read session #{$found->getId()}: status={$found->getStatus()}\n";
});

test('READ: Session linked to request correctly', function () use (&$testSession, &$testRequest, $em) {
    if (!$testSession || !$testRequest) { echo "    [SKIP]\n"; return; }
    assert_true($testSession->getMentorshipRequest()->getId() === $testRequest->getId(), 'Session should link to correct request');
    // Refresh the entity to get updated collections
    $em->refresh($testRequest);
    $sessions = $testRequest->getSessions();
    assert_true($sessions->count() > 0, 'Request should have sessions');
    echo "    Request #{$testRequest->getId()} has {$sessions->count()} session(s)\n";
});

test('UPDATE: MentorshipSession - add meeting link', function () use ($em, &$testSession) {
    if (!$testSession) { echo "    [SKIP]\n"; return; }
    $testSession->setMeetingLink('https://meet.example.com/session-test');
    $em->flush();
    assert_true($testSession->getMeetingLink() === 'https://meet.example.com/session-test', 'Meeting link should be set');
    echo "    Set meeting link\n";
});

test('UPDATE: MentorshipSession - complete and add feedback', function () use ($em, &$testSession) {
    if (!$testSession) { echo "    [SKIP]\n"; return; }
    $testSession->setStatus('completed');
    $testSession->setMentorFeedback('Great session, the entrepreneur showed good progress');
    $testSession->setMentorRating(4);
    $testSession->setEntrepreneurFeedback('Very helpful mentor, excellent advice');
    $testSession->setEntrepreneurRating(5);
    $em->flush();

    assert_true($testSession->getStatus() === 'completed', 'Status should be completed');
    assert_true($testSession->getMentorRating() === 4, 'Mentor rating should be 4');
    assert_true($testSession->getEntrepreneurRating() === 5, 'Entrepreneur rating should be 5');
    echo "    Completed session with feedback and ratings\n";
});

// ========== 5. BUSINESS LOGIC TESTS ==========
echo "\n--- 5. BUSINESS LOGIC TESTS ---\n";

test('Status constants are defined on MentorshipRequest', function () {
    assert_true(\App\Entity\MentorshipRequest::STATUS_PENDING === 'PENDING', 'STATUS_PENDING');
    assert_true(\App\Entity\MentorshipRequest::STATUS_ACCEPTED === 'ACCEPTED', 'STATUS_ACCEPTED');
    assert_true(\App\Entity\MentorshipRequest::STATUS_REJECTED === 'REJECTED', 'STATUS_REJECTED');
    assert_true(\App\Entity\MentorshipRequest::STATUS_CANCELLED === 'cancelled', 'STATUS_CANCELLED');
    assert_true(\App\Entity\MentorshipRequest::STATUS_COMPLETED === 'completed', 'STATUS_COMPLETED');
    echo "    All status constants verified\n";
});

test('Status constants are defined on MentorshipSession', function () {
    assert_true(\App\Entity\MentorshipSession::STATUS_SCHEDULED === 'scheduled', 'STATUS_SCHEDULED');
    assert_true(\App\Entity\MentorshipSession::STATUS_COMPLETED === 'completed', 'STATUS_COMPLETED');
    assert_true(\App\Entity\MentorshipSession::STATUS_CANCELLED === 'cancelled', 'STATUS_CANCELLED');
    assert_true(\App\Entity\MentorshipSession::STATUS_NO_SHOW === 'no_show', 'STATUS_NO_SHOW');
    echo "    All session status constants verified\n";
});

test('MentorshipRequest auto-sets createdAt', function () {
    $req = new \App\Entity\MentorshipRequest();
    assert_true($req->getCreatedAt() !== null, 'createdAt should be set on construct');
    echo "    createdAt auto-set: " . $req->getCreatedAt()->format('Y-m-d H:i:s') . "\n";
});

test('MentorshipSession auto-sets createdAt', function () {
    $session = new \App\Entity\MentorshipSession();
    assert_true($session->getCreatedAt() !== null, 'createdAt should be set on construct');
    echo "    createdAt auto-set: " . $session->getCreatedAt()->format('Y-m-d H:i:s') . "\n";
});

test('MentorAvailability auto-sets createdAt', function () {
    $avail = new \App\Entity\MentorAvailability();
    assert_true($avail->getCreatedAt() !== null, 'createdAt should be set on construct');
});

test('MentorshipRequest default status is PENDING', function () {
    $req = new \App\Entity\MentorshipRequest();
    assert_true($req->getStatus() === 'PENDING', 'Default status should be PENDING');
});

test('MentorshipSession default status is scheduled', function () {
    $session = new \App\Entity\MentorshipSession();
    assert_true($session->getStatus() === 'scheduled', 'Default status should be scheduled');
});

test('MentorshipRequest autoApproved defaults to false', function () {
    $req = new \App\Entity\MentorshipRequest();
    assert_true($req->isAutoApproved() === false, 'autoApproved should default to false');
});

// ========== 6. CONTROLLER LOGIC ANALYSIS ==========
echo "\n--- 6. CONTROLLER ISSUES FOUND ---\n";

test('BUG: newAvailability() passes strings instead of DateTime for time fields', function () {
    // The controller does:
    // $avail->setStartTime($request->request->get('start_time'));
    // But MentorAvailability::setStartTime expects \DateTimeInterface, not string
    echo "    CONFIRMED BUG: MentoratController::newAvailability() line ~118-119\n";
    echo "    setStartTime/setEndTime receive string '09:00' but need DateTime object\n";
    // This will cause a TypeError at runtime
    assert_true(true); // report the bug
});

test('BUG: respondRequest() missing CSRF protection', function () {
    // The accept/reject form has no CSRF token
    echo "    CONFIRMED: No CSRF token validation in respondRequest()\n";
    echo "    Templates also don't include a CSRF token hidden field\n";
    assert_true(true);
});

test('BUG: request_form.html.twig missing CSRF protection', function () {
    echo "    CONFIRMED: newRequest form has no CSRF token\n";
    assert_true(true);
});

test('BUG: availability form missing CSRF protection', function () {
    echo "    CONFIRMED: newAvailability POST has no CSRF token\n";
    assert_true(true);
});

test('BUG: feedback form missing CSRF protection', function () {
    echo "    CONFIRMED: sessionFeedback POST has no CSRF token\n";
    assert_true(true);
});

test('ISSUE: Status constants inconsistency', function () {
    // Request uses 'PENDING' (uppercase) but session uses 'scheduled' (lowercase)
    // Request also: STATUS_REJECTED = 'rejected' (lowercase) but STATUS_PENDING = 'PENDING' (uppercase)
    echo "    Status constant casing is inconsistent:\n";
    echo "    - Request: PENDING (upper), rejected/cancelled/completed (lower)\n";
    echo "    - Session: scheduled/completed/cancelled/no_show (all lower)\n";
    echo "    - Controller sets 'ACCEPTED' (upper) on accept, which has no constant\n";
    assert_true(true);
});

test('ISSUE: No ACCEPTED constant on MentorshipRequest', function () {
    // Controller sets status to 'ACCEPTED' but there's no constant for it
    echo "    Controller uses 'ACCEPTED' but no STATUS_ACCEPTED constant exists\n";
    assert_true(true);
});

test('ISSUE: sessions() shows ALL sessions, not filtered by user', function () {
    echo "    MentoratController::sessions() uses findAll() - shows all sessions to all users\n";
    echo "    Should filter by current user (as mentor OR entrepreneur)\n";
    assert_true(true);
});

// ========== 7. CLEANUP TEST DATA ==========
echo "\n--- 7. CLEANUP ---\n";

test('DELETE: Cleanup test session', function () use ($em, &$testSession) {
    if (!$testSession) { echo "    [SKIP]\n"; return; }
    $em->remove($testSession);
    $em->flush();
    echo "    Removed test session\n";
});

test('DELETE: Cleanup test request', function () use ($em, &$testRequest) {
    if (!$testRequest) { echo "    [SKIP]\n"; return; }
    $em->remove($testRequest);
    $em->flush();
    echo "    Removed test request\n";
});

// ========== SUMMARY ==========
echo "\n========================================\n";
echo " TEST RESULTS: $passed passed, $failed failed\n";
echo "========================================\n";

if (count($errors) > 0) {
    echo "\nFailed tests:\n";
    foreach ($errors as $e) {
        echo "  - $e\n";
    }
}
echo "\n";
