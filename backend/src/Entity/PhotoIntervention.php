<?php
namespace App\Entity;
use ApiPlatform\Metadata\ApiResource;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity] #[ORM\Table(name: 'photos_intervention')]
#[ORM\Index(columns: ['rendez_vous_id', 'type'], name: 'idx_photo_rdv_type')]
#[ApiResource]
class PhotoIntervention
{
    #[ORM\Id] #[ORM\GeneratedValue] #[ORM\Column] private ?int $id = null;
    #[ORM\Column(nullable: true)] private ?int $atelierId = null;
    #[ORM\ManyToOne(targetEntity: RendezVous::class, inversedBy: 'photosIntervention')] #[ORM\JoinColumn(name: 'rendez_vous_id', nullable: false)] private RendezVous $rendezVous;
    #[ORM\Column(length: 300)] private string $filename;
    #[ORM\Column(length: 300, nullable: true)] private ?string $originalName = null;
    #[ORM\Column(type: 'text', nullable: true)] private ?string $annotationJson = null;
    #[ORM\Column(type: 'text', nullable: true)] private ?string $description = null;
    #[ORM\Column(type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'])] private \DateTimeInterface $createdAt;

    // LOT 2 — type, sha256, exif, takenAt
    #[ORM\Column(length: 50, nullable: true)] private ?string $type = null;
    #[ORM\Column(length: 64, nullable: true)] private ?string $sha256 = null;
    #[ORM\Column(type: 'json', nullable: true)] private ?array $exif = null;
    #[ORM\Column(type: 'datetime', nullable: true)] private ?\DateTimeInterface $takenAt = null;

    public function __construct() { $this->createdAt = new \DateTime(); }

    public function getId(): ?int { return $this->id; }
    public function getAtelierId(): ?int { return $this->atelierId; }
    public function setAtelierId(?int $v): static { $this->atelierId = $v; return $this; }
    public function getRendezVous(): RendezVous { return $this->rendezVous; }
    public function setRendezVous(RendezVous $v): static { $this->rendezVous = $v; return $this; }
    public function getFilename(): string { return $this->filename; }
    public function setFilename(string $v): static { $this->filename = $v; return $this; }
    public function getOriginalName(): ?string { return $this->originalName; }
    public function setOriginalName(?string $v): static { $this->originalName = $v; return $this; }
    public function getAnnotationJson(): ?string { return $this->annotationJson; }
    public function setAnnotationJson(?string $v): static { $this->annotationJson = $v; return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $v): static { $this->description = $v; return $this; }

    // LOT 2 getters/setters
    public function getType(): ?string { return $this->type; }
    public function setType(?string $v): static { $this->type = $v; return $this; }
    public function getSha256(): ?string { return $this->sha256; }
    public function setSha256(?string $v): static { $this->sha256 = $v; return $this; }
    public function getExif(): ?array { return $this->exif; }
    public function setExif(?array $v): static { $this->exif = $v; return $this; }
    public function getTakenAt(): ?\DateTimeInterface { return $this->takenAt; }
    public function setTakenAt(?\DateTimeInterface $v): static { $this->takenAt = $v; return $this; }
}
