<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260604180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute les campagnes de remise en etat VO, leurs lignes prestations et le workflow pieces';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
CREATE TABLE vo_remises_en_etat (
    id SERIAL NOT NULL,
    atelier_id INT DEFAULT NULL,
    vo_purchase_id INT DEFAULT NULL,
    vo_depot_vente_id INT DEFAULT NULL,
    campaign_index INT NOT NULL DEFAULT 1,
    titre VARCHAR(180) NOT NULL,
    status VARCHAR(40) NOT NULL DEFAULT 'a_chiffrer',
    priority VARCHAR(20) NOT NULL DEFAULT 'normale',
    diagnostic_notes TEXT DEFAULT NULL,
    workshop_notes TEXT DEFAULT NULL,
    business_notes TEXT DEFAULT NULL,
    requested_by INT DEFAULT NULL,
    validated_by INT DEFAULT NULL,
    requested_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    validated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
    planned_for TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
    started_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
    completed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
    closed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
    created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY(id)
)
SQL);
        $this->addSql('CREATE INDEX IDX_VO_RE_PURCHASE ON vo_remises_en_etat (vo_purchase_id)');
        $this->addSql('CREATE INDEX IDX_VO_RE_DEPOT ON vo_remises_en_etat (vo_depot_vente_id)');
        $this->addSql('CREATE INDEX IDX_VO_RE_REQUESTED_BY ON vo_remises_en_etat (requested_by)');
        $this->addSql('CREATE INDEX IDX_VO_RE_VALIDATED_BY ON vo_remises_en_etat (validated_by)');
        $this->addSql('CREATE UNIQUE INDEX uniq_vo_re_purchase_campaign ON vo_remises_en_etat (vo_purchase_id, campaign_index) WHERE vo_purchase_id IS NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX uniq_vo_re_depot_campaign ON vo_remises_en_etat (vo_depot_vente_id, campaign_index) WHERE vo_depot_vente_id IS NOT NULL');
        $this->addSql("CREATE UNIQUE INDEX uniq_vo_re_purchase_active ON vo_remises_en_etat (vo_purchase_id) WHERE vo_purchase_id IS NOT NULL AND status NOT IN ('cloturee', 'annulee')");
        $this->addSql("CREATE UNIQUE INDEX uniq_vo_re_depot_active ON vo_remises_en_etat (vo_depot_vente_id) WHERE vo_depot_vente_id IS NOT NULL AND status NOT IN ('cloturee', 'annulee')");
        $this->addSql("ALTER TABLE vo_remises_en_etat ADD CONSTRAINT chk_vo_re_source CHECK (((CASE WHEN vo_purchase_id IS NOT NULL THEN 1 ELSE 0 END) + (CASE WHEN vo_depot_vente_id IS NOT NULL THEN 1 ELSE 0 END)) = 1)");
        $this->addSql('ALTER TABLE vo_remises_en_etat ADD CONSTRAINT FK_VO_RE_PURCHASE FOREIGN KEY (vo_purchase_id) REFERENCES vo_purchases (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE vo_remises_en_etat ADD CONSTRAINT FK_VO_RE_DEPOT FOREIGN KEY (vo_depot_vente_id) REFERENCES vo_depot_ventes (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE vo_remises_en_etat ADD CONSTRAINT FK_VO_RE_REQUESTED_BY FOREIGN KEY (requested_by) REFERENCES users (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE vo_remises_en_etat ADD CONSTRAINT FK_VO_RE_VALIDATED_BY FOREIGN KEY (validated_by) REFERENCES users (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql(<<<'SQL'
CREATE TABLE vo_remise_en_etat_lignes (
    id SERIAL NOT NULL,
    remise_en_etat_id INT NOT NULL,
    prestation_id INT DEFAULT NULL,
    libelle VARCHAR(255) NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    estimated_unit_ht NUMERIC(10, 2) NOT NULL DEFAULT '0.00',
    estimated_total_ht NUMERIC(10, 2) NOT NULL DEFAULT '0.00',
    actual_total_ht NUMERIC(10, 2) DEFAULT NULL,
    estimated_minutes INT NOT NULL DEFAULT 0,
    actual_minutes INT DEFAULT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'proposee',
    notes TEXT DEFAULT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY(id)
)
SQL);
        $this->addSql('CREATE INDEX IDX_VO_RE_LINE_REMISE ON vo_remise_en_etat_lignes (remise_en_etat_id)');
        $this->addSql('CREATE INDEX IDX_VO_RE_LINE_PRESTATION ON vo_remise_en_etat_lignes (prestation_id)');
        $this->addSql('ALTER TABLE vo_remise_en_etat_lignes ADD CONSTRAINT FK_VO_RE_LINE_REMISE FOREIGN KEY (remise_en_etat_id) REFERENCES vo_remises_en_etat (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE vo_remise_en_etat_lignes ADD CONSTRAINT FK_VO_RE_LINE_PRESTATION FOREIGN KEY (prestation_id) REFERENCES prestations (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql(<<<'SQL'
CREATE TABLE vo_remise_en_etat_pieces (
    id SERIAL NOT NULL,
    remise_en_etat_id INT NOT NULL,
    libelle VARCHAR(255) NOT NULL,
    reference VARCHAR(120) DEFAULT NULL,
    quantity INT NOT NULL DEFAULT 1,
    supplier VARCHAR(160) DEFAULT NULL,
    estimated_unit_cost_ht NUMERIC(10, 2) NOT NULL DEFAULT '0.00',
    estimated_total_cost_ht NUMERIC(10, 2) NOT NULL DEFAULT '0.00',
    actual_total_cost_ht NUMERIC(10, 2) DEFAULT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'a_commander',
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY(id)
)
SQL);
        $this->addSql('CREATE INDEX IDX_VO_RE_PART_REMISE ON vo_remise_en_etat_pieces (remise_en_etat_id)');
        $this->addSql('ALTER TABLE vo_remise_en_etat_pieces ADD CONSTRAINT FK_VO_RE_PART_REMISE FOREIGN KEY (remise_en_etat_id) REFERENCES vo_remises_en_etat (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE vo_remise_en_etat_pieces DROP CONSTRAINT IF EXISTS FK_VO_RE_PART_REMISE');
        $this->addSql('DROP TABLE IF EXISTS vo_remise_en_etat_pieces');
        $this->addSql('ALTER TABLE vo_remise_en_etat_lignes DROP CONSTRAINT IF EXISTS FK_VO_RE_LINE_REMISE');
        $this->addSql('ALTER TABLE vo_remise_en_etat_lignes DROP CONSTRAINT IF EXISTS FK_VO_RE_LINE_PRESTATION');
        $this->addSql('DROP TABLE IF EXISTS vo_remise_en_etat_lignes');
        $this->addSql('ALTER TABLE vo_remises_en_etat DROP CONSTRAINT IF EXISTS FK_VO_RE_PURCHASE');
        $this->addSql('ALTER TABLE vo_remises_en_etat DROP CONSTRAINT IF EXISTS FK_VO_RE_DEPOT');
        $this->addSql('ALTER TABLE vo_remises_en_etat DROP CONSTRAINT IF EXISTS FK_VO_RE_REQUESTED_BY');
        $this->addSql('ALTER TABLE vo_remises_en_etat DROP CONSTRAINT IF EXISTS FK_VO_RE_VALIDATED_BY');
        $this->addSql('DROP INDEX IF EXISTS uniq_vo_re_purchase_active');
        $this->addSql('DROP INDEX IF EXISTS uniq_vo_re_depot_active');
        $this->addSql('DROP INDEX IF EXISTS uniq_vo_re_purchase_campaign');
        $this->addSql('DROP INDEX IF EXISTS uniq_vo_re_depot_campaign');
        $this->addSql('DROP TABLE IF EXISTS vo_remises_en_etat');
    }
}