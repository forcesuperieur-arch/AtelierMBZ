<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'user_atelier_roles')]
class UserAtelierRole
{
    #[ORM\Id]
    #[ORM\Column]
    private int $userId;

    #[ORM\Id]
    #[ORM\Column]
    private int $atelierId;

    #[ORM\Column(length: 50, options: ['default' => 'receptionnaire'])]
    private string $role = 'receptionnaire';

    #[ORM\Column(type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'])]
    private \DateTimeInterface $createdAt;

    public function __construct() { $this->createdAt = new \DateTime(); }
    public function getUserId(): int { return $this->userId; }
    public function setUserId(int $v): static { $this->userId = $v; return $this; }
    public function getAtelierId(): int { return $this->atelierId; }
    public function setAtelierId(int $v): static { $this->atelierId = $v; return $this; }
    public function getRole(): string { return $this->role; }
    public function setRole(string $v): static { $this->role = $v; return $this; }
    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
}
