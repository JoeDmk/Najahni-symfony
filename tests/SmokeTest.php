<?php

namespace App\Tests;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class SmokeTest extends WebTestCase
{
    private function loginAsAdmin($client): void
    {
        $em = static::getContainer()->get('doctrine')->getManager();
        $user = $em->getRepository(User::class)->findOneBy(['email' => 'admin@najahni.tn']);
        $client->loginUser($user);
    }

    /**
     * @dataProvider publicUrlProvider
     */
    public function testPublicPagesLoad(string $url): void
    {
        $client = static::createClient();
        $client->request('GET', $url);
        $this->assertLessThan(500, $client->getResponse()->getStatusCode(),
            "Page $url returned 500: " . substr($client->getResponse()->getContent(), 0, 300));
    }

    public static function publicUrlProvider(): \Generator
    {
        yield 'home' => ['/'];
        yield 'login' => ['/login'];
        yield 'register' => ['/register'];
        yield 'forgot-password' => ['/forgot-password'];
    }

    /**
     * @dataProvider authenticatedUrlProvider
     */
    public function testAuthenticatedPagesLoad(string $url): void
    {
        $client = static::createClient();
        $this->loginAsAdmin($client);
        $client->request('GET', $url);
        $status = $client->getResponse()->getStatusCode();
        $this->assertLessThan(500, $status,
            "Page $url returned $status: " . substr($client->getResponse()->getContent(), 0, 500));
    }

    public static function authenticatedUrlProvider(): \Generator
    {
        // ===== Profile =====
        yield 'profile' => ['/profile'];
        yield 'profile-edit' => ['/profile/edit'];
        yield 'notifications' => ['/profile/notifications'];
        yield 'login-history' => ['/profile/login-history'];

        // ===== Community =====
        yield 'community-posts' => ['/community/posts'];
        yield 'community-groups' => ['/community/groups'];
        yield 'community-group-new' => ['/community/groups/new'];
        yield 'community-events' => ['/community/events'];
        yield 'community-event-new' => ['/community/events/new'];

        // ===== Investment =====
        yield 'opportunities' => ['/investissement/opportunities'];
        yield 'my-offers' => ['/investissement/my-offers'];
        yield 'create-opportunity' => ['/investissement/create-opportunity'];

        // ===== Mentorat =====
        yield 'mentorat-requests' => ['/mentorat/requests'];
        yield 'mentorat-mentors' => ['/mentorat/mentors'];
        yield 'mentorat-sessions' => ['/mentorat/sessions'];
        yield 'mentorat-availability' => ['/mentorat/availability'];
        yield 'mentorat-chatbot' => ['/mentorat/chatbot'];
        yield 'mentorat-export-pdf' => ['/mentorat/sessions/export/pdf'];
        yield 'mentorat-export-excel' => ['/mentorat/sessions/export/excel'];

        // ===== Apprentissage =====
        yield 'apprentissage-cours' => ['/apprentissage/cours'];
        yield 'apprentissage-progression' => ['/apprentissage/progression'];
        yield 'apprentissage-badges' => ['/apprentissage/badges'];

        // ===== Projets =====
        yield 'projets' => ['/projets'];
        yield 'projets-new' => ['/projets/new'];

        // ===== Admin Dashboard =====
        yield 'admin-dashboard' => ['/admin'];

        // ===== Admin Users =====
        yield 'admin-users' => ['/admin/users'];
        yield 'admin-users-new' => ['/admin/users/new'];
        yield 'admin-users-stats' => ['/admin/users/stats'];
        yield 'admin-users-export-csv' => ['/admin/users/export-csv'];
        yield 'admin-users-broadcast' => ['/admin/users/broadcast'];

        // ===== Admin Community =====
        yield 'admin-community-groups' => ['/admin/community/groups'];
        yield 'admin-community-posts' => ['/admin/community/posts'];
        yield 'admin-community-events' => ['/admin/community/events'];

        // ===== Admin Investment =====
        yield 'admin-investment' => ['/admin/investissement'];
        yield 'admin-invest-create' => ['/admin/investissement/create'];
        yield 'admin-invest-offers' => ['/admin/investissement/offers'];

        // ===== Admin Mentorat =====
        yield 'admin-mentorat' => ['/admin/mentorat'];
        yield 'admin-mentorat-sessions' => ['/admin/mentorat/sessions'];

        // ===== Admin Apprentissage =====
        yield 'admin-cours' => ['/admin/apprentissage/cours'];
        yield 'admin-cours-new' => ['/admin/apprentissage/cours/new'];
        yield 'admin-badges' => ['/admin/apprentissage/badges'];
        yield 'admin-badges-new' => ['/admin/apprentissage/badges/new'];
        yield 'admin-progressions' => ['/admin/apprentissage/progressions'];

        // ===== Admin Projets =====
        yield 'admin-projets' => ['/admin/projets'];
    }
}
