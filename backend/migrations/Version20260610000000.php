<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260610000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create document_layouts table for visual PDF template editor';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE document_layouts (
                id SERIAL PRIMARY KEY,
                atelier_id INT DEFAULT NULL,
                code VARCHAR(50) NOT NULL,
                label VARCHAR(200) NOT NULL,
                layout_json JSON NOT NULL DEFAULT '[]',
                is_default BOOLEAN NOT NULL DEFAULT false,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT uq_doc_layout UNIQUE (atelier_id, code)
            )
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS document_layouts');
    }
}
