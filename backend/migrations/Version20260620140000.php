<?php
declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260620140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add segment column to clients table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE clients ADD segment VARCHAR(50) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE clients DROP segment');
    }
}
