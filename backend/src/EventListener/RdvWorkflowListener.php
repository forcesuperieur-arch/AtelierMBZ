<?php
namespace App\EventListener;

use App\Message\SendRappelMessage;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Workflow\Event\CompletedEvent;

/**
 * Listens to RDV workflow transitions and dispatches email notifications.
 */
#[AsEventListener(event: 'workflow.rendez_vous.completed.confirmer')]
#[AsEventListener(event: 'workflow.rendez_vous.completed.terminer')]
#[AsEventListener(event: 'workflow.rendez_vous.completed.attendre_pieces')]
#[AsEventListener(event: 'workflow.rendez_vous.completed.mettre_en_attente_pieces')]
#[AsEventListener(event: 'workflow.rendez_vous.completed.declarer_no_show')]
#[AsEventListener(event: 'workflow.rendez_vous.completed.no_show')]
class RdvWorkflowListener
{
    public function __construct(
        private MessageBusInterface $bus,
    ) {}

    public function __invoke(CompletedEvent $event): void
    {
        $rdv = $event->getSubject();
        $transition = $event->getTransition()->getName();

        match ($transition) {
            'confirmer' => $this->bus->dispatch(new SendRappelMessage($rdv->getId(), 'confirmation')),
            'terminer' => $this->onTerminer($rdv),
            'attendre_pieces', 'mettre_en_attente_pieces' => $this->bus->dispatch(new SendRappelMessage($rdv->getId(), 'attente_pieces')),
            'declarer_no_show', 'no_show' => $this->bus->dispatch(new SendRappelMessage($rdv->getId(), 'no_show')),
            default => null,
        };
    }

    private function onTerminer(object $rdv): void
    {
        $this->bus->dispatch(new SendRappelMessage($rdv->getId(), 'travaux_termines'));
    }
}
