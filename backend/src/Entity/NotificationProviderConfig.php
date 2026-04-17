<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'notification_provider_configs')]
#[ORM\UniqueConstraint(name: 'uniq_provider_atelier_channel', columns: ['atelier_id', 'channel', 'provider'])]
class NotificationProviderConfig
{
    #[ORM\Id] #[ORM\GeneratedValue] #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private int $atelierId;

    #[ORM\Column(length: 20)]
    private string $channel; // sms | email

    #[ORM\Column(length: 50)]
    private string $provider; // ovh | twilio | mailgun | smtp_custom

    #[ORM\Column(options: ['default' => false])]
    private bool $isPrimary = false;

    #[ORM\Column(options: ['default' => false])]
    private bool $isFallback = false;

    #[ORM\Column(options: ['default' => 1])]
    private int $priority = 1;

    #[ORM\Column(type: 'text')]
    private string $configEncrypted = ''; // JSON chiffré des clés API

    #[ORM\Column(options: ['default' => true])]
    private bool $isActive = true;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $lastTestAt = null;

    #[ORM\Column(nullable: true)]
    private ?bool $lastTestSuccess = null;

    #[ORM\Column(type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'])]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'])]
    private \DateTimeInterface $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }
    public function getAtelierId(): int { return $this->atelierId; }
    public function setAtelierId(int $v): static { $this->atelierId = $v; return $this; }
    public function getChannel(): string { return $this->channel; }
    public function setChannel(string $v): static { $this->channel = $v; return $this; }
    public function getProvider(): string { return $this->provider; }
    public function setProvider(string $v): static { $this->provider = $v; return $this; }
    public function isPrimary(): bool { return $this->isPrimary; }
    public function setIsPrimary(bool $v): static { $this->isPrimary = $v; return $this; }
    public function isFallback(): bool { return $this->isFallback; }
    public function setIsFallback(bool $v): static { $this->isFallback = $v; return $this; }
    public function getPriority(): int { return $this->priority; }
    public function setPriority(int $v): static { $this->priority = $v; return $this; }
    public function getConfigEncrypted(): string { return $this->configEncrypted; }
    public function setConfigEncrypted(string $v): static { $this->configEncrypted = $v; return $this; }
    public function isActive(): bool { return $this->isActive; }
    public function setIsActive(bool $v): static { $this->isActive = $v; return $this; }
    public function getLastTestAt(): ?\DateTimeInterface { return $this->lastTestAt; }
    public function setLastTestAt(?\DateTimeInterface $v): static { $this->lastTestAt = $v; return $this; }
    public function getLastTestSuccess(): ?bool { return $this->lastTestSuccess; }
    public function setLastTestSuccess(?bool $v): static { $this->lastTestSuccess = $v; return $this; }
    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeInterface { return $this->updatedAt; }
    public function setUpdatedAt(\DateTimeInterface $v): static { $this->updatedAt = $v; return $this; }
}
