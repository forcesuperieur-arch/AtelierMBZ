<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity]
#[ORM\Table(name: 'absences')]
#[ApiResource(
    normalizationContext: ['groups' => ['absence:read']],
    denormalizationContext: ['groups' => ['absence:write']],
)]
class Absence
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['absence:read'])]
    private ?int $id = null;

    #[ORM\Column(nullable: true)]
    private ?int $atelierId = null;

    #[ORM\ManyToOne(targetEntity: Mecanicien::class)]
    #[ORM\JoinColumn(name: 'mecanicien_id', nullable: false)]
    #[Groups(['absence:read', 'absence:write'])]
    private Mecanicien $mecanicien;

    #[ORM\Column(type: 'date')]
    #[Groups(['absence:read', 'absence:write'])]
    private \DateTimeInterface $dateDebut;

    #[ORM\Column(type: 'date')]
    #[Groups(['absence:read', 'absence:write'])]
    private \DateTimeInterface $dateFin;

    #[ORM\Column(length: 50)]
    #[Groups(['absence:read', 'absence:write'])]
    private string $motif;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['absence:read', 'absence:write'])]
    private ?string $notes = null;

    #[ORM\Column(type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'])]
    #[Groups(['absence:read'])]
    private \DateTimeInterface $createdAt;

    public function __construct() { $this->createdAt = new \DateTime(); }

    public function getId(): ?int { return $this->id; }
    public function getAtelierId(): ?int { return $this->atelierId; }
    public function setAtelierId(?int $v): static { $this->atelierId = $v; return $this; }
    public function getMecanicien(): Mecanicien { return $this->mecanicien; }
    public function setMecanicien(Mecanicien $v): static { $this->mecanicien = $v; return $this; }
    public function getDateDebut(): \DateTimeInterface { return $this->dateDebut; }
    public function setDateDebut(\DateTimeInterface $v): static { $this->dateDebut = $v; return $this; }
    public function getDateFin(): \DateTimeInterface { return $this->dateFin; }
    public function setDateFin(\DateTimeInterface $v): static { $this->dateFin = $v; return $this; }
    public function getMotif(): string { return $this->motif; }
    public function setMotif(string $v): static { $this->motif = $v; return $this; }
    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $v): static { $this->notes = $v; return $this; }
    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
}
