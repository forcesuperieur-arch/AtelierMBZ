<?php

namespace App\MessageHandler;

use App\Entity\RendezVous;
use App\Message\SendRappelMessage;
use App\Service\NotificationDispatcher;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class SendRappelHandler
{
    public function __construct(
        private EntityManagerInterface $em,
        private NotificationDispatcher $dispatcher,
    ) {}

    public function __invoke(SendRappelMessage $message): void
    {
        $rdv = $this->em->getRepository(RendezVous::class)->find($message->rdvId);
        if (!$rdv) {
            return;
        }

        $client = $rdv->getClient();
        if (!$client) {
            return;
        }

        $atId = $rdv->getAtelierId() ?? 0;
        $dateRdv = $rdv->getDateRdv()->format('d/m/Y');
        $heureRdv = $rdv->getHeureRdv()->format('H:i');

        $vars = [
            'client_nom'    => $client->getNom(),
            'client_prenom' => $client->getPrenom(),
            'date_rdv'      => $dateRdv,
            'heure_rdv'     => $heureRdv,
            'type_intervention' => $rdv->getTypeIntervention(),
        ];

        $attempted = false;
        $anySuccess = false;

        // Email
        if ($client->getEmail()) {
            $attempted = true;
            $anySuccess = $this->dispatcher->sendFromTemplate(
                $message->typeRappel,
                'email',
                $atId,
                $client->getEmail(),
                $vars,
                'RendezVous',
                $rdv->getId(),
            )->isSuccess() || $anySuccess;
        }

        // SMS
        if ($client->getTelephone()) {
            $attempted = true;
            $anySuccess = $this->dispatcher->sendFromTemplate(
                $message->typeRappel,
                'sms',
                $atId,
                $client->getTelephone(),
                $vars,
                'RendezVous',
                $rdv->getId(),
            )->isSuccess() || $anySuccess;
        }

        // Si on avait un destinataire mais qu'AUCUN canal n'a abouti (ex. panne
        // SMTP + SMS pendant le batch J-1), on lève : Messenger réessaiera puis
        // routera vers le transport `failed` (messenger.yaml) au lieu d'acquitter
        // le message et de perdre le rappel en silence. On ne lève JAMAIS sur un
        // succès partiel → pas de doublon d'envoi au rejeu.
        if ($attempted && !$anySuccess) {
            throw new \RuntimeException(sprintf(
                'Rappel %s non délivré pour le RDV #%d : tous les canaux ont échoué.',
                $message->typeRappel,
                $rdv->getId(),
            ));
        }
    }
}
