<?php
namespace App\Entity;
use ApiPlatform\Metadata\ApiResource;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity] #[ORM\Table(name: 'devis')] #[ORM\HasLifecycleCallbacks]
#[ApiResource(
    normalizationContext: ['groups' => ['devis:read']],
    denormalizationContext: ['groups' => ['devis:write']],
)]
class Devis
{
    #[ORM\Id] #[ORM\GeneratedValue] #[ORM\Column]
    #[Groups(['devis:read'])]
    private ?int $id = null;

    #[ORM\Column(nullable: true)] private ?int $atelierId = null;

    #[ORM\Column(length: 50, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 50)]
    #[Groups(['devis:read'])]
    private string $numeroDevis;

    #[ORM\ManyToOne(targetEntity: Client::class)] #[ORM\JoinColumn(name: 'client_id', nullable: false)]
    #[Groups(['devis:read', 'devis:write'])]
    private Client $client;

    #[ORM\ManyToOne(targetEntity: Vehicule::class)] #[ORM\JoinColumn(name: 'vehicule_id', nullable: true)]
    #[Groups(['devis:read', 'devis:write'])]
    private ?Vehicule $vehicule = null;

    #[ORM\Column(type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'])]
    #[Groups(['devis:read'])]
    private \DateTimeInterface $dateCreation;

    #[ORM\Column(type: 'date')]
    #[Groups(['devis:read', 'devis:write'])]
    private \DateTimeInterface $dateValidite;

    #[ORM\Column(length: 50, options: ['default' => 'brouillon'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 50)]
    #[Groups(['devis:read', 'devis:write'])]
    private string $statut = 'brouillon';

