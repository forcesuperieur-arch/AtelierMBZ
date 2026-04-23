<?php
namespace App\Entity;
use ApiPlatform\Metadata\ApiResource;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity] #[ORM\Table(name: 'lignes_devis')] #[ApiResource]
class LigneDevis
{
    #[ORM\Id] #[ORM\GeneratedValue] #[ORM\Column] private ?int $id = null;
    #[ORM\ManyToOne(targetEntity: Devis::class, inversedBy: 'lignes')] #[ORM\JoinColumn(nullable: false)] private Devis $devis;
    #[ORM\Column(length: 50)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 50)]
    private string $typeLigne;
    #[ORM\ManyToOne(targetEntity: ForfaitMO::class, inversedBy: 'lignesDevis')] #[ORM\JoinColumn(name: 'forfait_mo_id', nullable: true)] private ?ForfaitMO $forfaitMo = null;
    #[ORM\ManyToOne(targetEntity: PieceDetachee::class)] #[ORM\JoinColumn(name: 'piece_id', nullable: true)] private ?PieceDetachee $piece = null;
    #[ORM\Column(length: 300)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 300)]
    private string $designation;
    #[ORM\Column(type: 'text', nullable: true)] private ?string $descriptionDetail = null;
    #[ORM\Column(options: ['default' => 1])] private int $quantite = 1;
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    #[Assert\Regex(pattern: '/^\d+(\.\d{1,2})?$/')]
    private string $prixUnitaireHt;
    #[ORM\Column(type: 'float', options: ['default' => 20.0])] private float $tauxTva = 20.0;
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    #[Assert\Regex(pattern: '/^\d+(\.\d{1,2})?$/')]
    private string $totalLigneHt;
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    #[Assert\Regex(pattern: '/^\d+(\.\d{1,2})?$/')]
    private string $totalLigneTtc;
    #[ORM\Column(options: ['default' => 0])] private int $ordre = 0;

    public function getId(): ?int { return $this->id; }
    public function getDevis(): Devis { return $this->devis; }
    public function setDevis(Devis $v): static { $this->devis = $v; return $this; }
    public function getTypeLigne(): string { return $this->typeLigne; }
    public function setTypeLigne(string $v): static { $this->typeLigne = $v; return $this; }
    public function getForfaitMo(): ?ForfaitMO { return $this->forfaitMo; }
    public function setForfaitMo(?ForfaitMO $v): static { $this->forfaitMo = $v; return $this; }
    public function getPiece(): ?PieceDetachee { return $this->piece; }
    public function setPiece(?PieceDetachee $v): static { $this->piece = $v; return $this; }
    public function getDesignation(): string { return $this->designation; }
    public function setDesignation(string $v): static { $this->designation = $v; return $this; }
    public function getDescriptionDetail(): ?string { return $this->descriptionDetail; }
    public function setDescriptionDetail(?string $v): static { $this->descriptionDetail = $v; return $this; }
    public function getQuantite(): int { return $this->quantite; }
    public function setQuantite(int $v): static { $this->quantite = $v; return $this; }
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
