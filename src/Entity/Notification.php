<?php

namespace App\Entity;

use App\Repository\NotificationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: NotificationRepository::class)]
#[ORM\Table(name: 'notification')]
class Notification
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $message = null;

    #[ORM\Column(length: 20, options: ['default' => 'INFO'])]
    private string $type = 'INFO';

    #[ORM\Column(name: 'is_read')]
    private bool $read = false;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }
    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): static { $this->user = $user; return $this; }
    public function getTitle(): ?string { return $this->title; }
    public function setTitle(string $title): static { $this->title = $title; return $this; }
    public function getMessage(): ?string { return $this->message; }
    public function setMessage(string $message): static { $this->message = $message; return $this; }
    public function getType(): string { return $this->type; }
    public function setType(string $type): static { $this->type = $type; return $this; }
    public function isRead(): bool { return $this->read; }
    public function setRead(bool $read): static { $this->read = $read; return $this; }
    public function getCreatedAt(): ?\DateTimeInterface { return $this->createdAt; }
    public function setCreatedAt(\DateTimeInterface $createdAt): static { $this->createdAt = $createdAt; return $this; }

    public function getTypeIcon(): string
    {
        return match ($this->type) {
            'WARNING' => 'exclamation-triangle-fill',
            'SUCCESS' => 'check-circle-fill',
            'DANGER' => 'x-octagon-fill',
            'LOGIN' => 'key-fill',
            default => 'info-circle-fill',
        };
    }

    public function getTypeColor(): string
    {
        return match ($this->type) {
            'WARNING' => 'warning',
            'SUCCESS' => 'success',
            'DANGER' => 'danger',
            'LOGIN' => 'info',
            default => 'primary',
        };
    }
}
