<?php

namespace App\Security;

use App\Entity\Client;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Adapter that wraps a Client entity into a Symfony UserInterface.
 */
class ClientUserAdapter implements UserInterface, PasswordAuthenticatedUserInterface
{
    public function __construct(private Client $client) {}

    public function getUserIdentifier(): string
    {
        return $this->client->getEmail() ?? '';
    }

    public function getRoles(): array
    {
        return ['ROLE_CLIENT'];
    }

    public function getPassword(): ?string
    {
        return $this->client->getPassword();
    }

    public function eraseCredentials(): void
    {
        // no-op
    }

    public function getClient(): Client
    {
        return $this->client;
    }
}
