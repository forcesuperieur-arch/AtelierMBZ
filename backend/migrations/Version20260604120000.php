<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260604120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'VO module: purchases, depot-ventes, livre de police, documents, factures + vehicule VO fields';
    }

    public function up(Schema $schema): void
    {
        // VO fields on existing vehicules table
        $this->addSql('ALTER TABLE vehicules ADD COLUMN mileage INT DEFAULT NULL');
        $this->addSql('ALTER TABLE vehicules ADD COLUMN vin VARCHAR(17) DEFAULT NULL');
        $this->addSql('ALTER TABLE vehicules ADD COLUMN is_a2_compatible BOOLEAN DEFAULT FALSE NOT NULL');
        $this->addSql('ALTER TABLE vehicules ADD COLUMN date_premiere_mise_en_circulation DATE DEFAULT NULL');
        $this->addSql('ALTER TABLE vehicules ADD COLUMN couleur VARCHAR(50) DEFAULT NULL');
        $this->addSql('ALTER TABLE vehicules ADD COLUMN registration_cost NUMERIC(10, 2) DEFAULT NULL');
        $this->addSql('ALTER TABLE vehicules ADD COLUMN options_and_accessories JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE vehicules ADD COLUMN controle_technique_date DATE DEFAULT NULL');
        $this->addSql('ALTER TABLE vehicules ADD COLUMN controle_technique_resultat VARCHAR(30) DEFAULT NULL');

        // vo_purchases
        $this->addSql('CREATE TABLE vo_purchases (
            id SERIAL PRIMARY KEY,
            atelier_id INT DEFAULT NULL,
            vehicule_id INT NOT NULL,
            seller_id INT NOT NULL,
            expert_id INT DEFAULT NULL,
            purchase_price NUMERIC(10, 2) NOT NULL DEFAULT 0,
            target_sale_price NUMERIC(10, 2) NOT NULL DEFAULT 0,
            repair_estimates JSON DEFAULT NULL,
            status VARCHAR(30) NOT NULL DEFAULT \'brouillon\',
            purchase_date DATE DEFAULT NULL,
            sale_date DATE DEFAULT NULL,
            notes TEXT DEFAULT NULL,
            seller_id_type VARCHAR(50) DEFAULT NULL,
            seller_id_number VARCHAR(100) DEFAULT NULL,
            seller_id_date DATE DEFAULT NULL,
            non_gage_date DATE DEFAULT NULL,
            controle_technique_ok BOOLEAN NOT NULL DEFAULT FALSE,
            regime_tva VARCHAR(10) NOT NULL DEFAULT \'marge\',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_vo_purchase_vehicule FOREIGN KEY (vehicule_id) REFERENCES vehicules(id),
            CONSTRAINT fk_vo_purchase_seller FOREIGN KEY (seller_id) REFERENCES clients(id),
            CONSTRAINT fk_vo_purchase_expert FOREIGN KEY (expert_id) REFERENCES "users"(id)
        )');

        // vo_depot_ventes
        $this->addSql('CREATE TABLE vo_depot_ventes (
            id SERIAL PRIMARY KEY,
            atelier_id INT DEFAULT NULL,
            vehicule_id INT NOT NULL,
            deposant_id INT NOT NULL,
            gestionnaire_id INT DEFAULT NULL,
            prix_vente_souhaite NUMERIC(10, 2) NOT NULL DEFAULT 0,
            commission_type VARCHAR(20) NOT NULL DEFAULT \'pourcentage\',
            commission_valeur NUMERIC(10, 2) NOT NULL DEFAULT 0,
            date_debut DATE NOT NULL,
            date_fin DATE DEFAULT NULL,
            duree_mandat INT NOT NULL DEFAULT 90,
            status VARCHAR(30) NOT NULL DEFAULT \'actif\',
            conditions_restitution TEXT DEFAULT NULL,
            assurance_info TEXT DEFAULT NULL,
            notes TEXT DEFAULT NULL,
            deposant_id_type VARCHAR(50) DEFAULT NULL,
            deposant_id_number VARCHAR(100) DEFAULT NULL,
            deposant_id_date DATE DEFAULT NULL,
            prix_vente_effectif NUMERIC(10, 2) DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_vo_depot_vehicule FOREIGN KEY (vehicule_id) REFERENCES vehicules(id),
            CONSTRAINT fk_vo_depot_deposant FOREIGN KEY (deposant_id) REFERENCES clients(id),
            CONSTRAINT fk_vo_depot_gestionnaire FOREIGN KEY (gestionnaire_id) REFERENCES "users"(id)
        )');

        // vo_livre_police
        $this->addSql('CREATE TABLE vo_livre_police (
            id SERIAL PRIMARY KEY,
            atelier_id INT DEFAULT NULL,
            numero_ordre INT NOT NULL,
            type VARCHAR(20) NOT NULL,
            date_acquisition DATE NOT NULL,
            date_vente DATE DEFAULT NULL,
            description_bien TEXT NOT NULL,
            immatriculation VARCHAR(20) NOT NULL,
            vendeur_nom VARCHAR(100) NOT NULL,
            vendeur_prenom VARCHAR(100) NOT NULL,
            vendeur_adresse TEXT NOT NULL,
            vendeur_id_type VARCHAR(50) NOT NULL,
            vendeur_id_number VARCHAR(100) NOT NULL,
            vendeur_id_date DATE NOT NULL,
            prix_achat NUMERIC(10, 2) NOT NULL,
            prix_vente NUMERIC(10, 2) DEFAULT NULL,
            acheteur_nom VARCHAR(100) DEFAULT NULL,
            acheteur_prenom VARCHAR(100) DEFAULT NULL,
            acheteur_adresse TEXT DEFAULT NULL,
            vo_purchase_id INT DEFAULT NULL,
            vo_depot_vente_id INT DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_lp_purchase FOREIGN KEY (vo_purchase_id) REFERENCES vo_purchases(id),
            CONSTRAINT fk_lp_depot FOREIGN KEY (vo_depot_vente_id) REFERENCES vo_depot_ventes(id)
        )');

        // vo_documents
        $this->addSql('CREATE TABLE vo_documents (
            id SERIAL PRIMARY KEY,
            atelier_id INT DEFAULT NULL,
            type VARCHAR(30) NOT NULL,
            file_path VARCHAR(500) NOT NULL,
            original_filename VARCHAR(255) NOT NULL,
            mime_type VARCHAR(100) NOT NULL,
            vo_purchase_id INT DEFAULT NULL,
            vo_depot_vente_id INT DEFAULT NULL,
            date_expiration DATE DEFAULT NULL,
            retention_years INT NOT NULL DEFAULT 5,
            uploaded_by INT DEFAULT NULL,
            uploaded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_vodoc_purchase FOREIGN KEY (vo_purchase_id) REFERENCES vo_purchases(id),
            CONSTRAINT fk_vodoc_depot FOREIGN KEY (vo_depot_vente_id) REFERENCES vo_depot_ventes(id),
            CONSTRAINT fk_vodoc_user FOREIGN KEY (uploaded_by) REFERENCES "users"(id)
        )');

        // vo_factures
        $this->addSql('CREATE TABLE vo_factures (
            id SERIAL PRIMARY KEY,
            atelier_id INT DEFAULT NULL,
            numero_facture VARCHAR(50) NOT NULL UNIQUE,
            vo_purchase_id INT DEFAULT NULL,
            vo_depot_vente_id INT DEFAULT NULL,
            client_id INT NOT NULL,
            vehicule_id INT DEFAULT NULL,
            regime_tva VARCHAR(10) NOT NULL DEFAULT \'marge\',
            prix_achat_ht NUMERIC(10, 2) DEFAULT NULL,
            mention_tva_marge BOOLEAN NOT NULL DEFAULT TRUE,
            total_ht NUMERIC(10, 2) NOT NULL DEFAULT 0,
            total_tva NUMERIC(10, 2) NOT NULL DEFAULT 0,
            total_ttc NUMERIC(10, 2) NOT NULL DEFAULT 0,
            statut VARCHAR(50) NOT NULL DEFAULT \'emise\',
            mention_garantie_conformite BOOLEAN NOT NULL DEFAULT TRUE,
            kilometrage INT DEFAULT NULL,
            vin_facture VARCHAR(17) DEFAULT NULL,
            date_premiere_mise_en_circulation_facture DATE DEFAULT NULL,
            immatriculation VARCHAR(20) DEFAULT NULL,
            snap_client_nom VARCHAR(200) DEFAULT NULL,
            snap_client_prenom VARCHAR(200) DEFAULT NULL,
            snap_client_email VARCHAR(200) DEFAULT NULL,
            snap_client_telephone VARCHAR(20) DEFAULT NULL,
            snap_client_adresse TEXT DEFAULT NULL,
            snap_vehicule_plaque VARCHAR(20) DEFAULT NULL,
            snap_vehicule_marque VARCHAR(100) DEFAULT NULL,
            snap_vehicule_modele VARCHAR(100) DEFAULT NULL,
            date_creation TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            date_echeance DATE DEFAULT NULL,
            notes TEXT DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_vof_purchase FOREIGN KEY (vo_purchase_id) REFERENCES vo_purchases(id),
            CONSTRAINT fk_vof_depot FOREIGN KEY (vo_depot_vente_id) REFERENCES vo_depot_ventes(id),
            CONSTRAINT fk_vof_client FOREIGN KEY (client_id) REFERENCES clients(id),
            CONSTRAINT fk_vof_vehicule FOREIGN KEY (vehicule_id) REFERENCES vehicules(id)
        )');

        // Paiement: make facture_id nullable, add vo_facture_id
        $this->addSql('ALTER TABLE paiements ALTER COLUMN facture_id DROP NOT NULL');
        $this->addSql('ALTER TABLE paiements ADD COLUMN vo_facture_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE paiements ADD CONSTRAINT fk_paiement_vo_facture FOREIGN KEY (vo_facture_id) REFERENCES vo_factures(id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE paiements DROP CONSTRAINT IF EXISTS fk_paiement_vo_facture');
        $this->addSql('ALTER TABLE paiements DROP COLUMN IF EXISTS vo_facture_id');
        $this->addSql('ALTER TABLE paiements ALTER COLUMN facture_id SET NOT NULL');

        $this->addSql('DROP TABLE IF EXISTS vo_factures');
        $this->addSql('DROP TABLE IF EXISTS vo_documents');
        $this->addSql('DROP TABLE IF EXISTS vo_livre_police');
        $this->addSql('DROP TABLE IF EXISTS vo_depot_ventes');
        $this->addSql('DROP TABLE IF EXISTS vo_purchases');

        $this->addSql('ALTER TABLE vehicules DROP COLUMN IF EXISTS mileage');
        $this->addSql('ALTER TABLE vehicules DROP COLUMN IF EXISTS vin');
        $this->addSql('ALTER TABLE vehicules DROP COLUMN IF EXISTS is_a2_compatible');
        $this->addSql('ALTER TABLE vehicules DROP COLUMN IF EXISTS date_premiere_mise_en_circulation');
        $this->addSql('ALTER TABLE vehicules DROP COLUMN IF EXISTS couleur');
        $this->addSql('ALTER TABLE vehicules DROP COLUMN IF EXISTS registration_cost');
        $this->addSql('ALTER TABLE vehicules DROP COLUMN IF EXISTS options_and_accessories');
        $this->addSql('ALTER TABLE vehicules DROP COLUMN IF EXISTS controle_technique_date');
        $this->addSql('ALTER TABLE vehicules DROP COLUMN IF EXISTS controle_technique_resultat');
    }
}
