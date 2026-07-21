<?php

namespace App\EventListener;

use App\Entity\User;
use App\Security\UserSecurityGuard;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;

#[AsDoctrineListener(event: Events::prePersist)]
#[AsDoctrineListener(event: Events::preUpdate)]
#[AsDoctrineListener(event: Events::preRemove)]
class UserSecurityListener
{
    public function __construct(private UserSecurityGuard $guard) {}

    /**
     * À la CRÉATION d'un utilisateur : empêcher d'attribuer un rôle supérieur au sien
     * (un admin ne peut pas se créer un super_admin). En CLI (seed, app:create-admin)
     * aucun utilisateur n'est connecté → le guard passe (pas de blocage du bootstrap).
     */
    public function prePersist(PrePersistEventArgs $args): void
    {
        $entity = $args->getObject();
        if (!$entity instanceof User) {
            return;
        }

        $this->guard->preventEscalation($entity->getRole());
    }

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
