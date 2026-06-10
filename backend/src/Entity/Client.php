<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity]
#[ORM\Table(name: 'clients')]
#[ApiResource(
    normalizationContext: ['groups' => ['client:read']],
    denormalizationContext: ['groups' => ['client:write']],
)]
#[ApiFilter(SearchFilter::class, properties: ['nom' => 'partial', 'prenom' => 'partial'])]
class Client
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['client:read', 'rdv:read', 'devis:read', 'facture:read'])]
    private ?int $id = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['client:read', 'client:write'])]
    private ?int $atelierId = null;

    #[ORM\Column(length: 100)]
    #[Groups(['client:read', 'client:write', 'rdv:read', 'devis:read', 'facture:read', 'ordre:read'])]
    private string $nom;

    #[ORM\Column(length: 100)]
    #[Groups(['client:read', 'client:write', 'rdv:read', 'devis:read', 'facture:read', 'ordre:read'])]
    private string $prenom;

    #[ORM\Column(length: 20)]
    #[Groups(['client:read', 'client:write', 'rdv:read'])]
    private string $telephone;

    #[ORM\Column(length: 200, nullable: true)]
    #[Groups(['client:read', 'client:write', 'rdv:read'])]
    private ?string $email = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['client:read', 'client:write'])]
    private ?string $adresse = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Groups(['client:read', 'client:write'])]
    private ?string $segment = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['client:read', 'client:write'])]
    private ?string $notes = null;

    #[ORM\Column(type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'])]
    #[Groups(['client:read'])]
    private \DateTimeInterface $createdAt;

    // --- RGPD fields ---
    #[ORM\Column(type: 'datetime', nullable: true)]
    #[Groups(['client:read', 'client:write'])]
    private ?\DateTimeInterface $consentDate = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Groups(['client:read', 'client:write'])]
    private ?string $consentSource = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    #[Groups(['client:read'])]
    private ?\DateTimeInterface $lastActivityAt = null;

    #[ORM\Column(options: ['default' => false])]
    #[Groups(['client:read'])]
    private bool $isAnonymized = false;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $password = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $resetToken = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $resetTokenExpiresAt = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $emailVerifiedAt = null;

    /** @var Collection<int, Vehicule> */
    #[ORM\OneToMany(targetEntity: Vehicule::class, mappedBy: 'client')]
    #[Groups(['client:read'])]
    private Collection $vehicules;

    /** @var Collection<int, RendezVous> */
    #[ORM\OneToMany(targetEntity: RendezVous::class, mappedBy: 'client')]
    private Collection $rendezVous;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->vehicules = new ArrayCollection();
        $this->rendezVous = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }
    public function getAtelierId(): ?int { return $this->atelierId; }
    public function setAtelierId(?int $atelierId): static { $this->atelierId = $atelierId; return $this; }
    public function getNom(): string { return $this->nom; }
    public function setNom(string $nom): static { $this->nom = $nom; return $this; }
    public function getPrenom(): string { return $this->prenom; }
    public function setPrenom(string $prenom): static { $this->prenom = $prenom; return $this; }
    public function getTelephone(): string { return $this->telephone; }
    public function setTelephone(string $telephone): static { $this->telephone = $telephone; return $this; }
    public function getEmail(): ?string { return $this->email; }
    public function setEmail(?string $email): static { $this->email = $email; return $this; }
    public function getAdresse(): ?string { return $this->adresse; }
    public function setAdresse(?string $adresse): static { $this->adresse = $adresse; return $this; }
    public function getSegment(): ?string { return $this->segment; }
    public function setSegment(?string $segment): static { $this->segment = $segment; return $this; }
    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $notes): static { $this->notes = $notes; return $this; }
    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
    public function getVehicules(): Collection { return $this->vehicules; }
    public function getRendezVous(): Collection { return $this->rendezVous; }

    // --- RGPD ---
    public function getConsentDate(): ?\DateTimeInterface { return $this->consentDate; }
    public function setConsentDate(?\DateTimeInterface $v): static { $this->consentDate = $v; return $this; }
    public function getConsentSource(): ?string { return $this->consentSource; }
    public function setConsentSource(?string $v): static { $this->consentSource = $v; return $this; }
    public function getLastActivityAt(): ?\DateTimeInterface { return $this->lastActivityAt; }
    public function setLastActivityAt(?\DateTimeInterface $v): static { $this->lastActivityAt = $v; return $this; }
    public function getIsAnonymized(): bool { return $this->isAnonymized; }
    public function setIsAnonymized(bool $v): static { $this->isAnonymized = $v; return $this; }
    public function touchActivity(): void { $this->lastActivityAt = new \DateTime(); }

    public function getPassword(): ?string { return $this->password; }
    public function setPassword(?string $v): static { $this->password = $v; return $this; }
    public function getResetToken(): ?string { return $this->resetToken; }
    public function setResetToken(?string $v): static { $this->resetToken = $v; return $this; }
    public function getResetTokenExpiresAt(): ?\DateTimeInterface { return $this->resetTokenExpiresAt; }
    public function setResetTokenExpiresAt(?\DateTimeInterface $v): static { $this->resetTokenExpiresAt = $v; return $this; }
    public function getEmailVerifiedAt(): ?\DateTimeInterface { return $this->emailVerifiedAt; }
    public function setEmailVerifiedAt(?\DateTimeInterface $v): static { $this->emailVerifiedAt = $v; return $this; }
}
