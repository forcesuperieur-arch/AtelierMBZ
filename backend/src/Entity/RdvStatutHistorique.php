<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Trace horodatée des transitions de workflow d'un RDV.
 * Alimentée par RdvStatutHistoriqueListener — sert la timeline de l'espace client.
 */
#[ORM\Entity]
#[ORM\Table(name: 'rdv_statut_historique')]
#[ORM\Index(columns: ['rendez_vous_id', 'created_at'], name: 'idx_rdv_statut_histo_rdv')]
class RdvStatutHistorique
{
    #[ORM\Id] #[ORM\GeneratedValue] #[ORM\Column] private ?int $id = null;
    #[ORM\ManyToOne(targetEntity: RendezVous::class)] #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')] private ?RendezVous $rendezVous = null;
    #[ORM\Column(nullable: true)] private ?int $atelierId = null;
    #[ORM\Column(length: 100)] private string $transition = '';
    #[ORM\Column(length: 50)] private string $statut = '';
    #[ORM\Column(type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'])] private \DateTimeInterface $createdAt;

    public function __construct() { $this->createdAt = new \DateTime(); }

    public function getId(): ?int { return $this->id; }
    public function getRendezVous(): ?RendezVous { return $this->rendezVous; }
    public function setRendezVous(RendezVous $v): static { $this->rendezVous = $v; return $this; }
    public function getAtelierId(): ?int { return $this->atelierId; }
    public function setAtelierId(?int $v): static { $this->atelierId = $v; return $this; }
    public function getTransition(): string { return $this->transition; }
    public function setTransition(string $v): static { $this->transition = $v; return $this; }
    public function getStatut(): string { return $this->statut; }
    public function setStatut(string $v): static { $this->statut = $v; return $this; }
    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
}
