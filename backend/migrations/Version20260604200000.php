<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260604200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute le suivi DA SIV sur les rachats VO';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE vo_purchases ADD siv_status VARCHAR(20) DEFAULT 'a_preparer' NOT NULL");
        $this->addSql('ALTER TABLE vo_purchases ADD siv_reference VARCHAR(120) DEFAULT NULL');
        $this->addSql('ALTER TABLE vo_purchases ADD siv_recorded_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE vo_purchases ADD siv_notes TEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE vo_purchases DROP COLUMN IF EXISTS siv_notes');
        $this->addSql('ALTER TABLE vo_purchases DROP COLUMN IF EXISTS siv_recorded_at');
        $this->addSql('ALTER TABLE vo_purchases DROP COLUMN IF EXISTS siv_reference');
        $this->addSql('ALTER TABLE vo_purchases DROP COLUMN IF EXISTS siv_status');
    }
}
