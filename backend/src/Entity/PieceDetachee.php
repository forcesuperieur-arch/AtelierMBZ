<?php
namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'pieces_detachees')]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    shortName: 'Piece',
    normalizationContext: ['groups' => ['piece:read']],
    denormalizationContext: ['groups' => ['piece:write']],
    operations: [
        new GetCollection(uriTemplate: '/stock/pieces'),
        new Get(uriTemplate: '/stock/pieces/{id}'),
        new Post(uriTemplate: '/stock/pieces'),
        new Put(uriTemplate: '/stock/pieces/{id}'),
        new Delete(uriTemplate: '/stock/pieces/{id}'),
    ]
)]
#[ApiFilter(SearchFilter::class, properties: ['reference' => 'exact', 'nom' => 'partial', 'categorie' => 'exact'])]
class PieceDetachee
{
    #[ORM\Id] #[ORM\GeneratedValue] #[ORM\Column]
    #[Groups(['piece:read', 'mouvement:read', 'commande:read', 'piece_utilisee:read'])]
    private ?int $id = null;

    #[ORM\Column(nullable: true)] private ?int $atelierId = null;

    #[ORM\Column(length: 100, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    #[Groups(['piece:read', 'piece:write', 'mouvement:read', 'commande:read', 'piece_utilisee:read'])]
    private string $reference;

    #[ORM\Column(length: 100, nullable: true)]
    #[Groups(['piece:read', 'piece:write'])]
    private ?string $referenceFournisseur = null;

    #[ORM\Column(length: 200)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 200)]
    #[Groups(['piece:read', 'piece:write', 'mouvement:read', 'commande:read', 'piece_utilisee:read'])]
    private string $nom;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['piece:read', 'piece:write'])]
    private ?string $description = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Groups(['piece:read', 'piece:write'])]
    private ?string $categorie = null;

    #[ORM\Column(options: ['default' => 0])]
    #[Assert\PositiveOrZero]
    #[Groups(['piece:read', 'piece:write'])]
    private int $quantiteStock = 0;

    #[ORM\Column(options: ['default' => 5])]
    #[Assert\PositiveOrZero]
    #[Groups(['piece:read', 'piece:write'])]
    private int $quantiteMinimale = 5;

    #[ORM\Column(options: ['default' => 50])]
    #[Groups(['piece:read', 'piece:write'])]
    private int $quantiteMaximale = 50;

    #[ORM\Column(length: 100, nullable: true)]
    #[Groups(['piece:read', 'piece:write'])]
    private ?string $emplacement = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, options: ['default' => '0.00'])]
    #[Groups(['piece:read', 'piece:write'])]
    private string $prixAchatHt = '0.00';

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, options: ['default' => '0.00'])]
    #[Groups(['piece:read', 'piece:write'])]
    private string $prixVenteHt = '0.00';

    #[ORM\Column(type: 'float', options: ['default' => 20.0])]
    #[Groups(['piece:read', 'piece:write'])]
    private float $tvaTaux = 20.0;

    #[ORM\ManyToOne(targetEntity: Fournisseur::class)]
    #[ORM\JoinColumn(name: 'fournisseur_id', nullable: true)]
    #[Groups(['piece:read', 'piece:write'])]
    private ?Fournisseur $fournisseur = null;

    #[ORM\Column(options: ['default' => 1])]
    #[Groups(['piece:read', 'piece:write'])]
    private int $isActive = 1;

    #[ORM\Column(type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'])]
    #[Groups(['piece:read'])]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'])]
    #[Groups(['piece:read'])]
    private \DateTimeInterface $updatedAt;
    #[ORM\OneToMany(targetEntity: LigneCommandeFournisseur::class, mappedBy: 'piece')] private Collection $lignesCommande;
    #[ORM\OneToMany(targetEntity: PieceUtilisee::class, mappedBy: 'piece')] private Collection $utilisations;

    public function __construct() { $this->createdAt = new \DateTime(); $this->updatedAt = new \DateTime(); $this->lignesCommande = new ArrayCollection(); $this->utilisations = new ArrayCollection(); }
    #[ORM\PreUpdate] public function preUpdate(): void { $this->updatedAt = new \DateTime(); }

    public function getId(): ?int { return $this->id; }
    public function getAtelierId(): ?int { return $this->atelierId; }
    public function setAtelierId(?int $v): static { $this->atelierId = $v; return $this; }
    public function getReference(): string { return $this->reference; }
    public function setReference(string $v): static { $this->reference = $v; return $this; }
    public function getReferenceFournisseur(): ?string { return $this->referenceFournisseur; }
    public function setReferenceFournisseur(?string $v): static { $this->referenceFournisseur = $v; return $this; }
    public function getNom(): string { return $this->nom; }
    public function setNom(string $v): static { $this->nom = $v; return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $v): static { $this->description = $v; return $this; }
    public function getCategorie(): ?string { return $this->categorie; }
    public function setCategorie(?string $v): static { $this->categorie = $v; return $this; }
    public function getQuantiteStock(): int { return $this->quantiteStock; }
    public function setQuantiteStock(int $v): static { $this->quantiteStock = $v; return $this; }
    public function getQuantiteMinimale(): int { return $this->quantiteMinimale; }
    public function setQuantiteMinimale(int $v): static { $this->quantiteMinimale = $v; return $this; }
    public function getQuantiteMaximale(): int { return $this->quantiteMaximale; }
    public function setQuantiteMaximale(int $v): static { $this->quantiteMaximale = $v; return $this; }
    public function getEmplacement(): ?string { return $this->emplacement; }
    public function setEmplacement(?string $v): static { $this->emplacement = $v; return $this; }
    public function getPrixAchatHt(): string { return $this->prixAchatHt; }
    public function setPrixAchatHt(string $v): static { $this->prixAchatHt = $v; return $this; }
    public function getPrixVenteHt(): string { return $this->prixVenteHt; }
    public function setPrixVenteHt(string $v): static { $this->prixVenteHt = $v; return $this; }
    public function getTvaTaux(): float { return $this->tvaTaux; }
    public function setTvaTaux(float $v): static { $this->tvaTaux = $v; return $this; }
    public function getFournisseur(): ?Fournisseur { return $this->fournisseur; }
    public function setFournisseur(?Fournisseur $v): static { $this->fournisseur = $v; return $this; }
    public function getIsActive(): int { return $this->isActive; }
    public function setIsActive(int $v): static { $this->isActive = $v; return $this; }
    public function isStockBas(): bool { return $this->quantiteStock <= $this->quantiteMinimale; }
    public function getPrixVenteTtc(): float { return (float)$this->prixVenteHt * (1 + $this->tvaTaux / 100); }
}
