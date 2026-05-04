<?php

namespace App\Tests\Entity;

use App\Entity\Post;
use App\Entity\PostReaction;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

class PostTest extends TestCase
{
    private Post $post;
    private User $user;

    protected function setUp(): void
    {
        $this->post = new Post();
        $this->user = new User();
    }

    public function testPostInitialization(): void
    {
        $this->assertNull($this->post->getId());
        $this->assertNull($this->post->getUser());
        $this->assertNull($this->post->getContent());
        $this->assertNotNull($this->post->getCreatedAt());
        $this->assertNull($this->post->getImageUrl());
        $this->assertCount(0, $this->post->getReactions());
    }

    public function testSetContent(): void
    {
        $content = 'This is a test post content';
        $this->post->setContent($content);
        
        $this->assertEquals($content, $this->post->getContent());
    }

    public function testSetUser(): void
    {
        $this->post->setUser($this->user);
        
        $this->assertSame($this->user, $this->post->getUser());
    }

    public function testSetImageUrl(): void
    {
        $imageUrl = 'https://example.com/image.jpg';
        $this->post->setImageUrl($imageUrl);
        
        $this->assertEquals($imageUrl, $this->post->getImageUrl());
    }

    public function testCreatedAtIsSetOnConstruction(): void
    {
        $post = new Post();
        $createdAt = $post->getCreatedAt();
        
        $this->assertNotNull($createdAt);
        $this->assertInstanceOf(\DateTimeInterface::class, $createdAt);
        $this->assertEquals(date('Y-m-d'), $createdAt->format('Y-m-d'));
    }

    public function testGetReactionsCount(): void
    {
        $this->assertEquals(0, $this->post->getReactionsCount());
    }

    public function testGetReactionCountForType(): void
    {
        $this->assertEquals(0, $this->post->getReactionCountForType('like'));
        $this->assertEquals(0, $this->post->getReactionCountForType('love'));
    }

    public function testGetReactionTypeForUserWithNullUser(): void
    {
        $this->assertNull($this->post->getReactionTypeForUser(null));
    }

    public function testGetReactionTypeForUserWithoutReaction(): void
    {
        $this->assertNull($this->post->getReactionTypeForUser($this->user));
    }

    public function testGetReactionSummary(): void
    {
        $summary = $this->post->getReactionSummary();
        
        $this->assertIsArray($summary);
    }

    public function testPostFluentInterface(): void
    {
        $result = $this->post
            ->setContent('Fluent Post')
            ->setImageUrl('https://example.com/fluent.jpg');
        
        $this->assertSame($this->post, $result);
        $this->assertEquals('Fluent Post', $this->post->getContent());
        $this->assertEquals('https://example.com/fluent.jpg', $this->post->getImageUrl());
    }

    public function testGetReactions(): void
    {
        $this->assertCount(0, $this->post->getReactions());
    }

    public function testSetUserAndContent(): void
    {
        $content = 'Complete post';
        $this->post->setUser($this->user)->setContent($content);
        
        $this->assertSame($this->user, $this->post->getUser());
        $this->assertEquals($content, $this->post->getContent());
    }
}
