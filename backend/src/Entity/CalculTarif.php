<?php
namespace App\Entity;
use ApiPlatform\Metadata\ApiResource;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity] #[ORM\Table(name: 'calculs_tarifs')] #[ApiResource]
class CalculTarif
{
    #[ORM\Id] #[ORM\GeneratedValue] #[ORM\Column] private ?int $id = null;
    #[ORM\ManyToOne(targetEntity: Client::class)] #[ORM\JoinColumn(name: 'client_id', nullable: true)] private ?Client $client = null;
    #[ORM\ManyToOne(targetEntity: Vehicule::class)] #[ORM\JoinColumn(name: 'vehicule_id', nullable: true)] private ?Vehicule $vehicule = null;
    #[ORM\Column(type: 'text', nullable: true)] private ?string $prestationsIds = null;
    #[ORM\Column(type: 'text', nullable: true)] private ?string $piecesIds = null;
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, options: ['default' => '0.00'])] private string $totalMoHt = '0.00';
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, options: ['default' => '0.00'])] private string $totalPiecesHt = '0.00';
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, options: ['default' => '0.00'])] private string $totalHt = '0.00';
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, options: ['default' => '0.00'])] private string $totalTtc = '0.00';
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, options: ['default' => '0.00'])] private string $margePieces = '0.00';
    #[ORM\Column(options: ['default' => 1])] private int $delaiTotalJours = 1;
    #[ORM\Column(type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'])] private \DateTimeInterface $createdAt;

    public function __construct() { $this->createdAt = new \DateTime(); }

    public function getId(): ?int { return $this->id; }
    public function getClient(): ?Client { return $this->client; }
    public function setClient(?Client $v): static { $this->client = $v; return $this; }
    public function getVehicule(): ?Vehicule { return $this->vehicule; }
    public function setVehicule(?Vehicule $v): static { $this->vehicule = $v; return $this; }
    public function getPrestationsIds(): ?string { return $this->prestationsIds; }
    public function setPrestationsIds(?string $v): static { $this->prestationsIds = $v; return $this; }
    public function getPiecesIds(): ?string { return $this->piecesIds; }
    public function setPiecesIds(?string $v): static { $this->piecesIds = $v; return $this; }
    public function getTotalMoHt(): string { return $this->totalMoHt; }
    public function setTotalMoHt(string $v): static { $this->totalMoHt = $v; return $this; }
    public function getTotalPiecesHt(): string { return $this->totalPiecesHt; }
    public function setTotalPiecesHt(string $v): static { $this->totalPiecesHt = $v; return $this; }
    public function getTotalHt(): string { return $this->totalHt; }
    public function setTotalHt(string $v): static { $this->totalHt = $v; return $this; }
    public function getTotalTtc(): string { return $this->totalTtc; }
    public function setTotalTtc(string $v): static { $this->totalTtc = $v; return $this; }
    public function getMargePieces(): string { return $this->margePieces; }
    public function setMargePieces(string $v): static { $this->margePieces = $v; return $this; }
    public function getDelaiTotalJours(): int { return $this->delaiTotalJours; }
    public function setDelaiTotalJours(int $v): static { $this->delaiTotalJours = $v; return $this; }
}
