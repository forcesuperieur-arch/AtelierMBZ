<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'analytics_daily_snapshots')]
#[ORM\UniqueConstraint(name: 'uq_snapshot_atelier_date', columns: ['atelier_id', 'snapshot_date'])]
#[ORM\Index(columns: ['atelier_id', 'snapshot_date'], name: 'idx_snapshot_date')]
class AnalyticsDailySnapshot
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private int $atelierId;

    #[ORM\Column(type: 'date')]
    private ?\DateTimeInterface $snapshotDate = null;

    // RDV counts
    #[ORM\Column(options: ['default' => 0])]
    private int $nbRdvTotal = 0;

    #[ORM\Column(options: ['default' => 0])]
    private int $nbRdvConfirme = 0;

    #[ORM\Column(options: ['default' => 0])]
    private int $nbRdvEnCours = 0;

    #[ORM\Column(options: ['default' => 0])]
    private int $nbRdvTermine = 0;

    #[ORM\Column(options: ['default' => 0])]
    private int $nbRdvAnnule = 0;

    // OR
    #[ORM\Column(options: ['default' => 0])]
    private int $nbOrOuverts = 0;

    // Revenue
    #[ORM\Column(type: 'decimal', precision: 12, scale: 2, options: ['default' => '0.00'])]
    private string $caDuJourHt = '0.00';

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2, options: ['default' => '0.00'])]
    private string $caMoHt = '0.00';

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2, options: ['default' => '0.00'])]
    private string $caPiecesHt = '0.00';

    #[ORM\Column(options: ['default' => 0])]
    private int $nbFactures = 0;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, options: ['default' => '0.00'])]
    private string $panierMoyen = '0.00';

    // Load
    #[ORM\Column(options: ['default' => 0])]
    private int $chargePlanifieeMinutes = 0;

    #[ORM\Column(options: ['default' => 0])]
    private int $chargeEffectiveMinutes = 0;

    #[ORM\Column(options: ['default' => 0])]
    private int $occupationPontsPct = 0;

    // Clients
    #[ORM\Column(options: ['default' => 0])]
    private int $nbClientsNouveaux = 0;

    #[ORM\Column(options: ['default' => 0])]
    private int $nbClientsRecurrents = 0;

    // Devis conversion
    #[ORM\Column(options: ['default' => 0])]
    private int $nbDevis = 0;

    #[ORM\Column(options: ['default' => 0])]
    private int $nbDevisAcceptes = 0;

    // Performance
    #[ORM\Column(options: ['default' => 0])]
    private int $nbRetardsDepassement = 0;

    #[ORM\Column(options: ['default' => 0])]
    private int $nbAttenteRestitution = 0;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $syncedAt = null;

    public function __construct()
    {
        $this->syncedAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }
    public function getAtelierId(): int { return $this->atelierId; }
    public function setAtelierId(int $v): static { $this->atelierId = $v; return $this; }
    public function getSnapshotDate(): ?\DateTimeInterface { return $this->snapshotDate; }
    public function setSnapshotDate(?\DateTimeInterface $v): static { $this->snapshotDate = $v; return $this; }
    public function getNbRdvTotal(): int { return $this->nbRdvTotal; }
    public function setNbRdvTotal(int $v): static { $this->nbRdvTotal = $v; return $this; }
    public function getNbRdvConfirme(): int { return $this->nbRdvConfirme; }
    public function setNbRdvConfirme(int $v): static { $this->nbRdvConfirme = $v; return $this; }
    public function getNbRdvEnCours(): int { return $this->nbRdvEnCours; }
    public function setNbRdvEnCours(int $v): static { $this->nbRdvEnCours = $v; return $this; }
    public function getNbRdvTermine(): int { return $this->nbRdvTermine; }
    public function setNbRdvTermine(int $v): static { $this->nbRdvTermine = $v; return $this; }
    public function getNbRdvAnnule(): int { return $this->nbRdvAnnule; }
    public function setNbRdvAnnule(int $v): static { $this->nbRdvAnnule = $v; return $this; }
    public function getNbOrOuverts(): int { return $this->nbOrOuverts; }
    public function setNbOrOuverts(int $v): static { $this->nbOrOuverts = $v; return $this; }
    public function getCaDuJourHt(): string { return $this->caDuJourHt; }
    public function setCaDuJourHt(string $v): static { $this->caDuJourHt = $v; return $this; }
    public function getCaMoHt(): string { return $this->caMoHt; }
    public function setCaMoHt(string $v): static { $this->caMoHt = $v; return $this; }
    public function getCaPiecesHt(): string { return $this->caPiecesHt; }
    public function setCaPiecesHt(string $v): static { $this->caPiecesHt = $v; return $this; }
    public function getNbFactures(): int { return $this->nbFactures; }
    public function setNbFactures(int $v): static { $this->nbFactures = $v; return $this; }
    public function getPanierMoyen(): string { return $this->panierMoyen; }
    public function setPanierMoyen(string $v): static { $this->panierMoyen = $v; return $this; }
    public function getChargePlanifieeMinutes(): int { return $this->chargePlanifieeMinutes; }
    public function setChargePlanifieeMinutes(int $v): static { $this->chargePlanifieeMinutes = $v; return $this; }
    public function getChargeEffectiveMinutes(): int { return $this->chargeEffectiveMinutes; }
    public function setChargeEffectiveMinutes(int $v): static { $this->chargeEffectiveMinutes = $v; return $this; }
    public function getOccupationPontsPct(): int { return $this->occupationPontsPct; }
    public function setOccupationPontsPct(int $v): static { $this->occupationPontsPct = $v; return $this; }
    public function getNbClientsNouveaux(): int { return $this->nbClientsNouveaux; }
    public function setNbClientsNouveaux(int $v): static { $this->nbClientsNouveaux = $v; return $this; }
    public function getNbClientsRecurrents(): int { return $this->nbClientsRecurrents; }
    public function setNbClientsRecurrents(int $v): static { $this->nbClientsRecurrents = $v; return $this; }
    public function getNbDevis(): int { return $this->nbDevis; }
    public function setNbDevis(int $v): static { $this->nbDevis = $v; return $this; }
    public function getNbDevisAcceptes(): int { return $this->nbDevisAcceptes; }
    public function setNbDevisAcceptes(int $v): static { $this->nbDevisAcceptes = $v; return $this; }
    public function getNbRetardsDepassement(): int { return $this->nbRetardsDepassement; }
    public function setNbRetardsDepassement(int $v): static { $this->nbRetardsDepassement = $v; return $this; }
    public function getNbAttenteRestitution(): int { return $this->nbAttenteRestitution; }
    public function setNbAttenteRestitution(int $v): static { $this->nbAttenteRestitution = $v; return $this; }
    public function getSyncedAt(): ?\DateTimeInterface { return $this->syncedAt; }
    public function setSyncedAt(?\DateTimeInterface $v): static { $this->syncedAt = $v; return $this; }
}
