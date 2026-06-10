<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'analytics_client_facts')]
#[ORM\UniqueConstraint(name: 'uq_client_fact', columns: ['atelier_id', 'client_id', 'periode_annee', 'periode_mois'])]
#[ORM\Index(columns: ['atelier_id', 'periode_annee', 'periode_mois'], name: 'idx_client_fact_periode')]
#[ORM\Index(columns: ['atelier_id', 'client_segment'], name: 'idx_client_fact_segment')]
class AnalyticsClientFact
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private int $atelierId;

    #[ORM\Column]
    private int $clientId;

    #[ORM\Column]
    private int $periodeAnnee;

    #[ORM\Column]
    private int $periodeMois;

    #[ORM\Column(options: ['default' => 0])]
    private int $nbRdv = 0;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2, options: ['default' => '0.00'])]
    private string $caTotalHt = '0.00';

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, options: ['default' => '0.00'])]
    private string $caMoyenParRdv = '0.00';

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $dernierRdvDate = null;

    #[ORM\Column(nullable: true)]
    private ?int $joursDepuisDernierRdv = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $clientSegment = null;

    #[ORM\Column(options: ['default' => 0])]
    private int $nbVehicules = 0;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, options: ['default' => '0.00'])]
    private string $panierMoyen = '0.00';

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $syncedAt = null;

    public function __construct()
    {
        $this->syncedAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }
    public function getAtelierId(): int { return $this->atelierId; }
    public function setAtelierId(int $v): static { $this->atelierId = $v; return $this; }
    public function getClientId(): int { return $this->clientId; }
    public function setClientId(int $v): static { $this->clientId = $v; return $this; }
    public function getPeriodeAnnee(): int { return $this->periodeAnnee; }
    public function setPeriodeAnnee(int $v): static { $this->periodeAnnee = $v; return $this; }
    public function getPeriodeMois(): int { return $this->periodeMois; }
    public function setPeriodeMois(int $v): static { $this->periodeMois = $v; return $this; }
    public function getNbRdv(): int { return $this->nbRdv; }
    public function setNbRdv(int $v): static { $this->nbRdv = $v; return $this; }
    public function getCaTotalHt(): string { return $this->caTotalHt; }
    public function setCaTotalHt(string $v): static { $this->caTotalHt = $v; return $this; }
    public function getCaMoyenParRdv(): string { return $this->caMoyenParRdv; }
    public function setCaMoyenParRdv(string $v): static { $this->caMoyenParRdv = $v; return $this; }
    public function getDernierRdvDate(): ?\DateTimeInterface { return $this->dernierRdvDate; }
    public function setDernierRdvDate(?\DateTimeInterface $v): static { $this->dernierRdvDate = $v; return $this; }
    public function getJoursDepuisDernierRdv(): ?int { return $this->joursDepuisDernierRdv; }
    public function setJoursDepuisDernierRdv(?int $v): static { $this->joursDepuisDernierRdv = $v; return $this; }
    public function getClientSegment(): ?string { return $this->clientSegment; }
    public function setClientSegment(?string $v): static { $this->clientSegment = $v; return $this; }
    public function getNbVehicules(): int { return $this->nbVehicules; }
    public function setNbVehicules(int $v): static { $this->nbVehicules = $v; return $this; }
    public function getPanierMoyen(): string { return $this->panierMoyen; }
    public function setPanierMoyen(string $v): static { $this->panierMoyen = $v; return $this; }
    public function getSyncedAt(): ?\DateTimeInterface { return $this->syncedAt; }
    public function setSyncedAt(?\DateTimeInterface $v): static { $this->syncedAt = $v; return $this; }
}
