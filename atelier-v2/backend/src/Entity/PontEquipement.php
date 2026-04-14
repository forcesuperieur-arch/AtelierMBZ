<?php
namespace App\Entity;
use ApiPlatform\Metadata\ApiResource;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity] #[ORM\Table(name: 'pont_equipements')] #[ORM\HasLifecycleCallbacks] #[ApiResource]
class PontEquipement
{
    #[ORM\Id] #[ORM\GeneratedValue] #[ORM\Column] private ?int $id = null;
    #[ORM\ManyToOne(targetEntity: Pont::class)] #[ORM\JoinColumn(name: 'pont_id', nullable: false)] private Pont $pont;
    #[ORM\Column(length: 200)] private string $nom;
    #[ORM\Column(type: 'text', nullable: true)] private ?string $description = null;
    #[ORM\Column(type: 'boolean', options: ['default' => true])] private bool $isPresent = true;
    #[ORM\Column(type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'])] private \DateTimeInterface $createdAt;
    #[ORM\Column(type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'])] private \DateTimeInterface $updatedAt;

    public function __construct() { $this->createdAt = new \DateTime(); $this->updatedAt = new \DateTime(); }
    #[ORM\PreUpdate] public function preUpdate(): void { $this->updatedAt = new \DateTime(); }

    public function getId(): ?int { return $this->id; }
    public function getPont(): Pont { return $this->pont; }
    public function setPont(Pont $v): static { $this->pont = $v; return $this; }
    public function getNom(): string { return $this->nom; }
    public function setNom(string $v): static { $this->nom = $v; return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $v): static { $this->description = $v; return $this; }
    public function isPresent(): bool { return $this->isPresent; }
    public function setIsPresent(bool $v): static { $this->isPresent = $v; return $this; }
}
