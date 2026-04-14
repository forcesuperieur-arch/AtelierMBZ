<?php
namespace App\Entity;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity] #[ORM\Table(name: 'pieces_utilisees')]
class PieceUtilisee
{
    #[ORM\Id] #[ORM\GeneratedValue] #[ORM\Column] private ?int $id = null;
    #[ORM\ManyToOne(targetEntity: RendezVous::class, inversedBy: 'piecesUtilisees')]
    #[ORM\JoinColumn(name: 'rendez_vous_id', nullable: false)] private RendezVous $rendezVous;
    #[ORM\ManyToOne(targetEntity: PieceDetachee::class, inversedBy: 'utilisations')]
    #[ORM\JoinColumn(name: 'piece_id', nullable: false)] private PieceDetachee $piece;
    #[ORM\Column] private int $quantite;
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)] private ?string $prixVenteUnitaire = null;
    #[ORM\Column(type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'])] private \DateTimeInterface $createdAt;

    public function __construct() { $this->createdAt = new \DateTime(); }
    public function getId(): ?int { return $this->id; }
    public function getRendezVous(): RendezVous { return $this->rendezVous; }
    public function setRendezVous(RendezVous $v): static { $this->rendezVous = $v; return $this; }
    public function getPiece(): PieceDetachee { return $this->piece; }
    public function setPiece(PieceDetachee $v): static { $this->piece = $v; return $this; }
    public function getQuantite(): int { return $this->quantite; }
    public function setQuantite(int $v): static { $this->quantite = $v; return $this; }
    public function getPrixVenteUnitaire(): ?string { return $this->prixVenteUnitaire; }
    public function setPrixVenteUnitaire(?string $v): static { $this->prixVenteUnitaire = $v; return $this; }
}
