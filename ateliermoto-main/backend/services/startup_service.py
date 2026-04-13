"""Application startup orchestration helpers.

The goal is to keep the FastAPI app lifespan thin and move bootstrap concerns
into focused service modules that can later be migrated fully to Alembic and
dedicated setup commands.
"""

import logging

from sqlalchemy.orm import Session

from auth import create_default_users
from seed import init_base_moto, init_intervention_types, init_moto_technical_specs, init_prestations
from seed_parametres import init_parametres
from services.runtime_migrations import (
    migrate_atelier_categorie_motos,
    migrate_demandes_travaux_supp,
    migrate_moto_technical_specs,
    migrate_mecanicien_user_link,
    migrate_multitenant_schema,
    migrate_role_permissions,
    migrate_token_suivi_rdv,
    migrate_user_atelier_roles,
    migrate_vehicule_client_id,
)

logger = logging.getLogger("ateliermoto.api")


def run_runtime_migrations(db: Session) -> None:
    """Run idempotent compatibility migrations still needed at startup."""
    logger.info("Startup migration: migrate_vehicule_client_id")
    migrate_vehicule_client_id(db)
    logger.info("Startup migration: migrate_demandes_travaux_supp")
    migrate_demandes_travaux_supp(db)
    logger.info("Startup migration: migrate_multitenant_schema")
    migrate_multitenant_schema(db)
    logger.info("Startup migration: create_default_users")
    create_default_users(db)
    logger.info("Startup migration: migrate_user_atelier_roles")
    migrate_user_atelier_roles(db)
    logger.info("Startup migration: migrate_role_permissions")
    migrate_role_permissions(db)
    logger.info("Startup migration: migrate_mecanicien_user_link")
    migrate_mecanicien_user_link(db)
    logger.info("Startup migration: migrate_atelier_categorie_motos")
    migrate_atelier_categorie_motos(db)
    logger.info("Startup migration: migrate_moto_technical_specs")
    migrate_moto_technical_specs(db)
    logger.info("Startup migration: migrate_token_suivi_rdv")
    migrate_token_suivi_rdv(db)
    logger.info("Startup migrations completed")


def run_reference_seeders(db: Session) -> None:
    """Seed immutable/reference business data required by the app."""
    logger.info("Startup seeder: init_intervention_types")
    init_intervention_types(db)
    logger.info("Startup seeder: init_base_moto")
    init_base_moto(db)
    logger.info("Startup seeder: init_moto_technical_specs")
    init_moto_technical_specs(db)
    logger.info("Startup seeder: init_prestations")
    init_prestations(db)
    logger.info("Startup seeder: init_parametres")
    init_parametres(db)
    logger.info("Startup seeders completed")


def run_startup_tasks(db: Session) -> None:
    """Run all startup bootstrap tasks in a stable, testable order."""
    run_runtime_migrations(db)
    run_reference_seeders(db)
    logger.info("Startup initialization completed")


__all__ = [
    "run_preprod_bootstrap",
    "run_reference_seeders",
    "run_runtime_migrations",
    "run_startup_tasks",
]


def run_preprod_bootstrap(db: Session) -> None:
    """Run only the core bootstrap required for a fast, reliable preprod install."""
    run_runtime_migrations(db)
    logger.info("Preprod seeder: init_intervention_types")
    init_intervention_types(db)
    logger.info("Preprod seeder: init_base_moto")
    init_base_moto(db)
    logger.info("Preprod seeder: init_prestations")
    init_prestations(db)
    logger.info("Preprod seeder: init_parametres")
    init_parametres(db)
    logger.info("Preprod bootstrap completed")
