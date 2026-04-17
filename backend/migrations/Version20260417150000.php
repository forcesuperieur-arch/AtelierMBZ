<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * LOT 3.2 — Add `source` column to annulation_rdv to track whether cancellation
 * originated from atelier staff, from the client, or was fired automatically
 * (e.g. no-show detection command).
 */
final class Version20260417150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "LOT 3.2 — add 'source' column to annulation_rdv";
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE annulation_rdv ADD COLUMN IF NOT EXISTS source VARCHAR(20) NOT NULL DEFAULT 'atelier'");
        // Mark no_show-driven cancellations as automatic in hindsight
        $this->addSql("UPDATE annulation_rdv SET source = 'automatique' WHERE motif = 'no_show' AND annule_par IS NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE annulation_rdv DROP COLUMN IF EXISTS source');
    }
}
