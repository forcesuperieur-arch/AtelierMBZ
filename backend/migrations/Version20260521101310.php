<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260521101310 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE ordres_reparation ADD signature_atelier_reception TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE ordres_reparation ADD signature_mecanicien TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE ordres_reparation ADD signature_client_restitution TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE ordres_reparation ADD travaux_realises TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE ordres_reparation ADD alertes JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE ordres_reparation ADD recommandations TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE ordres_reparation ADD garantie TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE ordres_reparation ADD kilometrage_restitution INT DEFAULT NULL');
        $this->addSql('ALTER TABLE ordres_reparation ADD prochaine_revision_km INT DEFAULT NULL');
        $this->addSql('ALTER TABLE ordres_reparation ADD prochaine_revision_date DATE DEFAULT NULL');
        $this->addSql('ALTER TABLE ordres_reparation ADD signe_mecanicien_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE ordres_reparation ADD signe_mecanicien_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE ordres_reparation ADD signe_receptionniste_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE ordres_reparation ADD signe_client_restitution_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE ordres_reparation ADD essai_routier_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE ordres_reparation ADD CONSTRAINT FK_20160E348BAE3A9 FOREIGN KEY (essai_routier_id) REFERENCES essai_routier (id) NOT DEFERRABLE');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_20160E348BAE3A9 ON ordres_reparation (essai_routier_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE ordres_reparation DROP CONSTRAINT FK_20160E348BAE3A9');
        $this->addSql('DROP INDEX UNIQ_20160E348BAE3A9');
        $this->addSql('ALTER TABLE ordres_reparation DROP signature_atelier_reception');
        $this->addSql('ALTER TABLE ordres_reparation DROP signature_mecanicien');
        $this->addSql('ALTER TABLE ordres_reparation DROP signature_client_restitution');
        $this->addSql('ALTER TABLE ordres_reparation DROP travaux_realises');
        $this->addSql('ALTER TABLE ordres_reparation DROP alertes');
        $this->addSql('ALTER TABLE ordres_reparation DROP recommandations');
        $this->addSql('ALTER TABLE ordres_reparation DROP garantie');
        $this->addSql('ALTER TABLE ordres_reparation DROP kilometrage_restitution');
        $this->addSql('ALTER TABLE ordres_reparation DROP prochaine_revision_km');
        $this->addSql('ALTER TABLE ordres_reparation DROP prochaine_revision_date');
        $this->addSql('ALTER TABLE ordres_reparation DROP signe_mecanicien_at');
        $this->addSql('ALTER TABLE ordres_reparation DROP signe_mecanicien_id');
        $this->addSql('ALTER TABLE ordres_reparation DROP signe_receptionniste_at');
        $this->addSql('ALTER TABLE ordres_reparation DROP signe_client_restitution_at');
        $this->addSql('ALTER TABLE ordres_reparation DROP essai_routier_id');
    }
}
