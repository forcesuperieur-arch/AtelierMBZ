<?php

namespace App\Security;

use App\Entity\Client;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class ClientUserProvider implements UserProviderInterface
{
    public function __construct(private EntityManagerInterface $em) {}

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        $client = $this->em->getRepository(Client::class)->findOneBy(['email' => $identifier]);
        if (!$client) {
            throw new UserNotFoundException(sprintf('Client "%s" not found.', $identifier));
        }
        return new ClientUserAdapter($client);
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof ClientUserAdapter) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', get_class($user)));
        }

        $client = $this->em->getRepository(Client::class)->find($user->getClient()->getId());
        if (!$client) {
            throw new UserNotFoundException('Client no longer exists.');
        }
        return new ClientUserAdapter($client);
    }

    public function supportsClass(string $class): bool
    {
        return $class === ClientUserAdapter::class || is_subclass_of($class, ClientUserAdapter::class);
    }
}
