<?php
namespace App\Entity;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Metadata\ApiFilter;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity] #[ORM\Table(name: 'ordres_reparation')]
#[ApiResource(
    shortName: 'ordres-reparation',
    normalizationContext: ['groups' => ['ordre:read']],
    denormalizationContext: ['groups' => ['ordre:write']],
    operations: [
        new GetCollection(uriTemplate: '/ordres-reparation'),
        new Get(uriTemplate: '/ordres-reparation/{id}'),
        new Post(uriTemplate: '/ordres-reparation'),
        new Put(uriTemplate: '/ordres-reparation/{id}'),
        new Delete(uriTemplate: '/ordres-reparation/{id}'),
    ],
)]
#[ApiFilter(SearchFilter::class, properties: ['statut' => 'exact'])]
#[ApiFilter(OrderFilter::class, properties: ['createdAt'])]
class OrdreReparation
{
    #[ORM\Id] #[ORM\GeneratedValue] #[ORM\Column]
    #[Groups(['ordre:read', 'rdv:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: RendezVous::class, inversedBy: 'ordresReparation')] #[ORM\JoinColumn(name: 'rendez_vous_id', nullable: false)]
    #[Groups(['ordre:read', 'ordre:write'])]
    private RendezVous $rendezVous;

    #[ORM\Column(length: 50)]
    #[Groups(['ordre:read', 'ordre:write', 'rdv:read'])]
    private string $numeroOr;

    #[ORM\Column(length: 50, options: ['default' => 'initial'])]
    #[Groups(['ordre:read', 'ordre:write', 'rdv:read'])]
    private string $typeOr = 'initial';

