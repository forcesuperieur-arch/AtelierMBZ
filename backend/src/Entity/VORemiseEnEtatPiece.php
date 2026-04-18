<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'vo_remise_en_etat_pieces')]
#[ORM\HasLifecycleCallbacks]
class VORemiseEnEtatPiece
{
    public const STATUS_EN_STOCK = 'en_stock';
    public const STATUS_A_COMMANDER = 'a_commander';
    public const STATUS_COMMANDEE = 'commandee';
    public const STATUS_RECUE = 'recue';
    public const STATUS_MONTEE = 'montee';
    public const STATUS_ANNULEE = 'annulee';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: VORemiseEnEtat::class, inversedBy: 'pieces')]
    #[ORM\JoinColumn(name: 'remise_en_etat_id', nullable: false, onDelete: 'CASCADE')]
    private VORemiseEnEtat $remiseEnEtat;

    #[ORM\Column(length: 255)]
    private string $libelle;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $reference = null;

    #[ORM\Column(options: ['default' => 1])]
    private int $quantity = 1;

    #[ORM\Column(length: 160, nullable: true)]
    private ?string $supplier = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $estimatedUnitCostHt = '0.00';

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $estimatedTotalCostHt = '0.00';

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $actualTotalCostHt = null;

    #[ORM\Column(length: 30, options: ['default' => self::STATUS_A_COMMANDER])]
    private string $status = self::STATUS_A_COMMANDER;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $updatedAt;

    public function __construct()
    {
        $now = new \DateTime();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    #[ORM\PreUpdate]
    public function preUpdate(): void
    {
        $this->updatedAt = new \DateTime();
    }

    public function recomputeEstimatedTotal(): void
    {
        $this->estimatedTotalCostHt = bcmul($this->estimatedUnitCostHt, (string) max(1, $this->quantity), 2);
    }

    public function getId(): ?int { return $this->id; }
    public function getRemiseEnEtat(): VORemiseEnEtat { return $this->remiseEnEtat; }
    public function setRemiseEnEtat(VORemiseEnEtat $remiseEnEtat): static { $this->remiseEnEtat = $remiseEnEtat; return $this; }
    public function getLibelle(): string { return $this->libelle; }
    public function setLibelle(string $libelle): static { $this->libelle = $libelle; return $this; }
    public function getReference(): ?string { return $this->reference; }
    public function setReference(?string $reference): static { $this->reference = $reference; return $this; }
    public function getQuantity(): int { return $this->quantity; }
    public function setQuantity(int $quantity): static { $this->quantity = max(1, $quantity); $this->recomputeEstimatedTotal(); return $this; }
    public function getSupplier(): ?string { return $this->supplier; }
    public function setSupplier(?string $supplier): static { $this->supplier = $supplier; return $this; }
    public function getEstimatedUnitCostHt(): string { return $this->estimatedUnitCostHt; }
    public function setEstimatedUnitCostHt(string $estimatedUnitCostHt): static { $this->estimatedUnitCostHt = $estimatedUnitCostHt; $this->recomputeEstimatedTotal(); return $this; }
    public function getEstimatedTotalCostHt(): string { return $this->estimatedTotalCostHt; }
    public function setEstimatedTotalCostHt(string $estimatedTotalCostHt): static { $this->estimatedTotalCostHt = $estimatedTotalCostHt; return $this; }
    public function getActualTotalCostHt(): ?string { return $this->actualTotalCostHt; }
    public function setActualTotalCostHt(?string $actualTotalCostHt): static { $this->actualTotalCostHt = $actualTotalCostHt; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }
    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $notes): static { $this->notes = $notes; return $this; }
    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeInterface { return $this->updatedAt; }
}