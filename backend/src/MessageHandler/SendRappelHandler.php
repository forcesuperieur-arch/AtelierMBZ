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

        // Email
        if ($client->getEmail()) {
            $this->dispatcher->sendFromTemplate(
                $message->typeRappel,
                'email',
                $atId,
                $client->getEmail(),
                $vars,
                'RendezVous',
                $rdv->getId(),
            );
        }

        // SMS
        if ($client->getTelephone()) {
            $this->dispatcher->sendFromTemplate(
                $message->typeRappel,
                'sms',
                $atId,
                $client->getTelephone(),
                $vars,
                'RendezVous',
                $rdv->getId(),
            );
        }
    }
}
