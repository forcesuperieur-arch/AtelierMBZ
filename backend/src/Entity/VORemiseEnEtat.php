<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'vo_remises_en_etat')]
#[ORM\HasLifecycleCallbacks]
class VORemiseEnEtat
{
    public const STATUS_A_CHIFFRER = 'a_chiffrer';
    public const STATUS_A_VALIDER = 'a_valider';
    public const STATUS_VALIDEE = 'validee';
    public const STATUS_PIECES_A_COMMANDER = 'pieces_a_commander';
    public const STATUS_EN_ATTENTE_PIECES = 'en_attente_pieces';
    public const STATUS_PLANIFIEE_ATELIER = 'planifiee_atelier';
    public const STATUS_EN_COURS = 'en_cours';
    public const STATUS_TERMINEE = 'terminee';
    public const STATUS_CLOTUREE = 'cloturee';
    public const STATUS_ANNULEE = 'annulee';

    public const FINAL_STATUSES = [
        self::STATUS_CLOTUREE,
        self::STATUS_ANNULEE,
    ];

    public const PRIORITY_BASSE = 'basse';
    public const PRIORITY_NORMALE = 'normale';
    public const PRIORITY_HAUTE = 'haute';
    public const PRIORITY_URGENTE = 'urgente';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(nullable: true)]
    private ?int $atelierId = null;

    #[ORM\ManyToOne(targetEntity: VOPurchase::class)]
    #[ORM\JoinColumn(name: 'vo_purchase_id', nullable: true, onDelete: 'CASCADE')]
    private ?VOPurchase $voPurchase = null;

    #[ORM\ManyToOne(targetEntity: VODepotVente::class)]
    #[ORM\JoinColumn(name: 'vo_depot_vente_id', nullable: true, onDelete: 'CASCADE')]
    private ?VODepotVente $voDepotVente = null;

    #[ORM\Column]
    private int $campaignIndex = 1;

    #[ORM\Column(length: 180)]
    private string $titre = 'Remise en etat VO';

    #[ORM\Column(length: 40, options: ['default' => self::STATUS_A_CHIFFRER])]
    private string $status = self::STATUS_A_CHIFFRER;

    #[ORM\Column(length: 20, options: ['default' => self::PRIORITY_NORMALE])]
    private string $priority = self::PRIORITY_NORMALE;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $diagnosticNotes = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $workshopNotes = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $businessNotes = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'requested_by', nullable: true, onDelete: 'SET NULL')]
    private ?User $requestedBy = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'validated_by', nullable: true, onDelete: 'SET NULL')]
    private ?User $validatedBy = null;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $requestedAt;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $validatedAt = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $plannedFor = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $startedAt = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $completedAt = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $closedAt = null;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $updatedAt;

    /** @var Collection<int, VORemiseEnEtatLigne> */
    #[ORM\OneToMany(targetEntity: VORemiseEnEtatLigne::class, mappedBy: 'remiseEnEtat', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $lignes;

    /** @var Collection<int, VORemiseEnEtatPiece> */
    #[ORM\OneToMany(targetEntity: VORemiseEnEtatPiece::class, mappedBy: 'remiseEnEtat', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $pieces;

    public function __construct()
    {
        $now = new \DateTime();
        $this->requestedAt = $now;
        $this->createdAt = $now;
        $this->updatedAt = $now;
        $this->lignes = new ArrayCollection();
        $this->pieces = new ArrayCollection();
    }

    #[ORM\PreUpdate]
    public function preUpdate(): void
    {
        $this->updatedAt = new \DateTime();
    }

    public function isClosed(): bool
    {
        return in_array($this->status, self::FINAL_STATUSES, true);
    }

    public function isBlockingSale(): bool
    {
        return !$this->isClosed();
    }

    public function getEstimatedMoCost(): string
    {
        $total = '0.00';
        foreach ($this->lignes as $ligne) {
            if ($ligne->getStatus() === VORemiseEnEtatLigne::STATUS_ANNULEE) {
                continue;
            }

            $total = bcadd($total, $ligne->getEstimatedTotalHt(), 2);
        }

        return $total;
    }

    public function getActualMoCost(): string
    {
        $total = '0.00';
        foreach ($this->lignes as $ligne) {
            if ($ligne->getStatus() === VORemiseEnEtatLigne::STATUS_ANNULEE || $ligne->getActualTotalHt() === null) {
                continue;
            }

            $total = bcadd($total, $ligne->getActualTotalHt(), 2);
        }

        return $total;
    }

    public function getEstimatedPartsCost(): string
    {
        $total = '0.00';
        foreach ($this->pieces as $piece) {
            if ($piece->getStatus() === VORemiseEnEtatPiece::STATUS_ANNULEE) {
                continue;
            }

            $total = bcadd($total, $piece->getEstimatedTotalCostHt(), 2);
        }

        return $total;
    }

    public function getActualPartsCost(): string
    {
        $total = '0.00';
        foreach ($this->pieces as $piece) {
            if ($piece->getStatus() === VORemiseEnEtatPiece::STATUS_ANNULEE || $piece->getActualTotalCostHt() === null) {
                continue;
            }

            $total = bcadd($total, $piece->getActualTotalCostHt(), 2);
        }

        return $total;
    }

    public function getEstimatedTotalCost(): string
    {
        return bcadd($this->getEstimatedMoCost(), $this->getEstimatedPartsCost(), 2);
    }

    public function getActualTotalCost(): string
    {
        return bcadd($this->getActualMoCost(), $this->getActualPartsCost(), 2);
    }

    public function getPendingPiecesCount(): int
    {
        $count = 0;
        foreach ($this->pieces as $piece) {
            if (in_array($piece->getStatus(), [
                VORemiseEnEtatPiece::STATUS_A_COMMANDER,
                VORemiseEnEtatPiece::STATUS_COMMANDEE,
                VORemiseEnEtatPiece::STATUS_RECUE,
            ], true)) {
                ++$count;
            }
        }

        return $count;
    }

    public function getSourceVehicule(): ?Vehicule
    {
        return $this->voPurchase?->getVehicule() ?? $this->voDepotVente?->getVehicule();
    }

    public function getSourceType(): string
    {
        return $this->voPurchase instanceof VOPurchase ? 'purchase' : 'depot';
    }

    public function getSourceId(): ?int
    {
        return $this->voPurchase?->getId() ?? $this->voDepotVente?->getId();
    }

    public function getId(): ?int { return $this->id; }
    public function getAtelierId(): ?int { return $this->atelierId; }
    public function setAtelierId(?int $atelierId): static { $this->atelierId = $atelierId; return $this; }
    public function getVoPurchase(): ?VOPurchase { return $this->voPurchase; }
    public function setVoPurchase(?VOPurchase $voPurchase): static { $this->voPurchase = $voPurchase; return $this; }
    public function getVoDepotVente(): ?VODepotVente { return $this->voDepotVente; }
    public function setVoDepotVente(?VODepotVente $voDepotVente): static { $this->voDepotVente = $voDepotVente; return $this; }
    public function getCampaignIndex(): int { return $this->campaignIndex; }
    public function setCampaignIndex(int $campaignIndex): static { $this->campaignIndex = $campaignIndex; return $this; }
    public function getTitre(): string { return $this->titre; }
    public function setTitre(string $titre): static { $this->titre = $titre; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }
    public function getPriority(): string { return $this->priority; }
    public function setPriority(string $priority): static { $this->priority = $priority; return $this; }
    public function getDiagnosticNotes(): ?string { return $this->diagnosticNotes; }
    public function setDiagnosticNotes(?string $diagnosticNotes): static { $this->diagnosticNotes = $diagnosticNotes; return $this; }
    public function getWorkshopNotes(): ?string { return $this->workshopNotes; }
    public function setWorkshopNotes(?string $workshopNotes): static { $this->workshopNotes = $workshopNotes; return $this; }
    public function getBusinessNotes(): ?string { return $this->businessNotes; }
    public function setBusinessNotes(?string $businessNotes): static { $this->businessNotes = $businessNotes; return $this; }
    public function getRequestedBy(): ?User { return $this->requestedBy; }
    public function setRequestedBy(?User $requestedBy): static { $this->requestedBy = $requestedBy; return $this; }
    public function getValidatedBy(): ?User { return $this->validatedBy; }
    public function setValidatedBy(?User $validatedBy): static { $this->validatedBy = $validatedBy; return $this; }
    public function getRequestedAt(): \DateTimeInterface { return $this->requestedAt; }
    public function setRequestedAt(\DateTimeInterface $requestedAt): static { $this->requestedAt = $requestedAt; return $this; }
    public function getValidatedAt(): ?\DateTimeInterface { return $this->validatedAt; }
    public function setValidatedAt(?\DateTimeInterface $validatedAt): static { $this->validatedAt = $validatedAt; return $this; }
    public function getPlannedFor(): ?\DateTimeInterface { return $this->plannedFor; }
    public function setPlannedFor(?\DateTimeInterface $plannedFor): static { $this->plannedFor = $plannedFor; return $this; }
    public function getStartedAt(): ?\DateTimeInterface { return $this->startedAt; }
    public function setStartedAt(?\DateTimeInterface $startedAt): static { $this->startedAt = $startedAt; return $this; }
    public function getCompletedAt(): ?\DateTimeInterface { return $this->completedAt; }
    public function setCompletedAt(?\DateTimeInterface $completedAt): static { $this->completedAt = $completedAt; return $this; }
    public function getClosedAt(): ?\DateTimeInterface { return $this->closedAt; }
    public function setClosedAt(?\DateTimeInterface $closedAt): static { $this->closedAt = $closedAt; return $this; }
    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeInterface { return $this->updatedAt; }

    /** @return Collection<int, VORemiseEnEtatLigne> */
    public function getLignes(): Collection { return $this->lignes; }

    public function addLigne(VORemiseEnEtatLigne $ligne): static
    {
        if (!$this->lignes->contains($ligne)) {
            $this->lignes->add($ligne);
            $ligne->setRemiseEnEtat($this);
        }

        return $this;
    }

    public function removeLigne(VORemiseEnEtatLigne $ligne): static
    {
        $this->lignes->removeElement($ligne);
        return $this;
    }

    /** @return Collection<int, VORemiseEnEtatPiece> */
    public function getPieces(): Collection { return $this->pieces; }

    public function addPiece(VORemiseEnEtatPiece $piece): static
    {
        if (!$this->pieces->contains($piece)) {
            $this->pieces->add($piece);
            $piece->setRemiseEnEtat($this);
        }

        return $this;
    }

    public function removePiece(VORemiseEnEtatPiece $piece): static
    {
        $this->pieces->removeElement($piece);
        return $this;
    }
}