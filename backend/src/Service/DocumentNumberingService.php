<?php

namespace App\Service;

use Doctrine\DBAL\Connection;

/**
 * Génération atomique de numéros de documents via séquences PostgreSQL.
 * Évite les race conditions sur Devis.numeroDevis et OrdreReparation.numeroOr.
 */
class DocumentNumberingService
{
    public function __construct(private Connection $connection)
    {
    }

    public function nextDevisNumber(): string
    {
        $year = date('Y');
        $this->ensureSequence('devis_numero_seq');
        $nextVal = (int) $this->connection->fetchOne("SELECT nextval('devis_numero_seq')");

        return sprintf('DEV-%s-%05d', $year, $nextVal);
    }

    public function nextOrdreReparationNumber(): string
    {
        $year = date('Y');
        $this->ensureSequence('ordre_reparation_numero_seq');
        $nextVal = (int) $this->connection->fetchOne("SELECT nextval('ordre_reparation_numero_seq')");

        return sprintf('OR-%s-%05d', $year, $nextVal);
    }

    private function ensureSequence(string $name): void
    {
        try {
            $this->connection->executeStatement(sprintf('CREATE SEQUENCE IF NOT EXISTS %s START 1', $name));
        } catch (\Throwable) {
            // Séquence déjà existante ou base indisponible : on laisse nextval lever l'erreur le cas échéant.
        }
    }
}
