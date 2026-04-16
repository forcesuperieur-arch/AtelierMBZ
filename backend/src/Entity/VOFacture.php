<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Get;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity]
#[ORM\Table(name: 'vo_factures')]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    normalizationContext: ['groups' => ['vofacture:read']],
    denormalizationContext: ['groups' => ['vofacture:write']],
    operations: [
        new GetCollection(security: "is_granted('ROLE_VO_MANAGER')"),
        new Get(security: "is_granted('ROLE_VO_MANAGER')"),
    ],
)]
class VOFacture
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['vofacture:read'])]
    private ?int $id = null;

    #[ORM\Column(nullable: true)]
    private ?int $atelierId = null;

    #[ORM\Column(length: 50, unique: true)]
    #[Groups(['vofacture:read'])]
    private string $numeroFacture;

    #[ORM\ManyToOne(targetEntity: VOPurchase::class)]
    #[ORM\JoinColumn(name: 'vo_purchase_id', nullable: true)]
    #[Groups(['vofacture:read'])]
    private ?VOPurchase $voPurchase = null;

    #[ORM\ManyToOne(targetEntity: VODepotVente::class)]
    #[ORM\JoinColumn(name: 'vo_depot_vente_id', nullable: true)]
    #[Groups(['vofacture:read'])]
    private ?VODepotVente $voDepotVente = null;

    #[ORM\ManyToOne(targetEntity: Client::class)]
    #[ORM\JoinColumn(name: 'client_id', nullable: false)]
    #[Groups(['vofacture:read'])]
    private Client $client;

    #[ORM\ManyToOne(targetEntity: Vehicule::class)]
    #[ORM\JoinColumn(name: 'vehicule_id', nullable: true)]
    #[Groups(['vofacture:read'])]
    private ?Vehicule $vehicule = null;

    // --- Régime TVA ---

    #[ORM\Column(length: 10, options: ['default' => 'marge'])]
    #[Groups(['vofacture:read'])]
    private string $regimeTva = 'marge';

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    #[Groups(['vofacture:read'])]
    private ?string $prixAchatHt = null;

    #[ORM\Column(options: ['default' => true])]
    #[Groups(['vofacture:read'])]
    private bool $mentionTvaMarge = true;

    // --- Montants ---

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, options: ['default' => '0.00'])]
    #[Groups(['vofacture:read'])]
    private string $totalHt = '0.00';

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, options: ['default' => '0.00'])]
    #[Groups(['vofacture:read'])]
    private string $totalTva = '0.00';

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, options: ['default' => '0.00'])]
    #[Groups(['vofacture:read'])]
    private string $totalTtc = '0.00';

    #[ORM\Column(length: 50, options: ['default' => 'emise'])]
    #[Groups(['vofacture:read'])]
    private string $statut = 'emise';

    // --- Mentions légales véhicule ---

    #[ORM\Column(options: ['default' => true])]
    #[Groups(['vofacture:read'])]
    private bool $mentionGarantieConformite = true;

    #[ORM\Column(nullable: true)]
    #[Groups(['vofacture:read'])]
    private ?int $kilometrage = null;

    #[ORM\Column(length: 17, nullable: true)]
    #[Groups(['vofacture:read'])]
    private ?string $vinFacture = null;

    #[ORM\Column(type: 'date', nullable: true)]
    #[Groups(['vofacture:read'])]
    private ?\DateTimeInterface $datePremiereMiseEnCirculationFacture = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Groups(['vofacture:read'])]
    private ?string $immatriculation = null;

    // --- Snapshots RGPD ---

    #[ORM\Column(length: 200, nullable: true)]
    #[Groups(['vofacture:read'])]
    private ?string $snapClientNom = null;

    #[ORM\Column(length: 200, nullable: true)]
    #[Groups(['vofacture:read'])]
    private ?string $snapClientPrenom = null;

    #[ORM\Column(length: 200, nullable: true)]
    #[Groups(['vofacture:read'])]
    private ?string $snapClientEmail = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Groups(['vofacture:read'])]
    private ?string $snapClientTelephone = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['vofacture:read'])]
    private ?string $snapClientAdresse = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Groups(['vofacture:read'])]
    private ?string $snapVehiculePlaque = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Groups(['vofacture:read'])]
    private ?string $snapVehiculeMarque = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Groups(['vofacture:read'])]
    private ?string $snapVehiculeModele = null;

    // --- Dates ---

    #[ORM\Column(type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'])]
    #[Groups(['vofacture:read'])]
    private \DateTimeInterface $dateCreation;

    #[ORM\Column(type: 'date', nullable: true)]
    #[Groups(['vofacture:read'])]
    private ?\DateTimeInterface $dateEcheance = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['vofacture:read'])]
    private ?string $notes = null;

    #[ORM\Column(type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'])]
    #[Groups(['vofacture:read'])]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'])]
    #[Groups(['vofacture:read'])]
    private \DateTimeInterface $updatedAt;

    #[ORM\OneToMany(targetEntity: Paiement::class, mappedBy: 'voFacture', cascade: ['persist', 'remove'])]
    #[Groups(['vofacture:read'])]
    private Collection $paiements;

    public function __construct()
    {
        $this->dateCreation = new \DateTime();
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
        $this->paiements = new ArrayCollection();
    }

    #[ORM\PrePersist]
    public function snapshotData(): void
    {
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

    #[ORM\PreUpdate]
    public function preUpdate(): void { $this->updatedAt = new \DateTime(); }

    // --- Getters / Setters ---

    public function getId(): ?int { return $this->id; }
    public function getAtelierId(): ?int { return $this->atelierId; }
    public function setAtelierId(?int $v): static { $this->atelierId = $v; return $this; }

    public function getNumeroFacture(): string { return $this->numeroFacture; }
    public function setNumeroFacture(string $v): static { $this->numeroFacture = $v; return $this; }

    public function getVoPurchase(): ?VOPurchase { return $this->voPurchase; }
    public function setVoPurchase(?VOPurchase $v): static { $this->voPurchase = $v; return $this; }

    public function getVoDepotVente(): ?VODepotVente { return $this->voDepotVente; }
    public function setVoDepotVente(?VODepotVente $v): static { $this->voDepotVente = $v; return $this; }

    public function getClient(): Client { return $this->client; }
    public function setClient(Client $v): static { $this->client = $v; return $this; }

    public function getVehicule(): ?Vehicule { return $this->vehicule; }
    public function setVehicule(?Vehicule $v): static { $this->vehicule = $v; return $this; }

    public function getRegimeTva(): string { return $this->regimeTva; }
    public function setRegimeTva(string $v): static { $this->regimeTva = $v; return $this; }

    public function getPrixAchatHt(): ?string { return $this->prixAchatHt; }
    public function setPrixAchatHt(?string $v): static { $this->prixAchatHt = $v; return $this; }

    public function getMentionTvaMarge(): bool { return $this->mentionTvaMarge; }
    public function setMentionTvaMarge(bool $v): static { $this->mentionTvaMarge = $v; return $this; }

    public function getTotalHt(): string { return $this->totalHt; }
    public function setTotalHt(string $v): static { $this->totalHt = $v; return $this; }

    public function getTotalTva(): string { return $this->totalTva; }
    public function setTotalTva(string $v): static { $this->totalTva = $v; return $this; }

    public function getTotalTtc(): string { return $this->totalTtc; }
    public function setTotalTtc(string $v): static { $this->totalTtc = $v; return $this; }

    public function getStatut(): string { return $this->statut; }
    public function setStatut(string $v): static { $this->statut = $v; return $this; }

    public function getMentionGarantieConformite(): bool { return $this->mentionGarantieConformite; }
    public function setMentionGarantieConformite(bool $v): static { $this->mentionGarantieConformite = $v; return $this; }

    public function getKilometrage(): ?int { return $this->kilometrage; }
    public function setKilometrage(?int $v): static { $this->kilometrage = $v; return $this; }

    public function getVinFacture(): ?string { return $this->vinFacture; }
    public function setVinFacture(?string $v): static { $this->vinFacture = $v; return $this; }

    public function getDatePremiereMiseEnCirculationFacture(): ?\DateTimeInterface { return $this->datePremiereMiseEnCirculationFacture; }
    public function setDatePremiereMiseEnCirculationFacture(?\DateTimeInterface $v): static { $this->datePremiereMiseEnCirculationFacture = $v; return $this; }

    public function getImmatriculation(): ?string { return $this->immatriculation; }
    public function setImmatriculation(?string $v): static { $this->immatriculation = $v; return $this; }

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

    public function getDateCreation(): \DateTimeInterface { return $this->dateCreation; }
    public function getDateEcheance(): ?\DateTimeInterface { return $this->dateEcheance; }
    public function setDateEcheance(?\DateTimeInterface $v): static { $this->dateEcheance = $v; return $this; }
    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $v): static { $this->notes = $v; return $this; }
    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeInterface { return $this->updatedAt; }
    public function getPaiements(): Collection { return $this->paiements; }
}
