<?php
namespace App\Entity;

use App\Repository\MentorshipRequestRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: MentorshipRequestRepository::class)]
#[ORM\Table(name: 'mentorship_request')]
#[ORM\HasLifecycleCallbacks]
class MentorshipRequest
{
    public const STATUS_PENDING = 'PENDING';
    public const STATUS_ACCEPTED = 'ACCEPTED';
    public const STATUS_AUTO_ACCEPTED = 'AUTO_ACCEPTED';
    public const STATUS_REJECTED = 'REJECTED';
    public const STATUS_CANCELLED = 'CANCELLED';
    public const STATUS_COMPLETED = 'COMPLETED';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'entrepreneur_id', nullable: true)]
    private ?User $entrepreneur = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'mentor_id', nullable: true)]
    private ?User $mentor = null;

    #[ORM\ManyToOne(targetEntity: Projet::class)]
    #[ORM\JoinColumn(name: 'project_id', nullable: true)]
    private ?Projet $project = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $date = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $time = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $motivation = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $goals = null;

    #[ORM\Column(nullable: true)]
    private ?float $matchScore = null;

    #[ORM\Column]
    private bool $autoApproved = false;

    #[ORM\Column(length: 50)]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\OneToMany(targetEntity: MentorshipSession::class, mappedBy: 'mentorshipRequest', cascade: ['remove'])]
    private Collection $sessions;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->sessions = new ArrayCollection();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void { $this->updatedAt = new \DateTime(); }

    public function getId(): ?int { return $this->id; }
    public function getEntrepreneur(): ?User { return $this->entrepreneur; }
    public function setEntrepreneur(?User $v): static { $this->entrepreneur = $v; return $this; }
    public function getMentor(): ?User { return $this->mentor; }
    public function setMentor(?User $v): static { $this->mentor = $v; return $this; }
    public function getProject(): ?Projet { return $this->project; }
    public function setProject(?Projet $v): static { $this->project = $v; return $this; }
    public function getDate(): ?\DateTimeInterface { return $this->date; }
    public function setDate(?\DateTimeInterface $v): static { $this->date = $v; return $this; }
    public function getTime(): ?string { return $this->time; }
    public function setTime(?string $v): static { $this->time = $v; return $this; }
    public function getMotivation(): ?string { return $this->motivation; }
    public function setMotivation(?string $v): static { $this->motivation = $v; return $this; }
    public function getGoals(): ?string { return $this->goals; }
    public function setGoals(?string $v): static { $this->goals = $v; return $this; }
    public function getMatchScore(): ?float { return $this->matchScore; }
    public function setMatchScore(?float $v): static { $this->matchScore = $v; return $this; }
    public function isAutoApproved(): bool { return $this->autoApproved; }
    public function setAutoApproved(bool $v): static { $this->autoApproved = $v; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $v): static { $this->status = $v; return $this; }
    public function getCreatedAt(): ?\DateTimeInterface { return $this->createdAt; }
    public function getUpdatedAt(): ?\DateTimeInterface { return $this->updatedAt; }
    /** @return Collection<int, MentorshipSession> */
    public function getSessions(): Collection { return $this->sessions; }
}
