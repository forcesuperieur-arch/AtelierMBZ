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

    // LOT 4 — new fields
    #[ORM\Column(length: 64, unique: true)] #[Groups(['demande:read'])]
    private string $tokenValidation;

    #[ORM\Column(type: 'json')] #[Groups(['demande:read'])]
    private array $prestationsChoisies = [];

    #[ORM\Column(type: 'text', nullable: true)] #[Groups(['demande:read'])]
    private ?string $photosJustificatives = null;

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $decisionIp = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $decisionUserAgent = null;

    #[ORM\Column(type: 'text', nullable: true)] #[Groups(['demande:read'])]
    private ?string $signatureClient = null;

    #[ORM\Column(type: 'datetime', nullable: true)] #[Groups(['demande:read'])]
    private ?\DateTimeInterface $signedAt = null;

    #[ORM\ManyToOne(targetEntity: OrdreReparation::class)] #[ORM\JoinColumn(name: 'or_complementaire_id', nullable: true)]
    #[Groups(['demande:read'])]
    private ?OrdreReparation $orComplementaire = null;

    public function __construct() {
        $this->createdAt = new \DateTime();
        $this->tokenValidation = bin2hex(random_bytes(32));
    }

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

    // LOT 4 — new getters/setters
    public function getTokenValidation(): string { return $this->tokenValidation; }
    public function setTokenValidation(string $v): static { $this->tokenValidation = $v; return $this; }
    public function getPrestationsChoisies(): array { return $this->prestationsChoisies; }
    public function setPrestationsChoisies(array $v): static { $this->prestationsChoisies = $v; return $this; }
    public function getPhotosJustificatives(): ?string { return $this->photosJustificatives; }
    public function setPhotosJustificatives(?string $v): static { $this->photosJustificatives = $v; return $this; }
    public function getDecisionIp(): ?string { return $this->decisionIp; }
    public function setDecisionIp(?string $v): static { $this->decisionIp = $v; return $this; }
    public function getDecisionUserAgent(): ?string { return $this->decisionUserAgent; }
    public function setDecisionUserAgent(?string $v): static { $this->decisionUserAgent = $v; return $this; }
    public function getSignatureClient(): ?string { return $this->signatureClient; }
    public function setSignatureClient(?string $v): static { $this->signatureClient = $v; return $this; }
    public function getSignedAt(): ?\DateTimeInterface { return $this->signedAt; }
    public function setSignedAt(?\DateTimeInterface $v): static { $this->signedAt = $v; return $this; }
    public function getOrComplementaire(): ?OrdreReparation { return $this->orComplementaire; }
    public function setOrComplementaire(?OrdreReparation $v): static { $this->orComplementaire = $v; return $this; }
}
