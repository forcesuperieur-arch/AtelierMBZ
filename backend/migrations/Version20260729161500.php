<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Anti-doublon du rappel de vidange (RappelProchaineRevisionCommand, étendu
 * pour vérifier aussi le seuil km) : posé une fois la notification envoyée,
 * jamais renvoyée pour le même OR.
 */
final class Version20260729161500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'OrdreReparation : vidange_notifiee_at (anti-doublon du rappel de vidange)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ordres_reparation ADD vidange_notifiee_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ordres_reparation DROP vidange_notifiee_at');
    }
}
