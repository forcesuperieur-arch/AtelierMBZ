<?php
namespace App\Entity;
use ApiPlatform\Metadata\ApiResource;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity] #[ORM\Table(name: 'grille_tarifaire')] #[ApiResource]
class GrilleTarifaire
{
    #[ORM\Id] #[ORM\GeneratedValue] #[ORM\Column] private ?int $id = null;
    #[ORM\Column(nullable: true)] private ?int $atelierId = null;
    #[ORM\ManyToOne(targetEntity: Prestation::class)] #[ORM\JoinColumn(name: 'prestation_id', nullable: false)] private Prestation $prestation;
    #[ORM\ManyToOne(targetEntity: CategorieMoto::class)] #[ORM\JoinColumn(name: 'categorie_moto_id', nullable: true)] private ?CategorieMoto $categorieMoto = null;
    #[ORM\Column(length: 50, options: ['default' => 'tous'])] private string $typeVehicule = 'tous';
    #[ORM\Column(nullable: true)] private ?int $cylindreeMin = null;
    #[ORM\Column(nullable: true)] private ?int $cylindreeMax = null;
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)] private string $prixHt;
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)] private string $prixTtc;
    #[ORM\Column] private int $tempsMinutes;
    #[ORM\Column(length: 50, options: ['default' => 'forfait'])] private string $typeTarif = 'forfait';
    #[ORM\Column(options: ['default' => 1])] private int $delaiJours = 1;
    #[ORM\Column(options: ['default' => 1])] private int $isActive = 1;
    #[ORM\Column(type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'])] private \DateTimeInterface $createdAt;

    public function __construct() { $this->createdAt = new \DateTime(); }

    public function getId(): ?int { return $this->id; }
    public function getAtelierId(): ?int { return $this->atelierId; }
    public function setAtelierId(?int $v): static { $this->atelierId = $v; return $this; }
    public function getPrestation(): Prestation { return $this->prestation; }
    public function setPrestation(Prestation $v): static { $this->prestation = $v; return $this; }
    public function getCategorieMoto(): ?CategorieMoto { return $this->categorieMoto; }
    public function setCategorieMoto(?CategorieMoto $v): static { $this->categorieMoto = $v; return $this; }
    public function getTypeVehicule(): string { return $this->typeVehicule; }
    public function setTypeVehicule(string $v): static { $this->typeVehicule = $v; return $this; }
    public function getCylindreeMin(): ?int { return $this->cylindreeMin; }
    public function setCylindreeMin(?int $v): static { $this->cylindreeMin = $v; return $this; }
    public function getCylindreeMax(): ?int { return $this->cylindreeMax; }
    public function setCylindreeMax(?int $v): static { $this->cylindreeMax = $v; return $this; }
    public function getPrixHt(): string { return $this->prixHt; }
    public function setPrixHt(string $v): static { $this->prixHt = $v; return $this; }
    public function getPrixTtc(): string { return $this->prixTtc; }
    public function setPrixTtc(string $v): static { $this->prixTtc = $v; return $this; }
    public function getTempsMinutes(): int { return $this->tempsMinutes; }
    public function setTempsMinutes(int $v): static { $this->tempsMinutes = $v; return $this; }
    public function getTypeTarif(): string { return $this->typeTarif; }
    public function setTypeTarif(string $v): static { $this->typeTarif = $v; return $this; }
    public function getDelaiJours(): int { return $this->delaiJours; }
    public function setDelaiJours(int $v): static { $this->delaiJours = $v; return $this; }
    public function getIsActive(): int { return $this->isActive; }
    public function setIsActive(int $v): static { $this->isActive = $v; return $this; }
}
