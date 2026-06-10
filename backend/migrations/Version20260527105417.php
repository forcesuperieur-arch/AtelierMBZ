<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260527105417 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE clients ADD password VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE clients ADD reset_token VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE clients ADD reset_token_expires_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE clients ADD email_verified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE clients DROP password');
        $this->addSql('ALTER TABLE clients DROP reset_token');
        $this->addSql('ALTER TABLE clients DROP reset_token_expires_at');
        $this->addSql('ALTER TABLE clients DROP email_verified_at');
    }
}
