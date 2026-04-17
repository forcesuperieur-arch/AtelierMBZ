<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'rapport_intervention')]
#[ORM\Index(columns: ['rendez_vous_id'], name: 'idx_rapport_rdv')]
class RapportIntervention
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(nullable: true)]
    private ?int $atelierId = null;

    #[ORM\ManyToOne(targetEntity: RendezVous::class)]
    #[ORM\JoinColumn(name: 'rendez_vous_id', nullable: false)]
    private RendezVous $rendezVous;

    #[ORM\Column(length: 50)]
    private string $statut = 'brouillon'; // brouillon, en_validation, signe, rectifie

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $travauxRealises = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $alertes = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $recommandations = null;

    #[ORM\Column(nullable: true)]
    private ?int $prochaineRevisionKm = null;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $prochaineRevisionDate = null;

    #[ORM\Column(nullable: true)]
    private ?int $kilometrageRestitution = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $garantie = null;

    // Signatures
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $signatureMecanicien = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $signeMecanicienAt = null;

    #[ORM\Column(nullable: true)]
    private ?int $signeMecanicienId = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $signatureClient = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $signeClientAt = null;

    // Snapshot
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $signedSnapshot = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $signedHash = null;

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $signedIp = null;

    // Rectification
    #[ORM\ManyToOne(targetEntity: self::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?self $rectifiedFrom = null;

    #[ORM\Column(length: 200, nullable: true)]
    private ?string $motifRectification = null;

    #[ORM\Column(nullable: true)]
    private ?int $rectifiedBy = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $rectifiedAt = null;

    // Email tracking
    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $emailSentAt = null;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    // Essai routier
    #[ORM\OneToOne(targetEntity: EssaiRoutier::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?EssaiRoutier $essaiRoutier = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    // ─── Getters / Setters ───

    public function getId(): ?int { return $this->id; }
    public function getAtelierId(): ?int { return $this->atelierId; }
    public function setAtelierId(?int $v): static { $this->atelierId = $v; return $this; }
    public function getRendezVous(): RendezVous { return $this->rendezVous; }
    public function setRendezVous(RendezVous $v): static { $this->rendezVous = $v; return $this; }
    public function getStatut(): string { return $this->statut; }
    public function setStatut(string $v): static { $this->statut = $v; return $this; }
    public function getTravauxRealises(): ?string { return $this->travauxRealises; }
    public function setTravauxRealises(?string $v): static { $this->travauxRealises = $v; return $this; }
    public function getAlertes(): ?array { return $this->alertes; }
    public function setAlertes(?array $v): static { $this->alertes = $v; return $this; }
    public function getRecommandations(): ?string { return $this->recommandations; }
    public function setRecommandations(?string $v): static { $this->recommandations = $v; return $this; }
    public function getProchaineRevisionKm(): ?int { return $this->prochaineRevisionKm; }
    public function setProchaineRevisionKm(?int $v): static { $this->prochaineRevisionKm = $v; return $this; }
    public function getProchaineRevisionDate(): ?\DateTimeInterface { return $this->prochaineRevisionDate; }
    public function setProchaineRevisionDate(?\DateTimeInterface $v): static { $this->prochaineRevisionDate = $v; return $this; }
    public function getKilometrageRestitution(): ?int { return $this->kilometrageRestitution; }
    public function setKilometrageRestitution(?int $v): static { $this->kilometrageRestitution = $v; return $this; }
    public function getGarantie(): ?string { return $this->garantie; }
    public function setGarantie(?string $v): static { $this->garantie = $v; return $this; }
    public function getSignatureMecanicien(): ?string { return $this->signatureMecanicien; }
    public function setSignatureMecanicien(?string $v): static { $this->signatureMecanicien = $v; return $this; }
    public function getSigneMecanicienAt(): ?\DateTimeInterface { return $this->signeMecanicienAt; }
    public function setSigneMecanicienAt(?\DateTimeInterface $v): static { $this->signeMecanicienAt = $v; return $this; }
    public function getSigneMecanicienId(): ?int { return $this->signeMecanicienId; }
    public function setSigneMecanicienId(?int $v): static { $this->signeMecanicienId = $v; return $this; }
    public function getSignatureClient(): ?string { return $this->signatureClient; }
    public function setSignatureClient(?string $v): static { $this->signatureClient = $v; return $this; }
    public function getSigneClientAt(): ?\DateTimeInterface { return $this->signeClientAt; }
    public function setSigneClientAt(?\DateTimeInterface $v): static { $this->signeClientAt = $v; return $this; }
    public function getSignedSnapshot(): ?array { return $this->signedSnapshot; }
    public function setSignedSnapshot(?array $v): static { $this->signedSnapshot = $v; return $this; }
    public function getSignedHash(): ?string { return $this->signedHash; }
    public function setSignedHash(?string $v): static { $this->signedHash = $v; return $this; }
    public function getSignedIp(): ?string { return $this->signedIp; }
    public function setSignedIp(?string $v): static { $this->signedIp = $v; return $this; }
    public function getRectifiedFrom(): ?self { return $this->rectifiedFrom; }
    public function setRectifiedFrom(?self $v): static { $this->rectifiedFrom = $v; return $this; }
    public function getMotifRectification(): ?string { return $this->motifRectification; }
    public function setMotifRectification(?string $v): static { $this->motifRectification = $v; return $this; }
    public function getRectifiedBy(): ?int { return $this->rectifiedBy; }
    public function setRectifiedBy(?int $v): static { $this->rectifiedBy = $v; return $this; }
    public function getRectifiedAt(): ?\DateTimeInterface { return $this->rectifiedAt; }
    public function setRectifiedAt(?\DateTimeInterface $v): static { $this->rectifiedAt = $v; return $this; }
    public function getEmailSentAt(): ?\DateTimeInterface { return $this->emailSentAt; }
    public function setEmailSentAt(?\DateTimeInterface $v): static { $this->emailSentAt = $v; return $this; }
    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
    public function getUpdatedAt(): ?\DateTimeInterface { return $this->updatedAt; }
    public function setUpdatedAt(?\DateTimeInterface $v): static { $this->updatedAt = $v; return $this; }
    public function getEssaiRoutier(): ?EssaiRoutier { return $this->essaiRoutier; }
    public function setEssaiRoutier(?EssaiRoutier $v): static { $this->essaiRoutier = $v; return $this; }

    // ─── Helpers ───

    public function isSignedByBoth(): bool
    {
        return $this->signatureMecanicien !== null && $this->signatureClient !== null;
    }

    public function isSigned(): bool
    {
        return in_array($this->statut, ['signe', 'rectifie'], true);
    }
}
