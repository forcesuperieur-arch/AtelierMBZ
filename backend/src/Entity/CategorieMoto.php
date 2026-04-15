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

#[ORM\Entity] #[ORM\Table(name: 'categorie_motos')]
#[ApiResource(
    shortName: 'CategorieMoto',
    normalizationContext: ['groups' => ['categorie:read']],
    denormalizationContext: ['groups' => ['categorie:write']],
    operations: [
        new GetCollection(uriTemplate: '/motos/categories'),
        new Get(uriTemplate: '/motos/categories/{id}'),
        new Post(uriTemplate: '/motos/categories'),
        new Put(uriTemplate: '/motos/categories/{id}'),
        new Delete(uriTemplate: '/motos/categories/{id}'),
    ]
)]
class CategorieMoto
{
    #[ORM\Id] #[ORM\GeneratedValue] #[ORM\Column]
    #[Groups(['categorie:read', 'modele:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 100, unique: true)]
    #[Groups(['categorie:read', 'categorie:write', 'modele:read'])]
    private string $nom;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['categorie:read', 'categorie:write'])]
    private ?string $description = null;

    #[ORM\Column(options: ['default' => 1])]
    #[Groups(['categorie:read', 'categorie:write'])]
    private int $isActive = 1;

    #[ORM\Column(type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'])]
    #[Groups(['categorie:read'])]
    private \DateTimeInterface $createdAt;

    #[ORM\OneToMany(targetEntity: ModeleMoto::class, mappedBy: 'categorie')]
    #[Groups(['categorie:read'])]
    private Collection $modeles;

    public function __construct() { $this->createdAt = new \DateTime(); $this->modeles = new ArrayCollection(); }

    public function getId(): ?int { return $this->id; }
    public function getNom(): string { return $this->nom; }
    public function setNom(string $v): static { $this->nom = $v; return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $v): static { $this->description = $v; return $this; }
    public function getIsActive(): int { return $this->isActive; }
    public function setIsActive(int $v): static { $this->isActive = $v; return $this; }
    public function getModeles(): Collection { return $this->modeles; }
}
