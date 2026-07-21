<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * « RDV sans faille » : garde-fou en base contre le double-booking d'un même pont
 * sur un même créneau. Index unique PARTIEL — ne s'applique qu'aux RDV avec un pont
 * assigné et non annulés. Backstop ultime, quel que soit le chemin de création
 * (public, staff, script) et immunisé contre les courses concurrentes.
 */
final class Version20260721130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'RDV sans faille : index unique partiel (atelier, pont, date, heure) hors annulé';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE UNIQUE INDEX uniq_rdv_pont_creneau ON rendez_vous (atelier_id, pont_id, date_rdv, heure_rdv) WHERE pont_id IS NOT NULL AND statut <> 'annule'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_rdv_pont_creneau');
    }
}
