<?php
namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Get;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity] #[ORM\Table(name: 'pieces_utilisees')]
#[ApiResource(
    normalizationContext: ['groups' => ['piece_utilisee:read']],
    operations: [
        new GetCollection(security: "is_granted('ROLE_USER')"),
        new Get(security: "is_granted('ROLE_USER')"),
    ],
)]
#[ApiFilter(SearchFilter::class, properties: ['rendezVous' => 'exact'])]
class PieceUtilisee
{
    #[ORM\Id] #[ORM\GeneratedValue] #[ORM\Column] #[Groups(['piece_utilisee:read'])] private ?int $id = null;
    #[ORM\ManyToOne(targetEntity: RendezVous::class, inversedBy: 'piecesUtilisees')]
    #[ORM\JoinColumn(name: 'rendez_vous_id', nullable: false)] #[Groups(['piece_utilisee:read'])] private RendezVous $rendezVous;
    #[ORM\ManyToOne(targetEntity: PieceDetachee::class, inversedBy: 'utilisations')]
    #[ORM\JoinColumn(name: 'piece_id', nullable: false)] #[Groups(['piece_utilisee:read'])] private PieceDetachee $piece;
    #[ORM\Column] #[Groups(['piece_utilisee:read'])] private int $quantite;
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)] #[Groups(['piece_utilisee:read'])] private ?string $prixVenteUnitaire = null;
    #[ORM\Column(type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'])] #[Groups(['piece_utilisee:read'])] private \DateTimeInterface $createdAt;

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
