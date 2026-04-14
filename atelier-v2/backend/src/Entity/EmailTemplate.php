<?php
namespace App\Entity;
use ApiPlatform\Metadata\ApiResource;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity] #[ORM\Table(name: 'email_templates')] #[ORM\HasLifecycleCallbacks]
#[ApiResource(
    normalizationContext: ['groups' => ['email:read']],
    denormalizationContext: ['groups' => ['email:write']],
)]
class EmailTemplate
{
    #[ORM\Id] #[ORM\GeneratedValue] #[ORM\Column] #[Groups(['email:read'])] private ?int $id = null;
    #[ORM\Column(nullable: true)] private ?int $atelierId = null;
    #[ORM\Column(length: 50)] #[Groups(['email:read', 'email:write'])] private string $code;
    #[ORM\Column(length: 200)] #[Groups(['email:read', 'email:write'])] private string $nom;
    #[ORM\Column(length: 500)] #[Groups(['email:read', 'email:write'])] private string $sujet;
    #[ORM\Column(type: 'text')] #[Groups(['email:read', 'email:write'])] private string $corpsHtml;
    #[ORM\Column(type: 'text', nullable: true)] #[Groups(['email:read', 'email:write'])] private ?string $corpsTexte = null;
    #[ORM\Column(type: 'text', nullable: true)] #[Groups(['email:read', 'email:write'])] private ?string $variablesDisponibles = null;
    #[ORM\Column(options: ['default' => 1])] #[Groups(['email:read', 'email:write'])] private int $isActive = 1;
    #[ORM\Column(type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'])] #[Groups(['email:read'])] private \DateTimeInterface $createdAt;
    #[ORM\Column(type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'])] #[Groups(['email:read'])] private \DateTimeInterface $updatedAt;

    public function __construct() { $this->createdAt = new \DateTime(); $this->updatedAt = new \DateTime(); }
    #[ORM\PreUpdate] public function preUpdate(): void { $this->updatedAt = new \DateTime(); }

    public function getId(): ?int { return $this->id; }
    public function getAtelierId(): ?int { return $this->atelierId; }
    public function setAtelierId(?int $v): static { $this->atelierId = $v; return $this; }
    public function getCode(): string { return $this->code; }
    public function setCode(string $v): static { $this->code = $v; return $this; }
    public function getNom(): string { return $this->nom; }
    public function setNom(string $v): static { $this->nom = $v; return $this; }
    public function getSujet(): string { return $this->sujet; }
    public function setSujet(string $v): static { $this->sujet = $v; return $this; }
    public function getCorpsHtml(): string { return $this->corpsHtml; }
    public function setCorpsHtml(string $v): static { $this->corpsHtml = $v; return $this; }
    public function getCorpsTexte(): ?string { return $this->corpsTexte; }
    public function setCorpsTexte(?string $v): static { $this->corpsTexte = $v; return $this; }
    public function getVariablesDisponibles(): ?string { return $this->variablesDisponibles; }
    public function setVariablesDisponibles(?string $v): static { $this->variablesDisponibles = $v; return $this; }
    public function getIsActive(): int { return $this->isActive; }
    public function setIsActive(int $v): static { $this->isActive = $v; return $this; }
}
