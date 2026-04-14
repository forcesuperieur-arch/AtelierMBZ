<?php
namespace App\Entity;
use ApiPlatform\Metadata\ApiResource;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity] #[ORM\Table(name: 'grille_tarifs')] #[ApiResource]
class GrilleTarifs
{
    #[ORM\Id] #[ORM\GeneratedValue] #[ORM\Column] private ?int $id = null;
    #[ORM\ManyToOne(targetEntity: CategorieMoto::class)] #[ORM\JoinColumn(name: 'categorie_moto_id', nullable: true)] private ?CategorieMoto $categorie = null;
    #[ORM\Column(length: 100)] private string $typeIntervention;
    #[ORM\Column(length: 200)] private string $nom;
    #[ORM\Column(type: 'text', nullable: true)] private ?string $description = null;
    #[ORM\Column] private int $tempsMinutes;
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)] private string $prixMoHt;
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)] private string $prixMoTtc;
    #[ORM\Column(type: 'boolean', options: ['default' => false])] private bool $piecesIncluses = false;
    #[ORM\Column(type: 'boolean', options: ['default' => true])] private bool $actif = true;
    #[ORM\Column(type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'])] private \DateTimeInterface $createdAt;

    public function __construct() { $this->createdAt = new \DateTime(); }

    public function getId(): ?int { return $this->id; }
    public function getCategorie(): ?CategorieMoto { return $this->categorie; }
    public function setCategorie(?CategorieMoto $v): static { $this->categorie = $v; return $this; }
    public function getTypeIntervention(): string { return $this->typeIntervention; }
    public function setTypeIntervention(string $v): static { $this->typeIntervention = $v; return $this; }
    public function getNom(): string { return $this->nom; }
    public function setNom(string $v): static { $this->nom = $v; return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $v): static { $this->description = $v; return $this; }
    public function getTempsMinutes(): int { return $this->tempsMinutes; }
    public function setTempsMinutes(int $v): static { $this->tempsMinutes = $v; return $this; }
    public function getPrixMoHt(): string { return $this->prixMoHt; }
    public function setPrixMoHt(string $v): static { $this->prixMoHt = $v; return $this; }
    public function getPrixMoTtc(): string { return $this->prixMoTtc; }
    public function setPrixMoTtc(string $v): static { $this->prixMoTtc = $v; return $this; }
    public function isPiecesIncluses(): bool { return $this->piecesIncluses; }
    public function setPiecesIncluses(bool $v): static { $this->piecesIncluses = $v; return $this; }
    public function isActif(): bool { return $this->actif; }
    public function setActif(bool $v): static { $this->actif = $v; return $this; }
}
