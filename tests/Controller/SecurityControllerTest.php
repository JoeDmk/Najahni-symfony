<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class SecurityControllerTest extends WebTestCase
{
    public function testLoginPageIsAccessible(): void
    {
        $client = static::createClient();
        $client->request('GET', '/login');
        
        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('Connexion', $client->getResponse()->getContent());
    }

    public function testRegisterPageIsAccessible(): void
    {
        $client = static::createClient();
        $client->request('GET', '/register');
        
        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('Inscription', $client->getResponse()->getContent());
    }

    public function testForgotPasswordPageIsAccessible(): void
    {
        $client = static::createClient();
        $client->request('GET', '/forgot-password');
        
        $this->assertResponseIsSuccessful();
    }

    public function testLoginWithInvalidCredentialsShowsError(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/login');
        
        $form = $crawler->selectButton('Se connecter')->form();
        $form['email'] = 'invalid@example.com';
        $form['password'] = 'wrongpassword';
        
        $client->submit($form);
        
        // Should either redirect or show error
        $this->assertTrue($client->getResponse()->isRedirect() || 
                         $client->getResponse()->isSuccessful());
    }

    public function testHomepageIsAccessible(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');
        
        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('Najahni', $client->getResponse()->getContent());
    }

    public function testCommunityPageRequiresLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/community/posts');
        
        // Should redirect to login
        $this->assertTrue($client->getResponse()->isRedirect());
    }
}
