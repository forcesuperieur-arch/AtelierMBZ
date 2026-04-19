<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity]
#[ORM\Table(name: 'vo_documents')]
#[ApiResource(
    normalizationContext: ['groups' => ['vodoc:read']],
    denormalizationContext: ['groups' => ['vodoc:write']],
    operations: [
        new GetCollection(security: "is_granted('ROLE_VO_MANAGER')"),
        new Get(security: "is_granted('ROLE_VO_MANAGER')"),
        new Post(security: "is_granted('ROLE_VO_MANAGER')"),
    ],
)]
class VODocument
{
    public const TYPE_CERFA_CESSION_ACHAT = 'cerfa_cession_achat';
    public const TYPE_CERFA_CESSION_VENTE = 'cerfa_cession_vente';
    public const TYPE_CARTE_GRISE = 'carte_grise';
    public const TYPE_NON_GAGE = 'non_gage';
    public const TYPE_CONTROLE_TECHNIQUE = 'controle_technique';
    public const TYPE_PIECE_IDENTITE = 'piece_identite';
    public const TYPE_CONTRAT_DEPOT_VENTE = 'contrat_depot_vente';
    public const TYPE_DA_SIV = 'da_siv';
    public const TYPE_RECEPISSE_DA = 'recepisse_da';
    public const TYPE_MANDAT_IMMATRICULATION = 'mandat_immatriculation';
    public const TYPE_FACTURE_VO = 'facture_vo';
    public const TYPE_PV_RACHAT = 'pv_rachat';
    public const TYPE_REMISE_EN_ETAT = 'remise_en_etat';
    public const TYPE_NOTICE_GARANTIE = 'notice_garantie';
    public const TYPE_SIGNATURE_CLIENT = 'signature_client';
    public const TYPE_PHOTO_VEHICULE = 'photo_vehicule';
    public const TYPE_AUTRE = 'autre';

    public const RETENTION_YEARS = [
        self::TYPE_CERFA_CESSION_ACHAT => 5,
        self::TYPE_CERFA_CESSION_VENTE => 5,
        self::TYPE_CARTE_GRISE => 5,
        self::TYPE_NON_GAGE => 5,
        self::TYPE_CONTROLE_TECHNIQUE => 5,
        self::TYPE_PIECE_IDENTITE => 0, // destroy after transcription (RGPD)
        self::TYPE_CONTRAT_DEPOT_VENTE => 5,
        self::TYPE_DA_SIV => 5,
        self::TYPE_RECEPISSE_DA => 5,
        self::TYPE_MANDAT_IMMATRICULATION => 5,
        self::TYPE_FACTURE_VO => 10,
        self::TYPE_PV_RACHAT => 5,
        self::TYPE_REMISE_EN_ETAT => 5,
        self::TYPE_NOTICE_GARANTIE => 5,
        self::TYPE_SIGNATURE_CLIENT => 5,
        self::TYPE_PHOTO_VEHICULE => 5,
        self::TYPE_AUTRE => 5,
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['vodoc:read'])]
    private ?int $id = null;

    #[ORM\Column(nullable: true)]
    private ?int $atelierId = null;

    #[ORM\Column(length: 30)]
    #[Groups(['vodoc:read', 'vodoc:write'])]
    private string $type;

    #[ORM\Column(length: 500)]
    #[Groups(['vodoc:read'])]
    private string $filePath;

    #[ORM\Column(length: 255)]
    #[Groups(['vodoc:read'])]
    private string $originalFilename;

    #[ORM\Column(length: 100)]
    #[Groups(['vodoc:read'])]
    private string $mimeType;

    #[ORM\ManyToOne(targetEntity: VOPurchase::class)]
    #[ORM\JoinColumn(name: 'vo_purchase_id', nullable: true)]
    #[Groups(['vodoc:read', 'vodoc:write'])]
    private ?VOPurchase $voPurchase = null;

    #[ORM\ManyToOne(targetEntity: VODepotVente::class)]
    #[ORM\JoinColumn(name: 'vo_depot_vente_id', nullable: true)]
    #[Groups(['vodoc:read', 'vodoc:write'])]
    private ?VODepotVente $voDepotVente = null;

    #[ORM\ManyToOne(targetEntity: VORemiseEnEtat::class)]
    #[ORM\JoinColumn(name: 'vo_remise_en_etat_id', nullable: true, onDelete: 'CASCADE')]
    #[Groups(['vodoc:read'])]
    private ?VORemiseEnEtat $voRemiseEnEtat = null;

    #[ORM\Column(type: 'date', nullable: true)]
    #[Groups(['vodoc:read', 'vodoc:write'])]
    private ?\DateTimeInterface $dateExpiration = null;

    #[ORM\Column]
    #[Groups(['vodoc:read'])]
    private int $retentionYears = 5;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'uploaded_by', nullable: true)]
    #[Groups(['vodoc:read'])]
    private ?User $uploadedBy = null;

    #[ORM\Column(type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'])]
    #[Groups(['vodoc:read'])]
    private \DateTimeInterface $uploadedAt;

    public function __construct()
    {
        $this->uploadedAt = new \DateTime();
    }

    public function isExpired(): bool
    {
        if (!$this->dateExpiration) {
            return false;
        }
        return $this->dateExpiration < new \DateTime('today');
    }

    public function getRetentionEndDate(): \DateTimeInterface
    {
        return (clone $this->uploadedAt)->modify("+{$this->retentionYears} years");
    }

    // --- Getters / Setters ---

    public function getId(): ?int { return $this->id; }
    public function getAtelierId(): ?int { return $this->atelierId; }
    public function setAtelierId(?int $v): static { $this->atelierId = $v; return $this; }

    public function getType(): string { return $this->type; }
    public function setType(string $v): static
    {
        $this->type = $v;
        $this->retentionYears = self::RETENTION_YEARS[$v] ?? 5;
        return $this;
    }

    public function getFilePath(): string { return $this->filePath; }
    public function setFilePath(string $v): static { $this->filePath = $v; return $this; }

    #[Groups(['vodoc:read'])]
    public function getDownloadPath(): string
    {
        return sprintf('/api/vo/documents/%d/download', $this->id ?? 0);
    }

    public function getOriginalFilename(): string { return $this->originalFilename; }
    public function setOriginalFilename(string $v): static { $this->originalFilename = $v; return $this; }

    public function getMimeType(): string { return $this->mimeType; }
    public function setMimeType(string $v): static { $this->mimeType = $v; return $this; }

    public function getVoPurchase(): ?VOPurchase { return $this->voPurchase; }
    public function setVoPurchase(?VOPurchase $v): static { $this->voPurchase = $v; return $this; }

    public function getVoDepotVente(): ?VODepotVente { return $this->voDepotVente; }
    public function setVoDepotVente(?VODepotVente $v): static { $this->voDepotVente = $v; return $this; }

    public function getVoRemiseEnEtat(): ?VORemiseEnEtat { return $this->voRemiseEnEtat; }
    public function setVoRemiseEnEtat(?VORemiseEnEtat $v): static { $this->voRemiseEnEtat = $v; return $this; }

    public function getDateExpiration(): ?\DateTimeInterface { return $this->dateExpiration; }
    public function setDateExpiration(?\DateTimeInterface $v): static { $this->dateExpiration = $v; return $this; }

    public function getRetentionYears(): int { return $this->retentionYears; }

    public function getUploadedBy(): ?User { return $this->uploadedBy; }
    public function setUploadedBy(?User $v): static { $this->uploadedBy = $v; return $this; }

    public function getUploadedAt(): \DateTimeInterface { return $this->uploadedAt; }
}
