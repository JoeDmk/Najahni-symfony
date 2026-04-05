<?php
namespace App\Entity;

use App\Repository\MentorshipSessionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MentorshipSessionRepository::class)]
#[ORM\Table(name: 'mentorship_session')]
#[ORM\HasLifecycleCallbacks]
class MentorshipSession
{
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_NO_SHOW = 'no_show';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: MentorshipRequest::class, inversedBy: 'sessions')]
    #[ORM\JoinColumn(name: 'mentorship_request_id', nullable: true, onDelete: 'CASCADE')]
    private ?MentorshipRequest $mentorshipRequest = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $scheduledAt = null;

    #[ORM\Column(nullable: true)]
    private ?int $durationMinutes = null;

    #[ORM\Column(length: 50)]
    private string $status = self::STATUS_SCHEDULED;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $meetingLink = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $mentorFeedback = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $entrepreneurFeedback = null;

    #[ORM\Column(nullable: true)]
    private ?int $mentorRating = null;

    #[ORM\Column(nullable: true)]
    private ?int $entrepreneurRating = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    public function __construct() { $this->createdAt = new \DateTime(); }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void { $this->updatedAt = new \DateTime(); }

    public function getId(): ?int { return $this->id; }
    public function getMentorshipRequest(): ?MentorshipRequest { return $this->mentorshipRequest; }
    public function setMentorshipRequest(?MentorshipRequest $v): static { $this->mentorshipRequest = $v; return $this; }
    public function getScheduledAt(): ?\DateTimeInterface { return $this->scheduledAt; }
    public function setScheduledAt(?\DateTimeInterface $v): static { $this->scheduledAt = $v; return $this; }
    public function getDurationMinutes(): ?int { return $this->durationMinutes; }
    public function setDurationMinutes(?int $v): static { $this->durationMinutes = $v; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $v): static { $this->status = $v; return $this; }
    public function getMeetingLink(): ?string { return $this->meetingLink; }
    public function setMeetingLink(?string $v): static { $this->meetingLink = $v; return $this; }
    public function getMentorFeedback(): ?string { return $this->mentorFeedback; }
    public function setMentorFeedback(?string $v): static { $this->mentorFeedback = $v; return $this; }
    public function getEntrepreneurFeedback(): ?string { return $this->entrepreneurFeedback; }
    public function setEntrepreneurFeedback(?string $v): static { $this->entrepreneurFeedback = $v; return $this; }
    public function getMentorRating(): ?int { return $this->mentorRating; }
    public function setMentorRating(?int $v): static { $this->mentorRating = $v; return $this; }
    public function getEntrepreneurRating(): ?int { return $this->entrepreneurRating; }
    public function setEntrepreneurRating(?int $v): static { $this->entrepreneurRating = $v; return $this; }
    public function getCreatedAt(): ?\DateTimeInterface { return $this->createdAt; }
    public function getUpdatedAt(): ?\DateTimeInterface { return $this->updatedAt; }
}
