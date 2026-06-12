<?php

namespace App\MessageHandler;

use App\Message\SyncAnalyticsMessage;
use App\Service\AnalyticsSyncService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class SyncAnalyticsHandler
{
    public function __construct(
        private AnalyticsSyncService $analyticsSync,
    ) {}

    public function __invoke(SyncAnalyticsMessage $message): void
    {
        // Idempotent : recalcule l'état courant ; syncRdv ignore un RDV disparu.
        $this->analyticsSync->syncRdv($message->rdvId);
    }
}
