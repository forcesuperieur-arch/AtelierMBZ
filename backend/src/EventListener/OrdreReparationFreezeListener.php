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

        $currentStatut = $entity->getStatut();
        $oldStatut = $args->hasChangedField('statut') ? (string) $args->getOldValue('statut') : $currentStatut;
        $wasFrozen = in_array($oldStatut, ['signe', 'execute', 'termine', 'rectifie'], true);
        $isFrozen = in_array($currentStatut, ['signe', 'execute', 'termine', 'rectifie'], true);

        if (!$wasFrozen && !$isFrozen) {
            return;
        }

        $changeSet = $args->getEntityChangeSet();

        $allowedFields = [
            'statut',
            'mechanicNotes',
            'mechanicNotesUpdatedAt',
            'mechanicCheckup',
            'mechanicCheckupUpdatedAt',
            'etatVehicule',
            'signedSnapshot',
            'signedHash',
            'signedAt',
        ];

        if (!$wasFrozen && $args->hasChangedField('statut') && $currentStatut === 'signe') {
            $allowedFields = [
                'statut',
                'signatureClient',
                'signedSnapshot',
                'signedHash',
                'signedAt',
                'signedIp',
                'signedUserAgent',
                'kilometrage',
                'etatVehicule',
            ];
        }

        foreach (array_keys($changeSet) as $field) {
            if (!in_array($field, $allowedFields, true)) {
                throw new \DomainException(
                    'OR signé — modification interdite. Utilisez la rectification.'
                );
            }
        }
    }
}
