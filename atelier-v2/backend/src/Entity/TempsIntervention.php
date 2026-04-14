<?php
namespace App\Entity;
use Doctrine\ORM\Mapping as ORM;

/** @deprecated Use GrilleTarifaire with categorie_moto_id instead */
#[ORM\Entity] #[ORM\Table(name: 'temps_interventions')] #[ORM\HasLifecycleCallbacks]
class TempsIntervention
{
    #[ORM\Id] #[ORM\GeneratedValue] #[ORM\Column] private ?int $id = null;
    #[ORM\ManyToOne(targetEntity: CategorieMoto::class)] #[ORM\JoinColumn(name: 'categorie_moto_id', nullable: false)] private CategorieMoto $categorieMoto;
    #[ORM\ManyToOne(targetEntity: InterventionType::class)] #[ORM\JoinColumn(name: 'intervention_type_id', nullable: false)] private InterventionType $interventionType;
    #[ORM\Column] private int $tempsMinutes;
    #[ORM\Column(type: 'float', options: ['default' => 1.0])] private float $coefficientDifficulte = 1.0;
    #[ORM\Column(type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'])] private \DateTimeInterface $createdAt;
    #[ORM\Column(type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'])] private \DateTimeInterface $updatedAt;

    public function __construct() { $this->createdAt = new \DateTime(); $this->updatedAt = new \DateTime(); }
    #[ORM\PreUpdate] public function preUpdate(): void { $this->updatedAt = new \DateTime(); }

    public function getId(): ?int { return $this->id; }
    public function getCategorieMoto(): CategorieMoto { return $this->categorieMoto; }
    public function setCategorieMoto(CategorieMoto $v): static { $this->categorieMoto = $v; return $this; }
    public function getInterventionType(): InterventionType { return $this->interventionType; }
    public function setInterventionType(InterventionType $v): static { $this->interventionType = $v; return $this; }
    public function getTempsMinutes(): int { return $this->tempsMinutes; }
    public function setTempsMinutes(int $v): static { $this->tempsMinutes = $v; return $this; }
    public function getCoefficientDifficulte(): float { return $this->coefficientDifficulte; }
    public function setCoefficientDifficulte(float $v): static { $this->coefficientDifficulte = $v; return $this; }
}
