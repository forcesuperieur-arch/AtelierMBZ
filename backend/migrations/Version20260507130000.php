<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * [LOT-0] Timeout session 30min (RGPD) — colonne users.last_activity_at.
 */
final class Version20260507130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '[LOT-0] Add last_activity_at to users for session inactivity timeout (30min)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD COLUMN IF NOT EXISTS last_activity_at TIMESTAMP DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP COLUMN IF EXISTS last_activity_at');
    }
}
