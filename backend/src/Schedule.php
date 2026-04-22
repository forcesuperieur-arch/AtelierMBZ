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
            // [SPRINT-3] Gardiennage: daily relances J+15/30/45/180 (7:00 AM)
            ->add(RecurringMessage::cron('0 7 * * *', new RunCommandMessage('app:relance-client-stockage')))
            // [SPRINT-3] DA SIV: daily check expiry J+10 alert / J+15 expire (6:00 AM)
            ->add(RecurringMessage::cron('0 6 * * *', new RunCommandMessage('app:check-da-siv-expiry')))
            // [SPRINT-3] Dépôt-vente: daily alert J-7 before mandate expiry (6:30 AM)
            ->add(RecurringMessage::cron('30 6 * * *', new RunCommandMessage('app:check-depot-vente-mandat')))
            // [SPRINT-4] I2 — Rappel révision J-30 (9:00 AM)
            ->add(RecurringMessage::cron('0 9 * * *', new RunCommandMessage('app:rappel-prochaine-revision')))
        ;
    }
}