    #[ORM\Column(nullable: true)]
    #[Groups(['ordre:read', 'ordre:write'])]
    private ?int $kilometrage = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['ordre:read', 'ordre:write'])]
    private ?string $etatVehicule = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['ordre:read', 'ordre:write'])]
    private ?string $mechanicNotes = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    #[Groups(['ordre:read'])]
    private ?\DateTimeInterface $mechanicNotesUpdatedAt = null;

    #[ORM\Column(type: 'text', options: ['default' => '{}'])]
    #[Groups(['ordre:read', 'ordre:write'])]
    private string $mechanicCheckup = '{}';

    #[ORM\Column(type: 'datetime', nullable: true)]
    #[Groups(['ordre:read'])]
    private ?\DateTimeInterface $mechanicCheckupUpdatedAt = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['ordre:read', 'ordre:write'])]
    private ?string $travaux = null;

    #[ORM\ManyToOne(targetEntity: DemandeTravauxSupp::class)] #[ORM\JoinColumn(name: 'demande_travaux_supp_id', nullable: true)]
    #[Groups(['ordre:read', 'ordre:write'])]
    private ?DemandeTravauxSupp $demandeTravauxSupp = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['ordre:read'])]
    private ?string $signatureClient = null;

    #[ORM\Column(length: 50, options: ['default' => 'brouillon'])]
    #[Groups(['ordre:read', 'ordre:write', 'rdv:read'])]
    private string $statut = 'brouillon';

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $signedSnapshot = null;

    #[ORM\Column(length: 64, nullable: true)]
    #[Groups(['ordre:read'])]
    private ?string $signedHash = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    #[Groups(['ordre:read'])]
    private ?\DateTimeInterface $signedAt = null;

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $signedIp = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $signedUserAgent = null;

    #[ORM\ManyToOne(targetEntity: OrdreReparation::class)]
    #[ORM\JoinColumn(name: 'rectified_from_id', nullable: true)]
    #[Groups(['ordre:read'])]
    private ?OrdreReparation $rectifiedFrom = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Groups(['ordre:read'])]
    private ?string $motifRectification = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['ordre:read'])]
    private ?int $rectifiedBy = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    #[Groups(['ordre:read'])]
    private ?\DateTimeInterface $rectifiedAt = null;

    // --- RGPD: Snapshot fields ---
    #[ORM\Column(length: 200, nullable: true)]
    #[Groups(['ordre:read'])]
    private ?string $snapClientNom = null;

    #[ORM\Column(length: 200, nullable: true)]
    #[Groups(['ordre:read'])]
    private ?string $snapClientPrenom = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Groups(['ordre:read'])]
    private ?string $snapVehiculePlaque = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Groups(['ordre:read'])]
    private ?string $snapVehiculeMarque = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Groups(['ordre:read'])]
    private ?string $snapVehiculeModele = null;

    #[ORM\Column(type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'])]
    #[Groups(['ordre:read'])]
    private \DateTimeInterface $createdAt;

    public function __construct() { $this->createdAt = new \DateTime(); }

    public function snapshotFromRdv(): void {
        $rdv = $this->rendezVous;
        if ($rdv->getClient() && !$this->snapClientNom) {
            $this->snapClientNom = $rdv->getClient()->getNom();
            $this->snapClientPrenom = $rdv->getClient()->getPrenom();
        }
        if ($rdv->getVehicule() && !$this->snapVehiculePlaque) {
            $this->snapVehiculePlaque = $rdv->getVehicule()->getPlaque();
            $this->snapVehiculeMarque = $rdv->getVehicule()->getMarque();
            $this->snapVehiculeModele = $rdv->getVehicule()->getModele();
        }
    }

    public function getId(): ?int { return $this->id; }
    public function getRendezVous(): RendezVous { return $this->rendezVous; }
    public function setRendezVous(RendezVous $v): static { $this->rendezVous = $v; return $this; }
    public function getNumeroOr(): string { return $this->numeroOr; }
    public function setNumeroOr(string $v): static { $this->numeroOr = $v; return $this; }
    public function getTypeOr(): string { return $this->typeOr; }
    public function setTypeOr(string $v): static { $this->typeOr = $v; return $this; }
    public function getKilometrage(): ?int { return $this->kilometrage; }
    public function setKilometrage(?int $v): static { $this->kilometrage = $v; return $this; }
    public function getEtatVehicule(): ?string { return $this->etatVehicule; }
    public function setEtatVehicule(?string $v): static { $this->etatVehicule = $v; return $this; }
    public function getMechanicNotes(): ?string { return $this->mechanicNotes; }
    public function setMechanicNotes(?string $v): static {
        $this->mechanicNotes = $v;
        $this->mechanicNotesUpdatedAt = new \DateTime();
        return $this;
    }
    public function getMechanicNotesUpdatedAt(): ?\DateTimeInterface { return $this->mechanicNotesUpdatedAt; }
    public function getMechanicCheckup(): array { return json_decode($this->mechanicCheckup, true) ?: []; }
    public function setMechanicCheckup(array $v): static {
        $this->mechanicCheckup = $v === []
            ? '{}'
            : (json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}');
        $this->mechanicCheckupUpdatedAt = new \DateTime();
        return $this;
    }
    public function getMechanicCheckupUpdatedAt(): ?\DateTimeInterface { return $this->mechanicCheckupUpdatedAt; }
    public function getTravaux(): ?string { return $this->travaux; }
    public function setTravaux(?string $v): static { $this->travaux = $v; return $this; }
    public function getDemandeTravauxSupp(): ?DemandeTravauxSupp { return $this->demandeTravauxSupp; }
    public function setDemandeTravauxSupp(?DemandeTravauxSupp $v): static { $this->demandeTravauxSupp = $v; return $this; }
    public function getSignatureClient(): ?string { return $this->signatureClient; }
    public function setSignatureClient(?string $v): static { $this->signatureClient = $v; return $this; }
    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }

    // --- Statut & Signature fields ---
    public function getStatut(): string { return $this->statut; }
    public function setStatut(string $v): static { $this->statut = $v; return $this; }
    public function getSignedSnapshot(): ?array { return $this->signedSnapshot; }
    public function setSignedSnapshot(?array $v): static { $this->signedSnapshot = $v; return $this; }
    public function getSignedHash(): ?string { return $this->signedHash; }
    public function setSignedHash(?string $v): static { $this->signedHash = $v; return $this; }
    public function getSignedAt(): ?\DateTimeInterface { return $this->signedAt; }
    public function setSignedAt(?\DateTimeInterface $v): static { $this->signedAt = $v; return $this; }
    public function getSignedIp(): ?string { return $this->signedIp; }
    public function setSignedIp(?string $v): static { $this->signedIp = $v; return $this; }
    public function getSignedUserAgent(): ?string { return $this->signedUserAgent; }
    public function setSignedUserAgent(?string $v): static { $this->signedUserAgent = $v; return $this; }
    public function getRectifiedFrom(): ?OrdreReparation { return $this->rectifiedFrom; }
    public function setRectifiedFrom(?OrdreReparation $v): static { $this->rectifiedFrom = $v; return $this; }
    public function getMotifRectification(): ?string { return $this->motifRectification; }
    public function setMotifRectification(?string $v): static { $this->motifRectification = $v; return $this; }
    public function getRectifiedBy(): ?int { return $this->rectifiedBy; }
    public function setRectifiedBy(?int $v): static { $this->rectifiedBy = $v; return $this; }
    public function getRectifiedAt(): ?\DateTimeInterface { return $this->rectifiedAt; }
    public function setRectifiedAt(?\DateTimeInterface $v): static { $this->rectifiedAt = $v; return $this; }
    public function isSigned(): bool { return in_array($this->statut, ['signe', 'execute', 'termine'], true); }
    public function isFrozen(): bool { return in_array($this->statut, ['signe', 'execute', 'termine', 'rectifie'], true); }

    // --- RGPD snapshot getters/setters ---
    public function getSnapClientNom(): ?string { return $this->snapClientNom; }
    public function setSnapClientNom(?string $v): static { $this->snapClientNom = $v; return $this; }
    public function getSnapClientPrenom(): ?string { return $this->snapClientPrenom; }
    public function setSnapClientPrenom(?string $v): static { $this->snapClientPrenom = $v; return $this; }
    public function getSnapVehiculePlaque(): ?string { return $this->snapVehiculePlaque; }
    public function setSnapVehiculePlaque(?string $v): static { $this->snapVehiculePlaque = $v; return $this; }
    public function getSnapVehiculeMarque(): ?string { return $this->snapVehiculeMarque; }
    public function setSnapVehiculeMarque(?string $v): static { $this->snapVehiculeMarque = $v; return $this; }
    public function getSnapVehiculeModele(): ?string { return $this->snapVehiculeModele; }
    public function setSnapVehiculeModele(?string $v): static { $this->snapVehiculeModele = $v; return $this; }
    public function hasSnapshot(): bool { return $this->snapClientNom !== null; }
}
