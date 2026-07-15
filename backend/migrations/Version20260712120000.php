<?php
declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260712120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Canal téléphone — trace du membre du staff ayant enregistré la décision client (demandes_travaux_supp.decision_enregistree_par_id, FK users ON DELETE SET NULL)';
    }

    public function up(Schema $schema): void
    {
        // Décision métier 2026-07-12 : accord client donné par téléphone,
        // enregistré par le staff — on trace « qui a pris l'appel ».
        $this->addSql('ALTER TABLE demandes_travaux_supp ADD decision_enregistree_par_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE demandes_travaux_supp ADD CONSTRAINT FK_DTS_DECISION_ENREGISTREE_PAR FOREIGN KEY (decision_enregistree_par_id) REFERENCES users (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_DTS_DECISION_ENREGISTREE_PAR ON demandes_travaux_supp (decision_enregistree_par_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE demandes_travaux_supp DROP CONSTRAINT FK_DTS_DECISION_ENREGISTREE_PAR');
        $this->addSql('DROP INDEX IDX_DTS_DECISION_ENREGISTREE_PAR');
        $this->addSql('ALTER TABLE demandes_travaux_supp DROP decision_enregistree_par_id');
    }
}
