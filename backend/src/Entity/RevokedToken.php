<?php
namespace App\Entity;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity] #[ORM\Table(name: 'revoked_tokens')]
class RevokedToken
{
    #[ORM\Id] #[ORM\GeneratedValue] #[ORM\Column] private ?int $id = null;
    #[ORM\Column(length: 255, unique: true)] private string $jti;
    #[ORM\Column(type: 'datetime')] private \DateTimeInterface $expiresAt;
    #[ORM\Column(length: 100, options: ['default' => 'manual'])] private string $reason = 'manual';
    #[ORM\Column(type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'])] private \DateTimeInterface $createdAt;

    public function __construct() { $this->createdAt = new \DateTime(); }

    public function getId(): ?int { return $this->id; }
    public function getJti(): string { return $this->jti; }
    public function setJti(string $v): static { $this->jti = $v; return $this; }
    public function getExpiresAt(): \DateTimeInterface { return $this->expiresAt; }
    public function setExpiresAt(\DateTimeInterface $v): static { $this->expiresAt = $v; return $this; }
    public function getReason(): string { return $this->reason; }
    public function setReason(string $v): static { $this->reason = $v; return $this; }
}
