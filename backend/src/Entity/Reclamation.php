<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Réclamation client suivie par le SRC (PILOTE_PLAN.md Lot C4) : "pas de CRM complet, un cahier
 * de bord" — un statut simple + des notes horodatées qui s'accumulent, pas de workflow complexe.
 * PAS de #[ApiResource] : la création/mutation passe exclusivement par CockpitFileTravailController
 * (rôle SRC, garde de rôle, notes en append-only via addNote()).
 */
#[ORM\Entity]
#[ORM\Table(name: 'reclamations')]
#[ORM\HasLifecycleCallbacks]
class Reclamation
{
    public const STATUTS = ['nouveau', 'en_cours', 'clos'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(nullable: true)]
    private ?int $atelierId = null;

    #[ORM\ManyToOne(targetEntity: Client::class)]
    #[ORM\JoinColumn(name: 'client_id', nullable: false)]
    private Client $client;

    #[ORM\ManyToOne(targetEntity: RendezVous::class)]
    #[ORM\JoinColumn(name: 'rendez_vous_id', nullable: true)]
    private ?RendezVous $rendezVous = null;

    #[ORM\Column(length: 500)]
    private string $sujet;

    #[ORM\Column(length: 20, options: ['default' => 'nouveau'])]
    private string $statut = 'nouveau';

    /** Cahier de bord : chaque entrée { auteur, date, texte } — append-only, jamais réécrit. */
    #[ORM\Column(type: 'json')]
    private array $notes = [];

    #[ORM\Column(type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'])]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'])]
    private \DateTimeInterface $updatedAt;

    #[ORM\Column(nullable: true)]
    private ?int $createdBy = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    #[ORM\PreUpdate]
    public function preUpdate(): void
    {
        $this->updatedAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }
    public function getAtelierId(): ?int { return $this->atelierId; }
    public function setAtelierId(?int $v): static { $this->atelierId = $v; return $this; }
    public function getClient(): Client { return $this->client; }
    public function setClient(Client $v): static { $this->client = $v; return $this; }
    public function getRendezVous(): ?RendezVous { return $this->rendezVous; }
    public function setRendezVous(?RendezVous $v): static { $this->rendezVous = $v; return $this; }
    public function getSujet(): string { return $this->sujet; }
    public function setSujet(string $v): static { $this->sujet = $v; return $this; }
    public function getStatut(): string { return $this->statut; }

    public function setStatut(string $statut): static
    {
        if (!in_array($statut, self::STATUTS, true)) {
            throw new \InvalidArgumentException(sprintf('Statut de réclamation invalide : %s', $statut));
        }
        $this->statut = $statut;

        return $this;
    }

    public function getNotes(): array { return $this->notes; }

    public function addNote(string $texte, ?int $auteurId, ?string $auteurNom): static
    {
        $this->notes[] = [
            'texte' => $texte,
            'auteur_id' => $auteurId,
            'auteur_nom' => $auteurNom,
            'date' => (new \DateTime())->format('c'),
        ];

        return $this;
    }

    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeInterface { return $this->updatedAt; }
    public function getCreatedBy(): ?int { return $this->createdBy; }
    public function setCreatedBy(?int $v): static { $this->createdBy = $v; return $this; }
}
