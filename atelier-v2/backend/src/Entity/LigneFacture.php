<?php
namespace App\Entity;
use ApiPlatform\Metadata\ApiResource;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity] #[ORM\Table(name: 'lignes_facture')] #[ApiResource]
class LigneFacture
{
    #[ORM\Id] #[ORM\GeneratedValue] #[ORM\Column] private ?int $id = null;
    #[ORM\Column(nullable: true)] private ?int $atelierId = null;
    #[ORM\ManyToOne(targetEntity: Facture::class, inversedBy: 'lignes')] #[ORM\JoinColumn(name: 'facture_id', nullable: false)] private Facture $facture;
    #[ORM\Column(length: 50)] private string $typeLigne;
    #[ORM\Column(length: 300)] private string $designation;
    #[ORM\Column(length: 100, nullable: true)] private ?string $reference = null;
    #[ORM\Column(type: 'float', options: ['default' => 1])] private float $quantite = 1;
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)] private string $prixUnitaireHt;
    #[ORM\Column(type: 'float', options: ['default' => 20.0])] private float $tauxTva = 20.0;
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)] private string $totalLigneHt;
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)] private string $totalLigneTtc;
    #[ORM\Column(options: ['default' => 0])] private int $ordre = 0;

    public function getId(): ?int { return $this->id; }
    public function getAtelierId(): ?int { return $this->atelierId; }
    public function setAtelierId(?int $v): static { $this->atelierId = $v; return $this; }
    public function getFacture(): Facture { return $this->facture; }
    public function setFacture(Facture $v): static { $this->facture = $v; return $this; }
    public function getTypeLigne(): string { return $this->typeLigne; }
    public function setTypeLigne(string $v): static { $this->typeLigne = $v; return $this; }
    public function getDesignation(): string { return $this->designation; }
    public function setDesignation(string $v): static { $this->designation = $v; return $this; }
    public function getReference(): ?string { return $this->reference; }
    public function setReference(?string $v): static { $this->reference = $v; return $this; }
    public function getQuantite(): float { return $this->quantite; }
    public function setQuantite(float $v): static { $this->quantite = $v; return $this; }
    public function getPrixUnitaireHt(): string { return $this->prixUnitaireHt; }
    public function setPrixUnitaireHt(string $v): static { $this->prixUnitaireHt = $v; return $this; }
    public function getTauxTva(): float { return $this->tauxTva; }
    public function setTauxTva(float $v): static { $this->tauxTva = $v; return $this; }
    public function getTotalLigneHt(): string { return $this->totalLigneHt; }
    public function setTotalLigneHt(string $v): static { $this->totalLigneHt = $v; return $this; }
    public function getTotalLigneTtc(): string { return $this->totalLigneTtc; }
    public function setTotalLigneTtc(string $v): static { $this->totalLigneTtc = $v; return $this; }
    public function getOrdre(): int { return $this->ordre; }
    public function setOrdre(int $v): static { $this->ordre = $v; return $this; }
}
