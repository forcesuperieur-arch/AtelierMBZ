<?php

namespace App\EventSubscriber;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * [LOT-0] Met à jour users.last_activity_at à chaque requête authentifiée
 * via UPDATE SQL direct (pas de flush de l'EntityManager pour éviter cycles).
 */
class SessionActivitySubscriber
{
    public function __construct(
        private TokenStorageInterface $tokenStorage,
        private EntityManagerInterface $em,
    ) {}

    #[AsEventListener(event: 'kernel.request', priority: 6)]
    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $token = $this->tokenStorage->getToken();
        if (!$token) {
            return;
        }

        $user = $token->getUser();
        if (!$user instanceof User) {
            return;
        }

        $conn = $this->em->getConnection();
        $conn->executeStatement(
            'UPDATE users SET last_activity_at = NOW() WHERE id = :id',
            ['id' => $user->getId()]
        );
    }
}
