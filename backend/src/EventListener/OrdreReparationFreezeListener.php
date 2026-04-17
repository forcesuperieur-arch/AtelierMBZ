<?php

namespace App\EventListener;

use App\Entity\OrdreReparation;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;

#[AsDoctrineListener(event: Events::preUpdate)]
class OrdreReparationFreezeListener
{
    public function preUpdate(PreUpdateEventArgs $args): void
    {
        $entity = $args->getObject();
        if (!$entity instanceof OrdreReparation) {
            return;
        }

        if (!in_array($entity->getStatut(), ['signe', 'execute', 'termine'], true)) {
            return;
        }

        $changeSet = $args->getEntityChangeSet();
        // Only statut transitions are allowed on frozen ORs
        $allowedFields = ['statut'];

        foreach (array_keys($changeSet) as $field) {
            if (!in_array($field, $allowedFields, true)) {
                throw new \DomainException(
                    'OR signé — modification interdite. Utilisez la rectification.'
                );
            }
        }
    }
}
