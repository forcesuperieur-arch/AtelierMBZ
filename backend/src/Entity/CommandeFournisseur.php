<?php
namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity] #[ORM\Table(name: 'commandes_fournisseur')] #[ApiResource]
class CommandeFournisseur
{
    #[ORM\Id] #[ORM\GeneratedValue] #[ORM\Column] #[Groups(['commande:read'])] private ?int $id = null;
    #[ORM\Column(nullable: true)] private ?int $atelierId = null;
    #[ORM\Column(length: 50, unique: true)] #[Groups(['commande:read'])] private string $numeroCommande;
    #[ORM\ManyToOne(targetEntity: Fournisseur::class, inversedBy: 'commandes')]
    #[ORM\JoinColumn(name: 'fournisseur_id', nullable: false)] #[Groups(['commande:read'])] private Fournisseur $fournisseur;
    #[ORM\Column(length: 50, options: ['default' => 'en_attente'])] #[Groups(['commande:read'])] private string $statut = 'en_attente';
    #[ORM\Column(type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'])] #[Groups(['commande:read'])] private \DateTimeInterface $dateCommande;
    #[ORM\Column(type: 'datetime', nullable: true)] #[Groups(['commande:read'])] private ?\DateTimeInterface $datePrevueLivraison = null;
    #[ORM\Column(type: 'datetime', nullable: true)] #[Groups(['commande:read'])] private ?\DateTimeInterface $dateReception = null;
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, options: ['default' => '0.00'])] #[Groups(['commande:read'])] private string $totalHt = '0.00';
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, options: ['default' => '0.00'])] #[Groups(['commande:read'])] private string $totalTtc = '0.00';
    #[ORM\Column(type: 'text', nullable: true)] #[Groups(['commande:read'])] private ?string $notes = null;
    #[ORM\OneToMany(targetEntity: LigneCommandeFournisseur::class, mappedBy: 'commande', cascade: ['persist', 'remove'])]
    #[Groups(['commande:read'])] private Collection $lignes;

    public function __construct() { $this->dateCommande = new \DateTime(); $this->lignes = new ArrayCollection(); }

    public function getId(): ?int { return $this->id; }
    public function getAtelierId(): ?int { return $this->atelierId; }
    public function setAtelierId(?int $v): static { $this->atelierId = $v; return $this; }
    public function getNumeroCommande(): string { return $this->numeroCommande; }
    public function setNumeroCommande(string $v): static { $this->numeroCommande = $v; return $this; }
    public function getFournisseur(): Fournisseur { return $this->fournisseur; }
    public function setFournisseur(Fournisseur $v): static { $this->fournisseur = $v; return $this; }
    public function getStatut(): string { return $this->statut; }
    public function setStatut(string $v): static { $this->statut = $v; return $this; }
    public function getDateCommande(): \DateTimeInterface { return $this->dateCommande; }
    public function setDateCommande(\DateTimeInterface $v): static { $this->dateCommande = $v; return $this; }
    public function getDatePrevueLivraison(): ?\DateTimeInterface { return $this->datePrevueLivraison; }
    public function setDatePrevueLivraison(?\DateTimeInterface $v): static { $this->datePrevueLivraison = $v; return $this; }
    public function getDateReception(): ?\DateTimeInterface { return $this->dateReception; }
    public function setDateReception(?\DateTimeInterface $v): static { $this->dateReception = $v; return $this; }
    public function getTotalHt(): string { return $this->totalHt; }
    public function setTotalHt(string $v): static { $this->totalHt = $v; return $this; }
    public function getTotalTtc(): string { return $this->totalTtc; }
    public function setTotalTtc(string $v): static { $this->totalTtc = $v; return $this; }
    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $v): static { $this->notes = $v; return $this; }
    public function getLignes(): Collection { return $this->lignes; }
}
