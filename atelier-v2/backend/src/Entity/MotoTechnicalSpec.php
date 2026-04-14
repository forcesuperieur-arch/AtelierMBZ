<?php
namespace App\Entity;
use ApiPlatform\Metadata\ApiResource;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity] #[ORM\Table(name: 'moto_technical_specs')] #[ORM\HasLifecycleCallbacks] #[ApiResource]
class MotoTechnicalSpec
{
    #[ORM\Id] #[ORM\GeneratedValue] #[ORM\Column] private ?int $id = null;
    #[ORM\ManyToOne(targetEntity: ModeleMoto::class, inversedBy: 'technicalSpecs')] #[ORM\JoinColumn(name: 'modele_moto_id', nullable: false)] private ModeleMoto $modele;
    #[ORM\Column(length: 150, nullable: true)] private ?string $variante = null;
    #[ORM\Column] private int $anneeDebut;
    #[ORM\Column(nullable: true)] private ?int $anneeFin = null;
    #[ORM\Column(length: 255, nullable: true)] private ?string $source = null;
    #[ORM\Column(type: 'text', options: ['default' => '{}'])] private string $generalJson = '{}';
    #[ORM\Column(type: 'text', options: ['default' => '{}'])] private string $moteurJson = '{}';
    #[ORM\Column(type: 'text', options: ['default' => '{}'])] private string $pneumatiqueJson = '{}';
    #[ORM\Column(type: 'text', options: ['default' => '{}'])] private string $freinageJson = '{}';
    #[ORM\Column(type: 'text', options: ['default' => '{}'])] private string $suspensionJson = '{}';
    #[ORM\Column(type: 'text', options: ['default' => '{}'])] private string $systemesElectriquesJson = '{}';
    #[ORM\Column(type: 'text', options: ['default' => '{}'])] private string $entretienJson = '{}';
    #[ORM\Column(type: 'text', nullable: true)] private ?string $notes = null;
    #[ORM\Column(type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'])] private \DateTimeInterface $createdAt;
    #[ORM\Column(type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'])] private \DateTimeInterface $updatedAt;

    public function __construct() { $this->createdAt = new \DateTime(); $this->updatedAt = new \DateTime(); }
    #[ORM\PreUpdate] public function preUpdate(): void { $this->updatedAt = new \DateTime(); }

    public function getId(): ?int { return $this->id; }
    public function getModele(): ModeleMoto { return $this->modele; }
    public function setModele(ModeleMoto $v): static { $this->modele = $v; return $this; }
    public function getVariante(): ?string { return $this->variante; }
    public function setVariante(?string $v): static { $this->variante = $v; return $this; }
    public function getAnneeDebut(): int { return $this->anneeDebut; }
    public function setAnneeDebut(int $v): static { $this->anneeDebut = $v; return $this; }
    public function getAnneeFin(): ?int { return $this->anneeFin; }
    public function setAnneeFin(?int $v): static { $this->anneeFin = $v; return $this; }
    public function getSource(): ?string { return $this->source; }
    public function setSource(?string $v): static { $this->source = $v; return $this; }
    public function getGeneralJson(): string { return $this->generalJson; }
    public function setGeneralJson(string $v): static { $this->generalJson = $v; return $this; }
    public function getMoteurJson(): string { return $this->moteurJson; }
    public function setMoteurJson(string $v): static { $this->moteurJson = $v; return $this; }
    public function getPneumatiqueJson(): string { return $this->pneumatiqueJson; }
    public function setPneumatiqueJson(string $v): static { $this->pneumatiqueJson = $v; return $this; }
    public function getFreinageJson(): string { return $this->freinageJson; }
    public function setFreinageJson(string $v): static { $this->freinageJson = $v; return $this; }
    public function getSuspensionJson(): string { return $this->suspensionJson; }
    public function setSuspensionJson(string $v): static { $this->suspensionJson = $v; return $this; }
    public function getSystemesElectriquesJson(): string { return $this->systemesElectriquesJson; }
    public function setSystemesElectriquesJson(string $v): static { $this->systemesElectriquesJson = $v; return $this; }
    public function getEntretienJson(): string { return $this->entretienJson; }
    public function setEntretienJson(string $v): static { $this->entretienJson = $v; return $this; }
    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $v): static { $this->notes = $v; return $this; }

    public function getGeneral(): array { return json_decode($this->generalJson, true) ?: []; }
    public function getMoteur(): array { return json_decode($this->moteurJson, true) ?: []; }
    public function getPneumatique(): array { return json_decode($this->pneumatiqueJson, true) ?: []; }
    public function getFreinage(): array { return json_decode($this->freinageJson, true) ?: []; }
    public function getSuspension(): array { return json_decode($this->suspensionJson, true) ?: []; }
    public function getSystemesElectriques(): array { return json_decode($this->systemesElectriquesJson, true) ?: []; }
    public function getEntretien(): array { return json_decode($this->entretienJson, true) ?: []; }
}
