<?php

namespace App\Tests\Entity;

use App\Entity\Comment;
use App\Entity\Group;
use App\Entity\Thread;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

class ThreadTest extends TestCase
{
    private Thread $thread;
    private User $user;
    private Group $group;

    protected function setUp(): void
    {
        $this->thread = new Thread();
        $this->user = new User();
        $this->group = new Group();
    }

    public function testThreadInitialization(): void
    {
        $this->assertNull($this->thread->getId());
        $this->assertNull($this->thread->getUser());
        $this->assertNull($this->thread->getGroup());
        $this->assertNull($this->thread->getTitle());
        $this->assertNull($this->thread->getContent());
        $this->assertNotNull($this->thread->getCreatedAt());
        $this->assertCount(0, $this->thread->getComments());
    }

    public function testSetTitle(): void
    {
        $title = 'Test Thread Title';
        $this->thread->setTitle($title);
        
        $this->assertEquals($title, $this->thread->getTitle());
    }

    public function testSetContent(): void
    {
        $content = 'This is test thread content';
        $this->thread->setContent($content);
        
        $this->assertEquals($content, $this->thread->getContent());
    }

    public function testSetUser(): void
    {
        $this->thread->setUser($this->user);
        
        $this->assertSame($this->user, $this->thread->getUser());
    }

    public function testSetGroup(): void
    {
        $this->thread->setGroup($this->group);
        
        $this->assertSame($this->group, $this->thread->getGroup());
    }

    public function testCreatedAtIsSetOnConstruction(): void
    {
        $thread = new Thread();
        $createdAt = $thread->getCreatedAt();
        
        $this->assertNotNull($createdAt);
        $this->assertInstanceOf(\DateTimeInterface::class, $createdAt);
        $this->assertEquals(date('Y-m-d'), $createdAt->format('Y-m-d'));
    }

    public function testThreadFluentInterface(): void
    {
        $result = $this->thread
            ->setTitle('Fluent Title')
            ->setContent('Fluent Content')
            ->setUser($this->user)
            ->setGroup($this->group);
        
        $this->assertSame($this->thread, $result);
        $this->assertEquals('Fluent Title', $this->thread->getTitle());
        $this->assertEquals('Fluent Content', $this->thread->getContent());
        $this->assertSame($this->user, $this->thread->getUser());
        $this->assertSame($this->group, $this->thread->getGroup());
    }

    public function testGetComments(): void
    {
        $this->assertCount(0, $this->thread->getComments());
    }

    public function testThreadBelongsToGroup(): void
    {
        $this->thread->setGroup($this->group);
        
        $this->assertSame($this->group, $this->thread->getGroup());
    }

    public function testThreadBelongsToUser(): void
    {
        $this->thread->setUser($this->user);
        
        $this->assertSame($this->user, $this->thread->getUser());
    }

    public function testCanSetNullGroup(): void
    {
        $this->thread->setGroup($this->group);
        $this->assertSame($this->group, $this->thread->getGroup());
        
        $this->thread->setGroup(null);
        $this->assertNull($this->thread->getGroup());
    }

    public function testCanSetNullUser(): void
    {
        $this->thread->setUser($this->user);
        $this->assertSame($this->user, $this->thread->getUser());
        
        $this->thread->setUser(null);
        $this->assertNull($this->thread->getUser());
    }

    public function testThreadWithMultipleProperties(): void
    {
        $this->thread
            ->setTitle('Comprehensive Thread')
            ->setContent('This is a comprehensive thread content')
            ->setUser($this->user)
            ->setGroup($this->group);
        
        $this->assertEquals('Comprehensive Thread', $this->thread->getTitle());
        $this->assertEquals('This is a comprehensive thread content', $this->thread->getContent());
        $this->assertSame($this->user, $this->thread->getUser());
        $this->assertSame($this->group, $this->thread->getGroup());
    }
}
