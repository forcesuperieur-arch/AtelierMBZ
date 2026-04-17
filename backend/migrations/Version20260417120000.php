<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260417120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'LOT 7: Add garantie_jours and necessite_essai to prestations';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE prestations ADD COLUMN garantie_jours INT DEFAULT NULL');
        $this->addSql('ALTER TABLE prestations ADD COLUMN necessite_essai BOOLEAN NOT NULL DEFAULT TRUE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE prestations DROP COLUMN IF EXISTS garantie_jours');
        $this->addSql('ALTER TABLE prestations DROP COLUMN IF EXISTS necessite_essai');
    }
}
