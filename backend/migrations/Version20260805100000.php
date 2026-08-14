<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Numérotation atomique des devis via séquence PostgreSQL : remplace le
 * random_int(1,99999) sur colonne unique (collision = 500 brut, sans retry).
 */
final class Version20260805100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Devis : séquence PostgreSQL devis_numero_seq pour la numérotation atomique';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE SEQUENCE IF NOT EXISTS devis_numero_seq START 1');

        // Best-effort : démarre la séquence après le plus haut numéro déjà en base.
        $this->addSql("SELECT setval('devis_numero_seq', GREATEST(
            COALESCE((SELECT MAX(CAST((regexp_match(numero_devis, '^DEV-[0-9]{4}-([0-9]{5})$'))[1] AS INTEGER)) FROM devis WHERE numero_devis ~ '^DEV-[0-9]{4}-[0-9]{5}\$'), 0),
            0
        ) + 1, false)");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP SEQUENCE IF EXISTS devis_numero_seq');
    }
}
