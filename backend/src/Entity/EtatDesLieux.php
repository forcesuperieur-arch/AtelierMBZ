<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * État des lieux d'entrée du véhicule (check-in au dépôt) — Lot B.
 *
 * Document gelé façon VO : snapshot JSON + hash sha256 + PDF archivé une
 * seule fois à la signature. Un seul état des lieux par RDV (index unique).
 *
 * Volontairement SANS #[ApiResource] : toute l'API passe par
 * EtatDesLieuxController (contrôle tenant + codes d'erreur machine-readable).
 * Jamais de #[Groups] sur signedSnapshot / signedIp / signedUserAgent.
 */
#[ORM\Entity]
#[ORM\Table(name: 'etat_des_lieux')]
#[ORM\UniqueConstraint(name: 'uniq_etat_des_lieux_rdv', columns: ['rendez_vous_id'])]
#[ORM\Index(columns: ['atelier_id'], name: 'idx_etat_des_lieux_atelier')]
class EtatDesLieux
{
    /** Valeurs machine du niveau de carburant (UI : jauge 5 segments). */
    public const NIVEAUX_CARBURANT = ['vide', 'quart', 'moitie', 'trois_quarts', 'plein'];

    /** Libellés français des niveaux de carburant (UI + PDF). */
    public const NIVEAUX_CARBURANT_LABELS = [
        'vide' => 'Vide',
        'quart' => '1/4',
        'moitie' => '1/2',
        'trois_quarts' => '3/4',
        'plein' => 'Plein',
    ];

    #[ORM\Id] #[ORM\GeneratedValue] #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: RendezVous::class)]
    #[ORM\JoinColumn(name: 'rendez_vous_id', nullable: false)]
    private RendezVous $rendezVous;

    /** Multi-tenant : colonne scalaire posée à la création (pattern TenantFilter). */
    #[ORM\Column(nullable: true)]
    private ?int $atelierId = null;

    // ─── Constat d'entrée ───

    #[ORM\Column(nullable: true)]
    private ?int $kilometrage = null;

    /** @see self::NIVEAUX_CARBURANT */
    #[ORM\Column(length: 20, nullable: true)]
    private ?string $niveauCarburant = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $observations = null;

    /** Copie figée du JSON libre d'état véhicule du RDV au moment de la signature. */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $etatVehicule = null;

    // ─── Signature client (canvas → data-URI) ───

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $signatureClient = null;

    /** Staff identifié par sa session au moment de la signature (pas de canvas atelier). */
    #[ORM\Column(length: 200, nullable: true)]
    private ?string $signedByNom = null;

    #[ORM\Column(nullable: true)]
    private ?int $signedByUserId = null;

    // ─── Gel du document (jamais sérialisé API) ───

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $signedSnapshot = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $signedHash = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $signedAt = null;

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $signedIp = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $signedUserAgent = null;

    /** Nom aléatoire 32 hex (bin2hex(random_bytes(16))).pdf — archive hors webroot. */
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $pdfFilename = null;

    // ─── Snapshot RGPD client / véhicule ───

    #[ORM\Column(length: 200, nullable: true)]
    private ?string $snapClientNom = null;

    #[ORM\Column(length: 200, nullable: true)]
    private ?string $snapClientPrenom = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $snapVehiculePlaque = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $snapVehiculeMarque = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $snapVehiculeModele = null;

    #[ORM\Column(type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'])]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function snapshotFromRdv(): void
    {
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

    public function isSigned(): bool
    {
        return $this->signedHash !== null;
    }

    // ─── Getters / Setters ───

    public function getId(): ?int { return $this->id; }
    public function getRendezVous(): RendezVous { return $this->rendezVous; }
    public function setRendezVous(RendezVous $v): static { $this->rendezVous = $v; return $this; }
    public function getAtelierId(): ?int { return $this->atelierId; }
    public function setAtelierId(?int $v): static { $this->atelierId = $v; return $this; }
    public function getKilometrage(): ?int { return $this->kilometrage; }
    public function setKilometrage(?int $v): static { $this->kilometrage = $v; return $this; }
    public function getNiveauCarburant(): ?string { return $this->niveauCarburant; }
    public function setNiveauCarburant(?string $v): static { $this->niveauCarburant = $v; return $this; }
    public function getObservations(): ?string { return $this->observations; }
    public function setObservations(?string $v): static { $this->observations = $v; return $this; }
    public function getEtatVehicule(): ?array { return $this->etatVehicule; }
    public function setEtatVehicule(?array $v): static { $this->etatVehicule = $v; return $this; }
    public function getSignatureClient(): ?string { return $this->signatureClient; }
    public function setSignatureClient(?string $v): static { $this->signatureClient = $v; return $this; }
    public function getSignedByNom(): ?string { return $this->signedByNom; }
    public function setSignedByNom(?string $v): static { $this->signedByNom = $v; return $this; }
    public function getSignedByUserId(): ?int { return $this->signedByUserId; }
    public function setSignedByUserId(?int $v): static { $this->signedByUserId = $v; return $this; }
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
    public function getPdfFilename(): ?string { return $this->pdfFilename; }
    public function setPdfFilename(?string $v): static { $this->pdfFilename = $v; return $this; }
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
    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
    public function getUpdatedAt(): ?\DateTimeInterface { return $this->updatedAt; }
    public function setUpdatedAt(?\DateTimeInterface $v): static { $this->updatedAt = $v; return $this; }
}
