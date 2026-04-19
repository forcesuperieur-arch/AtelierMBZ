<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'vo_counters')]
#[ORM\UniqueConstraint(name: 'uniq_vo_counters_scope', columns: ['counter_type', 'atelier_id', 'counter_year'])]
class VOCounter
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 30)]
    private string $counterType;

    #[ORM\Column(options: ['default' => 0])]
    private int $atelierId = 0;

    #[ORM\Column(options: ['default' => 0])]
    private int $counterYear = 0;

    #[ORM\Column(options: ['default' => 0])]
    private int $counterValue = 0;

    #[ORM\Column(type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'])]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'])]
    private \DateTimeInterface $updatedAt;

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    public function getId(): ?int { return $this->id; }
    public function getCounterType(): string { return $this->counterType; }
    public function setCounterType(string $counterType): static { $this->counterType = $counterType; return $this; }
    public function getAtelierId(): int { return $this->atelierId; }
    public function setAtelierId(int $atelierId): static { $this->atelierId = $atelierId; return $this; }
    public function getCounterYear(): int { return $this->counterYear; }
    public function setCounterYear(int $counterYear): static { $this->counterYear = $counterYear; return $this; }
    public function getCounterValue(): int { return $this->counterValue; }
    public function setCounterValue(int $counterValue): static { $this->counterValue = $counterValue; return $this; }
    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
    public function setCreatedAt(\DateTimeInterface $createdAt): static { $this->createdAt = $createdAt; return $this; }
    public function getUpdatedAt(): \DateTimeInterface { return $this->updatedAt; }
    public function setUpdatedAt(\DateTimeInterface $updatedAt): static { $this->updatedAt = $updatedAt; return $this; }
}