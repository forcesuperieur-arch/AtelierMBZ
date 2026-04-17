<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity] #[ORM\Table(name: 'notifications')]
#[ApiResource(
    normalizationContext: ['groups' => ['notif:read']],
    operations: [
        new GetCollection(uriTemplate: '/notifications'),
        new Patch(uriTemplate: '/notifications/{id}'),
    ],
)]
class Notification
{
    #[ORM\Id] #[ORM\GeneratedValue] #[ORM\Column]
    #[Groups(['notif:read'])]
    private ?int $id = null;

    #[ORM\Column(nullable: true)]
    private ?int $atelierId = null;

    #[ORM\Column(length: 100)]
    #[Groups(['notif:read'])]
    private string $type;

    #[ORM\Column(length: 500)]
    #[Groups(['notif:read'])]
    private string $message;

    #[ORM\Column(length: 100, nullable: true)]
    #[Groups(['notif:read'])]
    private ?string $entityType = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['notif:read'])]
    private ?int $entityId = null;

    #[ORM\Column(type: 'json')]
    #[Groups(['notif:read'])]
    private array $targetRoles = [];

    #[ORM\Column(nullable: true)]
    #[Groups(['notif:read'])]
    private ?int $targetUserId = null;

    #[ORM\Column(options: ['default' => false])]
    #[Groups(['notif:read', 'notif:write'])]
    private bool $isRead = false;

    #[ORM\Column(length: 50, options: ['default' => 'normal'])]
    #[Groups(['notif:read'])]
    private string $priority = 'normal';

    #[ORM\Column(type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'])]
    #[Groups(['notif:read'])]
    private \DateTimeInterface $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }
    public function getAtelierId(): ?int { return $this->atelierId; }
    public function setAtelierId(?int $v): static { $this->atelierId = $v; return $this; }
    public function getType(): string { return $this->type; }
    public function setType(string $v): static { $this->type = $v; return $this; }
    public function getMessage(): string { return $this->message; }
    public function setMessage(string $v): static { $this->message = $v; return $this; }
    public function getEntityType(): ?string { return $this->entityType; }
    public function setEntityType(?string $v): static { $this->entityType = $v; return $this; }
    public function getEntityId(): ?int { return $this->entityId; }
    public function setEntityId(?int $v): static { $this->entityId = $v; return $this; }
    public function getTargetRoles(): array { return $this->targetRoles; }
    public function setTargetRoles(array $v): static { $this->targetRoles = $v; return $this; }
    public function getTargetUserId(): ?int { return $this->targetUserId; }
    public function setTargetUserId(?int $v): static { $this->targetUserId = $v; return $this; }
    public function getIsRead(): bool { return $this->isRead; }
    public function setIsRead(bool $v): static { $this->isRead = $v; return $this; }
    public function getPriority(): string { return $this->priority; }
    public function setPriority(string $v): static { $this->priority = $v; return $this; }
    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
}
