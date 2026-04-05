"""Backward-compatible wrapper around the startup bootstrap services.

Kept temporarily so existing imports remain valid while the refactor moves the
actual runtime migration logic under `services/`.
"""

from services.runtime_migrations import (
    migrate_atelier_categorie_motos,
    migrate_demandes_travaux_supp,
    migrate_mecanicien_user_link,
    migrate_multitenant_schema,
    migrate_role_permissions,
    migrate_user_atelier_roles,
    migrate_vehicule_client_id,
)
from services.startup_service import run_reference_seeders, run_runtime_migrations, run_startup_tasks

__all__ = [
    "migrate_atelier_categorie_motos",
    "migrate_demandes_travaux_supp",
    "migrate_mecanicien_user_link",
    "migrate_multitenant_schema",
    "migrate_role_permissions",
    "migrate_user_atelier_roles",
    "migrate_vehicule_client_id",
    "run_reference_seeders",
    "run_runtime_migrations",
    "run_startup_tasks",
]
