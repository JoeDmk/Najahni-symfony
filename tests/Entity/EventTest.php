<?php

namespace App\Tests\Entity;

use App\Entity\Event;
use App\Entity\EventParticipant;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

class EventTest extends TestCase
{
    private Event $event;
    private User $creator;
    private User $participant;

    protected function setUp(): void
    {
        $this->event = new Event();
        $this->creator = new User();
        $this->participant = new User();
    }

    public function testEventInitialization(): void
    {
        $this->assertNull($this->event->getId());
        $this->assertNull($this->event->getTitle());
        $this->assertNull($this->event->getDescription());
        $this->assertNull($this->event->getEventDate());
        $this->assertEquals(0, $this->event->getCapacity());
        $this->assertNotNull($this->event->getCreatedAt());
        $this->assertNull($this->event->getCreatedBy());
        $this->assertCount(0, $this->event->getParticipants());
    }

    public function testSetTitle(): void
    {
        $title = 'Test Event';
        $this->event->setTitle($title);
        
        $this->assertEquals($title, $this->event->getTitle());
    }

    public function testSetDescription(): void
    {
        $description = 'This is a test event description';
        $this->event->setDescription($description);
        
        $this->assertEquals($description, $this->event->getDescription());
    }

    public function testSetEventDate(): void
    {
        $date = new \DateTime('+1 day');
        $this->event->setEventDate($date);
        
        $this->assertSame($date, $this->event->getEventDate());
    }

    public function testSetCapacity(): void
    {
        $this->event->setCapacity(50);
        
        $this->assertEquals(50, $this->event->getCapacity());
    }

    public function testSetCreatedBy(): void
    {
        $this->event->setCreatedBy($this->creator);
        
        $this->assertSame($this->creator, $this->event->getCreatedBy());
    }

    public function testCreatedAtIsSetOnConstruction(): void
    {
        $event = new Event();
        $createdAt = $event->getCreatedAt();
        
        $this->assertNotNull($createdAt);
        $this->assertInstanceOf(\DateTimeInterface::class, $createdAt);
        $this->assertEquals(date('Y-m-d'), $createdAt->format('Y-m-d'));
    }

    public function testGetParticipantsCount(): void
    {
        $this->assertEquals(0, $this->event->getParticipantsCount());
    }

    public function testHasCapacityWithUnlimitedCapacity(): void
    {
        $this->event->setCapacity(0);
        
        $this->assertTrue($this->event->hasCapacity());
    }

    public function testHasCapacityWithLimitedCapacity(): void
    {
        $this->event->setCapacity(50);
        
        $this->assertTrue($this->event->hasCapacity());
    }

    public function testHasParticipantWithNullUser(): void
    {
        $this->assertFalse($this->event->hasParticipant(null));
    }

    public function testGetParticipantForUserWithNullUser(): void
    {
        $this->assertNull($this->event->getParticipantForUser(null));
    }

    public function testEventFluentInterface(): void
    {
        $date = new \DateTime('+2 days');
        $result = $this->event
            ->setTitle('Fluent Event')
            ->setDescription('Fluent Description')
            ->setEventDate($date)
            ->setCapacity(100)
            ->setCreatedBy($this->creator);
        
        $this->assertSame($this->event, $result);
        $this->assertEquals('Fluent Event', $this->event->getTitle());
        $this->assertEquals('Fluent Description', $this->event->getDescription());
        $this->assertSame($date, $this->event->getEventDate());
        $this->assertEquals(100, $this->event->getCapacity());
        $this->assertSame($this->creator, $this->event->getCreatedBy());
    }

    public function testGetParticipants(): void
    {
        $this->assertCount(0, $this->event->getParticipants());
    }

    public function testCompleteEventSetup(): void
    {
        $date = new \DateTime('+1 day');
        
        $this->event
            ->setTitle('Complete Event')
            ->setDescription('A complete event setup')
            ->setEventDate($date)
            ->setCapacity(200)
            ->setCreatedBy($this->creator);
        
        $this->assertEquals('Complete Event', $this->event->getTitle());
        $this->assertEquals('A complete event setup', $this->event->getDescription());
        $this->assertSame($date, $this->event->getEventDate());
        $this->assertEquals(200, $this->event->getCapacity());
        $this->assertSame($this->creator, $this->event->getCreatedBy());
    }

    public function testCanSetNullDescription(): void
    {
        $this->event->setDescription('Description');
        $this->assertEquals('Description', $this->event->getDescription());
        
        $this->event->setDescription(null);
        $this->assertNull($this->event->getDescription());
    }

    public function testCanSetNullCreatedBy(): void
    {
        $this->event->setCreatedBy($this->creator);
        $this->assertSame($this->creator, $this->event->getCreatedBy());
        
        $this->event->setCreatedBy(null);
        $this->assertNull($this->event->getCreatedBy());
    }
}
