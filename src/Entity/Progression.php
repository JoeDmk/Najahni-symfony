<?php

namespace App\Entity;

use App\Repository\ProgressionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProgressionRepository::class)]
#[ORM\Table(name: 'progression')]
#[ORM\UniqueConstraint(name: 'unique_user_cours', columns: ['user_id', 'cours_id'])]
#[ORM\HasLifecycleCallbacks]
class Progression
{
    public const ETAT_NON_COMMENCE = 'NON_COMMENCE';
    public const ETAT_EN_COURS = 'EN_COURS';
    public const ETAT_COMPLETE = 'COMPLETE';
    public const ETAT_CERTIFIE = 'CERTIFIE';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'progressions')]
    #[ORM\JoinColumn(name: 'user_id', nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: Cours::class, inversedBy: 'progressions')]
    #[ORM\JoinColumn(name: 'cours_id', nullable: false, onDelete: 'CASCADE')]
    private ?Cours $cours = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2)]
    private string $pourcentage = '0.00';

    #[ORM\Column]
    private int $pointsXp = 0;

    #[ORM\Column]
    private int $niveau = 1;

    #[ORM\Column(length: 20, columnDefinition: "ENUM('NON_COMMENCE','EN_COURS','COMPLETE','CERTIFIE') DEFAULT 'NON_COMMENCE'")]
    private string $etat = self::ETAT_NON_COMMENCE;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $dateDebut = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dateObtention = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updatedAt = null;

    public function __construct()
    {
        $this->dateDebut = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }
    public function getUser(): ?User
    {
        return $this->user;
    }
    public function setUser(?User $v): static
    {
        $this->user = $v;
        return $this;
    }
    public function getCours(): ?Cours
    {
        return $this->cours;
    }
    public function setCours(?Cours $v): static
    {
        $this->cours = $v;
        return $this;
    }
    public function getPourcentage(): string
    {
        return $this->pourcentage;
    }
    public function setPourcentage(string $v): static
    {
        $this->pourcentage = $v;
        return $this;
    }
    public function getPointsXp(): int
    {
        return $this->pointsXp;
    }
    public function setPointsXp(int $v): static
    {
        $this->pointsXp = $v;
        return $this;
    }
    public function getNiveau(): int
    {
        return $this->niveau;
    }
    public function setNiveau(int $v): static
    {
        $this->niveau = $v;
        return $this;
    }
    public function getEtat(): string
    {
        return $this->etat;
    }
    public function setEtat(string $v): static
    {
        $this->etat = $v;
        return $this;
    }
    public function getDateDebut(): ?\DateTimeInterface
    {
        return $this->dateDebut;
    }
    public function setDateDebut(?\DateTimeInterface $d): static
    {
        $this->dateDebut = $d;
        return $this;
    }
    public function getDateObtention(): ?\DateTimeInterface
    {
        return $this->dateObtention;
    }
    public function setDateObtention(?\DateTimeInterface $v): static
    {
        $this->dateObtention = $v;
        return $this;
    }
    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function getEtatBadge(): string
    {
        return match ($this->etat) {
            self::ETAT_NON_COMMENCE => 'secondary',
            self::ETAT_EN_COURS => 'primary',
            self::ETAT_COMPLETE => 'success',
            self::ETAT_CERTIFIE => 'warning',
            default => 'secondary',
        };
    }
}
