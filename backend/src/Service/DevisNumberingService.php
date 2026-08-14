<?php

namespace App\Service;

use Doctrine\DBAL\Connection;

/**
 * Génération atomique du numéro de devis via une séquence PostgreSQL — évite
 * la race condition du précédent random_int(1,99999) sur colonne unique.
 */
class DevisNumberingService
{
    public function __construct(private Connection $connection)
    {
    }

    public function nextNumber(): string
    {
        $year = date('Y');
        $this->ensureSequence();
        $nextVal = (int) $this->connection->fetchOne("SELECT nextval('devis_numero_seq')");

        return sprintf('DEV-%s-%05d', $year, $nextVal);
    }

    private function ensureSequence(): void
    {
        try {
            $this->connection->executeStatement('CREATE SEQUENCE IF NOT EXISTS devis_numero_seq START 1');
        } catch (\Throwable) {
            // Séquence déjà existante ou base indisponible : nextval lèvera l'erreur le cas échéant.
        }
    }
}
