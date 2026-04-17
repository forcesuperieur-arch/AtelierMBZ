<?php
namespace App\Entity;
use ApiPlatform\Metadata\ApiResource;
use App\Enum\ModeTarification;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity] #[ORM\Table(name: 'prestations')] #[ORM\HasLifecycleCallbacks]
#[ApiResource(
    normalizationContext: ['groups' => ['prestation:read']],
    denormalizationContext: ['groups' => ['prestation:write']],
)]
class Prestation
{
    #[ORM\Id] #[ORM\GeneratedValue] #[ORM\Column] #[Groups(['prestation:read'])] private ?int $id = null;
    #[ORM\Column(nullable: true)] private ?int $atelierId = null;
    #[ORM\Column(length: 50, unique: true)] #[Groups(['prestation:read', 'prestation:write'])] private string $code;
    #[ORM\Column(length: 200)] #[Groups(['prestation:read', 'prestation:write'])] private string $nom;
    #[ORM\Column(type: 'text', nullable: true)] #[Groups(['prestation:read', 'prestation:write'])] private ?string $description = null;
    #[ORM\Column(length: 100, options: ['default' => 'entretien'])] #[Groups(['prestation:read', 'prestation:write'])] private string $categorie = 'entretien';
    #[ORM\Column(length: 100, nullable: true)] #[Groups(['prestation:read', 'prestation:write'])] private ?string $sousCategorie = null;
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, options: ['default' => '0.00'])] #[Groups(['prestation:read', 'prestation:write'])] private string $prixBaseHt = '0.00';
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, options: ['default' => '0.00'])] #[Groups(['prestation:read', 'prestation:write'])] private string $prixBaseTtc = '0.00';
    #[ORM\Column(options: ['default' => 30])] #[Groups(['prestation:read', 'prestation:write'])] private int $tempsEstimeMinutes = 30;
    #[ORM\Column(options: ['default' => 1])] #[Groups(['prestation:read', 'prestation:write'])] private int $delaiInterventionJours = 1;
    #[ORM\Column(length: 50, options: ['default' => 'forfait'])] #[Groups(['prestation:read', 'prestation:write'])] private string $typeTarif = 'forfait';
    #[ORM\Column(length: 50, options: ['default' => 'standard'])] #[Groups(['prestation:read', 'prestation:write'])] private string $tauxHoraireApplique = 'standard';
    #[ORM\Column(length: 50, options: ['default' => 'tous'])] #[Groups(['prestation:read', 'prestation:write'])] private string $typeVehicule = 'tous';
    #[ORM\Column(nullable: true)] #[Groups(['prestation:read', 'prestation:write'])] private ?int $cylindreeMin = null;
    #[ORM\Column(nullable: true)] #[Groups(['prestation:read', 'prestation:write'])] private ?int $cylindreeMax = null;
    #[ORM\Column(options: ['default' => 1])] #[Groups(['prestation:read', 'prestation:write'])] private int $isActive = 1;
    #[ORM\Column(options: ['default' => 0])] #[Groups(['prestation:read', 'prestation:write'])] private int $isForfait = 0;
    #[ORM\Column(options: ['default' => 0])] #[Groups(['prestation:read', 'prestation:write'])] private int $isPromo = 0;
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)] #[Groups(['prestation:read', 'prestation:write'])] private ?string $prixPromoTtc = null;
    #[ORM\Column(options: ['default' => 0])] #[Groups(['prestation:read', 'prestation:write'])] private int $inclutPieces = 0;
    #[ORM\Column(type: 'text', nullable: true)] #[Groups(['prestation:read', 'prestation:write'])] private ?string $descriptionPiecesIncluses = null;
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, options: ['default' => '0.00'])] #[Groups(['prestation:read', 'prestation:write'])] private string $coutPiecesInclusesHt = '0.00';
    #[ORM\Column(type: 'float', options: ['default' => 30.0])] #[Groups(['prestation:read', 'prestation:write'])] private float $margePiecesPourcent = 30.0;

    // LOT 7 — new spec fields
    #[ORM\Column(nullable: true)] #[Groups(['prestation:read', 'prestation:write'])]
    private ?int $garantieJours = null;

    #[ORM\Column(options: ['default' => true])] #[Groups(['prestation:read', 'prestation:write'])]
    private bool $necessiteEssai = true;

    /** @var Collection<int, GrilleTarifaire> */
    #[ORM\OneToMany(targetEntity: GrilleTarifaire::class, mappedBy: 'prestation', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[Groups(['prestation:read'])]
    private Collection $tarifsCategorie;

    #[ORM\Column(type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'])] #[Groups(['prestation:read'])] private \DateTimeInterface $createdAt;
    #[ORM\Column(type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'])] #[Groups(['prestation:read'])] private \DateTimeInterface $updatedAt;

    public function __construct() {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
        $this->tarifsCategorie = new ArrayCollection();
    }
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
    public function getCategorie(): string { return $this->categorie; }
    public function setCategorie(string $v): static { $this->categorie = $v; return $this; }
    public function getSousCategorie(): ?string { return $this->sousCategorie; }
    public function setSousCategorie(?string $v): static { $this->sousCategorie = $v; return $this; }
    public function getPrixBaseHt(): string { return $this->prixBaseHt; }
    public function setPrixBaseHt(string $v): static { $this->prixBaseHt = $v; return $this; }
    public function getPrixBaseTtc(): string { return $this->prixBaseTtc; }
    public function setPrixBaseTtc(string $v): static { $this->prixBaseTtc = $v; return $this; }
    public function getTempsEstimeMinutes(): int { return $this->tempsEstimeMinutes; }
    public function setTempsEstimeMinutes(int $v): static { $this->tempsEstimeMinutes = $v; return $this; }
    public function getDelaiInterventionJours(): int { return $this->delaiInterventionJours; }
    public function setDelaiInterventionJours(int $v): static { $this->delaiInterventionJours = $v; return $this; }
    public function getTypeTarif(): string { return $this->typeTarif; }
    public function setTypeTarif(string $v): static { $this->typeTarif = $v; return $this; }
    public function getTauxHoraireApplique(): string { return $this->tauxHoraireApplique; }
    public function setTauxHoraireApplique(string $v): static { $this->tauxHoraireApplique = $v; return $this; }
    public function getTypeVehicule(): string { return $this->typeVehicule; }
    public function setTypeVehicule(string $v): static { $this->typeVehicule = $v; return $this; }
    public function getCylindreeMin(): ?int { return $this->cylindreeMin; }
    public function setCylindreeMin(?int $v): static { $this->cylindreeMin = $v; return $this; }
    public function getCylindreeMax(): ?int { return $this->cylindreeMax; }
    public function setCylindreeMax(?int $v): static { $this->cylindreeMax = $v; return $this; }
    public function getIsActive(): int { return $this->isActive; }
    public function setIsActive(int $v): static { $this->isActive = $v; return $this; }
    public function getIsForfait(): int { return $this->isForfait; }
    public function setIsForfait(int $v): static { $this->isForfait = $v; return $this; }
    public function getIsPromo(): int { return $this->isPromo; }
    public function setIsPromo(int $v): static { $this->isPromo = $v; return $this; }
    public function getPrixPromoTtc(): ?string { return $this->prixPromoTtc; }
    public function setPrixPromoTtc(?string $v): static { $this->prixPromoTtc = $v; return $this; }
    public function getInclutPieces(): int { return $this->inclutPieces; }
    public function setInclutPieces(int $v): static { $this->inclutPieces = $v; return $this; }
    public function getMargePiecesPourcent(): float { return $this->margePiecesPourcent; }
    public function setMargePiecesPourcent(float $v): static { $this->margePiecesPourcent = $v; return $this; }

    // LOT 7 — new fields
    public function getGarantieJours(): ?int { return $this->garantieJours; }
    public function setGarantieJours(?int $v): static { $this->garantieJours = $v; return $this; }
    public function getNecessiteEssai(): bool { return $this->necessiteEssai; }
    public function setNecessiteEssai(bool $v): static { $this->necessiteEssai = $v; return $this; }

    /** @return Collection<int, GrilleTarifaire> */
    public function getTarifsCategorie(): Collection { return $this->tarifsCategorie; }
    public function addTarifCategorie(GrilleTarifaire $t): static { if (!$this->tarifsCategorie->contains($t)) { $this->tarifsCategorie->add($t); $t->setPrestation($this); } return $this; }
    public function removeTarifCategorie(GrilleTarifaire $t): static { $this->tarifsCategorie->removeElement($t); return $this; }

    // Spec aliases
    public function getLibelle(): string { return $this->nom; }
    public function getModeTarification(): ModeTarification { return ModeTarification::tryFrom($this->typeTarif) ?? ModeTarification::FORFAIT; }
    public function setModeTarification(ModeTarification $v): static { $this->typeTarif = $v->value; return $this; }
    public function getPrixForfait(): string { return $this->prixBaseTtc; }
}
