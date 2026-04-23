<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260423130654 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '[AUDIT-V1] Ajoute contrainte FK mecaniciens.user_id -> users.id ON DELETE SET NULL';
    }

    public function up(Schema $schema): void
    {
        // Pré-vérifié : aucun orphelin (mecaniciens.user_id pointe tous sur users existants).
        // Si un orphelin apparaît plus tard, ON DELETE SET NULL nettoie automatiquement.
        $this->addSql('ALTER TABLE mecaniciens ADD CONSTRAINT fk_mecaniciens_user_id FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE mecaniciens DROP CONSTRAINT IF EXISTS fk_mecaniciens_user_id');
    }
}
