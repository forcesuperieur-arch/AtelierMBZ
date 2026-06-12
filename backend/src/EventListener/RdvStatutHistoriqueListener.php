<?php

namespace App\EventListener;

use App\Entity\RdvStatutHistorique;
use App\Entity\RendezVous;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Workflow\Event\CompletedEvent;

/**
 * Écoute TOUTES les transitions du workflow RDV (événement generic « completed »)
 * et trace l'historique horodaté en base — l'audit trail Symfony Workflow ne
 * persiste rien. Sert la timeline de l'espace client.
 */
#[AsEventListener(event: 'workflow.rendez_vous.completed')]
class RdvStatutHistoriqueListener
{
    public function __construct(private EntityManagerInterface $em) {}

    public function __invoke(CompletedEvent $event): void
    {
        $rdv = $event->getSubject();
        if (!$rdv instanceof RendezVous) {
            return;
        }

        // Sur l'événement « completed », le marking est déjà appliqué :
        // la place courante EST le nouveau statut.
        $places = array_keys($event->getMarking()->getPlaces());
        $statut = $places[0] ?? $rdv->getStatut();

        $histo = new RdvStatutHistorique();
        $histo->setRendezVous($rdv);
        $histo->setAtelierId($rdv->getAtelierId());
        $histo->setTransition($event->getTransition()->getName());
        $histo->setStatut($statut);

        $this->em->persist($histo);
        $this->em->flush();
    }
}
