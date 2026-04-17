<?php

namespace App\Service;

/**
 * Result of a notification dispatch attempt.
 */
class NotificationResult
{
    public function __construct(
        private bool $success,
        private ?string $providerMessageId = null,
        private ?string $provider = null,
        private ?string $errorMessage = null,
    ) {}

    public function isSuccess(): bool { return $this->success; }
    public function getProviderMessageId(): ?string { return $this->providerMessageId; }
    public function getProvider(): ?string { return $this->provider; }
    public function getErrorMessage(): ?string { return $this->errorMessage; }

    public static function ok(string $provider, ?string $messageId = null): self
    {
        return new self(true, $messageId, $provider);
    }

    public static function fail(string $provider, string $error): self
    {
        return new self(false, null, $provider, $error);
    }

    public static function allFailed(): self
    {
        return new self(false, null, null, 'All providers failed');
    }
}
