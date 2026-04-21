<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260420194500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute la nature de facture et la relation facture origine pour le flux d\'avoir';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE factures ADD nature VARCHAR(20) DEFAULT 'facture' NOT NULL");
        $this->addSql('ALTER TABLE factures ADD facture_origine_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE factures ADD motif_correction TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE factures ADD CONSTRAINT FK_5D6B5D8AAB2D2F2F FOREIGN KEY (facture_origine_id) REFERENCES factures (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_5D6B5D8AAB2D2F2F ON factures (facture_origine_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IDX_5D6B5D8AAB2D2F2F');
        $this->addSql('ALTER TABLE factures DROP CONSTRAINT FK_5D6B5D8AAB2D2F2F');
        $this->addSql('ALTER TABLE factures DROP facture_origine_id');
        $this->addSql('ALTER TABLE factures DROP motif_correction');
        $this->addSql('ALTER TABLE factures DROP nature');
    }
}