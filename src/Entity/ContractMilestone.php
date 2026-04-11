<?php

namespace App\Entity;

use App\Repository\ContractMilestoneRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ContractMilestoneRepository::class)]
#[ORM\Table(name: 'contract_milestone')]
#[ORM\HasLifecycleCallbacks]
class ContractMilestone
{
    public const STATUS_PENDING = 'PENDING';
    public const STATUS_COMPLETED = 'COMPLETED';
    public const STATUS_CONFIRMED = 'CONFIRMED';
    public const STATUS_RELEASED = 'RELEASED';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: InvestmentContract::class, inversedBy: 'fundingMilestones')]
    #[ORM\JoinColumn(name: 'contract_id', nullable: false, onDelete: 'CASCADE')]
    private ?InvestmentContract $contract = null;

    #[ORM\Column(length: 255)]
    private string $label = '';

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2)]
    private string $percentage = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 2)]
    private string $amount = '0.00';

    #[ORM\Column(length: 20, options: ['default' => self::STATUS_PENDING])]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(type: Types::INTEGER)]
    private int $position = 0;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $paymentIntentId = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $completedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $confirmedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $releasedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void {}

    public function getId(): ?int { return $this->id; }

    public function getContract(): ?InvestmentContract { return $this->contract; }
    public function setContract(?InvestmentContract $contract): static { $this->contract = $contract; return $this; }

    public function getLabel(): string { return $this->label; }
    public function setLabel(string $label): static { $this->label = $label; return $this; }

    public function getPercentage(): string { return $this->percentage; }
    public function setPercentage(string $percentage): static { $this->percentage = $percentage; return $this; }

    public function getAmount(): string { return $this->amount; }
    public function setAmount(string $amount): static { $this->amount = $amount; return $this; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }

    public function getPosition(): int { return $this->position; }
    public function setPosition(int $position): static { $this->position = $position; return $this; }

    public function getPaymentIntentId(): ?string { return $this->paymentIntentId; }
    public function setPaymentIntentId(?string $paymentIntentId): static { $this->paymentIntentId = $paymentIntentId; return $this; }

    public function getCompletedAt(): ?\DateTimeInterface { return $this->completedAt; }
    public function setCompletedAt(?\DateTimeInterface $completedAt): static { $this->completedAt = $completedAt; return $this; }

    public function getConfirmedAt(): ?\DateTimeInterface { return $this->confirmedAt; }
    public function setConfirmedAt(?\DateTimeInterface $confirmedAt): static { $this->confirmedAt = $confirmedAt; return $this; }

    public function getReleasedAt(): ?\DateTimeInterface { return $this->releasedAt; }
    public function setReleasedAt(?\DateTimeInterface $releasedAt): static { $this->releasedAt = $releasedAt; return $this; }

    public function getCreatedAt(): ?\DateTimeInterface { return $this->createdAt; }

    public function isReleased(): bool
    {
        return $this->status === self::STATUS_RELEASED;
    }

    public function canBeMarkedComplete(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function canBeConfirmed(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function canBeReleased(): bool
    {
        return $this->status === self::STATUS_CONFIRMED;
    }
}
