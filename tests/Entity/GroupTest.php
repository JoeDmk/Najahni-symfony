<?php

namespace App\Tests\Entity;

use App\Entity\Group;
use App\Entity\GroupMember;
use App\Entity\Thread;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

class GroupTest extends TestCase
{
    private Group $group;
    private User $admin;
    private User $member;

    protected function setUp(): void
    {
        $this->group = new Group();
        $this->admin = new User();
        $this->member = new User();
    }

    public function testGroupInitialization(): void
    {
        $this->assertNull($this->group->getId());
        $this->assertNull($this->group->getName());
        $this->assertNull($this->group->getDescription());
        $this->assertNotNull($this->group->getCreatedAt());
        $this->assertFalse($this->group->isPrivate());
        $this->assertCount(0, $this->group->getMembers());
        $this->assertCount(0, $this->group->getThreads());
    }

    public function testSetName(): void
    {
        $name = 'Test Group';
        $this->group->setName($name);
        
        $this->assertEquals($name, $this->group->getName());
    }

    public function testSetDescription(): void
    {
        $description = 'This is a test group description';
        $this->group->setDescription($description);
        
        $this->assertEquals($description, $this->group->getDescription());
    }

    public function testSetGroupAdmin(): void
    {
        $this->group->setGroupAdmin($this->admin);
        
        $this->assertSame($this->admin, $this->group->getGroupAdmin());
    }

    public function testSetIsPrivate(): void
    {
        $this->assertFalse($this->group->isPrivate());
        
        $this->group->setIsPrivate(true);
        $this->assertTrue($this->group->isPrivate());
        
        $this->group->setIsPrivate(false);
        $this->assertFalse($this->group->isPrivate());
    }

    public function testGetMembersCount(): void
    {
        $this->assertEquals(0, $this->group->getMembersCount());
        
        $groupMember = new GroupMember();
        $groupMember->setUser($this->member);
        $groupMember->setGroup($this->group);
        
        $this->assertEquals(0, $this->group->getMembersCount());
    }

    public function testHasMemberWithNullUser(): void
    {
        $this->assertFalse($this->group->hasMember(null));
    }

    public function testHasMemberWithAdmin(): void
    {
        $this->group->setGroupAdmin($this->admin);
        
        $this->assertTrue($this->group->hasMember($this->admin));
    }

    public function testCreatedAtIsSetOnConstruction(): void
    {
        $group = new Group();
        $createdAt = $group->getCreatedAt();
        
        $this->assertNotNull($createdAt);
        $this->assertInstanceOf(\DateTimeInterface::class, $createdAt);
        $this->assertEquals(date('Y-m-d'), $createdAt->format('Y-m-d'));
    }

    public function testGroupFluentInterface(): void
    {
        $result = $this->group
            ->setName('Fluent Group')
            ->setDescription('Fluent Description')
            ->setIsPrivate(true);
        
        $this->assertSame($this->group, $result);
        $this->assertEquals('Fluent Group', $this->group->getName());
        $this->assertEquals('Fluent Description', $this->group->getDescription());
        $this->assertTrue($this->group->isPrivate());
    }

    public function testGetThreads(): void
    {
        $this->assertCount(0, $this->group->getThreads());
    }

    public function testGetMembers(): void
    {
        $this->assertCount(0, $this->group->getMembers());
    }
}
