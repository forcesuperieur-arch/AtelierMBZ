<?php

namespace App\EventSubscriber;

use App\Entity\Devis;
use App\Service\DocumentNumberingService;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;

#[AsDoctrineListener(event: Events::prePersist)]
class DevisNumeroSubscriber
{
    public function __construct(
        private DocumentNumberingService $numberingService,
    ) {}

    public function prePersist(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!$entity instanceof Devis) {
            return;
        }

        // getNumeroDevis() est typé string non-nullable : utilise Reflection pour tester l'initialisation.
        $ref = new \ReflectionProperty($entity, 'numeroDevis');
        if ($ref->isInitialized($entity) && $entity->getNumeroDevis() !== '') {
            return;
        }

        $entity->setNumeroDevis($this->numberingService->nextDevisNumber());
    }
}
