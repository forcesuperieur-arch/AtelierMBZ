<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'analytics_alert_rules')]
#[ORM\Index(columns: ['atelier_id', 'is_active'], name: 'idx_alert_rule_atelier_active')]
class AnalyticsAlertRule
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private int $atelierId;

    #[ORM\Column(length: 100)]
    private string $name = '';

    #[ORM\Column(length: 50)]
    private string $metric = '';

    #[ORM\Column(length: 10)]
    private string $operator = '>';

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2, options: ['default' => '0.00'])]
    private string $thresholdValue = '0.00';

    #[ORM\Column(length: 20, options: ['default' => 'warning'])]
    private string $severity = 'warning';

    #[ORM\Column(options: ['default' => 1])]
    private bool $isActive = true;

    #[ORM\Column(options: ['default' => 60])]
    private int $cooldownMinutes = 60;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $lastTriggeredAt = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTime $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }

    public function getAtelierId(): int { return $this->atelierId; }
    public function setAtelierId(int $atelierId): static { $this->atelierId = $atelierId; return $this; }

    public function getName(): string { return $this->name; }
    public function setName(string $name): static { $this->name = $name; return $this; }

    public function getMetric(): string { return $this->metric; }
    public function setMetric(string $metric): static { $this->metric = $metric; return $this; }

    public function getOperator(): string { return $this->operator; }
    public function setOperator(string $operator): static { $this->operator = $operator; return $this; }

    public function getThresholdValue(): string { return $this->thresholdValue; }
    public function setThresholdValue(string $thresholdValue): static { $this->thresholdValue = $thresholdValue; return $this; }

    public function getSeverity(): string { return $this->severity; }
    public function setSeverity(string $severity): static { $this->severity = $severity; return $this; }

    public function isActive(): bool { return $this->isActive; }
    public function setIsActive(bool $isActive): static { $this->isActive = $isActive; return $this; }

    public function getCooldownMinutes(): int { return $this->cooldownMinutes; }
    public function setCooldownMinutes(int $cooldownMinutes): static { $this->cooldownMinutes = $cooldownMinutes; return $this; }

    public function getLastTriggeredAt(): ?\DateTime { return $this->lastTriggeredAt; }
    public function setLastTriggeredAt(?\DateTimeInterface $lastTriggeredAt): static { $this->lastTriggeredAt = $lastTriggeredAt ? new \DateTime($lastTriggeredAt->format('Y-m-d H:i:s')) : null; return $this; }

    public function getCreatedAt(): ?\DateTime { return $this->createdAt; }
}
