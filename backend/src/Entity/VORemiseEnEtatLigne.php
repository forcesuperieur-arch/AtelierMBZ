<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'vo_remise_en_etat_lignes')]
#[ORM\HasLifecycleCallbacks]
class VORemiseEnEtatLigne
{
    public const STATUS_PROPOSEE = 'proposee';
    public const STATUS_VALIDEE = 'validee';
    public const STATUS_EN_COURS = 'en_cours';
    public const STATUS_TERMINEE = 'terminee';
    public const STATUS_ANNULEE = 'annulee';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: VORemiseEnEtat::class, inversedBy: 'lignes')]
    #[ORM\JoinColumn(name: 'remise_en_etat_id', nullable: false, onDelete: 'CASCADE')]
    private VORemiseEnEtat $remiseEnEtat;

    #[ORM\ManyToOne(targetEntity: Prestation::class)]
    #[ORM\JoinColumn(name: 'prestation_id', nullable: true, onDelete: 'SET NULL')]
    private ?Prestation $prestation = null;

    #[ORM\Column(length: 255)]
    private string $libelle;

    #[ORM\Column(options: ['default' => 1])]
    private int $quantity = 1;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $estimatedUnitHt = '0.00';

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $estimatedTotalHt = '0.00';

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $actualTotalHt = null;

    #[ORM\Column(options: ['default' => 0])]
    private int $estimatedMinutes = 0;

    #[ORM\Column(nullable: true)]
    private ?int $actualMinutes = null;

    #[ORM\Column(length: 30, options: ['default' => self::STATUS_PROPOSEE])]
    private string $status = self::STATUS_PROPOSEE;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(options: ['default' => 0])]
    private int $sortOrder = 0;

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
        $this->estimatedTotalHt = bcmul($this->estimatedUnitHt, (string) max(1, $this->quantity), 2);
    }

    public function getId(): ?int { return $this->id; }
    public function getRemiseEnEtat(): VORemiseEnEtat { return $this->remiseEnEtat; }
    public function setRemiseEnEtat(VORemiseEnEtat $remiseEnEtat): static { $this->remiseEnEtat = $remiseEnEtat; return $this; }
    public function getPrestation(): ?Prestation { return $this->prestation; }
    public function setPrestation(?Prestation $prestation): static { $this->prestation = $prestation; return $this; }
    public function getLibelle(): string { return $this->libelle; }
    public function setLibelle(string $libelle): static { $this->libelle = $libelle; return $this; }
    public function getQuantity(): int { return $this->quantity; }
    public function setQuantity(int $quantity): static { $this->quantity = max(1, $quantity); $this->recomputeEstimatedTotal(); return $this; }
    public function getEstimatedUnitHt(): string { return $this->estimatedUnitHt; }
    public function setEstimatedUnitHt(string $estimatedUnitHt): static { $this->estimatedUnitHt = $estimatedUnitHt; $this->recomputeEstimatedTotal(); return $this; }
    public function getEstimatedTotalHt(): string { return $this->estimatedTotalHt; }
    public function setEstimatedTotalHt(string $estimatedTotalHt): static { $this->estimatedTotalHt = $estimatedTotalHt; return $this; }
    public function getActualTotalHt(): ?string { return $this->actualTotalHt; }
    public function setActualTotalHt(?string $actualTotalHt): static { $this->actualTotalHt = $actualTotalHt; return $this; }
    public function getEstimatedMinutes(): int { return $this->estimatedMinutes; }
    public function setEstimatedMinutes(int $estimatedMinutes): static { $this->estimatedMinutes = max(0, $estimatedMinutes); return $this; }
    public function getActualMinutes(): ?int { return $this->actualMinutes; }
    public function setActualMinutes(?int $actualMinutes): static { $this->actualMinutes = $actualMinutes !== null ? max(0, $actualMinutes) : null; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }
    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $notes): static { $this->notes = $notes; return $this; }
    public function getSortOrder(): int { return $this->sortOrder; }
    public function setSortOrder(int $sortOrder): static { $this->sortOrder = $sortOrder; return $this; }
    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeInterface { return $this->updatedAt; }
}