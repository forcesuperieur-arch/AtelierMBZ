<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Absences partielles : ajoute heure_debut / heure_fin (optionnelles) à `absences`.
 * Nullable → rétrocompatible : une absence sans heures reste sur la journée entière.
 */
final class Version20260721120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Absences partielles : heure_debut / heure_fin optionnelles sur absences';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE absences ADD heure_debut TIME(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE absences ADD heure_fin TIME(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE absences DROP heure_debut');
        $this->addSql('ALTER TABLE absences DROP heure_fin');
    }
}
