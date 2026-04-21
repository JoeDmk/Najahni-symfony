<?php
namespace App\Entity;

use App\Repository\BadgeRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: BadgeRepository::class)]
#[ORM\Table(name: 'badge')]
class Badge
{
    public const RARETE_COMMUN = 'COMMUN';
    public const RARETE_RARE = 'RARE';
    public const RARETE_EPIQUE = 'EPIQUE';
    public const RARETE_LEGENDAIRE = 'LEGENDAIRE';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100, unique: true)]
    #[Assert\NotBlank(message: 'Le nom est obligatoire.')]
    #[Assert\Length(min: 2, max: 100, minMessage: 'Le nom doit contenir au moins {{ limit }} caractères.', maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères.')]
    private ?string $nom = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 1000, maxMessage: 'La description ne peut pas dépasser {{ limit }} caractères.')]
    private ?string $description = null;

    #[ORM\Column(length: 50)]
    private string $icone = '';

    #[ORM\Column(name: 'condition_obtention', type: Types::TEXT)]
    #[Assert\NotBlank(message: 'La condition d\'obtention est obligatoire.')]
    private ?string $conditionObtention = null;

    #[ORM\Column]
    #[Assert\PositiveOrZero(message: 'Les points requis doivent être positifs ou nuls.')]
    private int $pointsRequis = 0;

    #[ORM\Column]
    #[Assert\PositiveOrZero(message: 'Le nombre de cours requis doit être positif ou nul.')]
    private int $coursRequis = 0;

    #[ORM\Column]
    #[Assert\PositiveOrZero(message: 'Le niveau requis doit être positif ou nul.')]
    private int $niveauRequis = 0;

    #[ORM\Column(length: 50)]
    private string $categorie = 'Général';

    #[ORM\Column(length: 20, columnDefinition: "ENUM('COMMUN','RARE','EPIQUE','LEGENDAIRE') DEFAULT 'COMMUN'")]
    private string $rarete = self::RARETE_COMMUN;

    #[ORM\Column]
    private bool $actif = true;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    public function __construct() { $this->createdAt = new \DateTime(); }

    public function getId(): ?int { return $this->id; }
    public function getNom(): ?string { return $this->nom; }
    public function setNom(string $v): static { $this->nom = $v; return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $v): static { $this->description = $v; return $this; }
    public function getIcone(): string { return $this->icone; }
    public function setIcone(string $v): static { $this->icone = $v; return $this; }
    public function getConditionObtention(): ?string { return $this->conditionObtention; }
    public function setConditionObtention(string $v): static { $this->conditionObtention = $v; return $this; }
    public function getPointsRequis(): int { return $this->pointsRequis; }
    public function setPointsRequis(int $v): static { $this->pointsRequis = $v; return $this; }
    public function getCoursRequis(): int { return $this->coursRequis; }
    public function setCoursRequis(int $v): static { $this->coursRequis = $v; return $this; }
    public function getNiveauRequis(): int { return $this->niveauRequis; }
    public function setNiveauRequis(int $v): static { $this->niveauRequis = $v; return $this; }
    public function getCategorie(): string { return $this->categorie; }
    public function setCategorie(string $v): static { $this->categorie = $v; return $this; }
    public function getRarete(): string { return $this->rarete; }
    public function setRarete(string $v): static { $this->rarete = $v; return $this; }
    public function isActif(): bool { return $this->actif; }
    public function setActif(bool $v): static { $this->actif = $v; return $this; }
    public function getCreatedAt(): ?\DateTimeInterface { return $this->createdAt; }

    public function getRareteBadge(): string
    {
        return match ($this->rarete) {
            self::RARETE_COMMUN => 'secondary', self::RARETE_RARE => 'info',
            self::RARETE_EPIQUE => 'warning', self::RARETE_LEGENDAIRE => 'danger', default => 'secondary',
        };
    }
}
