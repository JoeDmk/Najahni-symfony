<?php
namespace App\Entity;

use App\Repository\GroupRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: GroupRepository::class)]
#[ORM\Table(name: '`groups`')]
class Group
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    private ?string $name = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'group_admin_id', nullable: false, onDelete: 'CASCADE')]
    private ?User $groupAdmin = null;

    #[ORM\Column]
    private bool $isPrivate = false;

    #[ORM\OneToMany(targetEntity: Thread::class, mappedBy: 'group', cascade: ['remove'])]
    private Collection $threads;

    #[ORM\OneToMany(targetEntity: GroupMember::class, mappedBy: 'group', cascade: ['remove'])]
    private Collection $members;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->threads = new ArrayCollection();
        $this->members = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }
    public function getName(): ?string { return $this->name; }
    public function setName(string $v): static { $this->name = $v; return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $v): static { $this->description = $v; return $this; }
    public function getCreatedAt(): ?\DateTimeInterface { return $this->createdAt; }
    public function getGroupAdmin(): ?User { return $this->groupAdmin; }
    public function setGroupAdmin(?User $v): static { $this->groupAdmin = $v; return $this; }
    public function isPrivate(): bool { return $this->isPrivate; }
    public function setIsPrivate(bool $v): static { $this->isPrivate = $v; return $this; }
    /** @return Collection<int, Thread> */
    public function getThreads(): Collection { return $this->threads; }
    /** @return Collection<int, GroupMember> */
    public function getMembers(): Collection { return $this->members; }

    public function getMembersCount(): int
    {
        return $this->members->count();
    }

    public function hasMember(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        if ($this->groupAdmin?->getId() === $user->getId()) {
            return true;
        }

        foreach ($this->members as $member) {
            if ($member->getUser()?->getId() === $user->getId()) {
                return true;
            }
        }

        return false;
    }
}
