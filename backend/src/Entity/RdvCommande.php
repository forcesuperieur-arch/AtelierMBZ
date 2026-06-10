<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity]
#[ORM\Table(name: 'rdv_commandes')]
#[ORM\Index(columns: ['rdv_id'], name: 'idx_rdv_commande_rdv')]
#[ApiResource(
    operations: [
        new GetCollection(security: "is_granted('ROLE_USER')"),
        new Get(security: "is_granted('ROLE_USER')"),
        new Post(security: "is_granted('ROLE_ADMIN')"),
        new Patch(security: "is_granted('ROLE_ADMIN')"),
        new Delete(security: "is_granted('ROLE_ADMIN')"),
    ],
    normalizationContext: ['groups' => ['rdv_commande:read']],
    denormalizationContext: ['groups' => ['rdv_commande:write']],
)]
class RdvCommande
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['rdv_commande:read', 'rdv:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: RendezVous::class, inversedBy: 'commandes')]
    #[ORM\JoinColumn(name: 'rdv_id', nullable: false)]
    #[Groups(['rdv_commande:read', 'rdv_commande:write'])]
    private ?RendezVous $rendezVous = null;

    #[ORM\Column(length: 100)]
    #[Groups(['rdv_commande:read', 'rdv_commande:write', 'rdv:read'])]
    private string $numero = '';

    #[ORM\Column(type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'])]
    #[Groups(['rdv_commande:read'])]
    private \DateTimeInterface $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }

    public function getRendezVous(): ?RendezVous { return $this->rendezVous; }
    public function setRendezVous(?RendezVous $rendezVous): static { $this->rendezVous = $rendezVous; return $this; }

    public function getNumero(): string { return $this->numero; }
    public function setNumero(string $numero): static { $this->numero = $numero; return $this; }

    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
}
