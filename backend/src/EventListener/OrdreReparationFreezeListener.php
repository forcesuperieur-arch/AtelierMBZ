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
            // Le scellé de RÉCEPTION (signedSnapshot/signedHash/signedAt) est IMMUABLE
            // une fois l'OR gelé : il n'apparaît volontairement PAS dans cette liste,
            // pour que toute tentative de réécriture lève une DomainException.
        ];

        if (!$wasFrozen && $args->hasChangedField('statut') && $currentStatut === 'signe') {
            $allowedFields = [
                'statut',
                'signatureClient',
                'signatureClientRestitution',
                'signeClientRestitutionAt',
                'signedSnapshot',
                'signedHash',
                'signedAt',
                'signedIp',
                'signedUserAgent',
                'kilometrage',
                'etatVehicule',
                // Scellé final posé au moment de la signature de restitution.
                'finalSnapshot',
                'finalHash',
                'finalizedAt',
                'pdfArchiveName',
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
