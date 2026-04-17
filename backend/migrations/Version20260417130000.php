<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260417130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'LOT 4: DemandeTravauxSupp new fields + Notification table';
    }

    public function up(Schema $schema): void
    {
        // DemandeTravauxSupp — new LOT 4 fields
        $this->addSql('ALTER TABLE demandes_travaux_supp ADD COLUMN token_validation VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE demandes_travaux_supp ADD COLUMN prestations_choisies JSON NOT NULL DEFAULT \'[]\'');
        $this->addSql('ALTER TABLE demandes_travaux_supp ADD COLUMN photos_justificatives TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE demandes_travaux_supp ADD COLUMN decision_ip VARCHAR(45) DEFAULT NULL');
        $this->addSql('ALTER TABLE demandes_travaux_supp ADD COLUMN decision_user_agent VARCHAR(500) DEFAULT NULL');
        $this->addSql('ALTER TABLE demandes_travaux_supp ADD COLUMN signature_client TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE demandes_travaux_supp ADD COLUMN signed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE demandes_travaux_supp ADD COLUMN or_complementaire_id INT DEFAULT NULL');

        // Backfill token_validation for existing rows
        $this->addSql("UPDATE demandes_travaux_supp SET token_validation = md5(random()::text || id::text || now()::text) || md5(random()::text) WHERE token_validation IS NULL");

        // Now set NOT NULL + UNIQUE
        $this->addSql('ALTER TABLE demandes_travaux_supp ALTER COLUMN token_validation SET NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX uniq_demande_token ON demandes_travaux_supp (token_validation)');

        // FK to ordres_reparation
        $this->addSql('ALTER TABLE demandes_travaux_supp ADD CONSTRAINT fk_demande_or_comp FOREIGN KEY (or_complementaire_id) REFERENCES ordres_reparation (id) ON DELETE SET NULL');

        // Notifications table
        $this->addSql('CREATE TABLE notifications (
            id SERIAL PRIMARY KEY,
            atelier_id INT DEFAULT NULL,
            type VARCHAR(100) NOT NULL,
            message VARCHAR(500) NOT NULL,
            entity_type VARCHAR(100) DEFAULT NULL,
            entity_id INT DEFAULT NULL,
            target_roles JSON NOT NULL DEFAULT \'[]\',
            target_user_id INT DEFAULT NULL,
            is_read BOOLEAN NOT NULL DEFAULT FALSE,
            priority VARCHAR(50) NOT NULL DEFAULT \'normal\',
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP
        )');
        $this->addSql('CREATE INDEX idx_notif_atelier_read ON notifications (atelier_id, is_read)');
        $this->addSql('CREATE INDEX idx_notif_target_user ON notifications (target_user_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS notifications');
        $this->addSql('ALTER TABLE demandes_travaux_supp DROP CONSTRAINT IF EXISTS fk_demande_or_comp');
        $this->addSql('DROP INDEX IF EXISTS uniq_demande_token');
        $this->addSql('ALTER TABLE demandes_travaux_supp DROP COLUMN IF EXISTS token_validation');
        $this->addSql('ALTER TABLE demandes_travaux_supp DROP COLUMN IF EXISTS prestations_choisies');
        $this->addSql('ALTER TABLE demandes_travaux_supp DROP COLUMN IF EXISTS photos_justificatives');
        $this->addSql('ALTER TABLE demandes_travaux_supp DROP COLUMN IF EXISTS decision_ip');
        $this->addSql('ALTER TABLE demandes_travaux_supp DROP COLUMN IF EXISTS decision_user_agent');
        $this->addSql('ALTER TABLE demandes_travaux_supp DROP COLUMN IF EXISTS signature_client');
        $this->addSql('ALTER TABLE demandes_travaux_supp DROP COLUMN IF EXISTS signed_at');
        $this->addSql('ALTER TABLE demandes_travaux_supp DROP COLUMN IF EXISTS or_complementaire_id');
    }
}
