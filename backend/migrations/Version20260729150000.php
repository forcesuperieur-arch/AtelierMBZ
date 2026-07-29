<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * `ClientEspaceController::vehicules()/updateVehicule()` appelaient déjà
 * `getKilometrage()`/`setKilometrage()`/`setNotes()` sur `Vehicule` (portail
 * client, écran « Mes motos ») : les colonnes n'avaient jamais été ajoutées à
 * l'entité, ce qui faisait planter l'endpoint en 500 dès qu'un client
 * consultait ses motos.
 */
final class Version20260729150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Vehicule : ajoute kilometrage et notes (colonnes déjà consommées par le portail client)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE vehicules ADD kilometrage INT DEFAULT NULL');
        $this->addSql('ALTER TABLE vehicules ADD notes TEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE vehicules DROP kilometrage');
        $this->addSql('ALTER TABLE vehicules DROP notes');
    }
}
