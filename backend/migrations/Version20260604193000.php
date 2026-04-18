<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260604193000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute la signature documentaire et l archivage PDF des remises en etat VO';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE vo_remises_en_etat ADD signature_data TEXT DEFAULT NULL");
        $this->addSql("ALTER TABLE vo_remises_en_etat ADD signed_snapshot JSON DEFAULT NULL");
        $this->addSql("ALTER TABLE vo_remises_en_etat ADD signed_hash VARCHAR(64) DEFAULT NULL");
        $this->addSql("ALTER TABLE vo_remises_en_etat ADD signed_by INT DEFAULT NULL");
        $this->addSql("ALTER TABLE vo_remises_en_etat ADD signed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL");
        $this->addSql("ALTER TABLE vo_remises_en_etat ADD signed_ip VARCHAR(64) DEFAULT NULL");
        $this->addSql("ALTER TABLE vo_remises_en_etat ADD signed_user_agent VARCHAR(500) DEFAULT NULL");
        $this->addSql('CREATE INDEX IDX_VO_RE_SIGNED_BY ON vo_remises_en_etat (signed_by)');
        $this->addSql('ALTER TABLE vo_remises_en_etat ADD CONSTRAINT FK_VO_RE_SIGNED_BY FOREIGN KEY (signed_by) REFERENCES users (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('ALTER TABLE vo_documents ADD vo_remise_en_etat_id INT DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_VO_DOC_REMISE ON vo_documents (vo_remise_en_etat_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_vo_document_remise_type ON vo_documents (vo_remise_en_etat_id, type) WHERE vo_remise_en_etat_id IS NOT NULL');
        $this->addSql('ALTER TABLE vo_documents ADD CONSTRAINT FK_VO_DOC_REMISE FOREIGN KEY (vo_remise_en_etat_id) REFERENCES vo_remises_en_etat (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE vo_documents DROP CONSTRAINT IF EXISTS FK_VO_DOC_REMISE');
        $this->addSql('DROP INDEX IF EXISTS uniq_vo_document_remise_type');
        $this->addSql('DROP INDEX IF EXISTS IDX_VO_DOC_REMISE');
        $this->addSql('ALTER TABLE vo_documents DROP COLUMN IF EXISTS vo_remise_en_etat_id');

        $this->addSql('ALTER TABLE vo_remises_en_etat DROP CONSTRAINT IF EXISTS FK_VO_RE_SIGNED_BY');
        $this->addSql('DROP INDEX IF EXISTS IDX_VO_RE_SIGNED_BY');
        $this->addSql('ALTER TABLE vo_remises_en_etat DROP COLUMN IF EXISTS signature_data');
        $this->addSql('ALTER TABLE vo_remises_en_etat DROP COLUMN IF EXISTS signed_snapshot');
        $this->addSql('ALTER TABLE vo_remises_en_etat DROP COLUMN IF EXISTS signed_hash');
        $this->addSql('ALTER TABLE vo_remises_en_etat DROP COLUMN IF EXISTS signed_by');
        $this->addSql('ALTER TABLE vo_remises_en_etat DROP COLUMN IF EXISTS signed_at');
        $this->addSql('ALTER TABLE vo_remises_en_etat DROP COLUMN IF EXISTS signed_ip');
        $this->addSql('ALTER TABLE vo_remises_en_etat DROP COLUMN IF EXISTS signed_user_agent');
    }
}
