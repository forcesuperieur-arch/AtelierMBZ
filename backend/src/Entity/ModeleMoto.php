<?php
namespace App\Entity;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Delete;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity] #[ORM\Table(name: 'modele_motos')] #[ORM\HasLifecycleCallbacks]
#[ApiResource(
    shortName: 'ModeleMoto',
    normalizationContext: ['groups' => ['modele:read']],
    denormalizationContext: ['groups' => ['modele:write']],
    operations: [
        new GetCollection(uriTemplate: '/motos/modeles'),
        new Get(uriTemplate: '/motos/modeles/{id}'),
        new Post(uriTemplate: '/motos/modeles'),
        new Put(uriTemplate: '/motos/modeles/{id}'),
        new Delete(uriTemplate: '/motos/modeles/{id}'),
    ]
)]
class ModeleMoto
{
    #[ORM\Id] #[ORM\GeneratedValue] #[ORM\Column]
    #[Groups(['modele:read', 'categorie:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Groups(['modele:read', 'modele:write'])]
    private string $marque;

    #[ORM\Column(length: 100)]
    #[Groups(['modele:read', 'modele:write'])]
    private string $modele;

    #[ORM\ManyToOne(targetEntity: CategorieMoto::class, inversedBy: 'modeles')] #[ORM\JoinColumn(name: 'categorie_id', nullable: false)]
    #[Groups(['modele:read', 'modele:write'])]
    private CategorieMoto $categorie;

    #[ORM\Column(nullable: true)]
    #[Groups(['modele:read', 'modele:write'])]
    private ?int $cylindreeMin = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['modele:read', 'modele:write'])]
    private ?int $cylindreeMax = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['modele:read', 'modele:write'])]
    private ?int $anneeDebut = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['modele:read', 'modele:write'])]
    private ?int $anneeFin = null;

    #[ORM\Column(type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'])]
    #[Groups(['modele:read'])]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'])]
    #[Groups(['modele:read'])]
    private \DateTimeInterface $updatedAt;
    #[ORM\OneToMany(targetEntity: MotoTechnicalSpec::class, mappedBy: 'modele', cascade: ['persist', 'remove'])]
    private Collection $technicalSpecs;

    public function __construct() { $this->createdAt = new \DateTime(); $this->updatedAt = new \DateTime(); $this->technicalSpecs = new ArrayCollection(); }
    #[ORM\PreUpdate] public function preUpdate(): void { $this->updatedAt = new \DateTime(); }

    public function getId(): ?int { return $this->id; }
    public function getMarque(): string { return $this->marque; }
    public function setMarque(string $v): static { $this->marque = $v; return $this; }
    public function getModele(): string { return $this->modele; }
    public function setModele(string $v): static { $this->modele = $v; return $this; }
    public function getCategorie(): CategorieMoto { return $this->categorie; }
    public function setCategorie(CategorieMoto $v): static { $this->categorie = $v; return $this; }
    public function getCylindreeMin(): ?int { return $this->cylindreeMin; }
    public function setCylindreeMin(?int $v): static { $this->cylindreeMin = $v; return $this; }
    public function getCylindreeMax(): ?int { return $this->cylindreeMax; }
    public function setCylindreeMax(?int $v): static { $this->cylindreeMax = $v; return $this; }
    public function getAnneeDebut(): ?int { return $this->anneeDebut; }
    public function setAnneeDebut(?int $v): static { $this->anneeDebut = $v; return $this; }
    public function getAnneeFin(): ?int { return $this->anneeFin; }
    public function setAnneeFin(?int $v): static { $this->anneeFin = $v; return $this; }
    public function getTechnicalSpecs(): Collection { return $this->technicalSpecs; }

    public function getCylindreeDisplay(): string
    {
        if ($this->cylindreeMin && $this->cylindreeMax) {
            return $this->cylindreeMin === $this->cylindreeMax ? "{$this->cylindreeMin}cc" : "{$this->cylindreeMin}-{$this->cylindreeMax}cc";
        }
        return $this->cylindreeMin ? "{$this->cylindreeMin}cc+" : 'N/A';
    }
}
