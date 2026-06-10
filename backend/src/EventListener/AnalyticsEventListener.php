<?php

namespace App\EventListener;

use App\Entity\RendezVous;
use App\Entity\Facture;
use App\Entity\OrdreReparation;
use App\Service\AnalyticsSyncService;
use Doctrine\Persistence\Event\LifecycleEventArgs;

class AnalyticsEventListener
{
    public function __construct(private AnalyticsSyncService $analyticsSync) {}

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
            $this->analyticsSync->syncRdv($entity->getId());
        } elseif ($entity instanceof Facture || $entity instanceof OrdreReparation) {
            $rdv = $entity->getRendezVous();
            if ($rdv) {
                $this->analyticsSync->syncRdv($rdv->getId());
            }
        }
    }
}
