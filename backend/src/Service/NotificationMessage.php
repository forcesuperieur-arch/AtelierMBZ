<?php

namespace App\Service;

/**
 * DTO for messages sent through NotificationDispatcher.
 */
class NotificationMessage
{
    public function __construct(
        private string $channel, // sms | email
        private int $atelierId,
        private string $recipient, // phone number or email
        private string $body,
        private ?string $subject = null,
        private ?string $templateCode = null,
        private ?string $relatedEntityType = null,
        private ?int $relatedEntityId = null,
    ) {}

    public function getChannel(): string { return $this->channel; }
    public function getAtelierId(): int { return $this->atelierId; }
    public function getRecipient(): string { return $this->recipient; }
    public function getBody(): string { return $this->body; }
    public function getSubject(): ?string { return $this->subject; }
    public function getTemplateCode(): ?string { return $this->templateCode; }
    public function getRelatedEntityType(): ?string { return $this->relatedEntityType; }
    public function getRelatedEntityId(): ?int { return $this->relatedEntityId; }
}
