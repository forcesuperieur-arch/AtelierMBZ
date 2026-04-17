<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260604140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Mechanic workspace backend: dedicated mechanic notes/checkup, RDV cancellation metadata, essai validation fields and mecanicien user index';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE rendez_vous ADD COLUMN IF NOT EXISTS motif_annulation VARCHAR(50) DEFAULT NULL');
        $this->addSql('ALTER TABLE rendez_vous ADD COLUMN IF NOT EXISTS commentaire_annulation TEXT DEFAULT NULL');

        $this->addSql('ALTER TABLE ordres_reparation ADD COLUMN IF NOT EXISTS mechanic_notes TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE ordres_reparation ADD COLUMN IF NOT EXISTS mechanic_notes_updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql("ALTER TABLE ordres_reparation ADD COLUMN IF NOT EXISTS mechanic_checkup TEXT NOT NULL DEFAULT '{}'");
        $this->addSql('ALTER TABLE ordres_reparation ADD COLUMN IF NOT EXISTS mechanic_checkup_updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');

        $this->addSql('ALTER TABLE essai_routier ADD COLUMN IF NOT EXISTS observations TEXT DEFAULT NULL');
        $this->addSql("ALTER TABLE essai_routier ADD COLUMN IF NOT EXISTS statut VARCHAR(30) NOT NULL DEFAULT 'en_cours'");
        $this->addSql('ALTER TABLE essai_routier ADD COLUMN IF NOT EXISTS validated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');

        $this->addSql('UPDATE essai_routier SET observations = anomalies WHERE observations IS NULL AND anomalies IS NOT NULL');
        $this->addSql(<<<'SQL'
UPDATE essai_routier
SET statut = CASE
    WHEN km_fin IS NOT NULL AND km_debut IS NOT NULL AND km_fin > km_debut AND signature_mecanicien IS NOT NULL THEN 'valide'
    ELSE 'en_cours'
END
WHERE statut IS NULL OR statut = ''
SQL);
        $this->addSql(<<<'SQL'
UPDATE essai_routier
SET validated_at = COALESCE(validated_at, realise_at)
WHERE km_fin IS NOT NULL AND km_debut IS NOT NULL AND km_fin > km_debut AND signature_mecanicien IS NOT NULL
SQL);

        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS uniq_mecanicien_user_id ON mecaniciens (user_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS uniq_mecanicien_user_id');

        $this->addSql('ALTER TABLE essai_routier DROP COLUMN IF EXISTS validated_at');
        $this->addSql('ALTER TABLE essai_routier DROP COLUMN IF EXISTS statut');
        $this->addSql('ALTER TABLE essai_routier DROP COLUMN IF EXISTS observations');

        $this->addSql('ALTER TABLE ordres_reparation DROP COLUMN IF EXISTS mechanic_checkup_updated_at');
        $this->addSql('ALTER TABLE ordres_reparation DROP COLUMN IF EXISTS mechanic_checkup');
        $this->addSql('ALTER TABLE ordres_reparation DROP COLUMN IF EXISTS mechanic_notes_updated_at');
        $this->addSql('ALTER TABLE ordres_reparation DROP COLUMN IF EXISTS mechanic_notes');

        $this->addSql('ALTER TABLE rendez_vous DROP COLUMN IF EXISTS commentaire_annulation');
        $this->addSql('ALTER TABLE rendez_vous DROP COLUMN IF EXISTS motif_annulation');
    }
}