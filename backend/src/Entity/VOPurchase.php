<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Patch;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity]
#[ORM\Table(name: 'vo_purchases')]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    normalizationContext: ['groups' => ['vo:read']],
    denormalizationContext: ['groups' => ['vo:write']],
    operations: [
        new GetCollection(security: "is_granted('ROLE_VO_MANAGER')"),
        new Get(security: "is_granted('ROLE_VO_MANAGER')"),
        new Post(security: "is_granted('ROLE_VO_MANAGER')"),
        new Patch(security: "is_granted('ROLE_VO_MANAGER')"),
    ],
)]
class VOPurchase
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['vo:read'])]
    private ?int $id = null;

    #[ORM\Column(nullable: true)]
    private ?int $atelierId = null;

    #[ORM\ManyToOne(targetEntity: Vehicule::class)]
    #[ORM\JoinColumn(name: 'vehicule_id', nullable: false)]
    #[Groups(['vo:read', 'vo:write'])]
    private Vehicule $vehicule;

    #[ORM\ManyToOne(targetEntity: Client::class)]
    #[ORM\JoinColumn(name: 'seller_id', nullable: false)]
    #[Groups(['vo:read', 'vo:write'])]
    private Client $seller;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'expert_id', nullable: true)]
    #[Groups(['vo:read', 'vo:write'])]
    private ?User $expert = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    #[Groups(['vo:read', 'vo:write'])]
    private string $purchasePrice = '0.00';

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    #[Groups(['vo:read', 'vo:write'])]
    private string $targetSalePrice = '0.00';

    #[ORM\Column(type: 'json', nullable: true)]
    #[Groups(['vo:read', 'vo:write'])]
    private ?array $repairEstimates = null;

    #[ORM\Column(length: 30, options: ['default' => 'brouillon'])]
    #[Groups(['vo:read', 'vo:write'])]
    private string $status = 'brouillon';

    #[ORM\Column(type: 'date', nullable: true)]
    #[Groups(['vo:read', 'vo:write'])]
    private ?\DateTimeInterface $purchaseDate = null;

    #[ORM\Column(type: 'date', nullable: true)]
    #[Groups(['vo:read', 'vo:write'])]
    private ?\DateTimeInterface $saleDate = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['vo:read', 'vo:write'])]
    private ?string $notes = null;

    // --- Identité vendeur (Livre de Police) ---

    #[ORM\Column(length: 50, nullable: true)]
    #[Groups(['vo:read', 'vo:write'])]
    private ?string $sellerIdType = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Groups(['vo:read', 'vo:write'])]
    private ?string $sellerIdNumber = null;

    #[ORM\Column(type: 'date', nullable: true)]
    #[Groups(['vo:read', 'vo:write'])]
    private ?\DateTimeInterface $sellerIdDate = null;

    // --- Documents & conformité ---

    #[ORM\Column(type: 'date', nullable: true)]
    #[Groups(['vo:read', 'vo:write'])]
    private ?\DateTimeInterface $nonGageDate = null;

    #[ORM\Column(options: ['default' => false])]
    #[Groups(['vo:read', 'vo:write'])]
    private bool $controleTechniqueOk = false;

    #[ORM\Column(length: 10, options: ['default' => 'marge'])]
    #[Groups(['vo:read', 'vo:write'])]
    private string $regimeTva = 'marge';

    #[ORM\Column(type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'])]
    #[Groups(['vo:read'])]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'])]
    #[Groups(['vo:read'])]
    private \DateTimeInterface $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    #[ORM\PreUpdate]
    public function preUpdate(): void { $this->updatedAt = new \DateTime(); }

    /**
     * Calculate margin: targetSalePrice - (purchasePrice + sum(repairEstimates))
     */
    public function getMargin(): string
    {
        $totalFre = '0.00';
        foreach ($this->repairEstimates ?? [] as $estimate) {
            $amount = (string) ($estimate['amount'] ?? '0');
            $totalFre = bcadd($totalFre, $amount, 2);
        }

        $totalCost = bcadd($this->purchasePrice, $totalFre, 2);
        return bcsub($this->targetSalePrice, $totalCost, 2);
    }

    /**
     * Total des FRE (Frais de Remise en État)
     */
    public function getTotalFre(): string
    {
        $total = '0.00';
        foreach ($this->repairEstimates ?? [] as $estimate) {
            $amount = (string) ($estimate['amount'] ?? '0');
            $total = bcadd($total, $amount, 2);
        }
        return $total;
    }

    // --- Getters / Setters ---

    public function getId(): ?int { return $this->id; }
    public function getAtelierId(): ?int { return $this->atelierId; }
    public function setAtelierId(?int $v): static { $this->atelierId = $v; return $this; }

    public function getVehicule(): Vehicule { return $this->vehicule; }
    public function setVehicule(Vehicule $v): static { $this->vehicule = $v; return $this; }

    public function getSeller(): Client { return $this->seller; }
    public function setSeller(Client $v): static { $this->seller = $v; return $this; }

    public function getExpert(): ?User { return $this->expert; }
    public function setExpert(?User $v): static { $this->expert = $v; return $this; }

    public function getPurchasePrice(): string { return $this->purchasePrice; }
    public function setPurchasePrice(string $v): static { $this->purchasePrice = $v; return $this; }

    public function getTargetSalePrice(): string { return $this->targetSalePrice; }
    public function setTargetSalePrice(string $v): static { $this->targetSalePrice = $v; return $this; }

    public function getRepairEstimates(): ?array { return $this->repairEstimates; }
    public function setRepairEstimates(?array $v): static { $this->repairEstimates = $v; return $this; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $v): static { $this->status = $v; return $this; }

    public function getPurchaseDate(): ?\DateTimeInterface { return $this->purchaseDate; }
    public function setPurchaseDate(?\DateTimeInterface $v): static { $this->purchaseDate = $v; return $this; }

    public function getSaleDate(): ?\DateTimeInterface { return $this->saleDate; }
    public function setSaleDate(?\DateTimeInterface $v): static { $this->saleDate = $v; return $this; }

    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $v): static { $this->notes = $v; return $this; }

    public function getSellerIdType(): ?string { return $this->sellerIdType; }
    public function setSellerIdType(?string $v): static { $this->sellerIdType = $v; return $this; }

    public function getSellerIdNumber(): ?string { return $this->sellerIdNumber; }
    public function setSellerIdNumber(?string $v): static { $this->sellerIdNumber = $v; return $this; }

    public function getSellerIdDate(): ?\DateTimeInterface { return $this->sellerIdDate; }
    public function setSellerIdDate(?\DateTimeInterface $v): static { $this->sellerIdDate = $v; return $this; }

    public function getNonGageDate(): ?\DateTimeInterface { return $this->nonGageDate; }
    public function setNonGageDate(?\DateTimeInterface $v): static { $this->nonGageDate = $v; return $this; }

    public function getControleTechniqueOk(): bool { return $this->controleTechniqueOk; }
    public function setControleTechniqueOk(bool $v): static { $this->controleTechniqueOk = $v; return $this; }

    public function getRegimeTva(): string { return $this->regimeTva; }
    public function setRegimeTva(string $v): static { $this->regimeTva = $v; return $this; }

    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeInterface { return $this->updatedAt; }
}
