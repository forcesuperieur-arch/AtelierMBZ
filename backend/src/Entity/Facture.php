<?php
namespace App\Entity;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Get;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity] #[ORM\Table(name: 'factures')] #[ORM\HasLifecycleCallbacks]
#[ApiResource(
    normalizationContext: ['groups' => ['facture:read']],
    denormalizationContext: ['groups' => ['facture:write']],
    operations: [
        new GetCollection(),
        new Get(),
    ],
)]
class Facture
{
    public const NATURE_FACTURE = 'facture';
    public const NATURE_AVOIR = 'avoir';

    public const STATUS_EMISE = 'emise';
    public const STATUS_PAYEE = 'payee';
    public const STATUS_PARTIELLEMENT_PAYEE = 'partiellement_payee';
    public const STATUS_CORRIGEE = 'corrigee';

    #[ORM\Id] #[ORM\GeneratedValue] #[ORM\Column]
    #[Groups(['facture:read'])]
    private ?int $id = null;

    #[ORM\Column(nullable: true)] private ?int $atelierId = null;

    #[ORM\Column(length: 50, unique: true)]
    #[Groups(['facture:read'])]
    private string $numeroFacture;

    #[ORM\ManyToOne(targetEntity: RendezVous::class)] #[ORM\JoinColumn(name: 'rendez_vous_id', nullable: false)]
    #[Groups(['facture:read', 'facture:write'])]
    private RendezVous $rendezVous;

    #[ORM\ManyToOne(targetEntity: Client::class)] #[ORM\JoinColumn(name: 'client_id', nullable: false)]
    #[Groups(['facture:read', 'facture:write'])]
    private Client $client;

    #[ORM\ManyToOne(targetEntity: Vehicule::class)] #[ORM\JoinColumn(name: 'vehicule_id', nullable: true)]
    #[Groups(['facture:read', 'facture:write'])]
    private ?Vehicule $vehicule = null;

    #[ORM\Column(length: 20, options: ['default' => 'facture'])]
    #[Groups(['facture:read'])]
    private string $nature = self::NATURE_FACTURE;

