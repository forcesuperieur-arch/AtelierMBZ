<?php
declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260703110000 extends AbstractMigration
{
    /** Ancien corps par défaut du template email travaux_termines (catalogue avant Lot B). */
    private const ANCIEN_CORPS = '<p>Bonjour {{client_prenom}},</p><p>Les travaux sur votre véhicule sont terminés. Vous pouvez venir le récupérer.</p>';

    /** Nouveau corps par défaut : bloc état des lieux optionnel (variable toujours fournie par les call sites). */
    private const NOUVEAU_CORPS = self::ANCIEN_CORPS . '{{etat_des_lieux_bloc}}';

    public function getDescription(): string
    {
        return 'Lot B — litige à la restitution (rendez_vous.litige_signale/litige_commentaire) + bloc état des lieux dans le template email travaux_termines (non personnalisés uniquement)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE rendez_vous ADD litige_signale BOOLEAN NOT NULL DEFAULT FALSE');
        $this->addSql('ALTER TABLE rendez_vous ADD litige_commentaire TEXT DEFAULT NULL');

        // Propage le nouveau défaut du catalogue aux templates email travaux_termines
        // NON personnalisés (corps strictement égal à l'ancien défaut). Les templates
        // modifiés par un admin ne sont jamais écrasés.
        $this->addSql(sprintf(
            "UPDATE notification_templates
             SET corps = '%s',
                 variables = '[\"client_prenom\",\"etat_des_lieux_bloc\"]',
                 updated_at = NOW()
             WHERE code = 'travaux_termines' AND channel = 'email' AND corps = '%s'",
            self::NOUVEAU_CORPS,
            self::ANCIEN_CORPS,
        ));
    }

    public function down(Schema $schema): void
    {
        $this->addSql(sprintf(
            "UPDATE notification_templates
             SET corps = '%s',
                 variables = '[\"client_prenom\"]',
                 updated_at = NOW()
             WHERE code = 'travaux_termines' AND channel = 'email' AND corps = '%s'",
            self::ANCIEN_CORPS,
            self::NOUVEAU_CORPS,
        ));
        $this->addSql('ALTER TABLE rendez_vous DROP litige_commentaire');
        $this->addSql('ALTER TABLE rendez_vous DROP litige_signale');
    }
}
