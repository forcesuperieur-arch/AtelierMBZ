<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use App\Entity\Trait\VOTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity]
#[ORM\Table(name: 'vehicules')]
#[ORM\UniqueConstraint(name: 'uniq_vehicule_vin_atelier', columns: ['vin', 'atelier_id'])]
#[UniqueEntity(fields: ['vin', 'atelierId'], ignoreNull: true)]
#[ApiResource(
    normalizationContext: ['groups' => ['vehicule:read']],
    denormalizationContext: ['groups' => ['vehicule:write']],
)]
#[ApiFilter(SearchFilter::class, properties: ['plaque' => 'exact', 'marque' => 'partial', 'modele' => 'partial'])]
class Vehicule
{
    use VOTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['vehicule:read', 'rdv:read', 'client:read'])]
    private ?int $id = null;

    #[ORM\Column(nullable: true)]
    private ?int $atelierId = null;

    #[ORM\Column(length: 20)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 20)]
    #[Groups(['vehicule:read', 'vehicule:write', 'rdv:read', 'client:read'])]
    private string $plaque;

    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\Length(max: 100)]
    #[Groups(['vehicule:read', 'vehicule:write', 'rdv:read', 'client:read', 'ordre:read'])]
    private ?string $marque = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\Length(max: 100)]
    #[Groups(['vehicule:read', 'vehicule:write', 'rdv:read', 'client:read', 'ordre:read'])]
    private ?string $modele = null;

    #[ORM\Column(nullable: true)]
    #[Assert\Range(min: 1900, max: 2100)]
    #[Groups(['vehicule:read', 'vehicule:write'])]
    private ?int $annee = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Groups(['vehicule:read', 'vehicule:write'])]
    private ?string $cylindree = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Groups(['vehicule:read', 'vehicule:write'])]
    private ?string $typeMoto = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Groups(['vehicule:read', 'vehicule:write'])]
    private ?string $typeVariante = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Groups(['vehicule:read', 'vehicule:write'])]
    private ?string $denominationCommerciale = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Groups(['vehicule:read', 'vehicule:write'])]
    private ?string $genreNational = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Groups(['vehicule:read', 'vehicule:write'])]
    private ?string $numeroFormuleCg = null;

    #[ORM\ManyToOne(targetEntity: Client::class, inversedBy: 'vehicules')]
    #[ORM\JoinColumn(name: 'client_id', nullable: true)]
    #[Groups(['vehicule:read', 'vehicule:write'])]
    private ?Client $client = null;

    #[ORM\ManyToOne(targetEntity: CategorieMoto::class)]
    #[ORM\JoinColumn(name: 'categorie_id', nullable: true)]
    #[Groups(['vehicule:read', 'vehicule:write'])]
    private ?CategorieMoto $categorie = null;

    #[ORM\ManyToOne(targetEntity: ModeleMoto::class)]
    #[ORM\JoinColumn(name: 'modele_id', nullable: true)]
    #[Groups(['vehicule:read', 'vehicule:write'])]
    private ?ModeleMoto $modeleRef = null;

    /** @var Collection<int, RendezVous> */
    #[ORM\OneToMany(targetEntity: RendezVous::class, mappedBy: 'vehicule')]
    private Collection $rendezVous;

    public function __construct()
    {
        $this->rendezVous = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }
    public function getAtelierId(): ?int { return $this->atelierId; }
    public function setAtelierId(?int $atelierId): static { $this->atelierId = $atelierId; return $this; }
    public function getPlaque(): string { return $this->plaque; }
    public function setPlaque(string $plaque): static { $this->plaque = $plaque; return $this; }
    public function getMarque(): ?string { return $this->marque; }
    public function setMarque(?string $marque): static { $this->marque = $marque; return $this; }
    public function getModele(): ?string { return $this->modele; }
    public function setModele(?string $modele): static { $this->modele = $modele; return $this; }
    public function getAnnee(): ?int { return $this->annee; }
    public function setAnnee(?int $annee): static { $this->annee = $annee; return $this; }
    public function getCylindree(): ?string { return $this->cylindree; }
    public function setCylindree(?string $cylindree): static { $this->cylindree = $cylindree; return $this; }
    public function getTypeMoto(): ?string { return $this->typeMoto; }
    public function setTypeMoto(?string $typeMoto): static { $this->typeMoto = $typeMoto; return $this; }
    public function getTypeVariante(): ?string { return $this->typeVariante; }
    public function setTypeVariante(?string $v): static { $this->typeVariante = $v; return $this; }
    public function getDenominationCommerciale(): ?string { return $this->denominationCommerciale; }
    public function setDenominationCommerciale(?string $v): static { $this->denominationCommerciale = $v; return $this; }
    public function getGenreNational(): ?string { return $this->genreNational; }
    public function setGenreNational(?string $v): static { $this->genreNational = $v; return $this; }
    public function getNumeroFormuleCg(): ?string { return $this->numeroFormuleCg; }
    public function setNumeroFormuleCg(?string $v): static { $this->numeroFormuleCg = $v; return $this; }
    public function getClient(): ?Client { return $this->client; }
    public function setClient(?Client $client): static { $this->client = $client; return $this; }
    public function getCategorie(): ?CategorieMoto { return $this->categorie; }
    public function setCategorie(?CategorieMoto $categorie): static { $this->categorie = $categorie; return $this; }
    public function getModeleRef(): ?ModeleMoto { return $this->modeleRef; }
    public function setModeleRef(?ModeleMoto $modeleRef): static { $this->modeleRef = $modeleRef; return $this; }
    public function getRendezVous(): Collection { return $this->rendezVous; }
}
