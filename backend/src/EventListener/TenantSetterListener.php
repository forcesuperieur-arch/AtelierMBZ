<?php
namespace App\EventListener;

use App\Service\CurrentAtelierResolver;
use Doctrine\ORM\Event\PrePersistEventArgs;

/**
 * Automatically sets atelier_id on new entities based on the current atelier context.
 */
class TenantSetterListener
{
    public function __construct(private CurrentAtelierResolver $currentAtelierResolver) {}

    public function prePersist(PrePersistEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!method_exists($entity, 'getAtelierId') || !method_exists($entity, 'setAtelierId')) {
            return;
        }

        if ($entity->getAtelierId() !== null) {
            return;
        }

        $atelierId = $this->currentAtelierResolver->resolveAtelierId();
        if ($atelierId !== null) {
            $entity->setAtelierId($atelierId);
        }
    }
}
