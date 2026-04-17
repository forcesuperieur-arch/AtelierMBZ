<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260417043703 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE photos_intervention ADD type VARCHAR(50) DEFAULT NULL');
        $this->addSql('ALTER TABLE photos_intervention ADD sha256 VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE photos_intervention ADD exif JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE photos_intervention ADD taken_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_photo_rdv_type ON photos_intervention (rendez_vous_id, type)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX idx_photo_rdv_type');
        $this->addSql('ALTER TABLE photos_intervention DROP type');
        $this->addSql('ALTER TABLE photos_intervention DROP sha256');
        $this->addSql('ALTER TABLE photos_intervention DROP exif');
        $this->addSql('ALTER TABLE photos_intervention DROP taken_at');
    }
}
