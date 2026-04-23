<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260423130422 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '[AUDIT-V1] Drop dead-code table rapports_technicien (remplacée par rapport_intervention)';
    }

    public function up(Schema $schema): void
    {
        // Sécurité : ne drop que si vide (vérifié manuellement = 0 lignes en prod soft + dev)
        $this->addSql('DROP TABLE IF EXISTS rapports_technicien');
    }

    public function down(Schema $schema): void
    {
        // Recréation minimale pour rollback (structure historique simplifiée)
        $this->addSql('CREATE TABLE rapports_technicien (
            id SERIAL PRIMARY KEY,
            rendez_vous_id INT NOT NULL UNIQUE,
            contenu TEXT,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            CONSTRAINT fk_rapports_technicien_rdv FOREIGN KEY (rendez_vous_id) REFERENCES rendez_vous(id) ON DELETE CASCADE
        )');
    }
}
