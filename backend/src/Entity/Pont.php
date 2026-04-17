<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\State\ArchiveResourceProcessor;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity]
#[ORM\Table(name: 'ponts')]
#[ApiResource(
    normalizationContext: ['groups' => ['pont:read']],
    denormalizationContext: ['groups' => ['pont:write']],
    operations: [
        new GetCollection(security: "is_granted('ROLE_USER')"),
        new Get(security: "is_granted('ROLE_USER')"),
        new Post(security: "is_granted('ROLE_ADMIN')"),
        new Patch(security: "is_granted('ROLE_ADMIN')"),
        new Delete(security: "is_granted('ROLE_ADMIN')", processor: ArchiveResourceProcessor::class),
    ]
)]
class Pont
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['pont:read', 'rdv:read'])]
    private ?int $id = null;

    #[ORM\Column(nullable: true)]
    private ?int $atelierId = null;

    #[ORM\Column(length: 100)]
    #[Groups(['pont:read', 'pont:write', 'rdv:read'])]
    private string $nom;

    #[ORM\Column(length: 50, options: ['default' => 'moto'])]
    #[Groups(['pont:read', 'pont:write'])]
    private string $typePont = 'moto';

    #[ORM\Column(options: ['default' => 500])]
    #[Groups(['pont:read', 'pont:write'])]
    private int $capaciteKg = 500;

    #[ORM\Column(options: ['default' => 1])]
    #[Groups(['pont:read', 'pont:write'])]
    private int $isActive = 1;

    #[ORM\Column(options: ['default' => 0])]
    #[Groups(['pont:read', 'pont:write'])]
    private int $ordreAffichage = 0;

    #[ORM\ManyToOne(targetEntity: Mecanicien::class)]
    #[ORM\JoinColumn(name: 'mecanicien_id', nullable: true)]
    #[Groups(['pont:read', 'pont:write'])]
    private ?Mecanicien $mecanicien = null;

    /** @var Collection<int, RendezVous> */
    #[ORM\OneToMany(targetEntity: RendezVous::class, mappedBy: 'pont')]
    private Collection $rendezVous;

    public function __construct() { $this->rendezVous = new ArrayCollection(); }

    public function getId(): ?int { return $this->id; }
    public function getAtelierId(): ?int { return $this->atelierId; }
    public function setAtelierId(?int $v): static { $this->atelierId = $v; return $this; }
    public function getNom(): string { return $this->nom; }
    public function setNom(string $v): static { $this->nom = $v; return $this; }
    public function getTypePont(): string { return $this->typePont; }
    public function setTypePont(string $v): static { $this->typePont = $v; return $this; }
    public function getCapaciteKg(): int { return $this->capaciteKg; }
    public function setCapaciteKg(int $v): static { $this->capaciteKg = $v; return $this; }
    public function getIsActive(): int { return $this->isActive; }
    public function setIsActive(int $v): static { $this->isActive = $v; return $this; }
    public function getOrdreAffichage(): int { return $this->ordreAffichage; }
    public function setOrdreAffichage(int $v): static { $this->ordreAffichage = $v; return $this; }
    public function getMecanicien(): ?Mecanicien { return $this->mecanicien; }
    public function setMecanicien(?Mecanicien $v): static { $this->mecanicien = $v; return $this; }
    public function getRendezVous(): Collection { return $this->rendezVous; }
}
