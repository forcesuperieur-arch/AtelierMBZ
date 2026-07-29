<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Intervalle de vidange par défaut (km et mois), configurable en administration.
 * Suggéré au mécano à la restitution (prochaine_revision_km/date sur l'OR,
 * ajustable) et utilisé par le rappel client (dû au premier des deux seuils
 * atteint — logique constructeur, pas un simple repli).
 */
final class Version20260729160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'ConfigAtelier : intervalle de vidange par défaut (km + mois)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE config_atelier ADD vidange_intervalle_km INT DEFAULT 7000 NOT NULL');
        $this->addSql('ALTER TABLE config_atelier ADD vidange_intervalle_mois INT DEFAULT 12 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE config_atelier DROP vidange_intervalle_km');
        $this->addSql('ALTER TABLE config_atelier DROP vidange_intervalle_mois');
    }
}
