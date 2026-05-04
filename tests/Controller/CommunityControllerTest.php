<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class CommunityControllerTest extends WebTestCase
{
    public function testCommunityRoutesRequireAuthentication(): void
    {
        $client = static::createClient();
        
        // Test that community routes are protected
        $client->request('GET', '/community');
        $this->assertEquals(302, $client->getResponse()->getStatusCode());
    }

    public function testRedirectToLoginWhenNotAuthenticated(): void
    {
        $client = static::createClient();
        
        $client->request('GET', '/community');
        $response = $client->getResponse();
        
        $this->assertEquals(302, $response->getStatusCode());
        $this->assertStringContainsString('/login', $response->headers->get('location'));
    }

    public function testCommunityResponseStructure(): void
    {
        $client = static::createClient();
        
        // Try accessing community routes without auth
        $client->request('GET', '/community/posts');
        
        // Should redirect to login
        $this->assertEquals(302, $client->getResponse()->getStatusCode());
    }

    public function testCommunityControllerIsAccessible(): void
    {
        $client = static::createClient();
        
        // Test that the route exists
        $client->request('GET', '/community/posts');
        
        // Either 302 (redirect) or 200 (if logged in) - route should exist
        $statusCode = $client->getResponse()->getStatusCode();
        $this->assertTrue(in_array($statusCode, [200, 302]));
    }

    public function testThreadsEndpointExists(): void
    {
        $client = static::createClient();
        
        $client->request('GET', '/community/threads');
        
        $statusCode = $client->getResponse()->getStatusCode();
        $this->assertTrue(in_array($statusCode, [200, 302]));
    }

    public function testGroupsEndpointExists(): void
    {
        $client = static::createClient();
        
        $client->request('GET', '/community/groups');
        
        $statusCode = $client->getResponse()->getStatusCode();
        $this->assertTrue(in_array($statusCode, [200, 302]));
    }

    public function testEventsEndpointExists(): void
    {
        $client = static::createClient();
        
        $client->request('GET', '/community/events');
        
        $statusCode = $client->getResponse()->getStatusCode();
        $this->assertTrue(in_array($statusCode, [200, 302]));
    }

    public function testCommunityHttpMethods(): void
    {
        $client = static::createClient();
        
        // Test GET requests
        $client->request('GET', '/community/posts');
        $this->assertTrue(in_array($client->getResponse()->getStatusCode(), [200, 302]));
        
        $client->request('GET', '/community/threads');
        $this->assertTrue(in_array($client->getResponse()->getStatusCode(), [200, 302]));
        
        $client->request('GET', '/community/groups');
        $this->assertTrue(in_array($client->getResponse()->getStatusCode(), [200, 302]));
        
        $client->request('GET', '/community/events');
        $this->assertTrue(in_array($client->getResponse()->getStatusCode(), [200, 302]));
    }
}
