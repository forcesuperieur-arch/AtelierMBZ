<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Réclamations client (PILOTE_PLAN.md Lot C4, file de travail SRC) : cahier de bord simple
 * (statut nouveau/en_cours/clos + notes horodatées en JSON append-only), pas un CRM.
 */
final class Version20260806110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute la table reclamations (Lot C4, cahier de bord SRC)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
CREATE TABLE reclamations (
    id SERIAL PRIMARY KEY,
    atelier_id INT DEFAULT NULL,
    client_id INT NOT NULL,
    rendez_vous_id INT DEFAULT NULL,
    sujet VARCHAR(500) NOT NULL,
    statut VARCHAR(20) DEFAULT 'nouveau' NOT NULL,
    notes JSON NOT NULL,
    created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL,
    created_by INT DEFAULT NULL
)
SQL);
        $this->addSql('ALTER TABLE reclamations ADD CONSTRAINT FK_RECLAMATIONS_CLIENT FOREIGN KEY (client_id) REFERENCES clients (id)');
        $this->addSql('ALTER TABLE reclamations ADD CONSTRAINT FK_RECLAMATIONS_RDV FOREIGN KEY (rendez_vous_id) REFERENCES rendez_vous (id)');
        $this->addSql('CREATE INDEX IDX_RECLAMATIONS_ATELIER ON reclamations (atelier_id)');
        $this->addSql('CREATE INDEX IDX_RECLAMATIONS_STATUT ON reclamations (statut)');
        $this->addSql('CREATE INDEX IDX_RECLAMATIONS_CLIENT ON reclamations (client_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE reclamations');
    }
}
