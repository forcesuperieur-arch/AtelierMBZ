<?php
namespace App\EventListener;

use Doctrine\ORM\Event\PrePersistEventArgs;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Automatically sets atelier_id on new entities based on the current user.
 */
class TenantSetterListener
{
    public function __construct(private Security $security) {}

    public function prePersist(PrePersistEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!method_exists($entity, 'getAtelierId') || !method_exists($entity, 'setAtelierId')) {
            return;
        }

        // Don't override if already set
        if ($entity->getAtelierId() !== null) {
            return;
        }

        $user = $this->security->getUser();
        if ($user && method_exists($user, 'getAtelierId')) {
            $entity->setAtelierId($user->getAtelierId());
        }
    }
}
