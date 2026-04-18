<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260604190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Allow depot drafts without vehicle or deposant until companion finalization';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE vo_depot_ventes ALTER COLUMN vehicule_id DROP NOT NULL');
        $this->addSql('ALTER TABLE vo_depot_ventes ALTER COLUMN deposant_id DROP NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE vo_depot_ventes ALTER COLUMN vehicule_id SET NOT NULL');
        $this->addSql('ALTER TABLE vo_depot_ventes ALTER COLUMN deposant_id SET NOT NULL');
    }
}