    #[ORM\ManyToOne(targetEntity: self::class)]
    #[ORM\JoinColumn(name: 'facture_origine_id', nullable: true, onDelete: 'SET NULL')]
    private ?self $factureOrigine = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['facture:read'])]
    private ?string $motifCorrection = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, options: ['default' => '0.00'])]
    #[Groups(['facture:read', 'facture:write'])]
    private string $totalMoHt = '0.00';

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, options: ['default' => '0.00'])]
    #[Groups(['facture:read', 'facture:write'])]
    private string $totalPiecesHt = '0.00';

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, options: ['default' => '0.00'])]
    #[Groups(['facture:read', 'facture:write'])]
    private string $totalHt = '0.00';

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, options: ['default' => '0.00'])]
    #[Groups(['facture:read'])]
    private string $tvaMo = '0.00';

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, options: ['default' => '0.00'])]
    #[Groups(['facture:read'])]
    private string $tvaPieces = '0.00';

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, options: ['default' => '0.00'])]
    #[Groups(['facture:read'])]
    private string $totalTva = '0.00';

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, options: ['default' => '0.00'])]
    #[Groups(['facture:read', 'facture:write'])]
    private string $totalTtc = '0.00';

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, options: ['default' => '0.00'])]
    #[Groups(['facture:read', 'facture:write'])]
    private string $remisePourcentage = '0.00';

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, options: ['default' => '0.00'])]
    #[Groups(['facture:read', 'facture:write'])]
    private string $remiseMontant = '0.00';

    #[ORM\Column(options: ['default' => 0])]
    #[Groups(['facture:read', 'facture:write'])]
    private int $tempsFactureMinutes = 0;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, options: ['default' => '65.00'])]
    #[Groups(['facture:read', 'facture:write'])]
    private string $tauxHoraire = '65.00';

    #[ORM\Column(type: 'float', options: ['default' => 20.0])]
    #[Groups(['facture:read'])]
    private float $tvaMoTaux = 20.0;

    #[ORM\Column(type: 'float', options: ['default' => 20.0])]
    #[Groups(['facture:read'])]
    private float $tvaPiecesTaux = 20.0;

    #[ORM\Column(length: 50, options: ['default' => 'emise'])]
    #[Groups(['facture:read', 'facture:write'])]
    private string $statut = self::STATUS_EMISE;

    #[ORM\Column(type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'])]
    #[Groups(['facture:read'])]
    private \DateTimeInterface $dateCreation;

    #[ORM\Column(type: 'date', nullable: true)]
    #[Groups(['facture:read', 'facture:write'])]
    private ?\DateTimeInterface $dateEcheance = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['facture:read', 'facture:write'])]
    private ?string $notes = null;

    // --- RGPD: Snapshot fields (immutable copy at invoice creation) ---
    #[ORM\Column(length: 200, nullable: true)]
    #[Groups(['facture:read'])]
    private ?string $snapClientNom = null;

    #[ORM\Column(length: 200, nullable: true)]
    #[Groups(['facture:read'])]
    private ?string $snapClientPrenom = null;

    #[ORM\Column(length: 200, nullable: true)]
    #[Groups(['facture:read'])]
    private ?string $snapClientEmail = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Groups(['facture:read'])]
    private ?string $snapClientTelephone = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['facture:read'])]
    private ?string $snapClientAdresse = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Groups(['facture:read'])]
    private ?string $snapVehiculePlaque = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Groups(['facture:read'])]
    private ?string $snapVehiculeMarque = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Groups(['facture:read'])]
    private ?string $snapVehiculeModele = null;

    #[ORM\Column(type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'])]
    #[Groups(['facture:read'])]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'])]
    #[Groups(['facture:read'])]
    private \DateTimeInterface $updatedAt;

    #[ORM\OneToMany(targetEntity: Paiement::class, mappedBy: 'facture', cascade: ['persist', 'remove'])]
    #[Groups(['facture:read'])]
    private Collection $paiements;

    #[ORM\OneToMany(targetEntity: LigneFacture::class, mappedBy: 'facture', cascade: ['persist', 'remove'])]
    #[Groups(['facture:read'])]
    private Collection $lignes;

    public function __construct() {
        $this->dateCreation = new \DateTime(); $this->createdAt = new \DateTime(); $this->updatedAt = new \DateTime();
        $this->paiements = new ArrayCollection(); $this->lignes = new ArrayCollection();
    }
    #[ORM\PrePersist] public function snapshotClientData(): void {
        if ($this->client && !$this->snapClientNom) {
            $this->snapClientNom = $this->client->getNom();
            $this->snapClientPrenom = $this->client->getPrenom();
            $this->snapClientEmail = $this->client->getEmail();
            $this->snapClientTelephone = $this->client->getTelephone();
            $this->snapClientAdresse = $this->client->getAdresse();
        }
        if ($this->vehicule && !$this->snapVehiculePlaque) {
            $this->snapVehiculePlaque = $this->vehicule->getPlaque();
            $this->snapVehiculeMarque = $this->vehicule->getMarque();
            $this->snapVehiculeModele = $this->vehicule->getModele();
        }
    }
    #[ORM\PreUpdate] public function preUpdate(): void { $this->updatedAt = new \DateTime(); }

    public function getId(): ?int { return $this->id; }
    public function getAtelierId(): ?int { return $this->atelierId; }
    public function setAtelierId(?int $v): static { $this->atelierId = $v; return $this; }
    public function getNumeroFacture(): string { return $this->numeroFacture; }
    public function setNumeroFacture(string $v): static { $this->numeroFacture = $v; return $this; }
    public function getRendezVous(): RendezVous { return $this->rendezVous; }
    public function setRendezVous(RendezVous $v): static { $this->rendezVous = $v; return $this; }
    public function getClient(): Client { return $this->client; }
    public function setClient(Client $v): static { $this->client = $v; return $this; }
    public function getVehicule(): ?Vehicule { return $this->vehicule; }
    public function setVehicule(?Vehicule $v): static { $this->vehicule = $v; return $this; }
    public function getNature(): string { return $this->nature; }
    public function setNature(string $v): static { $this->nature = $v; return $this; }
    public function isAvoir(): bool { return $this->nature === self::NATURE_AVOIR; }
    public function getFactureOrigine(): ?self { return $this->factureOrigine; }
    public function setFactureOrigine(?self $v): static { $this->factureOrigine = $v; return $this; }
    #[Groups(['facture:read'])]
    public function getFactureOrigineId(): ?int { return $this->factureOrigine?->getId(); }
    public function getMotifCorrection(): ?string { return $this->motifCorrection; }
    public function setMotifCorrection(?string $v): static { $this->motifCorrection = $v; return $this; }
    public function getTotalMoHt(): string { return $this->totalMoHt; }
    public function setTotalMoHt(string $v): static { $this->totalMoHt = $v; return $this; }
    public function getTotalPiecesHt(): string { return $this->totalPiecesHt; }
    public function setTotalPiecesHt(string $v): static { $this->totalPiecesHt = $v; return $this; }
    public function getTotalHt(): string { return $this->totalHt; }
    public function setTotalHt(string $v): static { $this->totalHt = $v; return $this; }
    public function getTvaMo(): string { return $this->tvaMo; }
    public function setTvaMo(string $v): static { $this->tvaMo = $v; return $this; }
    public function getTvaPieces(): string { return $this->tvaPieces; }
    public function setTvaPieces(string $v): static { $this->tvaPieces = $v; return $this; }
    public function getTotalTva(): string { return $this->totalTva; }
    public function setTotalTva(string $v): static { $this->totalTva = $v; return $this; }
    public function getTotalTtc(): string { return $this->totalTtc; }
    public function setTotalTtc(string $v): static { $this->totalTtc = $v; return $this; }
    public function getRemisePourcentage(): string { return $this->remisePourcentage; }
    public function setRemisePourcentage(string $v): static { $this->remisePourcentage = $v; return $this; }
    public function getRemiseMontant(): string { return $this->remiseMontant; }
    public function setRemiseMontant(string $v): static { $this->remiseMontant = $v; return $this; }
    public function getTempsFactureMinutes(): int { return $this->tempsFactureMinutes; }
    public function setTempsFactureMinutes(int $v): static { $this->tempsFactureMinutes = $v; return $this; }
    public function getTauxHoraire(): string { return $this->tauxHoraire; }
    public function setTauxHoraire(string $v): static { $this->tauxHoraire = $v; return $this; }
    public function getStatut(): string { return $this->statut; }
    public function setStatut(string $v): static { $this->statut = $v; return $this; }
    public function getDateCreation(): \DateTimeInterface { return $this->dateCreation; }
    public function getDateEcheance(): ?\DateTimeInterface { return $this->dateEcheance; }
    public function setDateEcheance(?\DateTimeInterface $v): static { $this->dateEcheance = $v; return $this; }
    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $v): static { $this->notes = $v; return $this; }
    public function getTvaMoTaux(): float { return $this->tvaMoTaux; }
    public function setTvaMoTaux(float $v): static { $this->tvaMoTaux = $v; return $this; }
    public function getTvaPiecesTaux(): float { return $this->tvaPiecesTaux; }
    public function setTvaPiecesTaux(float $v): static { $this->tvaPiecesTaux = $v; return $this; }
    public function getPaiements(): Collection { return $this->paiements; }
    public function getLignes(): Collection { return $this->lignes; }

    // --- RGPD snapshot getters/setters ---
    public function getSnapClientNom(): ?string { return $this->snapClientNom; }
    public function setSnapClientNom(?string $v): static { $this->snapClientNom = $v; return $this; }
    public function getSnapClientPrenom(): ?string { return $this->snapClientPrenom; }
    public function setSnapClientPrenom(?string $v): static { $this->snapClientPrenom = $v; return $this; }
    public function getSnapClientEmail(): ?string { return $this->snapClientEmail; }
    public function setSnapClientEmail(?string $v): static { $this->snapClientEmail = $v; return $this; }
    public function getSnapClientTelephone(): ?string { return $this->snapClientTelephone; }
    public function setSnapClientTelephone(?string $v): static { $this->snapClientTelephone = $v; return $this; }
    public function getSnapClientAdresse(): ?string { return $this->snapClientAdresse; }
    public function setSnapClientAdresse(?string $v): static { $this->snapClientAdresse = $v; return $this; }
    public function getSnapVehiculePlaque(): ?string { return $this->snapVehiculePlaque; }
    public function setSnapVehiculePlaque(?string $v): static { $this->snapVehiculePlaque = $v; return $this; }
    public function getSnapVehiculeMarque(): ?string { return $this->snapVehiculeMarque; }
    public function setSnapVehiculeMarque(?string $v): static { $this->snapVehiculeMarque = $v; return $this; }
    public function getSnapVehiculeModele(): ?string { return $this->snapVehiculeModele; }
    public function setSnapVehiculeModele(?string $v): static { $this->snapVehiculeModele = $v; return $this; }
    public function hasSnapshot(): bool { return $this->snapClientNom !== null; }
}
