<?php

namespace App\Entity\Trait;

use Doctrine\ORM\Mapping as ORM;

trait VOCompanionTrait
{
    #[ORM\Column(length: 64, unique: true, nullable: true)]
    private ?string $companionToken = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $companionTokenCreatedAt = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $companionTokenExpiresAt = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $companionSignedAt = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $companionSignatureData = null;

    public function getCompanionToken(): ?string
    {
        return $this->companionToken;
    }

    public function setCompanionToken(?string $companionToken): static
    {
        $this->companionToken = $companionToken;

        return $this;
    }

    public function getCompanionTokenCreatedAt(): ?\DateTimeInterface
    {
        return $this->companionTokenCreatedAt;
    }

    public function setCompanionTokenCreatedAt(?\DateTimeInterface $companionTokenCreatedAt): static
    {
        $this->companionTokenCreatedAt = $companionTokenCreatedAt;

        return $this;
    }

    public function getCompanionTokenExpiresAt(): ?\DateTimeInterface
    {
        return $this->companionTokenExpiresAt;
    }

    public function setCompanionTokenExpiresAt(?\DateTimeInterface $companionTokenExpiresAt): static
    {
        $this->companionTokenExpiresAt = $companionTokenExpiresAt;

        return $this;
    }

    public function getCompanionSignedAt(): ?\DateTimeInterface
    {
        return $this->companionSignedAt;
    }

    public function setCompanionSignedAt(?\DateTimeInterface $companionSignedAt): static
    {
        $this->companionSignedAt = $companionSignedAt;

        return $this;
    }

    public function getCompanionSignatureData(): ?string
    {
        return $this->companionSignatureData;
    }

    public function setCompanionSignatureData(?string $companionSignatureData): static
    {
        $this->companionSignatureData = $companionSignatureData;

        return $this;
    }

    public function hasCompanionSignature(): bool
    {
        return $this->companionSignedAt !== null && $this->companionSignatureData !== null;
    }

    public function isCompanionTokenExpired(?\DateTimeInterface $now = null): bool
    {
        if ($this->companionTokenExpiresAt === null) {
            return true;
        }

        return $this->companionTokenExpiresAt < ($now ?? new \DateTimeImmutable());
    }

    public function ensureCompanionToken(int $ttlDays = 30): bool
    {
        if ($this->companionToken !== null && !$this->isCompanionTokenExpired()) {
            return false;
        }

        $this->regenerateCompanionToken($ttlDays);

        return true;
    }

    public function regenerateCompanionToken(int $ttlDays = 30): void
    {
        $createdAt = new \DateTimeImmutable();

        $this->companionToken = bin2hex(random_bytes(32));
        $this->companionTokenCreatedAt = $createdAt;
        $this->companionTokenExpiresAt = $createdAt->modify(sprintf('+%d days', max(1, $ttlDays)));
    }

    public function getCompanionPublicPath(): ?string
    {
        if ($this->companionToken === null) {
            return null;
        }

        return '/public/vo-companion?token=' . $this->companionToken;
    }
}