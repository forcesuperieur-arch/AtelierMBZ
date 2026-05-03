<?php
namespace App\Entity;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity] #[ORM\Table(name: 'lignes_commande_fournisseur')]
class LigneCommandeFournisseur
{
    #[ORM\Id] #[ORM\GeneratedValue] #[ORM\Column] #[Groups(['commande:read'])] private ?int $id = null;
    #[ORM\Column(nullable: true)] private ?int $atelierId = null;
    #[ORM\ManyToOne(targetEntity: CommandeFournisseur::class, inversedBy: 'lignes')]
    #[ORM\JoinColumn(name: 'commande_id', nullable: false)] private CommandeFournisseur $commande;
    #[ORM\ManyToOne(targetEntity: PieceDetachee::class, inversedBy: 'lignesCommande')]
    #[ORM\JoinColumn(name: 'piece_id', nullable: false)] #[Groups(['commande:read'])] private PieceDetachee $piece;
    #[ORM\Column] #[Groups(['commande:read'])] private int $quantiteDemandee;
    #[ORM\Column(options: ['default' => 0])] #[Groups(['commande:read'])] private int $quantiteRecue = 0;
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)] #[Groups(['commande:read'])] private string $prixUnitaireHt;

    public function getId(): ?int { return $this->id; }
    public function getAtelierId(): ?int { return $this->atelierId; }
    public function setAtelierId(?int $v): static { $this->atelierId = $v; return $this; }
    public function getCommande(): CommandeFournisseur { return $this->commande; }
    public function setCommande(CommandeFournisseur $v): static { $this->commande = $v; return $this; }
    public function getPiece(): PieceDetachee { return $this->piece; }
    public function setPiece(PieceDetachee $v): static { $this->piece = $v; return $this; }
    public function getQuantiteDemandee(): int { return $this->quantiteDemandee; }
    public function setQuantiteDemandee(int $v): static { $this->quantiteDemandee = $v; return $this; }
    public function getQuantiteRecue(): int { return $this->quantiteRecue; }
    public function setQuantiteRecue(int $v): static { $this->quantiteRecue = $v; return $this; }
    public function getPrixUnitaireHt(): string { return $this->prixUnitaireHt; }
    public function setPrixUnitaireHt(string $v): static { $this->prixUnitaireHt = $v; return $this; }
}
