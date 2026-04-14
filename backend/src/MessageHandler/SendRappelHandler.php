<?php
namespace App\MessageHandler;

use App\Entity\Client;
use App\Entity\EmailTemplate;
use App\Entity\RappelEmail;
use App\Entity\RendezVous;
use App\Message\SendRappelMessage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Email;

#[AsMessageHandler]
class SendRappelHandler
{
    public function __construct(
        private EntityManagerInterface $em,
        private MailerInterface $mailer,
    ) {}

    public function __invoke(SendRappelMessage $message): void
    {
        $rdv = $this->em->getRepository(RendezVous::class)->find($message->rdvId);
        if (!$rdv) return;

        $client = $rdv->getClient();
        if (!$client || !$client->getEmail()) return;

        // Find email template
        $template = $this->em->getRepository(EmailTemplate::class)->findOneBy([
            'code' => $message->typeRappel,
            'isActive' => 1,
        ]);

        $sujet = $template ? $template->getSujet() : 'Rappel - Votre rendez-vous';
        $corps = $template ? $template->getCorpsHtml() : $this->getDefaultBody($message->typeRappel);

        // Replace template variables
        $variables = [
            '{{client_nom}}' => $client->getNom(),
            '{{client_prenom}}' => $client->getPrenom(),
            '{{date_rdv}}' => $rdv->getDateRdv()->format('d/m/Y'),
            '{{heure_rdv}}' => $rdv->getHeureRdv()->format('H:i'),
            '{{type_intervention}}' => $rdv->getTypeIntervention(),
            '{{token_suivi}}' => $rdv->getTokenSuivi(),
        ];

        $corpsRendu = strtr($corps, $variables);
        $sujetRendu = strtr($sujet, $variables);

        // Send email
        $email = (new Email())
            ->to($client->getEmail())
            ->subject($sujetRendu)
            ->html($corpsRendu);

        $rappel = new RappelEmail();
        $rappel->setAtelierId($rdv->getAtelierId());
        $rappel->setRdv($rdv);
        $rappel->setClient($client);
        $rappel->setTypeRappel($message->typeRappel);
        $rappel->setDestinataire($client->getEmail());
        $rappel->setSujet($sujetRendu);
        $rappel->setDateEnvoiPrevu(new \DateTime());

        try {
            $this->mailer->send($email);
            $rappel->setStatut('envoye');
            $rappel->setDateEnvoiReel(new \DateTime());
        } catch (\Exception $e) {
            $rappel->setStatut('erreur');
            $rappel->setErreur($e->getMessage());
        }

        $this->em->persist($rappel);
        $this->em->flush();
    }

    private function getDefaultBody(string $type): string
    {
        return match ($type) {
            'confirmation' => '<p>Bonjour {{client_prenom}},</p><p>Votre rendez-vous du {{date_rdv}} à {{heure_rdv}} pour {{type_intervention}} est confirmé.</p>',
            'rappel_j1' => '<p>Bonjour {{client_prenom}},</p><p>Rappel : votre rendez-vous est demain {{date_rdv}} à {{heure_rdv}}.</p>',
            'rappel_j3' => '<p>Bonjour {{client_prenom}},</p><p>Rappel : votre rendez-vous est dans 3 jours, le {{date_rdv}} à {{heure_rdv}}.</p>',
            default => '<p>Bonjour {{client_prenom}},</p><p>Information concernant votre rendez-vous du {{date_rdv}}.</p>',
        };
    }
}
