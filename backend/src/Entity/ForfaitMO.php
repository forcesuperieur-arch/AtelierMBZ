<?php
namespace App\Entity;
use ApiPlatform\Metadata\ApiResource;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity] #[ORM\Table(name: 'forfaits_mo')] #[ORM\HasLifecycleCallbacks] #[ApiResource]
class ForfaitMO
{
    #[ORM\Id] #[ORM\GeneratedValue] #[ORM\Column] private ?int $id = null;
    #[ORM\Column(nullable: true)] private ?int $atelierId = null;
    #[ORM\Column(length: 50, unique: true)] private string $code;
    #[ORM\Column(length: 200)] private string $nom;
    #[ORM\Column(type: 'text', nullable: true)] private ?string $description = null;
    #[ORM\Column(length: 100, nullable: true)] private ?string $categorie = null;
    #[ORM\Column] private int $tempsBaseMinutes;
    #[ORM\Column(length: 50, options: ['default' => 'standard'])] private string $tauxHoraireApplique = 'standard';
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)] private string $prixForfaitMoHt;
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)] private string $prixForfaitMoTtc;
    #[ORM\Column(options: ['default' => 0])] private int $inclutPieces = 0;
    #[ORM\Column(type: 'text', nullable: true)] private ?string $descriptionPiecesIncluses = null;
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, options: ['default' => '0.00'])] private string $prixPiecesInclusesHt = '0.00';
    #[ORM\Column(length: 50, options: ['default' => 'tous'])] private string $typeVehicule = 'tous';
    #[ORM\Column(nullable: true)] private ?int $cylindreeMin = null;
    #[ORM\Column(nullable: true)] private ?int $cylindreeMax = null;
    #[ORM\Column(options: ['default' => 1])] private int $isActive = 1;
    #[ORM\Column(options: ['default' => 0])] private int $isPromo = 0;
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)] private ?string $prixPromoMoTtc = null;
    #[ORM\Column(type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'])] private \DateTimeInterface $createdAt;
    #[ORM\Column(type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'])] private \DateTimeInterface $updatedAt;
    #[ORM\OneToMany(targetEntity: LigneDevis::class, mappedBy: 'forfaitMo')] private Collection $lignesDevis;

    public function __construct() { $this->createdAt = new \DateTime(); $this->updatedAt = new \DateTime(); $this->lignesDevis = new ArrayCollection(); }
    #[ORM\PreUpdate] public function preUpdate(): void { $this->updatedAt = new \DateTime(); }

    public function getId(): ?int { return $this->id; }
    public function getAtelierId(): ?int { return $this->atelierId; }
    public function setAtelierId(?int $v): static { $this->atelierId = $v; return $this; }
    public function getCode(): string { return $this->code; }
    public function setCode(string $v): static { $this->code = $v; return $this; }
    public function getNom(): string { return $this->nom; }
    public function setNom(string $v): static { $this->nom = $v; return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $v): static { $this->description = $v; return $this; }
    public function getCategorie(): ?string { return $this->categorie; }
    public function setCategorie(?string $v): static { $this->categorie = $v; return $this; }
    public function getTempsBaseMinutes(): int { return $this->tempsBaseMinutes; }
    public function setTempsBaseMinutes(int $v): static { $this->tempsBaseMinutes = $v; return $this; }
    public function getTauxHoraireApplique(): string { return $this->tauxHoraireApplique; }
    public function setTauxHoraireApplique(string $v): static { $this->tauxHoraireApplique = $v; return $this; }
    public function getPrixForfaitMoHt(): string { return $this->prixForfaitMoHt; }
    public function setPrixForfaitMoHt(string $v): static { $this->prixForfaitMoHt = $v; return $this; }
    public function getPrixForfaitMoTtc(): string { return $this->prixForfaitMoTtc; }
    public function setPrixForfaitMoTtc(string $v): static { $this->prixForfaitMoTtc = $v; return $this; }
    public function getInclutPieces(): int { return $this->inclutPieces; }
    public function setInclutPieces(int $v): static { $this->inclutPieces = $v; return $this; }
    public function getTypeVehicule(): string { return $this->typeVehicule; }
    public function setTypeVehicule(string $v): static { $this->typeVehicule = $v; return $this; }
    public function getCylindreeMin(): ?int { return $this->cylindreeMin; }
    public function setCylindreeMin(?int $v): static { $this->cylindreeMin = $v; return $this; }
    public function getCylindreeMax(): ?int { return $this->cylindreeMax; }
    public function setCylindreeMax(?int $v): static { $this->cylindreeMax = $v; return $this; }
    public function getIsActive(): int { return $this->isActive; }
    public function setIsActive(int $v): static { $this->isActive = $v; return $this; }
    public function getIsPromo(): int { return $this->isPromo; }
    public function setIsPromo(int $v): static { $this->isPromo = $v; return $this; }
    public function getPrixPromoMoTtc(): ?string { return $this->prixPromoMoTtc; }
    public function setPrixPromoMoTtc(?string $v): static { $this->prixPromoMoTtc = $v; return $this; }
}
