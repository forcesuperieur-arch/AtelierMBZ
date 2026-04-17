<?php

namespace App\EventListener;

use App\Entity\User;
use App\Security\UserSecurityGuard;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use Doctrine\ORM\Events;

#[AsDoctrineListener(event: Events::preUpdate)]
#[AsDoctrineListener(event: Events::preRemove)]
class UserSecurityListener
{
    public function __construct(private UserSecurityGuard $guard) {}

    public function preUpdate(PreUpdateEventArgs $args): void
    {
        $entity = $args->getObject();
        if (!$entity instanceof User) {
            return;
        }

        if ($args->hasChangedField('role')) {
            $newRole = $args->getNewValue('role');
            $this->guard->ensureLastSuperAdmin($entity, $newRole);
            $this->guard->preventEscalation($newRole);
        }
    }

    public function preRemove(PreRemoveEventArgs $args): void
    {
        $entity = $args->getObject();
        if (!$entity instanceof User) {
            return;
        }

        // null signals a delete operation
        $this->guard->ensureLastSuperAdmin($entity, null);
    }
}
