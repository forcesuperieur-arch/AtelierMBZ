<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Historique des mouvements de stock — traçabilité complète.
 * Chaque entrée, sortie ou ajustement est immuable et horodaté.
 */
#[ORM\Entity]
#[ORM\Table(name: 'mouvements_stock')]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    normalizationContext: ['groups' => ['mouvement:read']],
    operations: [
        new GetCollection(security: "is_granted('ROLE_USER')"),
        new Get(security: "is_granted('ROLE_USER')"),
        new Post(security: "is_granted('ROLE_ADMIN')"),
    ],
)]
class MouvementStock
{
    public const TYPE_ENTREE = 'entree';
    public const TYPE_SORTIE = 'sortie';
    public const TYPE_AJUSTEMENT = 'ajustement';
    public const TYPE_RECEPTION = 'reception';
    public const TYPE_COMMANDE = 'commande';

    public const TYPES = [
        self::TYPE_ENTREE,
        self::TYPE_SORTIE,
        self::TYPE_AJUSTEMENT,
        self::TYPE_RECEPTION,
        self::TYPE_COMMANDE,
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['mouvement:read'])]
    private ?int $id = null;

    #[ORM\Column(nullable: true)]
    private ?int $atelierId = null;

    #[ORM\ManyToOne(targetEntity: PieceDetachee::class)]
    #[ORM\JoinColumn(name: 'piece_id', nullable: false)]
    #[Groups(['mouvement:read'])]
    private PieceDetachee $piece;

    #[ORM\Column(length: 20)]
    #[Assert\Choice(choices: self::TYPES)]
    #[Groups(['mouvement:read'])]
    private string $type;

    #[ORM\Column]
    #[Groups(['mouvement:read'])]
    private int $quantite;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    #[Groups(['mouvement:read'])]
    private ?string $prixUnitaireHt = null;

    #[ORM\Column(length: 500, nullable: true)]
    #[Groups(['mouvement:read'])]
    private ?string $motif = null;

    #[ORM\ManyToOne(targetEntity: CommandeFournisseur::class)]
    #[ORM\JoinColumn(name: 'commande_fournisseur_id', nullable: true)]
    #[Groups(['mouvement:read'])]
    private ?CommandeFournisseur $commandeFournisseur = null;

    #[ORM\ManyToOne(targetEntity: RendezVous::class)]
    #[ORM\JoinColumn(name: 'rendez_vous_id', nullable: true)]
    #[Groups(['mouvement:read'])]
    private ?RendezVous $rendezVous = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'utilisateur_id', nullable: true)]
    #[Groups(['mouvement:read'])]
    private ?User $utilisateur = null;

    #[ORM\Column(type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'])]
    #[Groups(['mouvement:read'])]
    private \DateTimeInterface $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }
    public function getAtelierId(): ?int { return $this->atelierId; }
    public function setAtelierId(?int $v): static { $this->atelierId = $v; return $this; }

    public function getPiece(): PieceDetachee { return $this->piece; }
    public function setPiece(PieceDetachee $v): static { $this->piece = $v; return $this; }

    public function getType(): string { return $this->type; }
    public function setType(string $v): static { $this->type = $v; return $this; }

    public function getQuantite(): int { return $this->quantite; }
    public function setQuantite(int $v): static { $this->quantite = $v; return $this; }

    public function getPrixUnitaireHt(): ?string { return $this->prixUnitaireHt; }
    public function setPrixUnitaireHt(?string $v): static { $this->prixUnitaireHt = $v; return $this; }

    public function getMotif(): ?string { return $this->motif; }
    public function setMotif(?string $v): static { $this->motif = $v; return $this; }

    public function getCommandeFournisseur(): ?CommandeFournisseur { return $this->commandeFournisseur; }
    public function setCommandeFournisseur(?CommandeFournisseur $v): static { $this->commandeFournisseur = $v; return $this; }

    public function getRendezVous(): ?RendezVous { return $this->rendezVous; }
    public function setRendezVous(?RendezVous $v): static { $this->rendezVous = $v; return $this; }

    public function getUtilisateur(): ?User { return $this->utilisateur; }
    public function setUtilisateur(?User $v): static { $this->utilisateur = $v; return $this; }

    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
}
