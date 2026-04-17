<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'commande_piece')]
#[ORM\Index(columns: ['rendez_vous_id', 'statut'], name: 'idx_commande_piece_rdv_statut')]
class CommandePiece
{
    public const STATUTS = [
        'a_commander', 'commandee', 'expediee', 'recue', 'installee', 'annulee', 'retour_fournisseur',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(nullable: true)]
    private ?int $atelierId = null;

    #[ORM\ManyToOne(targetEntity: RendezVous::class)]
    #[ORM\JoinColumn(nullable: false)]
    private RendezVous $rendezVous;

    #[ORM\ManyToOne(targetEntity: OrdreReparation::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?OrdreReparation $ordreReparation = null;

    #[ORM\Column(length: 100)]
    private string $reference;

    #[ORM\Column(length: 255)]
    private string $designation;

    #[ORM\Column(options: ['default' => 1])]
    private int $quantite = 1;

    #[ORM\Column(length: 200, nullable: true)]
    private ?string $fournisseur = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $numeroCommandeFournisseur = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $prixAchat = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $prixVente = null;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $dateCommande;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $dateLivraisonEstimee = null;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $dateLivraisonReelle = null;

    #[ORM\Column(length: 30, options: ['default' => 'a_commander'])]
    private string $statut = 'a_commander';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $clientNotifieAt = null;

    #[ORM\Column(nullable: true)]
    private ?int $notifiedBy = null;

    public function __construct()
    {
        $this->dateCommande = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }
    public function getAtelierId(): ?int { return $this->atelierId; }
    public function setAtelierId(?int $v): static { $this->atelierId = $v; return $this; }
    public function getRendezVous(): RendezVous { return $this->rendezVous; }
    public function setRendezVous(RendezVous $v): static { $this->rendezVous = $v; return $this; }
    public function getOrdreReparation(): ?OrdreReparation { return $this->ordreReparation; }
    public function setOrdreReparation(?OrdreReparation $v): static { $this->ordreReparation = $v; return $this; }
    public function getReference(): string { return $this->reference; }
    public function setReference(string $v): static { $this->reference = $v; return $this; }
    public function getDesignation(): string { return $this->designation; }
    public function setDesignation(string $v): static { $this->designation = $v; return $this; }
    public function getQuantite(): int { return $this->quantite; }
    public function setQuantite(int $v): static { $this->quantite = $v; return $this; }
    public function getFournisseur(): ?string { return $this->fournisseur; }
    public function setFournisseur(?string $v): static { $this->fournisseur = $v; return $this; }
    public function getNumeroCommandeFournisseur(): ?string { return $this->numeroCommandeFournisseur; }
    public function setNumeroCommandeFournisseur(?string $v): static { $this->numeroCommandeFournisseur = $v; return $this; }
    public function getPrixAchat(): ?string { return $this->prixAchat; }
    public function setPrixAchat(?string $v): static { $this->prixAchat = $v; return $this; }
    public function getPrixVente(): ?string { return $this->prixVente; }
    public function setPrixVente(?string $v): static { $this->prixVente = $v; return $this; }
    public function getDateCommande(): \DateTimeInterface { return $this->dateCommande; }
    public function setDateCommande(\DateTimeInterface $v): static { $this->dateCommande = $v; return $this; }
    public function getDateLivraisonEstimee(): ?\DateTimeInterface { return $this->dateLivraisonEstimee; }
    public function setDateLivraisonEstimee(?\DateTimeInterface $v): static { $this->dateLivraisonEstimee = $v; return $this; }
    public function getDateLivraisonReelle(): ?\DateTimeInterface { return $this->dateLivraisonReelle; }
    public function setDateLivraisonReelle(?\DateTimeInterface $v): static { $this->dateLivraisonReelle = $v; return $this; }
    public function getStatut(): string { return $this->statut; }
    public function setStatut(string $v): static { $this->statut = $v; return $this; }
    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $v): static { $this->notes = $v; return $this; }
    public function getClientNotifieAt(): ?\DateTimeInterface { return $this->clientNotifieAt; }
    public function setClientNotifieAt(?\DateTimeInterface $v): static { $this->clientNotifieAt = $v; return $this; }
    public function getNotifiedBy(): ?int { return $this->notifiedBy; }
    public function setNotifiedBy(?int $v): static { $this->notifiedBy = $v; return $this; }
}
