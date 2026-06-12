<?php

namespace App\EventListener;

use App\Entity\RendezVous;
use App\Entity\Facture;
use App\Entity\OrdreReparation;
use App\Message\SyncAnalyticsMessage;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Délègue le recalcul analytics au worker : exécuté en synchrone, il ajoutait
 * ~25 requêtes d'agrégats (+ flush imbriqués) à chaque écriture RDV/OR/Facture.
 */
class AnalyticsEventListener
{
    public function __construct(private MessageBusInterface $bus) {}

    public function postPersist(LifecycleEventArgs $args): void
    {
        $this->handle($args->getObject());
    }

    public function postUpdate(LifecycleEventArgs $args): void
    {
        $this->handle($args->getObject());
    }

    private function handle(object $entity): void
    {
        if ($entity instanceof RendezVous) {
            $this->bus->dispatch(new SyncAnalyticsMessage($entity->getId()));
        } elseif ($entity instanceof Facture || $entity instanceof OrdreReparation) {
            $rdv = $entity->getRendezVous();
            if ($rdv) {
                $this->bus->dispatch(new SyncAnalyticsMessage($rdv->getId()));
            }
        }
    }
}
