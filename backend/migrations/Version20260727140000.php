<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Règles métier sorties du code vers la configuration atelier : nombre de photos
 * d'entrée exigées, délai et fenêtre horaire des relances, jours de rappel avant
 * RDV, validité des liens clients publics, points de contrôle d'essai routier,
 * délai de re-signalement d'une alerte. Les valeurs par défaut reprennent
 * exactement les constantes historiques : aucun changement de comportement.
 */
final class Version20260727140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Config atelier : règles métier configurables (photos, relances, rappels, liens publics, essai routier)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE config_atelier ADD min_photos_entree INT DEFAULT 4 NOT NULL');
        $this->addSql('ALTER TABLE config_atelier ADD relance_travaux_delai_heures INT DEFAULT 4 NOT NULL');
        $this->addSql('ALTER TABLE config_atelier ADD relance_heure_min INT DEFAULT 8 NOT NULL');
        $this->addSql('ALTER TABLE config_atelier ADD relance_heure_max INT DEFAULT 19 NOT NULL');
        $this->addSql('ALTER TABLE config_atelier ADD rappels_rdv_jours JSON DEFAULT \'[1,3]\' NOT NULL');
        $this->addSql('ALTER TABLE config_atelier ADD lien_public_jours INT DEFAULT 30 NOT NULL');
        $this->addSql('ALTER TABLE config_atelier ADD essai_points_min INT DEFAULT 5 NOT NULL');
        $this->addSql('ALTER TABLE config_atelier ADD rappel_alerte_heures INT DEFAULT 24 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE config_atelier DROP min_photos_entree');
        $this->addSql('ALTER TABLE config_atelier DROP relance_travaux_delai_heures');
        $this->addSql('ALTER TABLE config_atelier DROP relance_heure_min');
        $this->addSql('ALTER TABLE config_atelier DROP relance_heure_max');
        $this->addSql('ALTER TABLE config_atelier DROP rappels_rdv_jours');
        $this->addSql('ALTER TABLE config_atelier DROP lien_public_jours');
        $this->addSql('ALTER TABLE config_atelier DROP essai_points_min');
        $this->addSql('ALTER TABLE config_atelier DROP rappel_alerte_heures');
    }
}
