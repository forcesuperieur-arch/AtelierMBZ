<?php
declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260719130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Sécurité multi-atelier : colonne ordres_reparation.atelier_id (isolation tenant du document légal) + backfill depuis le RDV';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ordres_reparation ADD atelier_id INT DEFAULT NULL');
        // Backfill : chaque OR hérite de l'atelier de son RDV.
        $this->addSql('UPDATE ordres_reparation o SET atelier_id = r.atelier_id FROM rendez_vous r WHERE r.id = o.rendez_vous_id');
        $this->addSql('CREATE INDEX IDX_OR_ATELIER ON ordres_reparation (atelier_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IDX_OR_ATELIER');
        $this->addSql('ALTER TABLE ordres_reparation DROP atelier_id');
    }
}
