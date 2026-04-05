"""Application startup orchestration helpers.

The goal is to keep the FastAPI app lifespan thin and move bootstrap concerns
into focused service modules that can later be migrated fully to Alembic and
dedicated setup commands.
"""

import logging

from sqlalchemy.orm import Session

from auth import create_default_users
from seed import init_base_moto, init_intervention_types, init_prestations
from seed_parametres import init_parametres
from services.runtime_migrations import (
    migrate_atelier_categorie_motos,
    migrate_demandes_travaux_supp,
    migrate_mecanicien_user_link,
    migrate_multitenant_schema,
    migrate_role_permissions,
    migrate_user_atelier_roles,
    migrate_vehicule_client_id,
)

logger = logging.getLogger("ateliermoto.api")


def run_runtime_migrations(db: Session) -> None:
    """Run idempotent compatibility migrations still needed at startup."""
    migrate_vehicule_client_id(db)
    migrate_demandes_travaux_supp(db)
    migrate_multitenant_schema(db)
    create_default_users(db)
    migrate_user_atelier_roles(db)
    migrate_role_permissions(db)
    migrate_mecanicien_user_link(db)
    migrate_atelier_categorie_motos(db)


def run_reference_seeders(db: Session) -> None:
    """Seed immutable/reference business data required by the app."""
    init_intervention_types(db)
    init_base_moto(db)
    init_prestations(db)
    init_parametres(db)


def run_startup_tasks(db: Session) -> None:
    """Run all startup bootstrap tasks in a stable, testable order."""
    run_runtime_migrations(db)
    run_reference_seeders(db)
    logger.info("Startup initialization completed")


__all__ = [
    "run_reference_seeders",
    "run_runtime_migrations",
    "run_startup_tasks",
]