    #[ORM\Column(nullable: true)]
    #[Groups(['devis:read', 'devis:write'])]
    private ?int $kilometrage = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, options: ['default' => '0.00'])]
    #[Assert\Regex(pattern: '/^\d+(\.\d{1,2})?$/')]
    #[Groups(['devis:read', 'devis:write'])]
    private string $totalMoHt = '0.00';

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, options: ['default' => '0.00'])]
    #[Assert\Regex(pattern: '/^\d+(\.\d{1,2})?$/')]
    #[Groups(['devis:read', 'devis:write'])]
    private string $totalPiecesHt = '0.00';

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, options: ['default' => '0.00'])]
    #[Assert\Regex(pattern: '/^\d+(\.\d{1,2})?$/')]
    #[Groups(['devis:read', 'devis:write'])]
    private string $totalHt = '0.00';

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, options: ['default' => '0.00'])]
    #[Assert\Regex(pattern: '/^\d+(\.\d{1,2})?$/')]
    #[Groups(['devis:read', 'devis:write'])]
    private string $totalTtc = '0.00';

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, options: ['default' => '0.00'])]
    #[Assert\Regex(pattern: '/^\d+(\.\d{1,2})?$/')]
    #[Groups(['devis:read', 'devis:write'])]
    private string $remisePourcentage = '0.00';

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, options: ['default' => '0.00'])]
    #[Assert\Regex(pattern: '/^\d+(\.\d{1,2})?$/')]
    #[Groups(['devis:read', 'devis:write'])]
    private string $remiseMontant = '0.00';

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, options: ['default' => '0.00'])]
    #[Assert\Regex(pattern: '/^\d+(\.\d{1,2})?$/')]
    #[Groups(['devis:read', 'devis:write'])]
    private string $acompteDemande = '0.00';

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['devis:read', 'devis:write'])]
    private ?string $notesClient = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['devis:read', 'devis:write'])]
    private ?string $notesInternes = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['devis:read', 'devis:write'])]
    private ?int $rendezVousId = null;

    // --- RGPD: Snapshot fields (immutable copy at creation) ---
    #[ORM\Column(length: 200, nullable: true)]
    #[Assert\Length(max: 200)]
    #[Groups(['devis:read'])]
    private ?string $snapClientNom = null;

    #[ORM\Column(length: 200, nullable: true)]
    #[Assert\Length(max: 200)]
    #[Groups(['devis:read'])]
    private ?string $snapClientPrenom = null;

    #[ORM\Column(length: 200, nullable: true)]
    #[Assert\Length(max: 200)]
    #[Assert\Email]
    #[Groups(['devis:read'])]
    private ?string $snapClientEmail = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Assert\Length(max: 20)]
    #[Groups(['devis:read'])]
    private ?string $snapClientTelephone = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Assert\Length(max: 20)]
    #[Groups(['devis:read'])]
    private ?string $snapVehiculePlaque = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\Length(max: 100)]
    #[Groups(['devis:read'])]
    private ?string $snapVehiculeMarque = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\Length(max: 100)]
    #[Groups(['devis:read'])]
    private ?string $snapVehiculeModele = null;

    #[ORM\Column(type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'])]
    #[Groups(['devis:read'])]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'])]
    #[Groups(['devis:read'])]
    private \DateTimeInterface $updatedAt;

    #[ORM\OneToMany(targetEntity: LigneDevis::class, mappedBy: 'devis', cascade: ['persist', 'remove'])]
    #[Groups(['devis:read'])]
    private Collection $lignes;

    public function __construct() { $this->dateCreation = new \DateTime(); $this->dateValidite = (new \DateTime())->modify('+30 days'); $this->createdAt = new \DateTime(); $this->updatedAt = new \DateTime(); $this->lignes = new ArrayCollection(); }
    #[ORM\PrePersist] public function prePersist(): void { /* numeroDevis géré par DevisNumeroSubscriber (séquence PG atomique) */ if (!isset($this->dateValidite)) { $this->dateValidite = (new \DateTime())->modify('+30 days'); } $this->snapshotClientData(); }
    public function snapshotClientData(): void {
        if ($this->client && !$this->snapClientNom) {
            $this->snapClientNom = $this->client->getNom();
            $this->snapClientPrenom = $this->client->getPrenom();
            $this->snapClientEmail = $this->client->getEmail();
            $this->snapClientTelephone = $this->client->getTelephone();
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
    public function getNumeroDevis(): string { return $this->numeroDevis; }
    public function setNumeroDevis(string $v): static { $this->numeroDevis = $v; return $this; }
    public function getClient(): Client { return $this->client; }
    public function setClient(Client $v): static { $this->client = $v; return $this; }
    public function getVehicule(): ?Vehicule { return $this->vehicule; }
    public function setVehicule(?Vehicule $v): static { $this->vehicule = $v; return $this; }
    public function getDateCreation(): \DateTimeInterface { return $this->dateCreation; }
    public function getDateValidite(): \DateTimeInterface { return $this->dateValidite; }
    public function setDateValidite(\DateTimeInterface $v): static { $this->dateValidite = $v; return $this; }
    public function getStatut(): string { return $this->statut; }
    public function setStatut(string $v): static { $this->statut = $v; return $this; }
    public function getKilometrage(): ?int { return $this->kilometrage; }
    public function setKilometrage(?int $v): static { $this->kilometrage = $v; return $this; }
    public function getTotalMoHt(): string { return $this->totalMoHt; }
    public function setTotalMoHt(string $v): static { $this->totalMoHt = $v; return $this; }
    public function getTotalPiecesHt(): string { return $this->totalPiecesHt; }
    public function setTotalPiecesHt(string $v): static { $this->totalPiecesHt = $v; return $this; }
    public function getTotalHt(): string { return $this->totalHt; }
    public function setTotalHt(string $v): static { $this->totalHt = $v; return $this; }
    public function getTotalTtc(): string { return $this->totalTtc; }
    public function setTotalTtc(string $v): static { $this->totalTtc = $v; return $this; }
    public function getRemisePourcentage(): string { return $this->remisePourcentage; }
    public function setRemisePourcentage(string $v): static { $this->remisePourcentage = $v; return $this; }
    public function getRemiseMontant(): string { return $this->remiseMontant; }
    public function setRemiseMontant(string $v): static { $this->remiseMontant = $v; return $this; }
    public function getAcompteDemande(): string { return $this->acompteDemande; }
    public function setAcompteDemande(string $v): static { $this->acompteDemande = $v; return $this; }
    public function getNotesClient(): ?string { return $this->notesClient; }
    public function setNotesClient(?string $v): static { $this->notesClient = $v; return $this; }
    public function getNotesInternes(): ?string { return $this->notesInternes; }
    public function setNotesInternes(?string $v): static { $this->notesInternes = $v; return $this; }
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
    public function getSnapVehiculePlaque(): ?string { return $this->snapVehiculePlaque; }
    public function setSnapVehiculePlaque(?string $v): static { $this->snapVehiculePlaque = $v; return $this; }
    public function getSnapVehiculeMarque(): ?string { return $this->snapVehiculeMarque; }
    public function setSnapVehiculeMarque(?string $v): static { $this->snapVehiculeMarque = $v; return $this; }
    public function getSnapVehiculeModele(): ?string { return $this->snapVehiculeModele; }
    public function setSnapVehiculeModele(?string $v): static { $this->snapVehiculeModele = $v; return $this; }
    public function hasSnapshot(): bool { return $this->snapClientNom !== null; }
}
