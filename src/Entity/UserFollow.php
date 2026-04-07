<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'user_follow')]
#[ORM\UniqueConstraint(name: 'unique_follow', columns: ['follower_id', 'followed_id'])]
class UserFollow
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $follower = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $followed = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }
    public function getFollower(): ?User { return $this->follower; }
    public function setFollower(?User $follower): static { $this->follower = $follower; return $this; }
    public function getFollowed(): ?User { return $this->followed; }
    public function setFollowed(?User $followed): static { $this->followed = $followed; return $this; }
    public function getCreatedAt(): ?\DateTimeInterface { return $this->createdAt; }
}
