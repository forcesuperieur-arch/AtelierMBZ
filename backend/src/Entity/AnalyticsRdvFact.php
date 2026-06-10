<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'analytics_rdv_facts')]
#[ORM\Index(columns: ['atelier_id', 'date_rdv'], name: 'idx_analytics_rdv_date')]
#[ORM\Index(columns: ['atelier_id', 'statut_rdv'], name: 'idx_analytics_rdv_statut')]
#[ORM\Index(columns: ['atelier_id', 'mecanicien_id'], name: 'idx_analytics_rdv_meca')]
#[ORM\Index(columns: ['atelier_id', 'client_id'], name: 'idx_analytics_rdv_client')]
class AnalyticsRdvFact
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private int $atelierId;

    #[ORM\Column]
    private int $rdvId;

    #[ORM\Column(type: 'date')]
    private ?\DateTimeInterface $dateRdv = null;

    #[ORM\Column(type: 'time', nullable: true)]
    private ?\DateTimeInterface $heureRdv = null;

    #[ORM\Column(length: 200, nullable: true)]
    private ?string $typeIntervention = null;

    #[ORM\Column(length: 50)]
    private string $statutRdv = 'en_attente';

    #[ORM\Column(nullable: true)]
    private ?int $clientId = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $clientSegment = null;

    #[ORM\Column(nullable: true)]
    private ?int $vehiculeId = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $vehiculeMarque = null;

    #[ORM\Column(nullable: true)]
    private ?int $vehiculeAgeAnnees = null;

    #[ORM\Column(nullable: true)]
    private ?int $mecanicienId = null;

    #[ORM\Column(length: 200, nullable: true)]
    private ?string $mecanicienNom = null;

    #[ORM\Column(nullable: true)]
    private ?int $pontId = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $pontNom = null;

    #[ORM\Column(nullable: true)]
    private ?int $tempsEstime = null;

    #[ORM\Column(nullable: true)]
    private ?int $tempsEffectif = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $prixEstime = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $prixFinal = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $caHt = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $caMoHt = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $caPiecesHt = null;

    #[ORM\Column(nullable: true)]
    private ?int $delaiReceptionDebut = null;

    #[ORM\Column(nullable: true)]
    private ?int $delaiFinRestitution = null;

    #[ORM\Column(nullable: true)]
    private ?int $delaiTotalCycle = null;

    #[ORM\Column]
    private bool $isDevisAccepte = false;

    #[ORM\Column]
    private bool $hasTravauxComplementaires = false;

    #[ORM\Column]
    private bool $isFacture = false;

    #[ORM\Column]
    private bool $isPaye = false;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $syncedAt = null;

    public function __construct()
    {
        $this->syncedAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }
    public function getAtelierId(): int { return $this->atelierId; }
    public function setAtelierId(int $v): static { $this->atelierId = $v; return $this; }
    public function getRdvId(): int { return $this->rdvId; }
    public function setRdvId(int $v): static { $this->rdvId = $v; return $this; }
    public function getDateRdv(): ?\DateTimeInterface { return $this->dateRdv; }
    public function setDateRdv(?\DateTimeInterface $v): static { $this->dateRdv = $v; return $this; }
    public function getHeureRdv(): ?\DateTimeInterface { return $this->heureRdv; }
    public function setHeureRdv(?\DateTimeInterface $v): static { $this->heureRdv = $v; return $this; }
    public function getTypeIntervention(): ?string { return $this->typeIntervention; }
    public function setTypeIntervention(?string $v): static { $this->typeIntervention = $v; return $this; }
    public function getStatutRdv(): string { return $this->statutRdv; }
    public function setStatutRdv(string $v): static { $this->statutRdv = $v; return $this; }
    public function getClientId(): ?int { return $this->clientId; }
    public function setClientId(?int $v): static { $this->clientId = $v; return $this; }
    public function getClientSegment(): ?string { return $this->clientSegment; }
    public function setClientSegment(?string $v): static { $this->clientSegment = $v; return $this; }
    public function getVehiculeId(): ?int { return $this->vehiculeId; }
    public function setVehiculeId(?int $v): static { $this->vehiculeId = $v; return $this; }
    public function getVehiculeMarque(): ?string { return $this->vehiculeMarque; }
    public function setVehiculeMarque(?string $v): static { $this->vehiculeMarque = $v; return $this; }
    public function getVehiculeAgeAnnees(): ?int { return $this->vehiculeAgeAnnees; }
    public function setVehiculeAgeAnnees(?int $v): static { $this->vehiculeAgeAnnees = $v; return $this; }
    public function getMecanicienId(): ?int { return $this->mecanicienId; }
    public function setMecanicienId(?int $v): static { $this->mecanicienId = $v; return $this; }
    public function getMecanicienNom(): ?string { return $this->mecanicienNom; }
    public function setMecanicienNom(?string $v): static { $this->mecanicienNom = $v; return $this; }
    public function getPontId(): ?int { return $this->pontId; }
    public function setPontId(?int $v): static { $this->pontId = $v; return $this; }
    public function getPontNom(): ?string { return $this->pontNom; }
    public function setPontNom(?string $v): static { $this->pontNom = $v; return $this; }
    public function getTempsEstime(): ?int { return $this->tempsEstime; }
    public function setTempsEstime(?int $v): static { $this->tempsEstime = $v; return $this; }
    public function getTempsEffectif(): ?int { return $this->tempsEffectif; }
    public function setTempsEffectif(?int $v): static { $this->tempsEffectif = $v; return $this; }
    public function getPrixEstime(): ?string { return $this->prixEstime; }
    public function setPrixEstime(?string $v): static { $this->prixEstime = $v; return $this; }
    public function getPrixFinal(): ?string { return $this->prixFinal; }
    public function setPrixFinal(?string $v): static { $this->prixFinal = $v; return $this; }
    public function getCaHt(): ?string { return $this->caHt; }
    public function setCaHt(?string $v): static { $this->caHt = $v; return $this; }
    public function getCaMoHt(): ?string { return $this->caMoHt; }
    public function setCaMoHt(?string $v): static { $this->caMoHt = $v; return $this; }
    public function getCaPiecesHt(): ?string { return $this->caPiecesHt; }
    public function setCaPiecesHt(?string $v): static { $this->caPiecesHt = $v; return $this; }
    public function getDelaiReceptionDebut(): ?int { return $this->delaiReceptionDebut; }
    public function setDelaiReceptionDebut(?int $v): static { $this->delaiReceptionDebut = $v; return $this; }
    public function getDelaiFinRestitution(): ?int { return $this->delaiFinRestitution; }
    public function setDelaiFinRestitution(?int $v): static { $this->delaiFinRestitution = $v; return $this; }
    public function getDelaiTotalCycle(): ?int { return $this->delaiTotalCycle; }
    public function setDelaiTotalCycle(?int $v): static { $this->delaiTotalCycle = $v; return $this; }
    public function isDevisAccepte(): bool { return $this->isDevisAccepte; }
    public function setIsDevisAccepte(bool $v): static { $this->isDevisAccepte = $v; return $this; }
    public function hasTravauxComplementaires(): bool { return $this->hasTravauxComplementaires; }
    public function setHasTravauxComplementaires(bool $v): static { $this->hasTravauxComplementaires = $v; return $this; }
    public function isFacture(): bool { return $this->isFacture; }
    public function setIsFacture(bool $v): static { $this->isFacture = $v; return $this; }
    public function isPaye(): bool { return $this->isPaye; }
    public function setIsPaye(bool $v): static { $this->isPaye = $v; return $this; }
    public function getSyncedAt(): ?\DateTimeInterface { return $this->syncedAt; }
    public function setSyncedAt(?\DateTimeInterface $v): static { $this->syncedAt = $v; return $this; }
}
