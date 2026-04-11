<?php

namespace App\Entity;

use App\Repository\InvestmentContractMessageRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InvestmentContractMessageRepository::class)]
#[ORM\Table(name: 'investment_contract_message')]
class InvestmentContractMessage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: InvestmentContract::class, inversedBy: 'messages')]
    #[ORM\JoinColumn(name: 'contract_id', nullable: false, onDelete: 'CASCADE')]
    private ?InvestmentContract $contract = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'sender_id', nullable: false, onDelete: 'CASCADE')]
    private ?User $sender = null;

    #[ORM\Column(type: Types::TEXT)]
    private string $body = '';

    #[ORM\Column(options: ['default' => false])]
    private bool $systemMessage = false;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }
    public function getContract(): ?InvestmentContract { return $this->contract; }
    public function setContract(?InvestmentContract $contract): static { $this->contract = $contract; return $this; }
    public function getSender(): ?User { return $this->sender; }
    public function setSender(?User $sender): static { $this->sender = $sender; return $this; }
    public function getBody(): string { return $this->body; }
    public function setBody(string $body): static { $this->body = $body; return $this; }
    public function isSystemMessage(): bool { return $this->systemMessage; }
    public function setSystemMessage(bool $systemMessage): static { $this->systemMessage = $systemMessage; return $this; }
    public function getCreatedAt(): ?\DateTimeInterface { return $this->createdAt; }
}