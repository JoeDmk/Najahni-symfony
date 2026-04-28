<?php

namespace App\Entity;

use App\Repository\InvestorProfileRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: InvestorProfileRepository::class)]
#[ORM\Table(name: 'investor_profile')]
#[ORM\HasLifecycleCallbacks]
class InvestorProfile
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', nullable: false, unique: true, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $preferredSectors = null;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 5], nullable: false)]
    private int $riskTolerance = 5;

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 2, options: ['default' => 0], nullable: false)]
    #[Assert\LessThan(propertyPath: 'budgetMax', message: 'Le budget minimum doit etre strictement inferieur au budget maximum.')]
    private string $budgetMin = '0';

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 2, options: ['default' => 10000000], nullable: false)]
    private string $budgetMax = '10000000';

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 12], nullable: false)]
    private int $horizonMonths = 12;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: false)]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: false)]
    private \DateTimeInterface $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }

    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): static { $this->user = $user; return $this; }

    public function getPreferredSectors(): ?string { return $this->preferredSectors; }
    public function setPreferredSectors(?string $v): static { $this->preferredSectors = $v; return $this; }

    public function getSectorArray(): array
    {
        if ($this->preferredSectors === null || trim($this->preferredSectors) === '') {
            return [];
        }
        return array_map('trim', explode(',', $this->preferredSectors));
    }

    public function getRiskTolerance(): int { return $this->riskTolerance; }
    public function setRiskTolerance(int $v): static { $this->riskTolerance = $v; return $this; }

    public function getBudgetMin(): string { return $this->budgetMin; }
    public function setBudgetMin(string $v): static { $this->budgetMin = $v; return $this; }

    public function getBudgetMax(): string { return $this->budgetMax; }
    public function setBudgetMax(string $v): static { $this->budgetMax = $v; return $this; }

    public function getHorizonMonths(): int { return $this->horizonMonths; }
    public function setHorizonMonths(int $v): static { $this->horizonMonths = $v; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $v): static { $this->description = $v; return $this; }

    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeInterface { return $this->updatedAt; }

    public function adaptFromInvestment(InvestmentOffer $offer, InvestmentOpportunity $opportunity): static
    {
        $amount = max(0.0, (float) $offer->getProposedAmount());
        $observedRiskTolerance = max(1, min(10, (int) round((($opportunity->getRiskScore() ?? 50.0) / 100) * 10)));
        $observedHorizon = 12;

        if ($opportunity->getDeadline() !== null) {
            $daysUntilDeadline = (int) (new \DateTimeImmutable())->diff($opportunity->getDeadline())->format('%r%a');
            if ($daysUntilDeadline > 0) {
                $observedHorizon = max(1, (int) ceil($daysUntilDeadline / 30));
            }
        }

        $sector = trim((string) $opportunity->getProject()?->getSecteur());
        $sectors = $this->mergePreferredSector($sector, $this->getSectorArray());
        $observedBudgetMin = max(0.0, $amount * 0.6);
        $observedBudgetMax = max($amount * 1.4, $observedBudgetMin + 1000.0);
        $currentBudgetMin = max(0.0, (float) $this->budgetMin);
        $currentBudgetMax = max($observedBudgetMax, (float) $this->budgetMax);

        $adaptedBudgetMin = $currentBudgetMin > 0
            ? ($currentBudgetMin + $observedBudgetMin) / 2
            : $observedBudgetMin;
        $adaptedBudgetMax = ($currentBudgetMax + max($amount, $observedBudgetMax)) / 2;

        if ($adaptedBudgetMin >= $adaptedBudgetMax) {
            $adaptedBudgetMax = $adaptedBudgetMin + max(1000.0, $amount * 0.25);
        }

        if ($sectors !== []) {
            $this->preferredSectors = implode(', ', $sectors);
        }

        $this->riskTolerance = max(1, min(10, (int) round(($this->riskTolerance + $observedRiskTolerance) / 2)));
        $this->horizonMonths = max(1, (int) round(($this->horizonMonths + $observedHorizon) / 2));
        $this->budgetMin = sprintf('%.2f', $adaptedBudgetMin);
        $this->budgetMax = sprintf('%.2f', $adaptedBudgetMax);
        $this->updatedAt = new \DateTime();

        return $this;
    }

    public function getImpliedRiskTolerance(): string
    {
        if ($this->riskTolerance <= 3) {
            return 'Prudent';
        }

        if ($this->riskTolerance <= 6) {
            return 'Modere';
        }

        return 'Agressif';
    }

    /**
     * @param list<string> $currentSectors
     * @return list<string>
     */
    private function mergePreferredSector(string $sector, array $currentSectors): array
    {
        $cleaned = [];
        $seen = [];

        foreach (array_merge($sector !== '' ? [$sector] : [], $currentSectors) as $entry) {
            $normalized = mb_strtolower(trim((string) $entry));
            if ($normalized === '' || isset($seen[$normalized])) {
                continue;
            }

            $seen[$normalized] = true;
            $cleaned[] = trim((string) $entry);
        }

        return array_slice($cleaned, 0, 6);
    }
}
