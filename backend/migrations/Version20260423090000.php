<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * CERFA field config : table de configuration des positions de champs CERFA.
 * Permet d'ajuster x/y/fontSize par field_key sans toucher au code.
 */
final class Version20260423090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Crée cerfa_field_config pour la configuration des positions de champs CERFA';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("
            CREATE TABLE cerfa_field_config (
                id          SERIAL PRIMARY KEY,
                cerfa_ref   VARCHAR(30)     NOT NULL,
                field_key   VARCHAR(80)     NOT NULL,
                label       VARCHAR(200)    NOT NULL,
                x           NUMERIC(6,2)    NOT NULL,
                y           NUMERIC(6,2)    NOT NULL,
                width       NUMERIC(6,2)    NOT NULL DEFAULT 0,
                font_size   NUMERIC(4,1)    NOT NULL DEFAULT 8,
                field_type  VARCHAR(20)     NOT NULL DEFAULT 'text',
                description TEXT            DEFAULT NULL,
                is_active   BOOLEAN         NOT NULL DEFAULT TRUE,
                created_at  TIMESTAMP       NOT NULL DEFAULT NOW(),
                updated_at  TIMESTAMP       NOT NULL DEFAULT NOW(),
                UNIQUE (cerfa_ref, field_key)
            )
        ");
        $this->addSql("CREATE INDEX idx_cerfa_field_ref ON cerfa_field_config (cerfa_ref, is_active)");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DROP TABLE cerfa_field_config");
    }
}
