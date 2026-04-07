<?php

namespace App\Tests;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class FeatureTest extends WebTestCase
{
    private function loginAsAdmin($client): void
    {
        $em = static::getContainer()->get('doctrine')->getManager();
        $user = $em->getRepository(User::class)->findOneBy(['email' => 'admin@najahni.tn']);
        $client->loginUser($user);
    }

    /**
     * @dataProvider searchRouteProvider
     */
    public function testSearchFunctionality(string $url): void
    {
        $client = static::createClient();
        $this->loginAsAdmin($client);
        $client->request('GET', $url);
        $status = $client->getResponse()->getStatusCode();
        $this->assertLessThan(500, $status,
            "Search page $url returned $status: " . substr($client->getResponse()->getContent(), 0, 500));
    }

    public static function searchRouteProvider(): \Generator
    {
        yield 'admin-users-search' => ['/admin/users?q=test'];
        yield 'admin-projets-search' => ['/admin/projets?q=test'];
        yield 'admin-invest-search' => ['/admin/investissement?q=test'];
        yield 'community-posts-search' => ['/community/posts?q=test'];
        yield 'community-events-search' => ['/community/events?q=test'];
        yield 'projets-search' => ['/projets?q=test'];
        yield 'cours-search' => ['/apprentissage/cours?q=test'];
    }

    public function testAdminUsersCsvExport(): void
    {
        $client = static::createClient();
        $this->loginAsAdmin($client);
        $client->request('GET', '/admin/users/export-csv');
        $this->assertSame(200, $client->getResponse()->getStatusCode());
        $this->assertStringContainsString('text/csv', $client->getResponse()->headers->get('Content-Type'));
    }

    public function testMentoratPdfExport(): void
    {
        $client = static::createClient();
        $this->loginAsAdmin($client);
        $client->request('GET', '/mentorat/sessions/export/pdf');
        $this->assertSame(200, $client->getResponse()->getStatusCode());
        $this->assertStringContainsString('application/pdf', $client->getResponse()->headers->get('Content-Type'));
    }

    public function testMentoratExcelExport(): void
    {
        $client = static::createClient();
        $this->loginAsAdmin($client);
        $client->request('GET', '/mentorat/sessions/export/excel');
        $this->assertSame(200, $client->getResponse()->getStatusCode());
        $this->assertStringContainsString('spreadsheetml', $client->getResponse()->headers->get('Content-Type'));
    }

    public function testProfileEditFormLoads(): void
    {
        $client = static::createClient();
        $this->loginAsAdmin($client);
        $client->request('GET', '/profile/edit');
        $this->assertSame(200, $client->getResponse()->getStatusCode());
        $this->assertSelectorExists('form');
    }

    public function testAdminDashboardHasStats(): void
    {
        $client = static::createClient();
        $this->loginAsAdmin($client);
        $crawler = $client->request('GET', '/admin');
        $this->assertSame(200, $client->getResponse()->getStatusCode());
        // Dashboard should have content
        $this->assertGreaterThan(0, strlen($client->getResponse()->getContent()));
    }

    public function testLoginPageHasForm(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/login');
        $this->assertSame(200, $client->getResponse()->getStatusCode());
        $this->assertSelectorExists('form');
        $this->assertSelectorExists('input[name="_username"]');
        $this->assertSelectorExists('input[name="_password"]');
    }

    public function testRegisterPageHasForm(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/register');
        $this->assertSame(200, $client->getResponse()->getStatusCode());
        $this->assertSelectorExists('form');
    }

    public function testUnauthenticatedRedirectsToLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/profile');
        $this->assertResponseRedirects('/login');
    }
}
