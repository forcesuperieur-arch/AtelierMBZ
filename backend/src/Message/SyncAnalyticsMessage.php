<?php

namespace App\Message;

/**
 * Demande de recalcul analytics pour un RDV (traité par le worker : le calcul
 * — ~25 requêtes d'agrégats — ne bloque plus la requête HTTP qui l'a déclenché).
 */
final class SyncAnalyticsMessage
{
    public function __construct(
        public readonly int $rdvId,
    ) {}
}
