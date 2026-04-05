<?php
namespace App\Entity;

use App\Repository\PostReactionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PostReactionRepository::class)]
#[ORM\Table(name: 'post_reactions')]
#[ORM\UniqueConstraint(name: 'unique_reaction', columns: ['post_id', 'user_id'])]
class PostReaction
{
    public const TYPE_LIKE = 'LIKE';
    public const TYPE_LOVE = 'LOVE';
    public const TYPE_HAHA = 'HAHA';
    public const TYPE_WOW = 'WOW';
    public const TYPE_SAD = 'SAD';
    public const TYPE_ANGRY = 'ANGRY';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Post::class, inversedBy: 'reactions')]
    #[ORM\JoinColumn(name: 'post_id', nullable: false, onDelete: 'CASCADE')]
    private ?Post $post = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(length: 20, columnDefinition: "ENUM('LIKE','LOVE','HAHA','WOW','SAD','ANGRY') NOT NULL DEFAULT 'LIKE'")]
    private string $reactionType = self::TYPE_LIKE;

    public function getId(): ?int { return $this->id; }
    public function getPost(): ?Post { return $this->post; }
    public function setPost(?Post $v): static { $this->post = $v; return $this; }
    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $v): static { $this->user = $v; return $this; }
    public function getReactionType(): string { return $this->reactionType; }
    public function setReactionType(string $v): static { $this->reactionType = $v; return $this; }

    public function getEmoji(): string
    {
        return match ($this->reactionType) {
            self::TYPE_LIKE => '👍', self::TYPE_LOVE => '❤️', self::TYPE_HAHA => '😂',
            self::TYPE_WOW => '😮', self::TYPE_SAD => '😢', self::TYPE_ANGRY => '😡',
            default => '👍',
        };
    }
}
