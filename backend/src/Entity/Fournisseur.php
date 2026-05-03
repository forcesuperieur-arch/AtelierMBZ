<?php
namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity]
#[ORM\Table(name: 'fournisseurs')]
#[ApiResource]
class Fournisseur
{
    #[ORM\Id] #[ORM\GeneratedValue] #[ORM\Column] #[Groups(['fournisseur:read', 'piece:read', 'commande:read'])]
    private ?int $id = null;
    #[ORM\Column(nullable: true)] private ?int $atelierId = null;
    #[ORM\Column(length: 200)] #[Groups(['fournisseur:read', 'commande:read', 'piece:read'])] private string $nom;
    #[ORM\Column(length: 200, nullable: true)] #[Groups(['fournisseur:read'])] private ?string $contact = null;
    #[ORM\Column(length: 20, nullable: true)] #[Groups(['fournisseur:read'])] private ?string $telephone = null;
    #[ORM\Column(length: 200, nullable: true)] #[Groups(['fournisseur:read'])] private ?string $email = null;
    #[ORM\Column(type: 'text', nullable: true)] private ?string $adresse = null;
    #[ORM\Column(length: 20, nullable: true)] private ?string $siret = null;
    #[ORM\Column(options: ['default' => 3])] #[Groups(['fournisseur:read'])] private int $delaiLivraisonJours = 3;
    #[ORM\Column(type: 'text', nullable: true)] private ?string $notes = null;
    #[ORM\Column(options: ['default' => 1])] private int $isActive = 1;
    #[ORM\Column(type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'])] private \DateTimeInterface $createdAt;
    #[ORM\OneToMany(targetEntity: CommandeFournisseur::class, mappedBy: 'fournisseur')]
    private Collection $commandes;

    public function __construct() { $this->createdAt = new \DateTime(); $this->commandes = new ArrayCollection(); }

    public function getId(): ?int { return $this->id; }
    public function getAtelierId(): ?int { return $this->atelierId; }
    public function setAtelierId(?int $v): static { $this->atelierId = $v; return $this; }
    public function getNom(): string { return $this->nom; }
    public function setNom(string $v): static { $this->nom = $v; return $this; }
    public function getContact(): ?string { return $this->contact; }
    public function setContact(?string $v): static { $this->contact = $v; return $this; }
    public function getTelephone(): ?string { return $this->telephone; }
    public function setTelephone(?string $v): static { $this->telephone = $v; return $this; }
    public function getEmail(): ?string { return $this->email; }
    public function setEmail(?string $v): static { $this->email = $v; return $this; }
    public function getAdresse(): ?string { return $this->adresse; }
    public function setAdresse(?string $v): static { $this->adresse = $v; return $this; }
    public function getSiret(): ?string { return $this->siret; }
    public function setSiret(?string $v): static { $this->siret = $v; return $this; }
    public function getDelaiLivraisonJours(): int { return $this->delaiLivraisonJours; }
    public function setDelaiLivraisonJours(int $v): static { $this->delaiLivraisonJours = $v; return $this; }
    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $v): static { $this->notes = $v; return $this; }
    public function getIsActive(): int { return $this->isActive; }
    public function setIsActive(int $v): static { $this->isActive = $v; return $this; }
    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
    public function getCommandes(): Collection { return $this->commandes; }
}
