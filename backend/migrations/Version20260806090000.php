<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Aligne le rôle service_client (SRC) sur sa définition PILOTE_PLAN.md (Lot C, cadrage
 * 2026-06-12) : rôle TRANSVERSE aux ateliers, EN LECTURE, sans accès en écriture hors
 * périmètre. Le template RoleMetier posé par Version20260604130000 (2026-06-04, avant ce
 * cadrage) accordait par erreur rdv.create/edit, clients.create/edit, devis.create — jamais
 * appliqué côté serveur (aucun Voter ne consulte ce modèle), mais lu par le frontend pour
 * afficher les boutons de création/édition. On retire ces 5 entrées et on ajoute le module
 * "cockpit" (vue Cockpit SRC) en lecture.
 */
final class Version20260806090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'service_client (SRC) : retire les droits d\'écriture hérités, ajoute le module cockpit en lecture';
    }

    private const WRITE_GRANTS_TO_REMOVE = [
        ['rdv', 'create'],
        ['rdv', 'edit'],
        ['clients', 'create'],
        ['clients', 'edit'],
        ['devis', 'create'],
    ];

    public function up(Schema $schema): void
    {
        foreach (self::WRITE_GRANTS_TO_REMOVE as [$module, $action]) {
            $this->addSql(
                <<<'SQL'
DELETE FROM role_permission_entries
WHERE module = ? AND action = ?
  AND role_metier_id IN (
    SELECT id FROM roles_metier WHERE atelier_id IS NULL AND code = 'service_client'
  )
SQL,
                [$module, $action]
            );
        }

        $this->addSql(
            <<<'SQL'
INSERT INTO role_permission_entries (role_metier_id, module, action, scope, granted)
SELECT rm.id, 'cockpit', 'view', 'reseau', true
FROM roles_metier rm
WHERE rm.code = 'service_client' AND rm.atelier_id IS NULL
  AND NOT EXISTS (
    SELECT 1 FROM role_permission_entries rpe
    WHERE rpe.role_metier_id = rm.id AND rpe.module = 'cockpit' AND rpe.action = 'view'
  )
SQL
        );
    }

    public function down(Schema $schema): void
    {
        foreach (self::WRITE_GRANTS_TO_REMOVE as [$module, $action]) {
            $this->addSql(
                <<<'SQL'
INSERT INTO role_permission_entries (role_metier_id, module, action, scope, granted)
SELECT rm.id, ?, ?, 'atelier', true
FROM roles_metier rm
WHERE rm.code = 'service_client' AND rm.atelier_id IS NULL
  AND NOT EXISTS (
    SELECT 1 FROM role_permission_entries rpe
    WHERE rpe.role_metier_id = rm.id AND rpe.module = ? AND rpe.action = ?
  )
SQL,
                [$module, $action, $module, $action]
            );
        }

        $this->addSql(
            "DELETE FROM role_permission_entries WHERE module = 'cockpit' AND action = 'view' AND role_metier_id IN (SELECT id FROM roles_metier WHERE atelier_id IS NULL AND code = 'service_client')"
        );
    }
}
