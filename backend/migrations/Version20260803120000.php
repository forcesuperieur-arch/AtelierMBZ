<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Espace mécanicien : rattache une photo à un point précis de checkup ou
 * d'essai routier (ex: "freinage_avant" en NOK), pour la retrouver dans la
 * galerie de saisie et dans le rapport/PDF signé.
 */
final class Version20260803120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'photos_intervention : checkpoint_source + checkpoint_key (photo par point NOK)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE photos_intervention ADD checkpoint_source VARCHAR(30) DEFAULT NULL');
        $this->addSql('ALTER TABLE photos_intervention ADD checkpoint_key VARCHAR(60) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE photos_intervention DROP checkpoint_source');
        $this->addSql('ALTER TABLE photos_intervention DROP checkpoint_key');
    }
}
