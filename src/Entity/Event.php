<?php
namespace App\Entity;

use App\Repository\EventRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: EventRepository::class)]
#[ORM\Table(name: 'events')]
class Event
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 150)]
    #[Assert\NotBlank(message: 'Le titre est obligatoire.')]
    #[Assert\Length(min: 3, max: 150, minMessage: 'Le titre doit contenir au moins {{ limit }} caractères.', maxMessage: 'Le titre ne peut pas dépasser {{ limit }} caractères.')]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\NotBlank(message: 'La description est obligatoire.')]
    #[Assert\Length(min: 10, max: 2000, minMessage: 'La description doit contenir au moins {{ limit }} caractères.', maxMessage: 'La description ne peut pas dépasser {{ limit }} caractères.')]
    private ?string $description = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Assert\NotBlank(message: 'La date est obligatoire.')]
    #[Assert\GreaterThan('now', message: 'La date doit être dans le futur.')]
    private ?\DateTimeInterface $eventDate = null;

    #[ORM\Column]
    #[Assert\Positive(message: 'La capacité doit être supérieure à 0.')]
    private int $capacity = 0;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'created_by', nullable: false, onDelete: 'CASCADE')]
    private ?User $createdBy = null;

    #[ORM\OneToMany(targetEntity: EventParticipant::class, mappedBy: 'event', cascade: ['remove'])]
    private Collection $participants;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->participants = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }
    public function getTitle(): ?string { return $this->title; }
    public function setTitle(string $v): static { $this->title = $v; return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $v): static { $this->description = $v; return $this; }
    public function getEventDate(): ?\DateTimeInterface { return $this->eventDate; }
    public function setEventDate(\DateTimeInterface $v): static { $this->eventDate = $v; return $this; }
    public function getCapacity(): int { return $this->capacity; }
    public function setCapacity(int $v): static { $this->capacity = $v; return $this; }
    public function getCreatedAt(): ?\DateTimeInterface { return $this->createdAt; }
    public function getCreatedBy(): ?User { return $this->createdBy; }
    public function setCreatedBy(?User $v): static { $this->createdBy = $v; return $this; }
    /** @return Collection<int, EventParticipant> */
    public function getParticipants(): Collection { return $this->participants; }
    public function getParticipantsCount(): int { return $this->participants->count(); }
    public function hasCapacity(): bool { return $this->capacity === 0 || $this->getParticipantsCount() < $this->capacity; }

    public function hasParticipant(?User $user): bool
    {
        return $this->getParticipantForUser($user) !== null;
    }

    public function getParticipantForUser(?User $user): ?EventParticipant
    {
        if ($user === null) {
            return null;
        }

        foreach ($this->participants as $participant) {
            if ($participant->getUser()?->getId() === $user->getId()) {
                return $participant;
            }
        }

        return null;
    }
}
