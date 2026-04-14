<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Delete;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity]
#[ORM\Table(name: 'ateliers')]
#[ApiResource(
    normalizationContext: ['groups' => ['atelier:read']],
    denormalizationContext: ['groups' => ['atelier:write']],
    operations: [
        new GetCollection(),
        new Get(),
        new Post(security: "is_granted('ROLE_SUPER_ADMIN')"),
        new Put(security: "is_granted('ROLE_ADMIN')"),
    ]
)]
class Atelier
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['atelier:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 200)]
    #[Groups(['atelier:read', 'atelier:write'])]
    private string $nom;

    #[ORM\Column(length: 100, unique: true)]
    #[Groups(['atelier:read', 'atelier:write'])]
    private string $slug;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['atelier:read', 'atelier:write'])]
    private ?string $adresse = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Groups(['atelier:read', 'atelier:write'])]
    private ?string $cp = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Groups(['atelier:read', 'atelier:write'])]
    private ?string $ville = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Groups(['atelier:read', 'atelier:write'])]
    private ?string $telephone = null;

    #[ORM\Column(length: 200, nullable: true)]
    #[Groups(['atelier:read', 'atelier:write'])]
    private ?string $email = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Groups(['atelier:read', 'atelier:write'])]
    private ?string $siret = null;

    #[ORM\Column(length: 30, nullable: true)]
    #[Groups(['atelier:read', 'atelier:write'])]
    private ?string $tvaIntracom = null;

    #[ORM\Column(length: 500, nullable: true)]
    #[Groups(['atelier:read', 'atelier:write'])]
    private ?string $logoUrl = null;

    #[ORM\Column(length: 50, options: ['default' => 'starter'])]
    #[Groups(['atelier:read', 'atelier:write'])]
    private string $plan = 'starter';

    #[ORM\Column(options: ['default' => true])]
    #[Groups(['atelier:read', 'atelier:write'])]
    private bool $actif = true;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['atelier:read', 'atelier:write'])]
    private ?string $configJson = null;

    #[ORM\Column(type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'])]
    #[Groups(['atelier:read'])]
    private \DateTimeInterface $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }
    public function getNom(): string { return $this->nom; }
    public function setNom(string $nom): static { $this->nom = $nom; return $this; }
    public function getSlug(): string { return $this->slug; }
    public function setSlug(string $slug): static { $this->slug = $slug; return $this; }
    public function getAdresse(): ?string { return $this->adresse; }
    public function setAdresse(?string $adresse): static { $this->adresse = $adresse; return $this; }
    public function getCp(): ?string { return $this->cp; }
    public function setCp(?string $cp): static { $this->cp = $cp; return $this; }
    public function getVille(): ?string { return $this->ville; }
    public function setVille(?string $ville): static { $this->ville = $ville; return $this; }
    public function getTelephone(): ?string { return $this->telephone; }
    public function setTelephone(?string $telephone): static { $this->telephone = $telephone; return $this; }
    public function getEmail(): ?string { return $this->email; }
    public function setEmail(?string $email): static { $this->email = $email; return $this; }
    public function getSiret(): ?string { return $this->siret; }
    public function setSiret(?string $siret): static { $this->siret = $siret; return $this; }
    public function getTvaIntracom(): ?string { return $this->tvaIntracom; }
    public function setTvaIntracom(?string $tvaIntracom): static { $this->tvaIntracom = $tvaIntracom; return $this; }
    public function getLogoUrl(): ?string { return $this->logoUrl; }
    public function setLogoUrl(?string $logoUrl): static { $this->logoUrl = $logoUrl; return $this; }
    public function getPlan(): string { return $this->plan; }
    public function setPlan(string $plan): static { $this->plan = $plan; return $this; }
    public function isActif(): bool { return $this->actif; }
    public function setActif(bool $actif): static { $this->actif = $actif; return $this; }
    public function getConfigJson(): ?string { return $this->configJson; }
    public function setConfigJson(?string $configJson): static { $this->configJson = $configJson; return $this; }
    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
}
