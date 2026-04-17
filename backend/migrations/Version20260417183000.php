<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260417183000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'VO PDA public companion flow: token, expiry and signature storage on purchases and depots';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE vo_purchases ADD companion_token VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE vo_purchases ADD companion_token_created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE vo_purchases ADD companion_token_expires_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE vo_purchases ADD companion_signed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE vo_purchases ADD companion_signature_data TEXT DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_VO_PURCHASES_COMPANION_TOKEN ON vo_purchases (companion_token)');

        $this->addSql('ALTER TABLE vo_depot_ventes ADD companion_token VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE vo_depot_ventes ADD companion_token_created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE vo_depot_ventes ADD companion_token_expires_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE vo_depot_ventes ADD companion_signed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE vo_depot_ventes ADD companion_signature_data TEXT DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_VO_DEPOTS_COMPANION_TOKEN ON vo_depot_ventes (companion_token)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_VO_PURCHASES_COMPANION_TOKEN');
        $this->addSql('ALTER TABLE vo_purchases DROP companion_token');
        $this->addSql('ALTER TABLE vo_purchases DROP companion_token_created_at');
        $this->addSql('ALTER TABLE vo_purchases DROP companion_token_expires_at');
        $this->addSql('ALTER TABLE vo_purchases DROP companion_signed_at');
        $this->addSql('ALTER TABLE vo_purchases DROP companion_signature_data');

        $this->addSql('DROP INDEX UNIQ_VO_DEPOTS_COMPANION_TOKEN');
        $this->addSql('ALTER TABLE vo_depot_ventes DROP companion_token');
        $this->addSql('ALTER TABLE vo_depot_ventes DROP companion_token_created_at');
        $this->addSql('ALTER TABLE vo_depot_ventes DROP companion_token_expires_at');
        $this->addSql('ALTER TABLE vo_depot_ventes DROP companion_signed_at');
        $this->addSql('ALTER TABLE vo_depot_ventes DROP companion_signature_data');
    }
}