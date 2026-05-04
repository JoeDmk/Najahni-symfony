<?php

namespace App\Tests\Entity;

use App\Entity\Comment;
use App\Entity\Thread;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

class CommentTest extends TestCase
{
    private Comment $comment;
    private User $user;
    private Thread $thread;

    protected function setUp(): void
    {
        $this->comment = new Comment();
        $this->user = new User();
        $this->thread = new Thread();
    }

    public function testCommentInitialization(): void
    {
        $this->assertNull($this->comment->getId());
        $this->assertNull($this->comment->getUser());
        $this->assertNull($this->comment->getThread());
        $this->assertNull($this->comment->getContent());
        $this->assertNotNull($this->comment->getCreatedAt());
    }

    public function testSetContent(): void
    {
        $content = 'This is a test comment';
        $this->comment->setContent($content);
        
        $this->assertEquals($content, $this->comment->getContent());
    }

    public function testSetUser(): void
    {
        $this->comment->setUser($this->user);
        
        $this->assertSame($this->user, $this->comment->getUser());
    }

    public function testSetThread(): void
    {
        $this->comment->setThread($this->thread);
        
        $this->assertSame($this->thread, $this->comment->getThread());
    }

    public function testCreatedAtIsSetOnConstruction(): void
    {
        $comment = new Comment();
        $createdAt = $comment->getCreatedAt();
        
        $this->assertNotNull($createdAt);
        $this->assertInstanceOf(\DateTimeInterface::class, $createdAt);
        $this->assertEquals(date('Y-m-d'), $createdAt->format('Y-m-d'));
    }

    public function testCommentFluentInterface(): void
    {
        $result = $this->comment
            ->setUser($this->user)
            ->setThread($this->thread)
            ->setContent('Fluent Comment');
        
        $this->assertSame($this->comment, $result);
        $this->assertSame($this->user, $this->comment->getUser());
        $this->assertSame($this->thread, $this->comment->getThread());
        $this->assertEquals('Fluent Comment', $this->comment->getContent());
    }

    public function testCommentBelongsToThread(): void
    {
        $this->comment->setThread($this->thread);
        $this->comment->setContent('Reply to thread');
        
        $this->assertSame($this->thread, $this->comment->getThread());
    }

    public function testCommentBelongsToUser(): void
    {
        $this->comment->setUser($this->user);
        
        $this->assertSame($this->user, $this->comment->getUser());
    }

    public function testCanSetNullThread(): void
    {
        $this->comment->setThread($this->thread);
        $this->assertSame($this->thread, $this->comment->getThread());
        
        $this->comment->setThread(null);
        $this->assertNull($this->comment->getThread());
    }

    public function testCanSetNullUser(): void
    {
        $this->comment->setUser($this->user);
        $this->assertSame($this->user, $this->comment->getUser());
        
        $this->comment->setUser(null);
        $this->assertNull($this->comment->getUser());
    }
}
