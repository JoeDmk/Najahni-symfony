<?php
namespace App\Entity;

use App\Repository\InvestmentOfferRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: InvestmentOfferRepository::class)]
#[ORM\Table(name: 'investment_offer')]
#[ORM\HasLifecycleCallbacks]
class InvestmentOffer
{
    public const STATUS_PENDING = 'PENDING';
    public const STATUS_ACCEPTED = 'ACCEPTED';
    public const STATUS_REJECTED = 'REJECTED';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 2)]
    #[Assert\NotBlank]
    #[Assert\Positive]
    private ?string $proposedAmount = null;

    #[ORM\Column(length: 20, columnDefinition: "ENUM('PENDING','ACCEPTED','REJECTED') DEFAULT 'PENDING'")]
    private string $status = self::STATUS_PENDING;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'investor_id', nullable: false, onDelete: 'CASCADE')]
    private ?User $investor = null;

    #[ORM\ManyToOne(targetEntity: InvestmentOpportunity::class, inversedBy: 'offers')]
    #[ORM\JoinColumn(name: 'opportunity_id', nullable: false, onDelete: 'CASCADE')]
    private ?InvestmentOpportunity $opportunity = null;

    #[ORM\Column]
    private bool $paid = false;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $paymentIntentId = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $paidAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void { $this->updatedAt = new \DateTime(); }

    public function getId(): ?int { return $this->id; }
    public function getProposedAmount(): ?string { return $this->proposedAmount; }
    public function setProposedAmount(string $v): static { $this->proposedAmount = $v; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $v): static { $this->status = $v; return $this; }
    public function getInvestor(): ?User { return $this->investor; }
    public function setInvestor(?User $v): static { $this->investor = $v; return $this; }
    public function getOpportunity(): ?InvestmentOpportunity { return $this->opportunity; }
    public function setOpportunity(?InvestmentOpportunity $v): static { $this->opportunity = $v; return $this; }
    public function isPaid(): bool { return $this->paid; }
    public function setPaid(bool $v): static { $this->paid = $v; return $this; }
    public function getPaymentIntentId(): ?string { return $this->paymentIntentId; }
    public function setPaymentIntentId(?string $v): static { $this->paymentIntentId = $v; return $this; }
    public function getPaidAt(): ?\DateTimeInterface { return $this->paidAt; }
    public function setPaidAt(?\DateTimeInterface $v): static { $this->paidAt = $v; return $this; }
    public function getCreatedAt(): ?\DateTimeInterface { return $this->createdAt; }
    public function getUpdatedAt(): ?\DateTimeInterface { return $this->updatedAt; }
}
