<?php
namespace App\Entity;
use ApiPlatform\Metadata\ApiResource;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity] #[ORM\Table(name: 'rapports_technicien')] #[ApiResource]
class RapportTechnicien
{
    #[ORM\Id] #[ORM\GeneratedValue] #[ORM\Column] private ?int $id = null;
    #[ORM\OneToOne(targetEntity: RendezVous::class, inversedBy: 'rapportTechnicien')] #[ORM\JoinColumn(name: 'rendez_vous_id', nullable: false, unique: true)] private RendezVous $rendezVous;
    #[ORM\Column(type: 'text', options: ['default' => '{}'])] private string $pointsControle = '{}';
    #[ORM\Column(type: 'text', nullable: true)] private ?string $alertes = null;
    #[ORM\Column(type: 'text', nullable: true)] private ?string $recommandations = null;
    #[ORM\Column(type: 'text', nullable: true)] private ?string $travauxRealises = null;
    #[ORM\Column(type: 'text', options: ['default' => '[]'])] private string $piecesUtilisees = '[]';
    #[ORM\Column(length: 50, options: ['default' => 'en_cours'])] private string $statut = 'en_cours';
    #[ORM\Column(type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'])] private \DateTimeInterface $dateDebut;
    #[ORM\Column(type: 'datetime', nullable: true)] private ?\DateTimeInterface $dateFin = null;

    public function __construct() { $this->dateDebut = new \DateTime(); }

    public function getId(): ?int { return $this->id; }
    public function getRendezVous(): RendezVous { return $this->rendezVous; }
    public function setRendezVous(RendezVous $v): static { $this->rendezVous = $v; return $this; }
    public function getPointsControle(): string { return $this->pointsControle; }
    public function setPointsControle(string $v): static { $this->pointsControle = $v; return $this; }
    public function getAlertes(): ?string { return $this->alertes; }
    public function setAlertes(?string $v): static { $this->alertes = $v; return $this; }
    public function getRecommandations(): ?string { return $this->recommandations; }
    public function setRecommandations(?string $v): static { $this->recommandations = $v; return $this; }
    public function getTravauxRealises(): ?string { return $this->travauxRealises; }
    public function setTravauxRealises(?string $v): static { $this->travauxRealises = $v; return $this; }
    public function getPiecesUtilisees(): string { return $this->piecesUtilisees; }
    public function setPiecesUtilisees(string $v): static { $this->piecesUtilisees = $v; return $this; }
    public function getStatut(): string { return $this->statut; }
    public function setStatut(string $v): static { $this->statut = $v; return $this; }
    public function getDateDebut(): \DateTimeInterface { return $this->dateDebut; }
    public function getDateFin(): ?\DateTimeInterface { return $this->dateFin; }
    public function setDateFin(?\DateTimeInterface $v): static { $this->dateFin = $v; return $this; }
}
