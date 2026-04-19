<?php

namespace App\EventSubscriber;

use App\Entity\User;
use App\Service\UserMecanicienSyncService;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Events;

#[AsDoctrineListener(event: Events::postPersist)]
#[AsDoctrineListener(event: Events::postUpdate)]
#[AsDoctrineListener(event: Events::postFlush)]
final class UserMecanicienSyncSubscriber
{
    /** @var array<int, User> */
    private array $queuedUsers = [];
    private bool $syncing = false;

    public function __construct(private UserMecanicienSyncService $syncService) {}

    public function postPersist(PostPersistEventArgs $args): void
    {
        $this->queueUser($args->getObject());
    }

    public function postUpdate(PostUpdateEventArgs $args): void
    {
        $this->queueUser($args->getObject());
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        if ($this->syncing || $this->queuedUsers === []) {
            return;
        }

        $this->syncing = true;
        $needsFlush = false;

        foreach ($this->queuedUsers as $user) {
            if ($this->syncService->syncForUser($user) !== null) {
                $needsFlush = true;
            }
        }

        $this->queuedUsers = [];

        if ($needsFlush) {
            $args->getObjectManager()->flush();
        }

        $this->syncing = false;
    }

    private function queueUser(object $entity): void
    {
        if (!$entity instanceof User || $entity->getId() === null) {
            return;
        }

        $this->queuedUsers[$entity->getId()] = $entity;
    }
}
