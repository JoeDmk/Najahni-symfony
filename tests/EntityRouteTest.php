<?php

namespace App\Tests;

use App\Entity\User;
use App\Entity\Cours;
use App\Entity\Badge;
use App\Entity\Projet;
use App\Entity\InvestmentOpportunity;
use App\Entity\MentorshipRequest;
use App\Entity\MentorshipSession;
use App\Entity\Post;
use App\Entity\Group;
use App\Entity\Event;
use App\Entity\Thread;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class EntityRouteTest extends WebTestCase
{
    private function loginAsAdmin($client): void
    {
        $em = static::getContainer()->get('doctrine')->getManager();
        $user = $em->getRepository(User::class)->findOneBy(['email' => 'admin@najahni.tn']);
        $client->loginUser($user);
    }

    private function getFirstId(string $entityClass): ?int
    {
        $em = static::getContainer()->get('doctrine')->getManager();
        $entity = $em->getRepository($entityClass)->findOneBy([]);
        return $entity?->getId();
    }

    /**
     * @dataProvider entityDetailRouteProvider
     */
    public function testEntityDetailRoutes(string $entityClass, string $urlPattern, bool $admin = false): void
    {
        $client = static::createClient();
        $this->loginAsAdmin($client);

        $id = $this->getFirstId($entityClass);
        if ($id === null) {
            $this->markTestSkipped("No $entityClass records in database");
        }

        $url = str_replace('{id}', (string)$id, $urlPattern);
        $client->request('GET', $url);
        $status = $client->getResponse()->getStatusCode();
        $this->assertLessThan(500, $status,
            "Page $url returned $status: " . substr($client->getResponse()->getContent(), 0, 500));
    }

    public static function entityDetailRouteProvider(): \Generator
    {
        // Apprentissage - Cours
        yield 'cours-show' => [Cours::class, '/apprentissage/cours/{id}'];
        yield 'admin-cours-edit' => [Cours::class, '/admin/apprentissage/cours/{id}/edit', true];

        // Apprentissage - Badges
        yield 'admin-badges-edit' => [Badge::class, '/admin/apprentissage/badges/{id}/edit', true];

        // Projets
        yield 'projet-show' => [Projet::class, '/projets/{id}'];
        yield 'projet-edit' => [Projet::class, '/projets/{id}/edit'];
        yield 'admin-projet-show' => [Projet::class, '/admin/projets/{id}'];

        // Investment
        yield 'opportunity-show' => [InvestmentOpportunity::class, '/investissement/opportunities/{id}'];
        yield 'admin-invest-show' => [InvestmentOpportunity::class, '/admin/investissement/{id}'];
        yield 'admin-invest-edit' => [InvestmentOpportunity::class, '/admin/investissement/{id}/edit'];

        // Community - Groups
        yield 'group-show' => [Group::class, '/community/groups/{id}'];

        // Community - Events
        yield 'event-show' => [Event::class, '/community/events/{id}'];

        // Community - Threads
        yield 'thread-show' => [Thread::class, '/community/threads/{id}'];

        // Admin Users
        yield 'admin-user-edit' => [User::class, '/admin/users/{id}/edit'];
        yield 'admin-user-history' => [User::class, '/admin/users/{id}/login-history'];
    }
}
