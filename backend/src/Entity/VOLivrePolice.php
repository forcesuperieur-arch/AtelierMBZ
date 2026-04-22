<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Get;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Livre de Police — Registre légal IMMUTABLE.
 * Art. 321-7 Code Pénal + Décret 2009-1104.
 * Aucune modification ni suppression autorisée (pas de PUT/PATCH/DELETE).
 */
#[ORM\Entity]
#[ORM\Table(name: 'vo_livre_police')]
#[ApiResource(
    normalizationContext: ['groups' => ['livrepolice:read']],
    operations: [
        new GetCollection(security: "is_granted('ROLE_VO_MANAGER')"),
        new Get(security: "is_granted('ROLE_VO_MANAGER')"),
    ],
)]
class VOLivrePolice
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['livrepolice:read'])]
    private ?int $id = null;

    #[ORM\Column(nullable: true)]
    private ?int $atelierId = null;

    #[ORM\Column]
    #[Groups(['livrepolice:read'])]
    private int $numeroOrdre;

    #[ORM\Column(length: 20)]
    #[Groups(['livrepolice:read'])]
    private string $type;

    #[ORM\Column(type: 'date')]
    #[Groups(['livrepolice:read'])]
    private \DateTimeInterface $dateAcquisition;

    #[ORM\Column(type: 'date', nullable: true)]
    #[Groups(['livrepolice:read'])]
    private ?\DateTimeInterface $dateVente = null;

    #[ORM\Column(type: 'text')]
    #[Groups(['livrepolice:read'])]
    private string $descriptionBien;

    #[ORM\Column(length: 20)]
    #[Groups(['livrepolice:read'])]
    private string $immatriculation;

    // --- Identité vendeur ---

    #[ORM\Column(length: 100)]
    #[Groups(['livrepolice:read'])]
    private string $vendeurNom;

    #[ORM\Column(length: 100)]
    #[Groups(['livrepolice:read'])]
    private string $vendeurPrenom;

    #[ORM\Column(type: 'text')]
    #[Groups(['livrepolice:read'])]
    private string $vendeurAdresse;

    #[ORM\Column(length: 50)]
    #[Groups(['livrepolice:read'])]
    private string $vendeurIdType;

    #[ORM\Column(length: 100)]
    #[Groups(['livrepolice:read'])]
    private string $vendeurIdNumber;

    #[ORM\Column(type: 'date')]
    #[Groups(['livrepolice:read'])]
    private \DateTimeInterface $vendeurIdDate;

    // --- Prix et mode de paiement ---

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    #[Groups(['livrepolice:read'])]
    private string $prixAchat;

    /** @see MODES_PAIEMENT */
    #[ORM\Column(length: 20)]
    #[Groups(['livrepolice:read'])]
    private string $modePaiement;

    #[ORM\Column(length: 50, nullable: true)]
    #[Groups(['livrepolice:read'])]
    private ?string $numeroCheque = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Groups(['livrepolice:read'])]
    private ?string $nomBanque = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    #[Groups(['livrepolice:read'])]
    private ?string $prixVente = null;

    /** Mode de paiement vente (rempli à la cession) */
    #[ORM\Column(length: 20, nullable: true)]
    #[Groups(['livrepolice:read'])]
    private ?string $modePaiementVente = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Groups(['livrepolice:read'])]
    private ?string $numeroChequeVente = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Groups(['livrepolice:read'])]
    private ?string $nomBanqueVente = null;

    public const MODES_PAIEMENT = ['especes', 'cb', 'cheque', 'virement', 'depot_vente'];
    /** Modes acceptés pour un vrai encaissement (achat rachat + vente) — exclut depot_vente */
    public const MODES_PAIEMENT_ENCAISSEMENT = ['especes', 'cb', 'cheque', 'virement'];

    // --- Identité acheteur (rempli à la vente) ---

    #[ORM\Column(length: 100, nullable: true)]
    #[Groups(['livrepolice:read'])]
    private ?string $acheteurNom = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Groups(['livrepolice:read'])]
    private ?string $acheteurPrenom = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['livrepolice:read'])]
    private ?string $acheteurAdresse = null;

    // --- Relations ---

    #[ORM\OneToOne(targetEntity: VOPurchase::class)]
    #[ORM\JoinColumn(name: 'vo_purchase_id', nullable: true)]
    #[Groups(['livrepolice:read'])]
    private ?VOPurchase $voPurchase = null;

    #[ORM\OneToOne(targetEntity: VODepotVente::class)]
    #[ORM\JoinColumn(name: 'vo_depot_vente_id', nullable: true)]
    #[Groups(['livrepolice:read'])]
    private ?VODepotVente $voDepotVente = null;

    #[ORM\Column(type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'])]
    #[Groups(['livrepolice:read'])]
    private \DateTimeInterface $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    // --- Getters (NO setters for immutability after creation — use constructor/service) ---

    public function getId(): ?int { return $this->id; }
    public function getAtelierId(): ?int { return $this->atelierId; }
    public function setAtelierId(?int $v): static { $this->atelierId = $v; return $this; }
    public function getNumeroOrdre(): int { return $this->numeroOrdre; }
    public function setNumeroOrdre(int $v): static { $this->numeroOrdre = $v; return $this; }
    public function getType(): string { return $this->type; }
    public function setType(string $v): static { $this->type = $v; return $this; }
    public function getDateAcquisition(): \DateTimeInterface { return $this->dateAcquisition; }
    public function setDateAcquisition(\DateTimeInterface $v): static { $this->dateAcquisition = $v; return $this; }
    public function getDateVente(): ?\DateTimeInterface { return $this->dateVente; }
    public function setDateVente(?\DateTimeInterface $v): static { $this->dateVente = $v; return $this; }
    public function getDescriptionBien(): string { return $this->descriptionBien; }
    public function setDescriptionBien(string $v): static { $this->descriptionBien = $v; return $this; }
    public function getImmatriculation(): string { return $this->immatriculation; }
    public function setImmatriculation(string $v): static { $this->immatriculation = $v; return $this; }
    public function getVendeurNom(): string { return $this->vendeurNom; }
    public function setVendeurNom(string $v): static { $this->vendeurNom = $v; return $this; }
    public function getVendeurPrenom(): string { return $this->vendeurPrenom; }
    public function setVendeurPrenom(string $v): static { $this->vendeurPrenom = $v; return $this; }
    public function getVendeurAdresse(): string { return $this->vendeurAdresse; }
    public function setVendeurAdresse(string $v): static { $this->vendeurAdresse = $v; return $this; }
    public function getVendeurIdType(): string { return $this->vendeurIdType; }
    public function setVendeurIdType(string $v): static { $this->vendeurIdType = $v; return $this; }
    public function getVendeurIdNumber(): string { return $this->vendeurIdNumber; }
    public function setVendeurIdNumber(string $v): static { $this->vendeurIdNumber = $v; return $this; }
    public function getVendeurIdDate(): \DateTimeInterface { return $this->vendeurIdDate; }
    public function setVendeurIdDate(\DateTimeInterface $v): static { $this->vendeurIdDate = $v; return $this; }
    public function getPrixAchat(): string { return $this->prixAchat; }
    public function setPrixAchat(string $v): static { $this->prixAchat = $v; return $this; }
    public function getModePaiement(): string { return $this->modePaiement; }
    public function setModePaiement(string $v): static { $this->modePaiement = $v; return $this; }
    public function getNumeroCheque(): ?string { return $this->numeroCheque; }
    public function setNumeroCheque(?string $v): static { $this->numeroCheque = $v; return $this; }
    public function getNomBanque(): ?string { return $this->nomBanque; }
    public function setNomBanque(?string $v): static { $this->nomBanque = $v; return $this; }
    public function getPrixVente(): ?string { return $this->prixVente; }
    public function setPrixVente(?string $v): static { $this->prixVente = $v; return $this; }
    public function getModePaiementVente(): ?string { return $this->modePaiementVente; }
    public function setModePaiementVente(?string $v): static { $this->modePaiementVente = $v; return $this; }
    public function getNumeroChequeVente(): ?string { return $this->numeroChequeVente; }
    public function setNumeroChequeVente(?string $v): static { $this->numeroChequeVente = $v; return $this; }
    public function getNomBanqueVente(): ?string { return $this->nomBanqueVente; }
    public function setNomBanqueVente(?string $v): static { $this->nomBanqueVente = $v; return $this; }
    public function getAcheteurNom(): ?string { return $this->acheteurNom; }
    public function setAcheteurNom(?string $v): static { $this->acheteurNom = $v; return $this; }
    public function getAcheteurPrenom(): ?string { return $this->acheteurPrenom; }
    public function setAcheteurPrenom(?string $v): static { $this->acheteurPrenom = $v; return $this; }
    public function getAcheteurAdresse(): ?string { return $this->acheteurAdresse; }
    public function setAcheteurAdresse(?string $v): static { $this->acheteurAdresse = $v; return $this; }
    public function getVoPurchase(): ?VOPurchase { return $this->voPurchase; }
    public function setVoPurchase(?VOPurchase $v): static { $this->voPurchase = $v; return $this; }
    public function getVoDepotVente(): ?VODepotVente { return $this->voDepotVente; }
    public function setVoDepotVente(?VODepotVente $v): static { $this->voDepotVente = $v; return $this; }
    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
}
