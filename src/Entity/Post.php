<?php
namespace App\Entity;

use App\Repository\PostRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PostRepository::class)]
#[ORM\Table(name: 'posts')]
class Post
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'posts')]
    #[ORM\JoinColumn(name: 'user_id', nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 500)]
    private ?string $content = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $imageUrl = null;

    #[ORM\OneToMany(targetEntity: PostReaction::class, mappedBy: 'post', cascade: ['remove'])]
    private Collection $reactions;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->reactions = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }
    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $v): static { $this->user = $v; return $this; }
    public function getContent(): ?string { return $this->content; }
    public function setContent(string $v): static { $this->content = $v; return $this; }
    public function getCreatedAt(): ?\DateTimeInterface { return $this->createdAt; }
    public function getImageUrl(): ?string { return $this->imageUrl; }
    public function setImageUrl(?string $v): static { $this->imageUrl = $v; return $this; }
    /** @return Collection<int, PostReaction> */
    public function getReactions(): Collection { return $this->reactions; }

    public function getReactionsCount(): int { return $this->reactions->count(); }

    public function getReactionCountForType(string $type): int
    {
        return count(array_filter(
            $this->reactions->toArray(),
            static fn (PostReaction $reaction): bool => $reaction->getReactionType() === $type,
        ));
    }

    public function getReactionTypeForUser(?User $user): ?string
    {
        if ($user === null) {
            return null;
        }

        foreach ($this->reactions as $reaction) {
            if ($reaction->getUser()?->getId() === $user->getId()) {
                return $reaction->getReactionType();
            }
        }

        return null;
    }

    public function getReactionSummary(): array
    {
        $summary = [
            PostReaction::TYPE_LIKE => 0,
            PostReaction::TYPE_LOVE => 0,
            PostReaction::TYPE_HAHA => 0,
            PostReaction::TYPE_WOW => 0,
            PostReaction::TYPE_SAD => 0,
            PostReaction::TYPE_ANGRY => 0,
        ];

        foreach ($this->reactions as $reaction) {
            $type = $reaction->getReactionType();
            $summary[$type] = ($summary[$type] ?? 0) + 1;
        }

        return $summary;
    }
}
