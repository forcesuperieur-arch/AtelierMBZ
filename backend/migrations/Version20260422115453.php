<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * [SPRINT-4]
 *  - I4  : rendez_vous.source (varchar 20, nullable) — origine du RDV (null=interne, 'web'=public)
 *  - I6  : config_atelier.duree_defaut_mandat_jours (int, défaut 90)
 *  - I20 : config_atelier.regime_tva_vo_default (varchar 10, défaut 'marge')
 */
final class Version20260422115453 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '[SPRINT-4] Adds source on rendez_vous, duree_defaut_mandat_jours and regime_tva_vo_default on config_atelier';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE rendez_vous ADD COLUMN IF NOT EXISTS source VARCHAR(20) DEFAULT NULL");
        $this->addSql("ALTER TABLE config_atelier ADD COLUMN IF NOT EXISTS duree_defaut_mandat_jours INT NOT NULL DEFAULT 90");
        $this->addSql("ALTER TABLE config_atelier ADD COLUMN IF NOT EXISTS regime_tva_vo_default VARCHAR(10) NOT NULL DEFAULT 'marge'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE rendez_vous DROP COLUMN IF EXISTS source");
        $this->addSql("ALTER TABLE config_atelier DROP COLUMN IF EXISTS duree_defaut_mandat_jours");
        $this->addSql("ALTER TABLE config_atelier DROP COLUMN IF EXISTS regime_tva_vo_default");
    }
}
