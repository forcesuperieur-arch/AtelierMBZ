<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260525165535 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Nettoyage des prestations inactives (E2E + VO test) et de leur grille tarifaire';
    }

    public function up(Schema $schema): void
    {
        // Supprimer d'abord les lignes de grille tarifaire liées aux prestations inactives
        // (FK NO ACTION sur grille_tarifaire.prestation_id)
        $this->addSql('DELETE FROM grille_tarifaire WHERE prestation_id IN (SELECT id FROM prestations WHERE is_active = 0)');

        // Supprimer les prestations inactives : 17 INACTIVE_E2E_* + 20 VO-PRIX-*
        $this->addSql('DELETE FROM prestations WHERE is_active = 0');
    }

    public function down(Schema $schema): void
    {
        // Pas de rollback possible sans restaurer les données
        $this->addSql('-- Données de test supprimées irréversiblement');
    }
}
