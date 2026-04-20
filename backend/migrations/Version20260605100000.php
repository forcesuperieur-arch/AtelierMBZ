<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260605100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add CERFA fields to vehicules table (type_variante D.2, denomination_commerciale D.3, genre_national J.1, numero_formule_cg)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE vehicules ADD type_variante VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE vehicules ADD denomination_commerciale VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE vehicules ADD genre_national VARCHAR(20) DEFAULT NULL');
        $this->addSql('ALTER TABLE vehicules ADD numero_formule_cg VARCHAR(20) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE vehicules DROP COLUMN type_variante');
        $this->addSql('ALTER TABLE vehicules DROP COLUMN denomination_commerciale');
        $this->addSql('ALTER TABLE vehicules DROP COLUMN genre_national');
        $this->addSql('ALTER TABLE vehicules DROP COLUMN numero_formule_cg');
    }
}
