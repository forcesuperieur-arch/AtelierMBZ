<?php
namespace App\Message;

/**
 * Message to send an email reminder for an appointment.
 */
class SendRappelMessage
{
    public function __construct(
        public readonly int $rdvId,
        public readonly string $typeRappel,  // confirmation, rappel_j1, rappel_j3, manuel
    ) {}
}
