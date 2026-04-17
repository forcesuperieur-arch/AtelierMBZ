<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'notification_logs')]
#[ORM\Index(columns: ['atelier_id', 'sent_at'], name: 'idx_notiflog_atelier_date')]
#[ORM\Index(columns: ['status'], name: 'idx_notiflog_status')]
#[ORM\Index(columns: ['related_entity_type', 'related_entity_id'], name: 'idx_notiflog_entity')]
class NotificationLog
{
    #[ORM\Id] #[ORM\GeneratedValue] #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private int $atelierId;

    #[ORM\Column(length: 20)]
    private string $channel; // sms | email

    #[ORM\Column(length: 50)]
    private string $provider;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $templateCode = null;

    #[ORM\Column(length: 255)]
    private string $toRecipient;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $subject = null;

    #[ORM\Column(length: 30)]
    private string $status = 'sent'; // sent | delivered | failed | bounced

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $providerMessageId = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $errorMessage = null;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $sentAt;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $deliveredAt = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $readAt = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $relatedEntityType = null;

    #[ORM\Column(nullable: true)]
    private ?int $relatedEntityId = null;

    public function __construct()
    {
        $this->sentAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }
    public function getAtelierId(): int { return $this->atelierId; }
    public function setAtelierId(int $v): static { $this->atelierId = $v; return $this; }
    public function getChannel(): string { return $this->channel; }
    public function setChannel(string $v): static { $this->channel = $v; return $this; }
    public function getProvider(): string { return $this->provider; }
    public function setProvider(string $v): static { $this->provider = $v; return $this; }
    public function getTemplateCode(): ?string { return $this->templateCode; }
    public function setTemplateCode(?string $v): static { $this->templateCode = $v; return $this; }
    public function getToRecipient(): string { return $this->toRecipient; }
    public function setToRecipient(string $v): static { $this->toRecipient = $v; return $this; }
    public function getSubject(): ?string { return $this->subject; }
    public function setSubject(?string $v): static { $this->subject = $v; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $v): static { $this->status = $v; return $this; }
    public function getProviderMessageId(): ?string { return $this->providerMessageId; }
    public function setProviderMessageId(?string $v): static { $this->providerMessageId = $v; return $this; }
    public function getErrorMessage(): ?string { return $this->errorMessage; }
    public function setErrorMessage(?string $v): static { $this->errorMessage = $v; return $this; }
    public function getSentAt(): \DateTimeInterface { return $this->sentAt; }
    public function setSentAt(\DateTimeInterface $v): static { $this->sentAt = $v; return $this; }
    public function getDeliveredAt(): ?\DateTimeInterface { return $this->deliveredAt; }
    public function setDeliveredAt(?\DateTimeInterface $v): static { $this->deliveredAt = $v; return $this; }
    public function getReadAt(): ?\DateTimeInterface { return $this->readAt; }
    public function setReadAt(?\DateTimeInterface $v): static { $this->readAt = $v; return $this; }
    public function getRelatedEntityType(): ?string { return $this->relatedEntityType; }
    public function setRelatedEntityType(?string $v): static { $this->relatedEntityType = $v; return $this; }
    public function getRelatedEntityId(): ?int { return $this->relatedEntityId; }
    public function setRelatedEntityId(?int $v): static { $this->relatedEntityId = $v; return $this; }
}
