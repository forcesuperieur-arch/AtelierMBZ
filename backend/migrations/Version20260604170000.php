<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260604170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'VO refonte: many-to-one vehicle mappings and atelier-scoped numbering counters';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
UPDATE vo_purchases vp
SET atelier_id = COALESCE(
    vp.atelier_id,
    (SELECT v.atelier_id FROM vehicules v WHERE v.id = vp.vehicule_id),
    (SELECT c.atelier_id FROM clients c WHERE c.id = vp.seller_id),
    0
)
SQL);
        $this->addSql(<<<'SQL'
UPDATE vo_depot_ventes vd
SET atelier_id = COALESCE(
    vd.atelier_id,
    (SELECT v.atelier_id FROM vehicules v WHERE v.id = vd.vehicule_id),
    (SELECT c.atelier_id FROM clients c WHERE c.id = vd.deposant_id),
    0
)
SQL);
        $this->addSql(<<<'SQL'
UPDATE vo_livre_police lp
SET atelier_id = COALESCE(
    lp.atelier_id,
    (SELECT vp.atelier_id FROM vo_purchases vp WHERE vp.id = lp.vo_purchase_id),
    (SELECT vd.atelier_id FROM vo_depot_ventes vd WHERE vd.id = lp.vo_depot_vente_id),
    0
)
SQL);
        $this->addSql(<<<'SQL'
UPDATE vo_factures vf
SET atelier_id = COALESCE(
    vf.atelier_id,
    (SELECT vp.atelier_id FROM vo_purchases vp WHERE vp.id = vf.vo_purchase_id),
    (SELECT vd.atelier_id FROM vo_depot_ventes vd WHERE vd.id = vf.vo_depot_vente_id),
    0
)
SQL);

        $this->addSql('DROP INDEX IF EXISTS "UNIQ_77FC78104A4A3511"');
        $this->addSql('DROP INDEX IF EXISTS "UNIQ_502B9DCF4A4A3511"');
        $this->addSql('DROP INDEX IF EXISTS uniq_vo_purchases_vehicule_id');
        $this->addSql('DROP INDEX IF EXISTS uniq_vo_depot_ventes_vehicule_id');
        $this->addSql('CREATE INDEX IF NOT EXISTS "IDX_77FC78104A4A3511" ON vo_purchases (vehicule_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS "IDX_502B9DCF4A4A3511" ON vo_depot_ventes (vehicule_id)');

        $this->addSql(<<<'SQL'
CREATE TABLE IF NOT EXISTS vo_counters (
    id SERIAL PRIMARY KEY,
    counter_type VARCHAR(30) NOT NULL,
    atelier_id INT NOT NULL DEFAULT 0,
    counter_year INT NOT NULL DEFAULT 0,
    counter_value INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
)
SQL);
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS uniq_vo_counters_scope ON vo_counters (counter_type, atelier_id, counter_year)');

        $this->addSql(<<<'SQL'
INSERT INTO vo_counters (counter_type, atelier_id, counter_year, counter_value)
SELECT 'livre_police', COALESCE(atelier_id, 0), 0, MAX(numero_ordre)
FROM vo_livre_police
GROUP BY COALESCE(atelier_id, 0)
ON CONFLICT (counter_type, atelier_id, counter_year) DO NOTHING
SQL);

        $this->addSql(<<<'SQL'
INSERT INTO vo_counters (counter_type, atelier_id, counter_year, counter_value)
SELECT
    'facture',
    COALESCE(atelier_id, 0),
    CAST(SUBSTRING(numero_facture FROM 5 FOR 4) AS INT),
    MAX(CAST(RIGHT(numero_facture, 4) AS INT))
FROM vo_factures
WHERE numero_facture ~ '^VOF-[0-9]{4}-[0-9]{4}$'
GROUP BY COALESCE(atelier_id, 0), SUBSTRING(numero_facture FROM 5 FOR 4)
ON CONFLICT (counter_type, atelier_id, counter_year) DO NOTHING
SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS "IDX_77FC78104A4A3511"');
        $this->addSql('DROP INDEX IF EXISTS "IDX_502B9DCF4A4A3511"');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS "UNIQ_77FC78104A4A3511" ON vo_purchases (vehicule_id)');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS "UNIQ_502B9DCF4A4A3511" ON vo_depot_ventes (vehicule_id)');
        $this->addSql('DROP INDEX IF EXISTS uniq_vo_counters_scope');
        $this->addSql('DROP TABLE IF EXISTS vo_counters');
    }
}