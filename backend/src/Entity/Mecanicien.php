<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity]
#[ORM\Table(name: 'mecaniciens')]
#[ApiResource(
    normalizationContext: ['groups' => ['mecanicien:read']],
    denormalizationContext: ['groups' => ['mecanicien:write']],
)]
class Mecanicien
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['mecanicien:read', 'rdv:read', 'pont:read'])]
    private ?int $id = null;

    #[ORM\Column(nullable: true)]
    private ?int $atelierId = null;

    #[ORM\Column(length: 100)]
    #[Groups(['mecanicien:read', 'mecanicien:write', 'rdv:read', 'pont:read', 'absence:read'])]
    private string $nom;

    #[ORM\Column(length: 100)]
    #[Groups(['mecanicien:read', 'mecanicien:write', 'rdv:read', 'pont:read', 'absence:read'])]
    private string $prenom;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['mecanicien:read', 'mecanicien:write'])]
    private ?string $specialites = null;

    #[ORM\Column(length: 7, options: ['default' => '#3b82f6'])]
    #[Groups(['mecanicien:read', 'mecanicien:write'])]
    private string $couleur = '#3b82f6';

    #[ORM\Column(options: ['default' => 1])]
    #[Groups(['mecanicien:read', 'mecanicien:write'])]
    private int $isActive = 1;

    #[ORM\Column(nullable: true)]
    #[Groups(['mecanicien:read', 'mecanicien:write'])]
    private ?int $userId = null;

    /** @var Collection<int, RendezVous> */
    #[ORM\OneToMany(targetEntity: RendezVous::class, mappedBy: 'mecanicien')]
    private Collection $rendezVous;

    public function __construct() { $this->rendezVous = new ArrayCollection(); }

    public function getId(): ?int { return $this->id; }
    public function getAtelierId(): ?int { return $this->atelierId; }
    public function setAtelierId(?int $v): static { $this->atelierId = $v; return $this; }
    public function getNom(): string { return $this->nom; }
    public function setNom(string $v): static { $this->nom = $v; return $this; }
    public function getPrenom(): string { return $this->prenom; }
    public function setPrenom(string $v): static { $this->prenom = $v; return $this; }
    public function getSpecialites(): ?string { return $this->specialites; }
    public function setSpecialites(?string $v): static { $this->specialites = $v; return $this; }
    public function getCouleur(): string { return $this->couleur; }
    public function setCouleur(string $v): static { $this->couleur = $v; return $this; }
    public function getIsActive(): int { return $this->isActive; }
    public function setIsActive(int $v): static { $this->isActive = $v; return $this; }
    public function getUserId(): ?int { return $this->userId; }
    public function setUserId(?int $v): static { $this->userId = $v; return $this; }
    public function getRendezVous(): Collection { return $this->rendezVous; }
}
