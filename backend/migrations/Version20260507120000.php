<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * [LOT-0] Numérotation atomique : séquences PostgreSQL pour Devis et OrdreReparation.
 * Remplace random_int() (race condition) et 'OR-{rdvId}-{date}' (collisions multi-OR/jour).
 */
final class Version20260507120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '[LOT-0] Add atomic PostgreSQL sequences for devis and ordre_reparation numbering';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE SEQUENCE IF NOT EXISTS devis_numero_seq START 1');
        $this->addSql('CREATE SEQUENCE IF NOT EXISTS ordre_reparation_numero_seq START 1');

        // Best-effort alignment : démarre la séquence devis après le plus haut numéro existant.
        $this->addSql("SELECT setval('devis_numero_seq', GREATEST(
            COALESCE((SELECT MAX(CAST((regexp_match(numero_devis, '^DEV-[0-9]{4}-([0-9]{5})$'))[1] AS INTEGER)) FROM devis WHERE numero_devis ~ '^DEV-[0-9]{4}-[0-9]{5}\$'), 0),
            0
        ) + 1, false)");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP SEQUENCE IF EXISTS devis_numero_seq');
        $this->addSql('DROP SEQUENCE IF EXISTS ordre_reparation_numero_seq');
    }
}
