<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260507195100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Transforme mecaniciens.user_id en FOREIGN KEY vers users.id';
    }

    public function up(Schema $schema): void
    {
        // Supprime l'index unique existant (remplacé par la contrainte FK + unique implicite)
        $this->addSql('DROP INDEX IF EXISTS uniq_mecanicien_user_id');
        // Ajoute la contrainte FOREIGN KEY
        $this->addSql('ALTER TABLE mecaniciens ADD CONSTRAINT FK_AE060BF9A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE mecaniciens DROP CONSTRAINT IF EXISTS FK_AE060BF9A76ED395');
        $this->addSql('CREATE UNIQUE INDEX uniq_mecanicien_user_id ON mecaniciens (user_id)');
    }
}
