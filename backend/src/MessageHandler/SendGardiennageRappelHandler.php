<?php

namespace App\MessageHandler;

use App\Entity\RendezVous;
use App\Message\SendGardiennageRappelMessage;
use App\Service\AuditService;
use App\Service\NotificationDispatcher;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class SendGardiennageRappelHandler
{
    public function __construct(
        private EntityManagerInterface $em,
        private NotificationDispatcher $notificationDispatcher,
        private AuditService $auditService,
    ) {}

    public function __invoke(SendGardiennageRappelMessage $message): void
    {
        $rdv = $this->em->getRepository(RendezVous::class)->find($message->rdvId);
        if (!$rdv) {
            return;
        }

        // Guard: only act if still in a non-recovered status
        if (!in_array($rdv->getStatut(), ['termine', 'en_attente_pieces', 'en_gardiennage'], true)) {
            return;
        }

        $client = $rdv->getClient();
        if (!$client) {
            return;
        }

        $vehicule = $rdv->getVehicule();
        $variables = [
            'client_nom'    => $client->getNom(),
            'client_prenom' => $client->getPrenom(),
            'seuil_jours'   => (string) $message->seuilJours,
            'plaque'        => $vehicule?->getPlaque() ?? '',
            'reference_rdv' => (string) $rdv->getId(),
        ];

        // Send SMS if client has a phone number
        if ($client->getTelephone()) {
            $this->notificationDispatcher->sendFromTemplate(
                $message->templateCode,
                'sms',
                $message->atelierId,
                $client->getTelephone(),
                $variables,
                'RendezVous',
                $rdv->getId(),
            );
        }

        // Send email if client has an email address
        if ($client->getEmail()) {
            $this->notificationDispatcher->sendFromTemplate(
                $message->templateCode,
                'email',
                $message->atelierId,
                $client->getEmail(),
                $variables,
                'RendezVous',
                $rdv->getId(),
            );
        }

        $this->auditService->log(
            'gardiennage_relance_' . $message->seuilJours . 'j',
            'RendezVous',
            $rdv->getId(),
            json_encode([
                'templateCode' => $message->templateCode,
                'clientId'     => $client->getId(),
                'seuilJours'   => $message->seuilJours,
            ], JSON_UNESCAPED_UNICODE),
        );
    }
}
