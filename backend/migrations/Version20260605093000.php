<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260605093000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Restore missing legal clauses for RGPD and workshop compliance';
    }

    public function up(Schema $schema): void
    {
        $now = date('Y-m-d H:i:s');

        $clauses = [
            'mandat_reparation' => [
                'Clause mandat de réparation',
                'La signature de l’ordre de réparation vaut mandat donné à l’atelier pour effectuer les opérations expressément acceptées par le client. Aucun travail complémentaire payant ne peut être engagé sans accord explicite préalable du client, sauf urgence de sécurité dûment justifiée.',
            ],
            'retention' => [
                'Clause de rétention',
                'Conformément au droit commun, l’atelier peut exercer un droit de rétention sur le véhicule jusqu’au règlement complet des sommes dues au titre des réparations, prestations et frais annexes régulièrement acceptés par le client.',
            ],
            'rgpd' => [
                'Information RGPD',
                'Les données personnelles collectées par l’atelier sont utilisées uniquement pour la gestion du dossier client, du véhicule, des rendez-vous, ordres de réparation, factures et obligations légales associées. Elles sont conservées pour la durée strictement nécessaire à ces finalités et aux obligations légales. Le client peut exercer ses droits d’accès, de rectification, d’opposition, de limitation et, lorsque la loi le permet, d’effacement, en contactant l’atelier.',
            ],
            'mentions_legales' => [
                'Mentions légales',
                'Les documents émis par l’atelier reprennent les mentions légales obligatoires applicables, notamment l’identification de l’établissement, les références du véhicule, la date, la numérotation et les informations nécessaires à la bonne exécution des obligations contractuelles et comptables.',
            ],
        ];

        foreach ($clauses as $code => [$libelle, $texte]) {
            $libelle = str_replace("'", "''", $libelle);
            $texte = str_replace("'", "''", $texte);

            $this->addSql(<<<SQL
                INSERT INTO clause_legale (atelier_id, code, libelle, texte, version, effective_from, is_active, created_at)
                SELECT NULL, '$code', '$libelle', '$texte', 1, '$now', true, '$now'
                WHERE NOT EXISTS (
                    SELECT 1 FROM clause_legale WHERE atelier_id IS NULL AND code = '$code'
                )
            SQL);
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM clause_legale WHERE atelier_id IS NULL AND code IN ('mandat_reparation', 'retention', 'rgpd', 'mentions_legales') AND version = 1");
    }
}
