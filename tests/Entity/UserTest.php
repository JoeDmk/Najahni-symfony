<?php

namespace App\Tests\Entity;

use App\Entity\User;
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{
    private User $user;

    protected function setUp(): void
    {
        $this->user = new User();
    }

    public function testUserEmailCanBeSet(): void
    {
        $email = 'test@najahni.tn';
        $this->user->setEmail($email);
        
        $this->assertEquals($email, $this->user->getEmail());
    }

    public function testUserRolesCanBeSet(): void
    {
        $this->user->setRoles(['ROLE_ENTREPRENEUR']);
        
        $this->assertContains('ROLE_ENTREPRENEUR', $this->user->getRoles());
    }

    public function testUserPasswordCanBeHashed(): void
    {
        $password = 'TestPassword123!';
        $this->user->setPassword($password);
        
        $this->assertNotEmpty($this->user->getPassword());
        $this->assertEquals($password, $this->user->getPassword());
    }

    public function testUserCreatedAtIsSet(): void
    {
        $now = new \DateTime();
        $this->user->setCreatedAt($now);
        
        $this->assertEquals($now, $this->user->getCreatedAt());
    }

    public function testUserIsNotVerifiedByDefault(): void
    {
        $this->assertFalse($this->user->isVerified());
    }

    public function testUserCanBeVerified(): void
    {
        $this->user->setIsVerified(true);
        
        $this->assertTrue($this->user->isVerified());
    }

    public function testUserHasFirstName(): void
    {
        $firstName = 'Ahmed';
        $this->user->setFirstName($firstName);
        
        $this->assertEquals($firstName, $this->user->getFirstName());
    }

    public function testUserHasLastName(): void
    {
        $lastName = 'Bennani';
        $this->user->setLastName($lastName);
        
        $this->assertEquals($lastName, $this->user->getLastName());
    }
}
