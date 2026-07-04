<?php
declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260703120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'KPI pilote — origine du RDV (rendez_vous.origine), canal de décision travaux supp (demandes_travaux_supp.decision_canal), recopie analytics (analytics_rdv_facts.origine + litige_signale)';
    }

    public function up(Schema $schema): void
    {
        // Origine du RDV : 'web' (booking public), 'comptoir'/'telephone' (staff),
        // 'devis' (conversion). Varchar libre, pas d'enum SQL : 'src_telephone'
        // arrivera au Lot C (booking proxy SRC).
        $this->addSql('ALTER TABLE rendez_vous ADD origine VARCHAR(30) DEFAULT NULL');

        // Canal de la décision client sur une demande de travaux supplémentaires :
        // 'client_token' (page publique tokenisée) / 'client_portail' (portail connecté).
        $this->addSql('ALTER TABLE demandes_travaux_supp ADD decision_canal VARCHAR(30) DEFAULT NULL');

        // Recopie dénormalisée dans la fact table analytics.
        $this->addSql('ALTER TABLE analytics_rdv_facts ADD origine VARCHAR(30) DEFAULT NULL');
        $this->addSql('ALTER TABLE analytics_rdv_facts ADD litige_signale BOOLEAN NOT NULL DEFAULT FALSE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE analytics_rdv_facts DROP litige_signale');
        $this->addSql('ALTER TABLE analytics_rdv_facts DROP origine');
        $this->addSql('ALTER TABLE demandes_travaux_supp DROP decision_canal');
        $this->addSql('ALTER TABLE rendez_vous DROP origine');
    }
}
