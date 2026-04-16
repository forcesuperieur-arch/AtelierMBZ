<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Patch;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity]
#[ORM\Table(name: 'vo_depot_ventes')]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    normalizationContext: ['groups' => ['vo:read']],
    denormalizationContext: ['groups' => ['vo:write']],
    operations: [
        new GetCollection(security: "is_granted('ROLE_VO_MANAGER')"),
        new Get(security: "is_granted('ROLE_VO_MANAGER')"),
        new Post(security: "is_granted('ROLE_VO_MANAGER')"),
        new Patch(security: "is_granted('ROLE_VO_MANAGER')"),
    ],
)]
class VODepotVente
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['vo:read'])]
    private ?int $id = null;

    #[ORM\Column(nullable: true)]
    private ?int $atelierId = null;

    #[ORM\OneToOne(targetEntity: Vehicule::class)]
    #[ORM\JoinColumn(name: 'vehicule_id', nullable: false)]
    #[Groups(['vo:read', 'vo:write'])]
    private Vehicule $vehicule;

    #[ORM\ManyToOne(targetEntity: Client::class)]
    #[ORM\JoinColumn(name: 'deposant_id', nullable: false)]
    #[Groups(['vo:read', 'vo:write'])]
    private Client $deposant;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'gestionnaire_id', nullable: true)]
    #[Groups(['vo:read', 'vo:write'])]
    private ?User $gestionnaire = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    #[Groups(['vo:read', 'vo:write'])]
    private string $prixVenteSouhaite = '0.00';

    #[ORM\Column(length: 20, options: ['default' => 'pourcentage'])]
    #[Groups(['vo:read', 'vo:write'])]
    private string $commissionType = 'pourcentage';

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    #[Groups(['vo:read', 'vo:write'])]
    private string $commissionValeur = '0.00';

    #[ORM\Column(type: 'date')]
    #[Groups(['vo:read', 'vo:write'])]
    private \DateTimeInterface $dateDebut;

    #[ORM\Column(type: 'date', nullable: true)]
    #[Groups(['vo:read', 'vo:write'])]
    private ?\DateTimeInterface $dateFin = null;

    #[ORM\Column(options: ['default' => 90])]
    #[Groups(['vo:read', 'vo:write'])]
    private int $dureeMandat = 90;

    #[ORM\Column(length: 30, options: ['default' => 'actif'])]
    #[Groups(['vo:read', 'vo:write'])]
    private string $status = 'actif';

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['vo:read', 'vo:write'])]
    private ?string $conditionsRestitution = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['vo:read', 'vo:write'])]
    private ?string $assuranceInfo = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['vo:read', 'vo:write'])]
    private ?string $notes = null;

    // --- Identité déposant (Livre de Police) ---

    #[ORM\Column(length: 50, nullable: true)]
    #[Groups(['vo:read', 'vo:write'])]
    private ?string $deposantIdType = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Groups(['vo:read', 'vo:write'])]
    private ?string $deposantIdNumber = null;

    #[ORM\Column(type: 'date', nullable: true)]
    #[Groups(['vo:read', 'vo:write'])]
    private ?\DateTimeInterface $deposantIdDate = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    #[Groups(['vo:read'])]
    private ?string $prixVenteEffectif = null;

    #[ORM\Column(type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'])]
    #[Groups(['vo:read'])]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'])]
    #[Groups(['vo:read'])]
    private \DateTimeInterface $updatedAt;

    public function __construct()
    {
        $this->dateDebut = new \DateTime();
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    #[ORM\PreUpdate]
    public function preUpdate(): void { $this->updatedAt = new \DateTime(); }

    /**
     * Calculate commission amount from effective sale price.
     */
    public function getCommissionAmount(?string $prixVente = null): string
    {
        $prix = $prixVente ?? $this->prixVenteEffectif ?? $this->prixVenteSouhaite;

        if ($this->commissionType === 'forfait') {
            return $this->commissionValeur;
        }

        // pourcentage
        return bcdiv(bcmul($prix, $this->commissionValeur, 4), '100', 2);
    }

    /**
     * Net amount to return to deposant after commission.
     */
    public function getDeposantNet(?string $prixVente = null): string
    {
        $prix = $prixVente ?? $this->prixVenteEffectif ?? $this->prixVenteSouhaite;
        return bcsub($prix, $this->getCommissionAmount($prixVente), 2);
    }

    /**
     * Check if mandate has expired.
     */
    public function isMandatExpire(): bool
    {
        $expiry = (clone $this->dateDebut)->modify("+{$this->dureeMandat} days");
        return $expiry < new \DateTime('today');
    }

    // --- Getters / Setters ---

    public function getId(): ?int { return $this->id; }
    public function getAtelierId(): ?int { return $this->atelierId; }
    public function setAtelierId(?int $v): static { $this->atelierId = $v; return $this; }

    public function getVehicule(): Vehicule { return $this->vehicule; }
    public function setVehicule(Vehicule $v): static { $this->vehicule = $v; return $this; }

    public function getDeposant(): Client { return $this->deposant; }
    public function setDeposant(Client $v): static { $this->deposant = $v; return $this; }

    public function getGestionnaire(): ?User { return $this->gestionnaire; }
    public function setGestionnaire(?User $v): static { $this->gestionnaire = $v; return $this; }

    public function getPrixVenteSouhaite(): string { return $this->prixVenteSouhaite; }
    public function setPrixVenteSouhaite(string $v): static { $this->prixVenteSouhaite = $v; return $this; }

    public function getCommissionType(): string { return $this->commissionType; }
    public function setCommissionType(string $v): static { $this->commissionType = $v; return $this; }

    public function getCommissionValeur(): string { return $this->commissionValeur; }
    public function setCommissionValeur(string $v): static { $this->commissionValeur = $v; return $this; }

    public function getDateDebut(): \DateTimeInterface { return $this->dateDebut; }
    public function setDateDebut(\DateTimeInterface $v): static { $this->dateDebut = $v; return $this; }

    public function getDateFin(): ?\DateTimeInterface { return $this->dateFin; }
    public function setDateFin(?\DateTimeInterface $v): static { $this->dateFin = $v; return $this; }

    public function getDureeMandat(): int { return $this->dureeMandat; }
    public function setDureeMandat(int $v): static { $this->dureeMandat = $v; return $this; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $v): static { $this->status = $v; return $this; }

    public function getConditionsRestitution(): ?string { return $this->conditionsRestitution; }
    public function setConditionsRestitution(?string $v): static { $this->conditionsRestitution = $v; return $this; }

    public function getAssuranceInfo(): ?string { return $this->assuranceInfo; }
    public function setAssuranceInfo(?string $v): static { $this->assuranceInfo = $v; return $this; }

    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $v): static { $this->notes = $v; return $this; }

    public function getDeposantIdType(): ?string { return $this->deposantIdType; }
    public function setDeposantIdType(?string $v): static { $this->deposantIdType = $v; return $this; }

    public function getDeposantIdNumber(): ?string { return $this->deposantIdNumber; }
    public function setDeposantIdNumber(?string $v): static { $this->deposantIdNumber = $v; return $this; }

    public function getDeposantIdDate(): ?\DateTimeInterface { return $this->deposantIdDate; }
    public function setDeposantIdDate(?\DateTimeInterface $v): static { $this->deposantIdDate = $v; return $this; }

    public function getPrixVenteEffectif(): ?string { return $this->prixVenteEffectif; }
    public function setPrixVenteEffectif(?string $v): static { $this->prixVenteEffectif = $v; return $this; }

    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeInterface { return $this->updatedAt; }
}
