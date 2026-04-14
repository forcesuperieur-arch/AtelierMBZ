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
            'terminer' => $this->bus->dispatch(new SendRappelMessage($rdv->getId(), 'travaux_termines')),
            default => null,
        };
    }
}
