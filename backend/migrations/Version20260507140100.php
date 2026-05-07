<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * [LOT-0] Hash d'intégrité SHA-256 sur Livre de Police (Art. 321-7 CP).
 */
final class Version20260507140100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '[LOT-0] Add integrity_hash to vo_livre_police for immutability proof';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE vo_livre_police ADD COLUMN IF NOT EXISTS integrity_hash VARCHAR(64) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE vo_livre_police DROP COLUMN IF EXISTS integrity_hash');
    }
}
