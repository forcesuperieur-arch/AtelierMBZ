<?php
namespace App\Entity;
use Doctrine\ORM\Mapping as ORM;

/** @deprecated Use Prestation + GrilleTarifaire instead */
#[ORM\Entity] #[ORM\Table(name: 'intervention_types')]
class InterventionType
{
    #[ORM\Id] #[ORM\GeneratedValue] #[ORM\Column] private ?int $id = null;
    #[ORM\Column(length: 200)] private string $nom;
    #[ORM\Column(type: 'text', nullable: true)] private ?string $description = null;
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)] private string $prixBase;
    #[ORM\Column] private int $tempsEstime;
    #[ORM\Column(options: ['default' => 1])] private int $isActive = 1;

    public function getId(): ?int { return $this->id; }
    public function getNom(): string { return $this->nom; }
    public function setNom(string $v): static { $this->nom = $v; return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $v): static { $this->description = $v; return $this; }
    public function getPrixBase(): string { return $this->prixBase; }
    public function setPrixBase(string $v): static { $this->prixBase = $v; return $this; }
    public function getTempsEstime(): int { return $this->tempsEstime; }
    public function setTempsEstime(int $v): static { $this->tempsEstime = $v; return $this; }
    public function getIsActive(): int { return $this->isActive; }
    public function setIsActive(int $v): static { $this->isActive = $v; return $this; }
}
