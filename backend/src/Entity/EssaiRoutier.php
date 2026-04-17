<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'essai_routier')]
class EssaiRoutier
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(nullable: true)]
    private ?int $atelierId = null;

    #[ORM\ManyToOne(targetEntity: RendezVous::class)]
    #[ORM\JoinColumn(name: 'rendez_vous_id', nullable: false)]
    private RendezVous $rendezVous;

    #[ORM\Column(nullable: true)]
    private ?int $kmDebut = null;

    #[ORM\Column(nullable: true)]
    private ?int $kmFin = null;

    #[ORM\Column(nullable: true)]
    private ?int $dureeMinutes = null;

    #[ORM\Column(type: 'decimal', precision: 6, scale: 2, nullable: true)]
    private ?string $distance = null;

    /** 10 structured control points as JSON */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $pointsControle = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $anomalies = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $actionsCorrectives = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $signatureMecanicien = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $realiseAt = null;

    #[ORM\Column(nullable: true)]
    private ?int $mecanicienId = null;

    #[ORM\Column(type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'])]
    private \DateTimeInterface $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->pointsControle = self::defaultPointsControle();
    }

    public static function defaultPointsControle(): array
    {
        return [
            ['label' => 'Freinage', 'ok' => null, 'commentaire' => ''],
            ['label' => 'Direction', 'ok' => null, 'commentaire' => ''],
            ['label' => 'Suspension', 'ok' => null, 'commentaire' => ''],
            ['label' => 'Moteur/accélération', 'ok' => null, 'commentaire' => ''],
            ['label' => 'Boîte de vitesses', 'ok' => null, 'commentaire' => ''],
            ['label' => 'Embrayage', 'ok' => null, 'commentaire' => ''],
            ['label' => 'Éclairage/clignotants', 'ok' => null, 'commentaire' => ''],
            ['label' => 'Bruits anormaux', 'ok' => null, 'commentaire' => ''],
            ['label' => 'Tableau de bord/voyants', 'ok' => null, 'commentaire' => ''],
            ['label' => 'Comportement général', 'ok' => null, 'commentaire' => ''],
        ];
    }

    public function getId(): ?int { return $this->id; }
    public function getAtelierId(): ?int { return $this->atelierId; }
    public function setAtelierId(?int $v): static { $this->atelierId = $v; return $this; }
    public function getRendezVous(): RendezVous { return $this->rendezVous; }
    public function setRendezVous(RendezVous $v): static { $this->rendezVous = $v; return $this; }
    public function getKmDebut(): ?int { return $this->kmDebut; }
    public function setKmDebut(?int $v): static { $this->kmDebut = $v; return $this; }
    public function getKmFin(): ?int { return $this->kmFin; }
    public function setKmFin(?int $v): static { $this->kmFin = $v; return $this; }
    public function getDureeMinutes(): ?int { return $this->dureeMinutes; }
    public function setDureeMinutes(?int $v): static { $this->dureeMinutes = $v; return $this; }
    public function getDistance(): ?string { return $this->distance; }
    public function setDistance(?string $v): static { $this->distance = $v; return $this; }
    public function getPointsControle(): ?array { return $this->pointsControle; }
    public function setPointsControle(?array $v): static { $this->pointsControle = $v; return $this; }
    public function getAnomalies(): ?string { return $this->anomalies; }
    public function setAnomalies(?string $v): static { $this->anomalies = $v; return $this; }
    public function getActionsCorrectives(): ?string { return $this->actionsCorrectives; }
    public function setActionsCorrectives(?string $v): static { $this->actionsCorrectives = $v; return $this; }
    public function getSignatureMecanicien(): ?string { return $this->signatureMecanicien; }
    public function setSignatureMecanicien(?string $v): static { $this->signatureMecanicien = $v; return $this; }
    public function getRealiseAt(): ?\DateTimeInterface { return $this->realiseAt; }
    public function setRealiseAt(?\DateTimeInterface $v): static { $this->realiseAt = $v; return $this; }
    public function getMecanicienId(): ?int { return $this->mecanicienId; }
    public function setMecanicienId(?int $v): static { $this->mecanicienId = $v; return $this; }
    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }

    public function hasAnomalies(): bool
    {
        if (!empty($this->anomalies)) {
            return true;
        }
        foreach ($this->pointsControle ?? [] as $pt) {
            if (isset($pt['ok']) && $pt['ok'] === false) {
                return true;
            }
        }
        return false;
    }

    public function isComplete(): bool
    {
        return $this->kmDebut !== null
            && $this->kmFin !== null
            && $this->signatureMecanicien !== null;
    }
}
