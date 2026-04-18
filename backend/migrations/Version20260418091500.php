<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260418091500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Allow VO purchase drafts without immediate seller or vehicle for companion QR startup';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE vo_purchases ALTER COLUMN vehicule_id DROP NOT NULL');
        $this->addSql('ALTER TABLE vo_purchases ALTER COLUMN seller_id DROP NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DELETE FROM vo_purchases WHERE vehicule_id IS NULL OR seller_id IS NULL');
        $this->addSql('ALTER TABLE vo_purchases ALTER COLUMN vehicule_id SET NOT NULL');
        $this->addSql('ALTER TABLE vo_purchases ALTER COLUMN seller_id SET NOT NULL');
    }
}
