<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Chaîne prestations → OR : fige les prestations réservées sur le RDV
 * (prestations_snapshot) et reporte un montant total ESTIMÉ (indicatif) sur
 * l'ordre de réparation (montant_estime), pour que le client signe un OR qui
 * liste ce qu'il a commandé + un total. Colonnes nullable (rétrocompat).
 */
final class Version20260722100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'RDV.prestations_snapshot (JSON) + OrdreReparation.montant_estime (total estimé indicatif)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE rendez_vous ADD prestations_snapshot JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE ordres_reparation ADD montant_estime NUMERIC(10, 2) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE rendez_vous DROP prestations_snapshot');
        $this->addSql('ALTER TABLE ordres_reparation DROP montant_estime');
    }
}
