<?php
declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260719120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Boucle légale OR : scellé final à la restitution (final_snapshot/final_hash/finalized_at) préservant le scellé de réception, + PDF archivé immuable (pdf_archive_name)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ordres_reparation ADD final_snapshot JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE ordres_reparation ADD final_hash VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE ordres_reparation ADD finalized_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE ordres_reparation ADD pdf_archive_name VARCHAR(64) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ordres_reparation DROP final_snapshot');
        $this->addSql('ALTER TABLE ordres_reparation DROP final_hash');
        $this->addSql('ALTER TABLE ordres_reparation DROP finalized_at');
        $this->addSql('ALTER TABLE ordres_reparation DROP pdf_archive_name');
    }
}
