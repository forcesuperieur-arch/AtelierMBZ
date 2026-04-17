<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'notification_escalations')]
#[ORM\Index(columns: ['scheduled_at', 'executed_at'], name: 'idx_escalation_schedule')]
class NotificationEscalation
{
    #[ORM\Id] #[ORM\GeneratedValue] #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Notification::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Notification $notification;

    #[ORM\Column]
    private int $level = 1;

    #[ORM\Column(length: 20)]
    private string $channel = 'push';

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $scheduledAt;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $executedAt = null;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $result = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $skipReason = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $targetInfo = null;

    public function getId(): ?int { return $this->id; }
    public function getNotification(): Notification { return $this->notification; }
    public function setNotification(Notification $v): static { $this->notification = $v; return $this; }
    public function getLevel(): int { return $this->level; }
    public function setLevel(int $v): static { $this->level = $v; return $this; }
    public function getChannel(): string { return $this->channel; }
    public function setChannel(string $v): static { $this->channel = $v; return $this; }
    public function getScheduledAt(): \DateTimeInterface { return $this->scheduledAt; }
    public function setScheduledAt(\DateTimeInterface $v): static { $this->scheduledAt = $v; return $this; }
    public function getExecutedAt(): ?\DateTimeInterface { return $this->executedAt; }
    public function setExecutedAt(?\DateTimeInterface $v): static { $this->executedAt = $v; return $this; }
    public function getResult(): ?string { return $this->result; }
    public function setResult(?string $v): static { $this->result = $v; return $this; }
    public function getSkipReason(): ?string { return $this->skipReason; }
    public function setSkipReason(?string $v): static { $this->skipReason = $v; return $this; }
    public function getTargetInfo(): ?string { return $this->targetInfo; }
    public function setTargetInfo(?string $v): static { $this->targetInfo = $v; return $this; }
}
