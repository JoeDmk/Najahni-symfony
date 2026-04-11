<?php

namespace App\Entity;

use App\Repository\InvestmentContractRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InvestmentContractRepository::class)]
#[ORM\Table(name: 'investment_contract')]
#[ORM\HasLifecycleCallbacks]
class InvestmentContract
{
    public const STATUS_NEGOTIATING = 'NEGOTIATING';
    public const STATUS_READY_TO_SIGN = 'READY_TO_SIGN';
    public const STATUS_SIGNED = 'SIGNED';
    public const STATUS_FUNDED = 'FUNDED';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'contract', targetEntity: InvestmentOffer::class)]
    #[ORM\JoinColumn(name: 'offer_id', nullable: false, unique: true, onDelete: 'CASCADE')]
    private ?InvestmentOffer $offer = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'investor_id', nullable: false, onDelete: 'CASCADE')]
    private ?User $investor = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'entrepreneur_id', nullable: false, onDelete: 'CASCADE')]
    private ?User $entrepreneur = null;

    #[ORM\Column(length: 255)]
    private string $title = '';

    #[ORM\Column(type: Types::TEXT)]
    private string $terms = '';

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, nullable: true)]
    private ?string $equityPercentage = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $consideration = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $milestones = null;

    #[ORM\Column(length: 20, options: ['default' => self::STATUS_NEGOTIATING])]
    private string $status = self::STATUS_NEGOTIATING;

    #[ORM\Column(length: 64)]
    private string $termsDigest = '0000000000000000000000000000000000000000000000000000000000000000';

    #[ORM\Column(length: 150, nullable: true)]
    private ?string $investorSignatureName = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $investorSignatureHash = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $investorSignedAt = null;

    #[ORM\Column(length: 150, nullable: true)]
    private ?string $entrepreneurSignatureName = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $entrepreneurSignatureHash = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $entrepreneurSignedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $lastMessageAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\OneToMany(targetEntity: InvestmentContractMessage::class, mappedBy: 'contract', orphanRemoval: true)]
    #[ORM\OrderBy(['id' => 'ASC'])]
    private Collection $messages;

    /** @var Collection<int, ContractMilestone> */
    #[ORM\OneToMany(targetEntity: ContractMilestone::class, mappedBy: 'contract', orphanRemoval: true, cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $fundingMilestones;

    public function __construct()
    {
        $this->messages = new ArrayCollection();
        $this->fundingMilestones = new ArrayCollection();
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
        $this->lastMessageAt = new \DateTime();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }
    public function getOffer(): ?InvestmentOffer { return $this->offer; }
    public function setOffer(?InvestmentOffer $offer): static
    {
        $this->offer = $offer;
        if ($offer && $offer->getContract() !== $this) {
            $offer->setContract($this);
        }
        return $this;
    }
    public function getInvestor(): ?User { return $this->investor; }
    public function setInvestor(?User $investor): static { $this->investor = $investor; return $this; }
    public function getEntrepreneur(): ?User { return $this->entrepreneur; }
    public function setEntrepreneur(?User $entrepreneur): static { $this->entrepreneur = $entrepreneur; return $this; }
    public function getTitle(): string { return $this->title; }
    public function setTitle(string $title): static { $this->title = $title; return $this; }
    public function getTerms(): string { return $this->terms; }
    public function setTerms(string $terms): static { $this->terms = $terms; return $this; }
    public function getEquityPercentage(): ?string { return $this->equityPercentage; }
    public function setEquityPercentage(?string $equityPercentage): static { $this->equityPercentage = $equityPercentage; return $this; }
    public function getConsideration(): ?string { return $this->consideration; }
    public function setConsideration(?string $consideration): static { $this->consideration = $consideration; return $this; }
    public function getMilestones(): ?string { return $this->milestones; }
    public function setMilestones(?string $milestones): static { $this->milestones = $milestones; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }
    public function getTermsDigest(): string { return $this->termsDigest; }
    public function setTermsDigest(string $termsDigest): static { $this->termsDigest = $termsDigest; return $this; }
    public function getInvestorSignatureName(): ?string { return $this->investorSignatureName; }
    public function setInvestorSignatureName(?string $investorSignatureName): static { $this->investorSignatureName = $investorSignatureName; return $this; }
    public function getInvestorSignatureHash(): ?string { return $this->investorSignatureHash; }
    public function setInvestorSignatureHash(?string $investorSignatureHash): static { $this->investorSignatureHash = $investorSignatureHash; return $this; }
    public function getInvestorSignedAt(): ?\DateTimeInterface { return $this->investorSignedAt; }
    public function setInvestorSignedAt(?\DateTimeInterface $investorSignedAt): static { $this->investorSignedAt = $investorSignedAt; return $this; }
    public function getEntrepreneurSignatureName(): ?string { return $this->entrepreneurSignatureName; }
    public function setEntrepreneurSignatureName(?string $entrepreneurSignatureName): static { $this->entrepreneurSignatureName = $entrepreneurSignatureName; return $this; }
    public function getEntrepreneurSignatureHash(): ?string { return $this->entrepreneurSignatureHash; }
    public function setEntrepreneurSignatureHash(?string $entrepreneurSignatureHash): static { $this->entrepreneurSignatureHash = $entrepreneurSignatureHash; return $this; }
    public function getEntrepreneurSignedAt(): ?\DateTimeInterface { return $this->entrepreneurSignedAt; }
    public function setEntrepreneurSignedAt(?\DateTimeInterface $entrepreneurSignedAt): static { $this->entrepreneurSignedAt = $entrepreneurSignedAt; return $this; }
    public function getLastMessageAt(): ?\DateTimeInterface { return $this->lastMessageAt; }
    public function setLastMessageAt(?\DateTimeInterface $lastMessageAt): static { $this->lastMessageAt = $lastMessageAt; return $this; }
    public function getCreatedAt(): ?\DateTimeInterface { return $this->createdAt; }
    public function getUpdatedAt(): ?\DateTimeInterface { return $this->updatedAt; }

    /** @return Collection<int, InvestmentContractMessage> */
    public function getMessages(): Collection
    {
        return $this->messages;
    }

    public function addMessage(InvestmentContractMessage $message): static
    {
        if (!$this->messages->contains($message)) {
            $this->messages->add($message);
            $message->setContract($this);
        }
        return $this;
    }

    public function belongsTo(User $user): bool
    {
        return $this->investor?->getId() === $user->getId() || $this->entrepreneur?->getId() === $user->getId();
    }

    public function getOtherParty(User $user): ?User
    {
        if ($this->investor?->getId() === $user->getId()) {
            return $this->entrepreneur;
        }

        if ($this->entrepreneur?->getId() === $user->getId()) {
            return $this->investor;
        }

        return null;
    }

    public function clearSignatures(): static
    {
        $this->investorSignatureName = null;
        $this->investorSignatureHash = null;
        $this->investorSignedAt = null;
        $this->entrepreneurSignatureName = null;
        $this->entrepreneurSignatureHash = null;
        $this->entrepreneurSignedAt = null;
        $this->status = self::STATUS_NEGOTIATING;

        return $this;
    }

    public function markSignedBy(User $user, string $signatureName, string $signatureHash, \DateTimeInterface $signedAt): static
    {
        if ($this->investor?->getId() === $user->getId()) {
            $this->investorSignatureName = $signatureName;
            $this->investorSignatureHash = $signatureHash;
            $this->investorSignedAt = $signedAt;
        }

        if ($this->entrepreneur?->getId() === $user->getId()) {
            $this->entrepreneurSignatureName = $signatureName;
            $this->entrepreneurSignatureHash = $signatureHash;
            $this->entrepreneurSignedAt = $signedAt;
        }

        $this->status = $this->isFullySigned() ? self::STATUS_SIGNED : self::STATUS_READY_TO_SIGN;

        return $this;
    }

    public function isFullySigned(): bool
    {
        return $this->investorSignedAt !== null && $this->entrepreneurSignedAt !== null;
    }

    public function hasSigned(User $user): bool
    {
        if ($this->investor?->getId() === $user->getId()) {
            return $this->investorSignedAt !== null;
        }

        if ($this->entrepreneur?->getId() === $user->getId()) {
            return $this->entrepreneurSignedAt !== null;
        }

        return false;
    }

    // ─── Funding Milestones ──────────────────────────────────

    /** @return Collection<int, ContractMilestone> */
    public function getFundingMilestones(): Collection
    {
        return $this->fundingMilestones;
    }

    public function addFundingMilestone(ContractMilestone $milestone): static
    {
        if (!$this->fundingMilestones->contains($milestone)) {
            $this->fundingMilestones->add($milestone);
            $milestone->setContract($this);
        }
        return $this;
    }

    public function removeFundingMilestone(ContractMilestone $milestone): static
    {
        $this->fundingMilestones->removeElement($milestone);
        return $this;
    }

    public function hasFundingMilestones(): bool
    {
        return !$this->fundingMilestones->isEmpty();
    }

    public function getReleasedMilestonesTotal(): float
    {
        $total = 0.0;
        foreach ($this->fundingMilestones as $m) {
            if ($m->isReleased()) {
                $total += (float) $m->getAmount();
            }
        }
        return $total;
    }

    public function areAllMilestonesReleased(): bool
    {
        if ($this->fundingMilestones->isEmpty()) {
            return false;
        }
        foreach ($this->fundingMilestones as $m) {
            if (!$m->isReleased()) {
                return false;
            }
        }
        return true;
    }

    public function getMilestoneProgressPercent(): float
    {
        if ($this->fundingMilestones->isEmpty()) {
            return 0.0;
        }
        $released = 0;
        foreach ($this->fundingMilestones as $m) {
            if ($m->isReleased()) {
                $released++;
            }
        }
        return round(($released / $this->fundingMilestones->count()) * 100, 1);
    }
}