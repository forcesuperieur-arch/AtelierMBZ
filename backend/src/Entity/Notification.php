<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity] #[ORM\Table(name: 'notifications')]
#[ORM\Index(columns: ['atelier_id', 'acknowledged_at'], name: 'idx_notif_atelier_ack')]
#[ORM\Index(columns: ['target_user_id', 'read_at'], name: 'idx_notif_user_read')]
class Notification
{
    #[ORM\Id] #[ORM\GeneratedValue] #[ORM\Column]
    #[Groups(['notif:read'])]
    private ?int $id = null;

    #[ORM\Column(nullable: true)]
    private ?int $atelierId = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['notif:read'])]
    private ?int $targetUserId = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Groups(['notif:read'])]
    private ?string $targetRole = null;

    #[ORM\Column(type: 'json')]
    #[Groups(['notif:read'])]
    private array $targetRoles = [];

    #[ORM\Column(length: 100)]
    #[Groups(['notif:read'])]
    private string $type;

    #[ORM\Column(length: 20, options: ['default' => 'info'])]
    #[Groups(['notif:read'])]
    private string $severity = 'info';

    #[ORM\Column(length: 255)]
    #[Groups(['notif:read'])]
    private string $title = '';

    #[ORM\Column(length: 500)]
    #[Groups(['notif:read'])]
    private string $message;

    #[ORM\Column(length: 500, nullable: true)]
    #[Groups(['notif:read'])]
    private ?string $actionUrl = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Groups(['notif:read'])]
    private ?string $relatedEntityType = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['notif:read'])]
    private ?int $relatedEntityId = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    #[Groups(['notif:read'])]
    private ?\DateTimeInterface $readAt = null;

    #[ORM\Column(nullable: true)]
    private ?int $readBy = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    #[Groups(['notif:read'])]
    private ?\DateTimeInterface $acknowledgedAt = null;

    #[ORM\Column(nullable: true)]
    private ?int $acknowledgedBy = null;

    #[ORM\Column(options: ['default' => false])]
    #[Groups(['notif:read', 'notif:write'])]
    private bool $isRead = false;

    #[ORM\Column(length: 50, options: ['default' => 'normal'])]
    #[Groups(['notif:read'])]
    private string $priority = 'normal';

    #[ORM\Column(type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'])]
    #[Groups(['notif:read'])]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: 'datetime', nullable: true)]
    #[Groups(['notif:read'])]
    private ?\DateTimeInterface $expiresAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }
    public function getAtelierId(): ?int { return $this->atelierId; }
    public function setAtelierId(?int $v): static { $this->atelierId = $v; return $this; }
    public function getTargetUserId(): ?int { return $this->targetUserId; }
    public function setTargetUserId(?int $v): static { $this->targetUserId = $v; return $this; }
    public function getTargetRole(): ?string { return $this->targetRole; }
    public function setTargetRole(?string $v): static { $this->targetRole = $v; return $this; }
    public function getTargetRoles(): array { return $this->targetRoles; }
    public function setTargetRoles(array $v): static { $this->targetRoles = $v; return $this; }
    public function getType(): string { return $this->type; }
    public function setType(string $v): static { $this->type = $v; return $this; }
    public function getSeverity(): string { return $this->severity; }
    public function setSeverity(string $v): static { $this->severity = $v; return $this; }
    public function getTitle(): string { return $this->title; }
    public function setTitle(string $v): static { $this->title = $v; return $this; }
    public function getMessage(): string { return $this->message; }
    public function setMessage(string $v): static { $this->message = $v; return $this; }
    public function getActionUrl(): ?string { return $this->actionUrl; }
    public function setActionUrl(?string $v): static { $this->actionUrl = $v; return $this; }
    public function getRelatedEntityType(): ?string { return $this->relatedEntityType; }
    public function setRelatedEntityType(?string $v): static { $this->relatedEntityType = $v; return $this; }
    public function getRelatedEntityId(): ?int { return $this->relatedEntityId; }
    public function setRelatedEntityId(?int $v): static { $this->relatedEntityId = $v; return $this; }
    public function getReadAt(): ?\DateTimeInterface { return $this->readAt; }
    public function setReadAt(?\DateTimeInterface $v): static { $this->readAt = $v; return $this; }
    public function getReadBy(): ?int { return $this->readBy; }
    public function setReadBy(?int $v): static { $this->readBy = $v; return $this; }
    public function getAcknowledgedAt(): ?\DateTimeInterface { return $this->acknowledgedAt; }
    public function setAcknowledgedAt(?\DateTimeInterface $v): static { $this->acknowledgedAt = $v; return $this; }
    public function getAcknowledgedBy(): ?int { return $this->acknowledgedBy; }
    public function setAcknowledgedBy(?int $v): static { $this->acknowledgedBy = $v; return $this; }
    public function getIsRead(): bool { return $this->isRead; }
    public function setIsRead(bool $v): static { $this->isRead = $v; return $this; }
    public function getPriority(): string { return $this->priority; }
    public function setPriority(string $v): static { $this->priority = $v; return $this; }
    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
    public function getExpiresAt(): ?\DateTimeInterface { return $this->expiresAt; }
    public function setExpiresAt(?\DateTimeInterface $v): static { $this->expiresAt = $v; return $this; }
}
