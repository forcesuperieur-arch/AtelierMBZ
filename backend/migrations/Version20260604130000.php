<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260604130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'RoleMetier direct-cutover foundation: SSO user fields, pending validation status, service_client template and user backfill';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE users ADD auth_provider VARCHAR(30) NOT NULL DEFAULT 'local'");
        $this->addSql("ALTER TABLE users ADD google_sub VARCHAR(191) DEFAULT NULL");
        $this->addSql("ALTER TABLE users ADD access_status VARCHAR(30) NOT NULL DEFAULT 'active'");
        $this->addSql('ALTER TABLE users ADD validated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD validated_by INT DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD last_login_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS UNIQ_USERS_GOOGLE_SUB ON users (google_sub)');
        $this->addSql('CREATE INDEX IF NOT EXISTS IDX_USERS_AUTH_PROVIDER ON users (auth_provider)');
        $this->addSql('CREATE INDEX IF NOT EXISTS IDX_USERS_ACCESS_STATUS ON users (access_status)');

        $this->addSql("UPDATE users SET auth_provider = 'local' WHERE auth_provider IS NULL OR auth_provider = ''");
        $this->addSql("UPDATE users SET access_status = CASE WHEN is_active = 1 THEN 'active' ELSE 'disabled' END WHERE access_status IS NULL OR access_status = ''");

        $this->addSql(<<<'SQL'
INSERT INTO roles_metier (atelier_id, code, libelle, description, base_role, is_system_template, is_active, created_at)
SELECT NULL, 'service_client', 'Service client', 'Accueil client, suivi commercial et préparation des dossiers.', 'ROLE_USER', true, true, CURRENT_TIMESTAMP
WHERE NOT EXISTS (
    SELECT 1 FROM roles_metier WHERE atelier_id IS NULL AND code = 'service_client'
)
SQL);

        $serviceClientPerms = [
            ['dashboard', 'view'],
            ['rdv', 'view'],
            ['rdv', 'create'],
            ['rdv', 'edit'],
            ['planning', 'view'],
            ['suivi', 'view'],
            ['clients', 'view'],
            ['clients', 'create'],
            ['clients', 'edit'],
            ['motos', 'view'],
            ['devis', 'view'],
            ['devis', 'create'],
            ['facturation', 'view'],
            ['or', 'view'],
        ];

        foreach ($serviceClientPerms as [$module, $action]) {
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

        $this->addSql(<<<'SQL'
UPDATE users u
SET role_metier_id = rm.id
FROM roles_metier rm
WHERE u.role_metier_id IS NULL
  AND rm.atelier_id IS NULL
  AND rm.code = CASE
    WHEN u.role IN ('super_admin', 'admin') THEN 'responsable_atelier'
    WHEN u.role IN ('receptionnaire', 'receptionniste') THEN 'receptionniste'
    WHEN u.role = 'mecanicien' THEN 'mecanicien'
    WHEN u.role = 'comptable' THEN 'comptable'
    WHEN u.role = 'vo_manager' THEN 'vo_manager'
    WHEN u.role = 'service_client' THEN 'service_client'
    ELSE 'service_client'
  END
SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS IDX_USERS_ACCESS_STATUS');
        $this->addSql('DROP INDEX IF EXISTS IDX_USERS_AUTH_PROVIDER');
        $this->addSql('DROP INDEX IF EXISTS UNIQ_USERS_GOOGLE_SUB');
        $this->addSql('ALTER TABLE users DROP COLUMN IF EXISTS last_login_at');
        $this->addSql('ALTER TABLE users DROP COLUMN IF EXISTS validated_by');
        $this->addSql('ALTER TABLE users DROP COLUMN IF EXISTS validated_at');
        $this->addSql('ALTER TABLE users DROP COLUMN IF EXISTS access_status');
        $this->addSql('ALTER TABLE users DROP COLUMN IF EXISTS google_sub');
        $this->addSql('ALTER TABLE users DROP COLUMN IF EXISTS auth_provider');

        $this->addSql("DELETE FROM role_permission_entries WHERE role_metier_id IN (SELECT id FROM roles_metier WHERE atelier_id IS NULL AND code = 'service_client')");
        $this->addSql("DELETE FROM roles_metier WHERE atelier_id IS NULL AND code = 'service_client'");
    }
}
