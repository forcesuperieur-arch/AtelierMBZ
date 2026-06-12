<?php

namespace App\EventListener;

use App\Entity\ConfigAtelier;
use App\Entity\Notification;
use App\Entity\RendezVous;
use App\Service\MercureNotifier;
use App\Service\NotificationDispatcher;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Workflow\Event\CompletedEvent;

/**
 * Listens to RDV workflow transitions and dispatches multi-channel notifications.
 *
 * Lot A : chaque étape signifiante notifie le client (transparence maximale),
 * sous réserve de l'interrupteur correspondant dans ConfigAtelier.notificationsEtapes.
 */
#[AsEventListener(event: 'workflow.rendez_vous.completed.confirmer')]
#[AsEventListener(event: 'workflow.rendez_vous.completed.reception')]
#[AsEventListener(event: 'workflow.rendez_vous.completed.start_travail')]
#[AsEventListener(event: 'workflow.rendez_vous.completed.reprendre_apres_pieces')]
#[AsEventListener(event: 'workflow.rendez_vous.completed.terminer')]
#[AsEventListener(event: 'workflow.rendez_vous.completed.attendre_pieces')]
#[AsEventListener(event: 'workflow.rendez_vous.completed.mettre_en_attente_pieces')]
#[AsEventListener(event: 'workflow.rendez_vous.completed.declarer_no_show')]
#[AsEventListener(event: 'workflow.rendez_vous.completed.no_show')]
class RdvWorkflowListener
{
    public function __construct(
        private NotificationDispatcher $dispatcher,
        private EntityManagerInterface $em,
        private MercureNotifier $mercure,
    ) {}

    public function __invoke(CompletedEvent $event): void
    {
        $rdv = $event->getSubject();
        if (!$rdv instanceof RendezVous) {
            return;
        }

        $transition = $event->getTransition()->getName();

        match ($transition) {
            'confirmer' => $this->notifyClient($rdv, 'rdv_confirmation', 'Confirmation RDV'),
            // Étapes intermédiaires : email/SMS client uniquement — pas de cloche
            // staff, c'est le staff lui-même qui vient de faire l'action.
            'reception' => $this->notifyClient($rdv, 'rdv_reception', 'Moto réceptionnée', staffNotif: false),
            'start_travail', 'reprendre_apres_pieces' => $this->notifyClient($rdv, 'travaux_demarres', 'Travaux démarrés', staffNotif: false),
            'terminer'  => $this->notifyClient($rdv, 'travaux_termines', 'Moto prête'),
            'attendre_pieces', 'mettre_en_attente_pieces' => $this->notifyClient($rdv, 'attente_pieces', 'En attente de pièces'),
            'declarer_no_show', 'no_show' => $this->notifyClient($rdv, 'no_show', 'Client absent (no-show)'),
            default => null,
        };
    }

    private function notifyClient(RendezVous $rdv, string $templateCode, string $uiTitle, bool $staffNotif = true): void
    {
        $client = $rdv->getClient();
        if (!$client) {
            return;
        }

        $atId = $rdv->getAtelierId() ?? 0;
        $dateRdv = $rdv->getDateRdv()->format('d/m/Y');
        $heureRdv = $rdv->getHeureRdv()->format('H:i');

        $etapeEnabled = $this->isEtapeEnabled($atId, $templateCode);

        $vars = [
            'client_nom'    => $client->getNom(),
            'client_prenom' => $client->getPrenom(),
            'date_rdv'      => $dateRdv,
            'heure_rdv'     => $heureRdv,
            'type_intervention' => $rdv->getTypeIntervention(),
        ];

        // Email
        if ($etapeEnabled && $client->getEmail()) {
            $this->dispatcher->sendFromTemplate(
                $templateCode,
                'email',
                $atId,
                $client->getEmail(),
                $vars,
                'RendezVous',
                $rdv->getId(),
            );
        }

        // SMS
        if ($etapeEnabled && $client->getTelephone()) {
            $this->dispatcher->sendFromTemplate(
                $templateCode,
                'sms',
                $atId,
                $client->getTelephone(),
                $vars,
                'RendezVous',
                $rdv->getId(),
            );
        }

        if (!$staffNotif) {
            return;
        }

        // Notification UI interne (jamais coupée par l'interrupteur client)
        $notif = new Notification();
        $notif->setAtelierId($atId);
        $notif->setType($templateCode);
        $notif->setSeverity(match ($templateCode) {
            'no_show' => 'warning',
            'travaux_termines' => 'success',
            default => 'info',
        });
        $notif->setTitle($uiTitle);
        $notif->setMessage($client->getPrenom() . ' ' . $client->getNom() . ' — ' . $dateRdv . ' ' . $heureRdv);
        $notif->setRelatedEntityType('RendezVous');
        $notif->setRelatedEntityId($rdv->getId());
        $notif->setTargetRoles(['ROLE_RECEPTIONNAIRE', 'ROLE_ADMIN']);

        $this->em->persist($notif);
        $this->em->flush();

        $this->mercure->publishToAtelier($atId, $notif);
    }

    private function isEtapeEnabled(int $atelierId, string $templateCode): bool
    {
        $config = $this->em->getRepository(ConfigAtelier::class)->findOneBy(['atelierId' => $atelierId]);

        // Pas de config = défauts (tout activé)
        return $config === null || $config->isNotificationEtapeEnabled($templateCode);
    }
}
