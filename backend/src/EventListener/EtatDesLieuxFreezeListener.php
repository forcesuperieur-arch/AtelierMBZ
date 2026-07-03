<?php

namespace App\EventListener;

use App\Entity\EtatDesLieux;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;

/**
 * Gel de l'état des lieux après signature (pattern OrdreReparationFreezeListener,
 * en plus strict) : une fois signé, AUCUN champ n'est modifiable — liste
 * blanche vide. Le flush de signature lui-même passe (l'ancien signedHash est
 * encore null à ce moment-là).
 */
#[AsDoctrineListener(event: Events::preUpdate)]
class EtatDesLieuxFreezeListener
{
    public function preUpdate(PreUpdateEventArgs $args): void
    {
        $entity = $args->getObject();
        if (!$entity instanceof EtatDesLieux) {
            return;
        }

        $oldHash = $args->hasChangedField('signedHash')
            ? $args->getOldValue('signedHash')
            : $entity->getSignedHash();

        // Pas encore signé (ou flush de signature en cours) : modifications libres
        if ($oldHash === null) {
            return;
        }

        throw new \DomainException(
            'État des lieux signé — document figé, modification interdite.'
        );
    }
}
