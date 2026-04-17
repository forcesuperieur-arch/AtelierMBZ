<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'annulation_rdv')]
#[ORM\Index(columns: ['rendez_vous_id'], name: 'idx_annulation_rdv')]
class AnnulationRdv
{
    public const MOTIFS = [
        'client_desiste',
        'client_no_show',
        'no_show',
        'atelier_indisponible',
        'force_majeure',
        'erreur_saisie',
        'autre',
    ];

    public const SOURCES = ['atelier', 'client', 'automatique'];

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
    private string $motif;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $commentaire = null;

    #[ORM\Column(nullable: true)]
    private ?int $annulePar = null;

    #[ORM\Column(length: 20, options: ['default' => 'atelier'])]
    private string $source = 'atelier';

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $annuleAt;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $heureRdvOriginal = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $statutAvantAnnulation = null;

    public function __construct()
    {
        $this->annuleAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }
    public function getAtelierId(): ?int { return $this->atelierId; }
    public function setAtelierId(?int $v): static { $this->atelierId = $v; return $this; }
    public function getRendezVous(): RendezVous { return $this->rendezVous; }
    public function setRendezVous(RendezVous $v): static { $this->rendezVous = $v; return $this; }
    public function getMotif(): string { return $this->motif; }
    public function setMotif(string $v): static { $this->motif = $v; return $this; }
    public function getCommentaire(): ?string { return $this->commentaire; }
    public function setCommentaire(?string $v): static { $this->commentaire = $v; return $this; }
    public function getAnnulePar(): ?int { return $this->annulePar; }
    public function setAnnulePar(?int $v): static { $this->annulePar = $v; return $this; }
    public function getSource(): string { return $this->source; }
    public function setSource(string $v): static {
        if (!in_array($v, self::SOURCES, true)) {
            throw new \InvalidArgumentException("Invalid source: $v");
        }
        $this->source = $v;
        return $this;
    }
    public function getAnnuleAt(): \DateTimeInterface { return $this->annuleAt; }
    public function setAnnuleAt(\DateTimeInterface $v): static { $this->annuleAt = $v; return $this; }
    public function getHeureRdvOriginal(): ?\DateTimeInterface { return $this->heureRdvOriginal; }
    public function setHeureRdvOriginal(?\DateTimeInterface $v): static { $this->heureRdvOriginal = $v; return $this; }
    public function getStatutAvantAnnulation(): ?string { return $this->statutAvantAnnulation; }
    public function setStatutAvantAnnulation(?string $v): static { $this->statutAvantAnnulation = $v; return $this; }
}
