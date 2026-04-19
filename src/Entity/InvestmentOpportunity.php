<?php
namespace App\Entity;

use App\Repository\InvestmentOpportunityRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: InvestmentOpportunityRepository::class)]
#[ORM\Table(name: 'investment_opportunity')]
#[ORM\HasLifecycleCallbacks]
class InvestmentOpportunity
{
    public const STATUS_OPEN = 'OPEN';
    public const STATUS_CLOSED = 'CLOSED';
    public const STATUS_FUNDED = 'FUNDED';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 2)]
    #[Assert\NotBlank(message: 'Le montant cible est obligatoire.')]
    #[Assert\Positive(message: 'Le montant doit être positif.')]
    #[Assert\GreaterThanOrEqual(value: 100, message: 'Le montant cible doit être au minimum 100 DT.')]
    private ?string $targetAmount = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\NotBlank(message: 'La description est obligatoire.')]
    #[Assert\Length(min: 10, max: 2000, minMessage: 'La description doit contenir au moins {{ limit }} caractères.', maxMessage: 'La description ne peut pas dépasser {{ limit }} caractères.')]
    private ?string $description = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    #[Assert\NotNull(message: 'La date limite est obligatoire.')]
    private ?\DateTimeInterface $deadline = null;

    #[ORM\Column(length: 20, columnDefinition: "ENUM('OPEN','CLOSED','FUNDED') DEFAULT 'OPEN'")]
    private string $status = self::STATUS_OPEN;

    #[ORM\ManyToOne(targetEntity: Projet::class, inversedBy: 'opportunities')]
    #[ORM\JoinColumn(name: 'project_id', nullable: false, onDelete: 'CASCADE')]
    private ?Projet $project = null;

    #[ORM\Column(nullable: true)]
    private ?float $riskScore = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $riskLabel = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\OneToMany(targetEntity: InvestmentOffer::class, mappedBy: 'opportunity')]
    private Collection $offers;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
        $this->offers = new ArrayCollection();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void { $this->updatedAt = new \DateTime(); }

    public function getId(): ?int { return $this->id; }
    public function getTargetAmount(): ?string { return $this->targetAmount; }
    public function setTargetAmount(string $v): static { $this->targetAmount = $v; return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $v): static { $this->description = $v; return $this; }
    public function getDeadline(): ?\DateTimeInterface { return $this->deadline; }
    public function setDeadline(?\DateTimeInterface $v): static { $this->deadline = $v; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $v): static { $this->status = $v; return $this; }
    public function getProject(): ?Projet { return $this->project; }
    public function setProject(?Projet $v): static { $this->project = $v; return $this; }
    public function getRiskScore(): ?float { return $this->riskScore; }
    public function setRiskScore(?float $v): static { $this->riskScore = $v; return $this; }
    public function getRiskLabel(): ?string { return $this->riskLabel; }
    public function setRiskLabel(?string $v): static { $this->riskLabel = $v; return $this; }
    public function getCreatedAt(): ?\DateTimeInterface { return $this->createdAt; }
    public function getUpdatedAt(): ?\DateTimeInterface { return $this->updatedAt; }
    /** @return Collection<int, InvestmentOffer> */
    public function getOffers(): Collection { return $this->offers; }

    public function getTotalFunded(): float
    {
        $total = 0;
        foreach ($this->offers as $offer) {
            if ($offer->isPaid()) {
                $total += (float) $offer->getProposedAmount();
            }
        }
        return $total;
    }

    public function getFundingPercentage(): float
    {
        $target = (float) $this->targetAmount;
        return $target > 0 ? min(100, ($this->getTotalFunded() / $target) * 100) : 0;
    }
}
