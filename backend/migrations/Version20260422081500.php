<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * [C3] VOLivrePolice : ajout colonnes mode_paiement, numero_cheque, nom_banque (achat + vente)
 * Conformité Art. 321-7 Code Pénal — traçabilité du mode de paiement obligatoire.
 *
 * mode_paiement : NOT NULL (especes/cb/cheque/virement/depot_vente)
 * Les entrées existantes en base (avant migration) reçoivent 'especes' comme valeur par défaut
 * pour rester conformes — à régulariser manuellement si besoin.
 */
final class Version20260422081500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '[C3] VOLivrePolice : mode_paiement + numero_cheque + nom_banque (achat + vente)';
    }

    public function up(Schema $schema): void
    {
        // Achat : mode_paiement obligatoire, numero_cheque + nom_banque optionnels
        $this->addSql("ALTER TABLE vo_livre_police ADD COLUMN mode_paiement VARCHAR(20) NOT NULL DEFAULT 'especes'");
        $this->addSql('ALTER TABLE vo_livre_police ADD COLUMN numero_cheque VARCHAR(50) DEFAULT NULL');
        $this->addSql('ALTER TABLE vo_livre_police ADD COLUMN nom_banque VARCHAR(100) DEFAULT NULL');

        // Vente : mode_paiement_vente nullable (rempli lors de la cession)
        $this->addSql('ALTER TABLE vo_livre_police ADD COLUMN mode_paiement_vente VARCHAR(20) DEFAULT NULL');
        $this->addSql('ALTER TABLE vo_livre_police ADD COLUMN numero_cheque_vente VARCHAR(50) DEFAULT NULL');
        $this->addSql('ALTER TABLE vo_livre_police ADD COLUMN nom_banque_vente VARCHAR(100) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE vo_livre_police DROP COLUMN IF EXISTS mode_paiement');
        $this->addSql('ALTER TABLE vo_livre_police DROP COLUMN IF EXISTS numero_cheque');
        $this->addSql('ALTER TABLE vo_livre_police DROP COLUMN IF EXISTS nom_banque');
        $this->addSql('ALTER TABLE vo_livre_police DROP COLUMN IF EXISTS mode_paiement_vente');
        $this->addSql('ALTER TABLE vo_livre_police DROP COLUMN IF EXISTS numero_cheque_vente');
        $this->addSql('ALTER TABLE vo_livre_police DROP COLUMN IF EXISTS nom_banque_vente');
    }
}
