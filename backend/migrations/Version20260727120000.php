<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Suivi « moto en atelier » : réglages back-office du seuil d'alerte (en heures
 * OUVRÉES, les jours de fermeture ne comptant pas) et interrupteur de l'alerte
 * automatique (notification cloche + e-mail récapitulatif). L'onglet de suivi reste
 * consultable même quand l'alerte est coupée.
 */
final class Version20260727120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Config atelier : seuil d\'alerte séjour atelier (heures ouvrées) + interrupteur d\'alerte';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE config_atelier ADD seuil_sejour_atelier_heures INT DEFAULT 72 NOT NULL');
        $this->addSql('ALTER TABLE config_atelier ADD alerte_sejour_atelier_active BOOLEAN DEFAULT true NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE config_atelier DROP seuil_sejour_atelier_heures');
        $this->addSql('ALTER TABLE config_atelier DROP alerte_sejour_atelier_active');
    }
}
