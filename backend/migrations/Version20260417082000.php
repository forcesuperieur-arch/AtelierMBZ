<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260417082000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add prenom and nom columns to users for proper admin account management';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD COLUMN IF NOT EXISTS prenom VARCHAR(120) DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD COLUMN IF NOT EXISTS nom VARCHAR(120) DEFAULT NULL');

        $this->addSql("UPDATE users SET prenom = COALESCE(prenom, INITCAP(split_part(regexp_replace(username, '[._-]+', ' ', 'g'), ' ', 1))) WHERE prenom IS NULL");
        $this->addSql("UPDATE users SET nom = COALESCE(nom, NULLIF(UPPER(trim(substring(regexp_replace(username, '[._-]+', ' ', 'g') from char_length(split_part(regexp_replace(username, '[._-]+', ' ', 'g'), ' ', 1)) + 1))), '')) WHERE nom IS NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP COLUMN IF EXISTS prenom');
        $this->addSql('ALTER TABLE users DROP COLUMN IF EXISTS nom');
    }
}
