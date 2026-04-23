<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260421080200 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute le type d operation et l atelier aux mouvements de paiement pour journaliser encaissements et remboursements';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE paiements ADD COLUMN IF NOT EXISTS atelier_id INT DEFAULT NULL");
        $this->addSql("ALTER TABLE paiements ADD COLUMN IF NOT EXISTS type_operation VARCHAR(30) DEFAULT 'encaissement' NOT NULL");
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_paiements_atelier_id ON paiements (atelier_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_paiements_type_operation ON paiements (type_operation)');

        $this->addSql('UPDATE paiements p SET atelier_id = f.atelier_id FROM factures f WHERE p.facture_id = f.id AND p.atelier_id IS NULL');
        $this->addSql('UPDATE paiements p SET atelier_id = vf.atelier_id FROM vo_factures vf WHERE p.vo_facture_id = vf.id AND p.atelier_id IS NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS idx_paiements_atelier_id');
        $this->addSql('DROP INDEX IF EXISTS idx_paiements_type_operation');
        $this->addSql('ALTER TABLE paiements DROP COLUMN IF EXISTS type_operation');
        $this->addSql('ALTER TABLE paiements DROP COLUMN IF EXISTS atelier_id');
    }
}