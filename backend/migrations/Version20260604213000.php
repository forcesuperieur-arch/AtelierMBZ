<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260604213000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Reattache les clients, vehicules et RDV legacy a un atelier valide';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
DO $$
DECLARE fallback_atelier_id integer;
BEGIN
    SELECT COALESCE(
        (SELECT id FROM ateliers WHERE actif = true ORDER BY id ASC LIMIT 1),
        (SELECT id FROM ateliers ORDER BY id ASC LIMIT 1)
    ) INTO fallback_atelier_id;

    IF fallback_atelier_id IS NULL THEN
        RETURN;
    END IF;

    UPDATE clients c
    SET atelier_id = src.atelier_id
    FROM (
        SELECT r.client_id, MIN(r.atelier_id) AS atelier_id
        FROM rendez_vous r
        INNER JOIN ateliers a ON a.id = r.atelier_id
        WHERE r.client_id IS NOT NULL
        GROUP BY r.client_id
    ) src
    WHERE c.id = src.client_id
      AND (c.atelier_id IS NULL OR NOT EXISTS (SELECT 1 FROM ateliers a2 WHERE a2.id = c.atelier_id));

    UPDATE clients c
    SET atelier_id = src.atelier_id
    FROM (
        SELECT v.client_id, MIN(v.atelier_id) AS atelier_id
        FROM vehicules v
        INNER JOIN ateliers a ON a.id = v.atelier_id
        WHERE v.client_id IS NOT NULL
        GROUP BY v.client_id
    ) src
    WHERE c.id = src.client_id
      AND (c.atelier_id IS NULL OR NOT EXISTS (SELECT 1 FROM ateliers a2 WHERE a2.id = c.atelier_id));

    UPDATE clients c
    SET atelier_id = fallback_atelier_id
    WHERE c.atelier_id IS NULL
       OR NOT EXISTS (SELECT 1 FROM ateliers a WHERE a.id = c.atelier_id);

    UPDATE vehicules v
    SET atelier_id = c.atelier_id
    FROM clients c
    WHERE c.id = v.client_id
      AND c.atelier_id IS NOT NULL
      AND (v.atelier_id IS NULL OR NOT EXISTS (SELECT 1 FROM ateliers a WHERE a.id = v.atelier_id));

    UPDATE vehicules v
    SET atelier_id = fallback_atelier_id
    WHERE v.atelier_id IS NULL
       OR NOT EXISTS (SELECT 1 FROM ateliers a WHERE a.id = v.atelier_id);

    UPDATE rendez_vous r
    SET atelier_id = COALESCE(
        c.atelier_id,
        (SELECT v.atelier_id FROM vehicules v WHERE v.id = r.vehicule_id),
        fallback_atelier_id
    )
    FROM clients c
    WHERE c.id = r.client_id
      AND (r.atelier_id IS NULL OR NOT EXISTS (SELECT 1 FROM ateliers a WHERE a.id = r.atelier_id));

    UPDATE rendez_vous r
    SET atelier_id = fallback_atelier_id
    WHERE r.atelier_id IS NULL
       OR NOT EXISTS (SELECT 1 FROM ateliers a WHERE a.id = r.atelier_id);
END $$;
SQL);
    }

    public function down(Schema $schema): void
    {
        // Migration de réparation de données legacy : rollback non déterministe.
    }
}
