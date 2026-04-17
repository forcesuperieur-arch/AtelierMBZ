<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260417110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'LOT 1: Add OR signature, freeze, and rectification fields';
    }

    public function up(Schema $schema): void
    {
        // Statut field
        $this->addSql("ALTER TABLE ordres_reparation ADD COLUMN statut VARCHAR(50) NOT NULL DEFAULT 'brouillon'");

        // Signature proof fields
        $this->addSql('ALTER TABLE ordres_reparation ADD COLUMN signed_snapshot JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE ordres_reparation ADD COLUMN signed_hash VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE ordres_reparation ADD COLUMN signed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE ordres_reparation ADD COLUMN signed_ip VARCHAR(45) DEFAULT NULL');
        $this->addSql('ALTER TABLE ordres_reparation ADD COLUMN signed_user_agent VARCHAR(500) DEFAULT NULL');

        // Rectification fields
        $this->addSql('ALTER TABLE ordres_reparation ADD COLUMN rectified_from_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE ordres_reparation ADD COLUMN motif_rectification VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE ordres_reparation ADD COLUMN rectified_by INT DEFAULT NULL');
        $this->addSql('ALTER TABLE ordres_reparation ADD COLUMN rectified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');

        $this->addSql('ALTER TABLE ordres_reparation ADD CONSTRAINT fk_or_rectified_from FOREIGN KEY (rectified_from_id) REFERENCES ordres_reparation (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX idx_or_rectified_from ON ordres_reparation (rectified_from_id)');
        $this->addSql('CREATE INDEX idx_or_statut ON ordres_reparation (statut)');

        // Backfill: mark existing ORs with signatures as 'signe'
        $this->addSql("UPDATE ordres_reparation SET statut = 'signe' WHERE signature_client IS NOT NULL AND signature_client != ''");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ordres_reparation DROP CONSTRAINT IF EXISTS fk_or_rectified_from');
        $this->addSql('DROP INDEX IF EXISTS idx_or_rectified_from');
        $this->addSql('DROP INDEX IF EXISTS idx_or_statut');
        $this->addSql('ALTER TABLE ordres_reparation DROP COLUMN IF EXISTS statut');
        $this->addSql('ALTER TABLE ordres_reparation DROP COLUMN IF EXISTS signed_snapshot');
        $this->addSql('ALTER TABLE ordres_reparation DROP COLUMN IF EXISTS signed_hash');
        $this->addSql('ALTER TABLE ordres_reparation DROP COLUMN IF EXISTS signed_at');
        $this->addSql('ALTER TABLE ordres_reparation DROP COLUMN IF EXISTS signed_ip');
        $this->addSql('ALTER TABLE ordres_reparation DROP COLUMN IF EXISTS signed_user_agent');
        $this->addSql('ALTER TABLE ordres_reparation DROP COLUMN IF EXISTS rectified_from_id');
        $this->addSql('ALTER TABLE ordres_reparation DROP COLUMN IF EXISTS motif_rectification');
        $this->addSql('ALTER TABLE ordres_reparation DROP COLUMN IF EXISTS rectified_by');
        $this->addSql('ALTER TABLE ordres_reparation DROP COLUMN IF EXISTS rectified_at');
    }
}
