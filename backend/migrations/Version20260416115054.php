<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260416115054 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE clients ADD consent_date TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE clients ADD consent_source VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE clients ADD last_activity_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE clients ADD is_anonymized BOOLEAN DEFAULT false NOT NULL');
        $this->addSql('ALTER TABLE devis ADD snap_client_nom VARCHAR(200) DEFAULT NULL');
        $this->addSql('ALTER TABLE devis ADD snap_client_prenom VARCHAR(200) DEFAULT NULL');
        $this->addSql('ALTER TABLE devis ADD snap_client_email VARCHAR(200) DEFAULT NULL');
        $this->addSql('ALTER TABLE devis ADD snap_client_telephone VARCHAR(20) DEFAULT NULL');
        $this->addSql('ALTER TABLE devis ADD snap_vehicule_plaque VARCHAR(20) DEFAULT NULL');
        $this->addSql('ALTER TABLE devis ADD snap_vehicule_marque VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE devis ADD snap_vehicule_modele VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE factures ADD snap_client_nom VARCHAR(200) DEFAULT NULL');
        $this->addSql('ALTER TABLE factures ADD snap_client_prenom VARCHAR(200) DEFAULT NULL');
        $this->addSql('ALTER TABLE factures ADD snap_client_email VARCHAR(200) DEFAULT NULL');
        $this->addSql('ALTER TABLE factures ADD snap_client_telephone VARCHAR(20) DEFAULT NULL');
        $this->addSql('ALTER TABLE factures ADD snap_client_adresse TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE factures ADD snap_vehicule_plaque VARCHAR(20) DEFAULT NULL');
        $this->addSql('ALTER TABLE factures ADD snap_vehicule_marque VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE factures ADD snap_vehicule_modele VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE ordres_reparation ADD snap_client_nom VARCHAR(200) DEFAULT NULL');
        $this->addSql('ALTER TABLE ordres_reparation ADD snap_client_prenom VARCHAR(200) DEFAULT NULL');
        $this->addSql('ALTER TABLE ordres_reparation ADD snap_vehicule_plaque VARCHAR(20) DEFAULT NULL');
        $this->addSql('ALTER TABLE ordres_reparation ADD snap_vehicule_marque VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE ordres_reparation ADD snap_vehicule_modele VARCHAR(100) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE clients DROP consent_date');
        $this->addSql('ALTER TABLE clients DROP consent_source');
        $this->addSql('ALTER TABLE clients DROP last_activity_at');
        $this->addSql('ALTER TABLE clients DROP is_anonymized');
        $this->addSql('ALTER TABLE devis DROP snap_client_nom');
        $this->addSql('ALTER TABLE devis DROP snap_client_prenom');
        $this->addSql('ALTER TABLE devis DROP snap_client_email');
        $this->addSql('ALTER TABLE devis DROP snap_client_telephone');
        $this->addSql('ALTER TABLE devis DROP snap_vehicule_plaque');
        $this->addSql('ALTER TABLE devis DROP snap_vehicule_marque');
        $this->addSql('ALTER TABLE devis DROP snap_vehicule_modele');
        $this->addSql('ALTER TABLE factures DROP snap_client_nom');
        $this->addSql('ALTER TABLE factures DROP snap_client_prenom');
        $this->addSql('ALTER TABLE factures DROP snap_client_email');
        $this->addSql('ALTER TABLE factures DROP snap_client_telephone');
        $this->addSql('ALTER TABLE factures DROP snap_client_adresse');
        $this->addSql('ALTER TABLE factures DROP snap_vehicule_plaque');
        $this->addSql('ALTER TABLE factures DROP snap_vehicule_marque');
        $this->addSql('ALTER TABLE factures DROP snap_vehicule_modele');
        $this->addSql('ALTER TABLE ordres_reparation DROP snap_client_nom');
        $this->addSql('ALTER TABLE ordres_reparation DROP snap_client_prenom');
        $this->addSql('ALTER TABLE ordres_reparation DROP snap_vehicule_plaque');
        $this->addSql('ALTER TABLE ordres_reparation DROP snap_vehicule_marque');
        $this->addSql('ALTER TABLE ordres_reparation DROP snap_vehicule_modele');
    }
}
