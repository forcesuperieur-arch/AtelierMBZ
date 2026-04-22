<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * [SPRINT-6] [C9] Contrainte UNIQUE (vin, atelier_id) sur vehicules
 * Empêche les doublons VIN par atelier — cohérence Livre de Police / DA SIV
 */
final class Version20260422130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '[SPRINT-6] Contrainte UNIQUE composite (vin, atelier_id) sur table vehicules';
    }

    public function up(Schema $schema): void
    {
        // Dédupliquer : pour chaque doublon (vin, atelier_id), conserver l'entrée la plus récente (id MAX),
        // mettre vin à NULL sur les plus anciennes — elles restent intactes mais ne bloquent plus la contrainte
        $this->addSql("
            UPDATE vehicules v
            SET vin = NULL
            WHERE vin IS NOT NULL
              AND id NOT IN (
                SELECT MAX(id)
                FROM vehicules
                WHERE vin IS NOT NULL
                GROUP BY vin, atelier_id
              )
              AND (vin, atelier_id) IN (
                SELECT vin, atelier_id
                FROM vehicules
                WHERE vin IS NOT NULL
                GROUP BY vin, atelier_id
                HAVING COUNT(*) > 1
              )
        ");

        // Contrainte partielle : vin IS NOT NULL (les VINs null ne sont pas contraints)
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS uniq_vehicule_vin_atelier ON vehicules (vin, atelier_id) WHERE vin IS NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS uniq_vehicule_vin_atelier');
    }
}
