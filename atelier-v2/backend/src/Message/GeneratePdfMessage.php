<?php
namespace App\Message;

/**
 * Message to generate a PDF asynchronously.
 */
class GeneratePdfMessage
{
    public function __construct(
        public readonly string $type,  // or, facture, devis
        public readonly int $entityId,
    ) {}
}
