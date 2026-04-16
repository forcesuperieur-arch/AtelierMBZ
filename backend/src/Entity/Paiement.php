<?php
namespace App\Entity;
use ApiPlatform\Metadata\ApiResource;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity] #[ORM\Table(name: 'paiements')]
#[ApiResource(
    normalizationContext: ['groups' => ['paiement:read']],
    denormalizationContext: ['groups' => ['paiement:write']],
)]
class Paiement
{
    #[ORM\Id] #[ORM\GeneratedValue] #[ORM\Column] #[Groups(['paiement:read', 'facture:read'])] private ?int $id = null;
    #[ORM\ManyToOne(targetEntity: Facture::class, inversedBy: 'paiements')] #[ORM\JoinColumn(name: 'facture_id', nullable: true)] #[Groups(['paiement:read', 'paiement:write'])] private ?Facture $facture = null;
    #[ORM\ManyToOne(targetEntity: VOFacture::class, inversedBy: 'paiements')] #[ORM\JoinColumn(name: 'vo_facture_id', nullable: true)] #[Groups(['paiement:read', 'paiement:write'])] private ?VOFacture $voFacture = null;
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)] #[Groups(['paiement:read', 'paiement:write', 'facture:read'])] private string $montant;
    #[ORM\Column(length: 50)] #[Groups(['paiement:read', 'paiement:write', 'facture:read'])] private string $modePaiement;
    #[ORM\Column(length: 200, nullable: true)] #[Groups(['paiement:read', 'paiement:write', 'facture:read'])] private ?string $reference = null;
    #[ORM\Column(type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'])] #[Groups(['paiement:read', 'paiement:write', 'facture:read'])] private \DateTimeInterface $datePaiement;
    #[ORM\Column(type: 'text', nullable: true)] #[Groups(['paiement:read', 'paiement:write', 'facture:read'])] private ?string $notes = null;
    #[ORM\Column(type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'])] #[Groups(['paiement:read'])] private \DateTimeInterface $createdAt;

    public function __construct() { $this->datePaiement = new \DateTime(); $this->createdAt = new \DateTime(); }

    public function getId(): ?int { return $this->id; }
    public function getFacture(): ?Facture { return $this->facture; }
    public function setFacture(?Facture $v): static { $this->facture = $v; return $this; }
    public function getVoFacture(): ?VOFacture { return $this->voFacture; }
    public function setVoFacture(?VOFacture $v): static { $this->voFacture = $v; return $this; }
    public function getMontant(): string { return $this->montant; }
    public function setMontant(string $v): static { $this->montant = $v; return $this; }
    public function getModePaiement(): string { return $this->modePaiement; }
    public function setModePaiement(string $v): static { $this->modePaiement = $v; return $this; }
    public function getReference(): ?string { return $this->reference; }
    public function setReference(?string $v): static { $this->reference = $v; return $this; }
    public function getDatePaiement(): \DateTimeInterface { return $this->datePaiement; }
    public function setDatePaiement(\DateTimeInterface $v): static { $this->datePaiement = $v; return $this; }
    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $v): static { $this->notes = $v; return $this; }
}
