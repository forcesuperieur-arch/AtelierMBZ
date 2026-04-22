<?php

namespace App\Message;

/**
 * Message to dispatch a gardiennage reminder notification to a client.
 */
class SendGardiennageRappelMessage
{
    public function __construct(
        public readonly int $rdvId,
        public readonly string $templateCode, // relance_gardiennage_j15|j30|j45|j180
        public readonly int $atelierId,
        public readonly int $seuilJours,
    ) {}
}
