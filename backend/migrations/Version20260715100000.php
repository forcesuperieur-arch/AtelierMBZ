<?php
declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260715100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Sécurité multi-atelier : colonne demandes_travaux_supp.atelier_id (discriminant tenant) + backfill depuis le RDV, pour participer au TenantFilter global comme les autres entités';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE demandes_travaux_supp ADD atelier_id INT DEFAULT NULL');
        // Backfill : chaque demande hérite de l'atelier de son RDV.
        $this->addSql('UPDATE demandes_travaux_supp d SET atelier_id = r.atelier_id FROM rendez_vous r WHERE r.id = d.rendez_vous_id');
        $this->addSql('CREATE INDEX IDX_DTS_ATELIER ON demandes_travaux_supp (atelier_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IDX_DTS_ATELIER');
        $this->addSql('ALTER TABLE demandes_travaux_supp DROP atelier_id');
    }
}
