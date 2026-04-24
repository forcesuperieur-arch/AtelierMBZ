<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260424153000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute char_box_width, char_gap et date_group_gap pour ajuster les champs CERFA boxés/date';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cerfa_field_config ADD char_box_width NUMERIC(5, 2) DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE cerfa_field_config ADD char_gap NUMERIC(5, 2) DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE cerfa_field_config ADD date_group_gap NUMERIC(5, 2) DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cerfa_field_config DROP char_box_width');
        $this->addSql('ALTER TABLE cerfa_field_config DROP char_gap');
        $this->addSql('ALTER TABLE cerfa_field_config DROP date_group_gap');
    }
}
