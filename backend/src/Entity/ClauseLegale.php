<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'clause_legale')]
#[ORM\UniqueConstraint(name: 'uq_clause_code_version', columns: ['atelier_id', 'code', 'version'])]
#[ORM\Index(columns: ['atelier_id', 'code', 'is_active'], name: 'idx_clause_active')]
class ClauseLegale
{
    public const CODES = ['accessoires', 'garantie', 'essai', 'gardiennage', 'cgv'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(nullable: true)]
    private ?int $atelierId = null;

    #[ORM\Column(length: 50)]
    private string $code;

    #[ORM\Column(length: 200)]
    private string $libelle;

    #[ORM\Column(type: 'text')]
    private string $texte;

    #[ORM\Column(options: ['default' => 1])]
    private int $version = 1;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $effectiveFrom;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $isActive = true;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $createdAt;

    public function __construct()
    {
        $this->effectiveFrom = new \DateTime();
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }
    public function getAtelierId(): ?int { return $this->atelierId; }
    public function setAtelierId(?int $v): static { $this->atelierId = $v; return $this; }
    public function getCode(): string { return $this->code; }
    public function setCode(string $v): static { $this->code = $v; return $this; }
    public function getLibelle(): string { return $this->libelle; }
    public function setLibelle(string $v): static { $this->libelle = $v; return $this; }
    public function getTexte(): string { return $this->texte; }
    public function setTexte(string $v): static { $this->texte = $v; return $this; }
    public function getVersion(): int { return $this->version; }
    public function setVersion(int $v): static { $this->version = $v; return $this; }
    public function getEffectiveFrom(): \DateTimeInterface { return $this->effectiveFrom; }
    public function setEffectiveFrom(\DateTimeInterface $v): static { $this->effectiveFrom = $v; return $this; }
    public function isActive(): bool { return $this->isActive; }
    public function setIsActive(bool $v): static { $this->isActive = $v; return $this; }
    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
}
