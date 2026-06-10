<?php
namespace App\Entity;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity] #[ORM\Table(name: 'horaires_atelier')] #[ORM\HasLifecycleCallbacks]
#[ApiResource(
    normalizationContext: ['groups' => ['horaire:read']],
    denormalizationContext: ['groups' => ['horaire:write']],
    operations: [
        new GetCollection(security: "is_granted('ROLE_USER')"),
        new Get(security: "is_granted('ROLE_USER')"),
        new Post(security: "is_granted('ROLE_ADMIN')"),
        new Patch(security: "is_granted('ROLE_ADMIN')"),
        new Delete(security: "is_granted('ROLE_ADMIN')"),
    ]
)]
class HoraireAtelier
{
    #[ORM\Id] #[ORM\GeneratedValue] #[ORM\Column] #[Groups(['horaire:read'])] private ?int $id = null;
    #[ORM\Column(nullable: true)] private ?int $atelierId = null;
    #[ORM\Column] #[Groups(['horaire:read', 'horaire:write'])] private int $jourSemaine;
    #[ORM\Column(length: 5, nullable: true)] #[Groups(['horaire:read', 'horaire:write'])] private ?string $heureOuverture = null;
    #[ORM\Column(length: 5, nullable: true)] #[Groups(['horaire:read', 'horaire:write'])] private ?string $heureFermeture = null;
    #[ORM\Column(length: 5, nullable: true)] #[Groups(['horaire:read', 'horaire:write'])] private ?string $pauseDebut = null;
    #[ORM\Column(length: 5, nullable: true)] #[Groups(['horaire:read', 'horaire:write'])] private ?string $pauseFin = null;
    #[ORM\Column(options: ['default' => 1])] #[Groups(['horaire:read', 'horaire:write'])] private int $isOuvert = 1;
    #[ORM\Column(type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'])] #[Groups(['horaire:read'])] private \DateTimeInterface $updatedAt;

    public function __construct() { $this->updatedAt = new \DateTime(); }
    #[ORM\PreUpdate] public function preUpdate(): void { $this->updatedAt = new \DateTime(); }

    public function getId(): ?int { return $this->id; }
    public function getAtelierId(): ?int { return $this->atelierId; }
    public function setAtelierId(?int $v): static { $this->atelierId = $v; return $this; }
    public function getJourSemaine(): int { return $this->jourSemaine; }
    public function setJourSemaine(int $v): static { $this->jourSemaine = $v; return $this; }
    public function getHeureOuverture(): ?string { return $this->heureOuverture; }
    public function setHeureOuverture(?string $v): static { $this->heureOuverture = $v; return $this; }
    public function getHeureFermeture(): ?string { return $this->heureFermeture; }
    public function setHeureFermeture(?string $v): static { $this->heureFermeture = $v; return $this; }
    public function getPauseDebut(): ?string { return $this->pauseDebut; }
    public function setPauseDebut(?string $v): static { $this->pauseDebut = $v; return $this; }
    public function getPauseFin(): ?string { return $this->pauseFin; }
    public function setPauseFin(?string $v): static { $this->pauseFin = $v; return $this; }
    public function getIsOuvert(): int { return $this->isOuvert; }
    public function setIsOuvert(int $v): static { $this->isOuvert = $v; return $this; }
}
