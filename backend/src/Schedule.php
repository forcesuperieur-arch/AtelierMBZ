<?php

namespace App;

use App\Message\ProcessScheduledRappels;
use Symfony\Component\Console\Messenger\RunCommandMessage;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule as SymfonySchedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Contracts\Cache\CacheInterface;

#[AsSchedule]
class Schedule implements ScheduleProviderInterface
{
    public function __construct(
        private CacheInterface $cache,
    ) {
    }

    public function getSchedule(): SymfonySchedule
    {
        return (new SymfonySchedule())
            ->stateful($this->cache)
            ->processOnlyLastMissedRun(true)
            ->add(RecurringMessage::cron('0 8 * * *', new ProcessScheduledRappels()))
            // RGPD: monthly data retention purge (1st of each month at 3:00 AM)
            ->add(RecurringMessage::cron('0 3 1 * *', new RunCommandMessage('app:rgpd-purge --execute')))
            // LOT 5: Process notification escalations every minute
            ->add(RecurringMessage::cron('* * * * *', new RunCommandMessage('app:process-notification-escalations')))
            // RGPD: daily purge of identity documents after LP transcription (4:00 AM)
            ->add(RecurringMessage::cron('0 4 * * *', new RunCommandMessage('app:purge-identity-documents')))
            // Rappel révision J-30 (9:00 AM daily)
            ->add(RecurringMessage::cron('0 9 * * *', new RunCommandMessage('app:rappel-prochaine-revision')))
            // Lot A : relance H+4 des travaux supp sans décision client (fenêtre 8h-19h gérée par la commande)
            ->add(RecurringMessage::cron('10 * * * *', new RunCommandMessage('app:relance-demandes-travaux')))
            // Alerte motos immobilisées à l'atelier depuis plus de 72h ouvrées (8h30 daily)
            ->add(RecurringMessage::cron('30 8 * * *', new RunCommandMessage('app:alerte-sejour-atelier')))
            // Réconciliation analytique (2:00 AM) : la table de faits est
            // alimentée message par message et dérive dès qu'un message est
            // perdu ou qu'un RDV est écrit hors du flux applicatif. Un rebuild
            // nocturne garantit que les chiffres de la page Stat correspondent
            // aux rendez-vous réels, sans traquer chaque chemin d'écriture.
            ->add(RecurringMessage::cron('0 2 * * *', new RunCommandMessage('app:analytics:rebuild --all')))
            // Expiration des devis dont la date de validité est dépassée (5:00 AM daily) :
            // le statut "expire" n'était jamais posé, un devis restait valable indéfiniment.
            ->add(RecurringMessage::cron('0 5 * * *', new RunCommandMessage('app:devis-expire')))
        ;
    }
}
