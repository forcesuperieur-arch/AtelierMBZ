<?php
namespace App\Entity;
use ApiPlatform\Metadata\ApiResource;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity] #[ORM\Table(name: 'demandes_travaux_supp')]
#[ApiResource(
    normalizationContext: ['groups' => ['demande:read']],
    denormalizationContext: ['groups' => ['demande:write']],
)]
class DemandeTravauxSupp
{
    #[ORM\Id] #[ORM\GeneratedValue] #[ORM\Column] #[Groups(['demande:read', 'rdv:read'])] private ?int $id = null;
    #[ORM\ManyToOne(targetEntity: RendezVous::class, inversedBy: 'demandesTravauxSupp')] #[ORM\JoinColumn(name: 'rendez_vous_id', nullable: false)] #[Groups(['demande:read', 'demande:write'])] private RendezVous $rendezVous;
    #[ORM\Column(type: 'text', nullable: true)] #[Groups(['demande:read', 'demande:write'])] private ?string $description = null;
    #[ORM\Column(type: 'text', nullable: true)] #[Groups(['demande:read', 'demande:write'])] private ?string $prestationsDemandees = null;
    #[ORM\Column(length: 50, options: ['default' => 'normal'])] #[Groups(['demande:read', 'demande:write'])] private string $urgence = 'normal';
    #[ORM\Column(nullable: true)] #[Groups(['demande:read', 'demande:write'])] private ?int $tempsEstime = null;
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)] #[Groups(['demande:read', 'demande:write'])] private ?string $prixEstime = null;
    #[ORM\Column(length: 50, options: ['default' => 'en_attente'])] #[Groups(['demande:read', 'demande:write'])] private string $statut = 'en_attente';
    #[ORM\Column(type: 'text', nullable: true)] #[Groups(['demande:read', 'demande:write'])] private ?string $notesReceptionniste = null;
    #[ORM\Column(length: 50, nullable: true)] #[Groups(['demande:read', 'demande:write'])] private ?string $decisionClient = null;
    #[ORM\Column(type: 'datetime', nullable: true)] #[Groups(['demande:read'])] private ?\DateTimeInterface $decisionClientAt = null;
    #[ORM\Column(type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'])] #[Groups(['demande:read'])] private \DateTimeInterface $createdAt;
    #[ORM\Column(type: 'datetime', nullable: true)] #[Groups(['demande:read'])] private ?\DateTimeInterface $approvedAt = null;
    #[ORM\Column(nullable: true)] #[Groups(['demande:read'])] private ?int $approvedBy = null;

    public function __construct() { $this->createdAt = new \DateTime(); }

    public function getId(): ?int { return $this->id; }
    public function getRendezVous(): RendezVous { return $this->rendezVous; }
    public function setRendezVous(RendezVous $v): static { $this->rendezVous = $v; return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $v): static { $this->description = $v; return $this; }
    public function getPrestationsDemandees(): ?string { return $this->prestationsDemandees; }
    public function setPrestationsDemandees(?string $v): static { $this->prestationsDemandees = $v; return $this; }
    public function getUrgence(): string { return $this->urgence; }
    public function setUrgence(string $v): static { $this->urgence = $v; return $this; }
    public function getTempsEstime(): ?int { return $this->tempsEstime; }
    public function setTempsEstime(?int $v): static { $this->tempsEstime = $v; return $this; }
    public function getPrixEstime(): ?string { return $this->prixEstime; }
    public function setPrixEstime(?string $v): static { $this->prixEstime = $v; return $this; }
    public function getStatut(): string { return $this->statut; }
    public function setStatut(string $v): static { $this->statut = $v; return $this; }
    public function getNotesReceptionniste(): ?string { return $this->notesReceptionniste; }
    public function setNotesReceptionniste(?string $v): static { $this->notesReceptionniste = $v; return $this; }
    public function getDecisionClient(): ?string { return $this->decisionClient; }
    public function setDecisionClient(?string $v): static { $this->decisionClient = $v; return $this; }
    public function getDecisionClientAt(): ?\DateTimeInterface { return $this->decisionClientAt; }
    public function setDecisionClientAt(?\DateTimeInterface $v): static { $this->decisionClientAt = $v; return $this; }
    public function getApprovedAt(): ?\DateTimeInterface { return $this->approvedAt; }
    public function setApprovedAt(?\DateTimeInterface $v): static { $this->approvedAt = $v; return $this; }
    public function getApprovedBy(): ?int { return $this->approvedBy; }
    public function setApprovedBy(?int $v): static { $this->approvedBy = $v; return $this; }
}
