<?php
namespace App\Entity;
use ApiPlatform\Metadata\ApiResource;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity] #[ORM\Table(name: 'atelier_categorie_motos')] #[ORM\HasLifecycleCallbacks] #[ApiResource]
class AtelierCategorieMoto
{
    #[ORM\Id] #[ORM\GeneratedValue] #[ORM\Column] private ?int $id = null;
    #[ORM\Column] private int $atelierId;
    #[ORM\ManyToOne(targetEntity: CategorieMoto::class)] #[ORM\JoinColumn(name: 'categorie_moto_id', nullable: false)] private CategorieMoto $categorieMoto;
    #[ORM\Column(type: 'boolean', options: ['default' => true])] private bool $isActive = true;
    #[ORM\Column(type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'])] private \DateTimeInterface $createdAt;
    #[ORM\Column(type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'])] private \DateTimeInterface $updatedAt;

    public function __construct() { $this->createdAt = new \DateTime(); $this->updatedAt = new \DateTime(); }
    #[ORM\PreUpdate] public function preUpdate(): void { $this->updatedAt = new \DateTime(); }

    public function getId(): ?int { return $this->id; }
    public function getAtelierId(): int { return $this->atelierId; }
    public function setAtelierId(int $v): static { $this->atelierId = $v; return $this; }
    public function getCategorieMoto(): CategorieMoto { return $this->categorieMoto; }
    public function setCategorieMoto(CategorieMoto $v): static { $this->categorieMoto = $v; return $this; }
    public function isActive(): bool { return $this->isActive; }
    public function setIsActive(bool $v): static { $this->isActive = $v; return $this; }
}
