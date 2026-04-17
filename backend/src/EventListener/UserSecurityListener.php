<?php

namespace App\EventListener;

use App\Entity\User;
use App\Security\UserSecurityGuard;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
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

        if ($args->hasChangedField('role') || $args->hasChangedField('isActive') || $args->hasChangedField('accessStatus')) {
            $newRole = $args->hasChangedField('role') ? (string) $args->getNewValue('role') : $entity->getRole();
            $newIsActive = $args->hasChangedField('isActive') ? (int) $args->getNewValue('isActive') : $entity->getIsActive();
            $newAccessStatus = $args->hasChangedField('accessStatus') ? (string) $args->getNewValue('accessStatus') : $entity->getAccessStatus();

            $this->guard->ensureLastSuperAdmin($entity, $newRole, $newIsActive, $newAccessStatus);

            if ($args->hasChangedField('role')) {
                $this->guard->preventEscalation($newRole);
            }
        }
    }

    public function preRemove(PreRemoveEventArgs $args): void
    {
        $entity = $args->getObject();
        if (!$entity instanceof User) {
            return;
        }

        $this->guard->ensureLastSuperAdmin($entity, null);
    }
}
