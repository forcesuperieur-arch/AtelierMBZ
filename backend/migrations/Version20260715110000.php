<?php
declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260715110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Robustesse : au plus un OR complémentaire par demande de travaux supp (index unique partiel) — barrière DB contre la double-soumission (double-clic / retry réseau)';
    }

    public function up(Schema $schema): void
    {
        // Un accord travaux supp ne peut donner qu'UN seul OR complémentaire.
        // L'index unique partiel garantit qu'une décision rejouée en parallèle
        // ne crée pas de doublon (facturation/document légal en double) : le 2e
        // flush viole la contrainte et est traduit en 409 côté service.
        $this->addSql("CREATE UNIQUE INDEX UNIQ_OR_COMPLEMENTAIRE_PAR_DEMANDE ON ordres_reparation (demande_travaux_supp_id) WHERE type_or = 'complementaire' AND demande_travaux_supp_id IS NOT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_OR_COMPLEMENTAIRE_PAR_DEMANDE');
    }
}
