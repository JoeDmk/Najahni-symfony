<?php
namespace App\Entity;

use App\Repository\CoursRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CoursRepository::class)]
#[ORM\Table(name: 'cours')]
#[ORM\HasLifecycleCallbacks]
class Cours
{
    public const NIVEAU_DEBUTANT = 'DEBUTANT';
    public const NIVEAU_INTERMEDIAIRE = 'INTERMEDIAIRE';
    public const NIVEAU_AVANCE = 'AVANCE';
    public const NIVEAU_EXPERT = 'EXPERT';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le titre est obligatoire.')]
    #[Assert\Length(min: 3, max: 255, minMessage: 'Le titre doit contenir au moins {{ limit }} caractères.', maxMessage: 'Le titre ne peut pas dépasser {{ limit }} caractères.')]
    private ?string $titre = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 5000, maxMessage: 'La description ne peut pas dépasser {{ limit }} caractères.')]
    private ?string $description = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'La catégorie est obligatoire.')]
    #[Assert\Length(max: 100)]
    private string $categorie = 'Général';

    #[ORM\Column(length: 20, columnDefinition: "ENUM('DEBUTANT','INTERMEDIAIRE','AVANCE','EXPERT') NOT NULL DEFAULT 'DEBUTANT'")]
    private string $niveauDifficulte = self::NIVEAU_DEBUTANT;

    #[ORM\Column]
    #[Assert\PositiveOrZero(message: 'Les points XP doivent être positifs ou nuls.')]
    private int $pointsXp = 100;

    #[ORM\Column(nullable: true)]
    #[Assert\PositiveOrZero(message: 'La durée doit être positive ou nulle.')]
    private ?int $dureeEstimee = 60;

    #[ORM\Column(length: 500, nullable: true)]
    #[Assert\Url(message: 'L\'URL de l\'image n\'est pas valide.')]
    private ?string $imageUrl = null;

    #[ORM\Column]
    private bool $certification = false;

    #[ORM\Column]
    private bool $actif = true;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $documentPath = null;

    #[ORM\Column(length: 500, nullable: true)]
    #[Assert\Url(message: 'L\'URL de la vidéo n\'est pas valide.')]
    private ?string $videoUrl = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\OneToMany(targetEntity: Progression::class, mappedBy: 'cours')]
    private Collection $progressions;

    #[ORM\OneToMany(targetEntity: CoursComment::class, mappedBy: 'cours', cascade: ['remove'])]
    private Collection $comments;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
        $this->progressions = new ArrayCollection();
        $this->comments = new ArrayCollection();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void { $this->updatedAt = new \DateTime(); }

    public function getId(): ?int { return $this->id; }
    public function getTitre(): ?string { return $this->titre; }
    public function setTitre(string $v): static { $this->titre = $v; return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $v): static { $this->description = $v; return $this; }
    public function getCategorie(): string { return $this->categorie; }
    public function setCategorie(string $v): static { $this->categorie = $v; return $this; }
    public function getNiveauDifficulte(): string { return $this->niveauDifficulte; }
    public function setNiveauDifficulte(string $v): static { $this->niveauDifficulte = $v; return $this; }
    public function getPointsXp(): int { return $this->pointsXp; }
    public function setPointsXp(int $v): static { $this->pointsXp = $v; return $this; }
    public function getDureeEstimee(): ?int { return $this->dureeEstimee; }
    public function setDureeEstimee(?int $v): static { $this->dureeEstimee = $v; return $this; }
    public function getImageUrl(): ?string { return $this->imageUrl; }
    public function setImageUrl(?string $v): static { $this->imageUrl = $v; return $this; }
    public function isCertification(): bool { return $this->certification; }
    public function setCertification(bool $v): static { $this->certification = $v; return $this; }
    public function isActif(): bool { return $this->actif; }
    public function setActif(bool $v): static { $this->actif = $v; return $this; }
    public function getDocumentPath(): ?string { return $this->documentPath; }
    public function setDocumentPath(?string $v): static { $this->documentPath = $v; return $this; }
    public function getVideoUrl(): ?string { return $this->videoUrl; }
    public function setVideoUrl(?string $v): static { $this->videoUrl = $v; return $this; }
    public function getCreatedAt(): ?\DateTimeInterface { return $this->createdAt; }
    public function getUpdatedAt(): ?\DateTimeInterface { return $this->updatedAt; }
    /** @return Collection<int, Progression> */
    public function getProgressions(): Collection { return $this->progressions; }
    /** @return Collection<int, CoursComment> */
    public function getComments(): Collection { return $this->comments; }

    public function getNiveauBadge(): string
    {
        return match ($this->niveauDifficulte) {
            self::NIVEAU_DEBUTANT => 'success', self::NIVEAU_INTERMEDIAIRE => 'info',
            self::NIVEAU_AVANCE => 'warning', self::NIVEAU_EXPERT => 'danger', default => 'secondary',
        };
    }
}
