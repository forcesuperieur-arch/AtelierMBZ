<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260420170500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop legacy unused pricing tables no longer mapped by Doctrine';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS calculs_tarifs CASCADE');
        $this->addSql('DROP TABLE IF EXISTS grille_tarifs CASCADE');
        $this->addSql('DROP TABLE IF EXISTS temps_interventions CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE TABLE IF NOT EXISTS grille_tarifs (id INT NOT NULL, prestation_id INT DEFAULT NULL, categorie_moto_id INT DEFAULT NULL, duree_estimee INT DEFAULT NULL, prix_unitaire DOUBLE PRECISION DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE TABLE IF NOT EXISTS temps_interventions (id INT NOT NULL, prestation_id INT DEFAULT NULL, categorie_moto_id INT DEFAULT NULL, duree_minutes INT DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE TABLE IF NOT EXISTS calculs_tarifs (id INT NOT NULL, rendez_vous_id INT DEFAULT NULL, prestation_id INT DEFAULT NULL, quantite INT DEFAULT NULL, prix_calcule DOUBLE PRECISION DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('ALTER TABLE calculs_tarifs ADD CONSTRAINT FK_303572A4A4A3511 FOREIGN KEY (rendez_vous_id) REFERENCES rendez_vous (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE calculs_tarifs ADD CONSTRAINT FK_303572A19EB6921 FOREIGN KEY (prestation_id) REFERENCES prestations (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE grille_tarifs ADD CONSTRAINT FK_EB7CE9A8273B5C0E FOREIGN KEY (categorie_moto_id) REFERENCES categorie_motos (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE temps_interventions ADD CONSTRAINT FK_C5B3AF4A273B5C0E FOREIGN KEY (categorie_moto_id) REFERENCES categorie_motos (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE temps_interventions ADD CONSTRAINT FK_C5B3AF4A8EA2F8F6 FOREIGN KEY (prestation_id) REFERENCES prestations (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }
}