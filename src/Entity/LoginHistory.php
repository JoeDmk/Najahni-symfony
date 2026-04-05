<?php

namespace App\Entity;

use App\Repository\LoginHistoryRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LoginHistoryRepository::class)]
#[ORM\Table(name: 'login_history')]
class LoginHistory
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $ipAddress = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $deviceInfo = null;

    #[ORM\Column(length: 20)]
    private string $loginMethod = 'PASSWORD';

    #[ORM\Column]
    private bool $success = true;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $location = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $loginTime = null;

    public function __construct()
    {
        $this->loginTime = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }
    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): static { $this->user = $user; return $this; }
    public function getIpAddress(): ?string { return $this->ipAddress; }
    public function setIpAddress(?string $ipAddress): static { $this->ipAddress = $ipAddress; return $this; }
    public function getDeviceInfo(): ?string { return $this->deviceInfo; }
    public function setDeviceInfo(?string $deviceInfo): static { $this->deviceInfo = $deviceInfo; return $this; }
    public function getLoginMethod(): string { return $this->loginMethod; }
    public function setLoginMethod(string $loginMethod): static { $this->loginMethod = $loginMethod; return $this; }
    public function isSuccess(): bool { return $this->success; }
    public function setSuccess(bool $success): static { $this->success = $success; return $this; }
    public function getLocation(): ?string { return $this->location; }
    public function setLocation(?string $location): static { $this->location = $location; return $this; }
    public function getLoginTime(): ?\DateTimeInterface { return $this->loginTime; }
    public function setLoginTime(\DateTimeInterface $loginTime): static { $this->loginTime = $loginTime; return $this; }
}
