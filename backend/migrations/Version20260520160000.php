<?php
declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260520160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Fix created_at and last_triggered_at types on analytics_alert_rules';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE analytics_alert_rules ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE analytics_alert_rules ALTER last_triggered_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
    }

    public function down(Schema $schema): void
    {
        // no-op
    }
}
