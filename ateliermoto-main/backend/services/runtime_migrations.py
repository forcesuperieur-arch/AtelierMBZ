"""Idempotent runtime migrations kept temporarily outside Alembic.

This module isolates compatibility/backfill steps so the application startup
stays readable while the remaining schema changes are progressively migrated to
proper Alembic revisions.
"""

import json

from sqlalchemy import inspect, text
from sqlalchemy.orm import Session

from models import Atelier, AtelierCategorieMoto, CategorieMoto, RolePermission, User, UserAtelierRole


def migrate_demandes_travaux_supp(db: Session):
    """Migration: ajouter colonnes prestations_demandees, decision_client."""
    try:
        inspector = inspect(db.bind)
        columns = [c["name"] for c in inspector.get_columns("demandes_travaux_supp")]
        for col, typ in [
            ("prestations_demandees", "TEXT"),
            ("decision_client", "VARCHAR(50)"),
            ("decision_client_at", "TIMESTAMP"),
        ]:
            if col not in columns:
                db.execute(text(f"ALTER TABLE demandes_travaux_supp ADD COLUMN {col} {typ}"))
                db.commit()
                print(f"[MIGRATION] Colonne {col} ajoutee a demandes_travaux_supp")
    except Exception as e:
        db.rollback()
        print(f"[MIGRATION] demandes_travaux_supp: {e}")


def migrate_multitenant_schema(db: Session):
    """Migration Lot 1 v2: table ateliers + colonnes atelier_id + backfill atelier #1."""
    try:
        inspector = inspect(db.bind)
        tables = set(inspector.get_table_names())

        if "ateliers" not in tables:
            db.execute(text("""
                CREATE TABLE ateliers (
                    id SERIAL PRIMARY KEY,
                    nom VARCHAR(200) NOT NULL,
                    slug VARCHAR(100) NOT NULL UNIQUE,
                    adresse TEXT,
                    cp VARCHAR(20),
                    ville VARCHAR(100),
                    telephone VARCHAR(20),
                    email VARCHAR(200),
                    siret VARCHAR(20),
                    tva_intracom VARCHAR(30),
                    logo_url VARCHAR(500),
                    plan VARCHAR(50) DEFAULT 'starter',
                    actif BOOLEAN DEFAULT TRUE,
                    config_json TEXT,
                    created_at TIMESTAMP DEFAULT NOW()
                )
            """))
            db.commit()
            print("[MIGRATION] Table ateliers creee")

        atelier = db.query(Atelier).filter(Atelier.slug == "default").first()
        if not atelier:
            atelier = Atelier(nom="Mon Atelier", slug="default", plan="starter", actif=True)
            db.add(atelier)
            db.commit()
            db.refresh(atelier)
            print(f"[MIGRATION] Atelier par defaut cree (id={atelier.id})")

        atelier_id = atelier.id
        tenant_tables = [
            "clients",
            "vehicules",
            "rendez_vous",
            "users",
            "ponts",
            "mecaniciens",
            "absences",
            "pieces_detachees",
            "fournisseurs",
            "commandes_fournisseur",
            "config_atelier",
            "forfaits_mo",
            "devis",
            "prestations",
            "grille_tarifaire",
            "grille_tarifs",
            "calculs_tarifs",
            "factures",
            "horaires_atelier",
        ]

        for table_name in tenant_tables:
            if table_name not in tables:
                continue
            columns = [c["name"] for c in inspector.get_columns(table_name)]
            if "atelier_id" not in columns:
                db.execute(text(f"ALTER TABLE {table_name} ADD COLUMN atelier_id INTEGER REFERENCES ateliers(id)"))
                db.commit()
                print(f"[MIGRATION] Colonne atelier_id ajoutee sur {table_name}")
            db.execute(text(f"UPDATE {table_name} SET atelier_id = :aid WHERE atelier_id IS NULL"), {"aid": atelier_id})
            db.commit()
    except Exception as e:
        db.rollback()
        print(f"[MIGRATION] multitenant_schema: {e}")


def migrate_user_atelier_roles(db: Session):
    """Migration Lot 4 v2: table user_atelier_roles + backfill depuis users."""
    try:
        inspector = inspect(db.bind)
        tables = set(inspector.get_table_names())
        if "user_atelier_roles" not in tables:
            db.execute(text("""
                CREATE TABLE user_atelier_roles (
                    user_id INTEGER NOT NULL REFERENCES users(id),
                    atelier_id INTEGER NOT NULL REFERENCES ateliers(id),
                    role VARCHAR(50) NOT NULL DEFAULT 'receptionnaire',
                    created_at TIMESTAMP DEFAULT NOW(),
                    PRIMARY KEY (user_id, atelier_id)
                )
            """))
            db.commit()
            print("[MIGRATION] Table user_atelier_roles creee")

        users = db.query(User).all()
        for user in users:
            aid = user.atelier_id or 1
            existing = db.query(UserAtelierRole).filter(
                UserAtelierRole.user_id == user.id,
                UserAtelierRole.atelier_id == aid,
            ).first()
            if not existing:
                db.add(UserAtelierRole(user_id=user.id, atelier_id=aid, role=user.role or "receptionnaire"))
        db.commit()
    except Exception as e:
        db.rollback()
        print(f"[MIGRATION] user_atelier_roles: {e}")


def migrate_role_permissions(db: Session):
    """Migration RBAC: table role_permissions + defaults."""
    try:
        inspector = inspect(db.bind)
        tables = set(inspector.get_table_names())
        if "role_permissions" not in tables:
            db.execute(text("""
                CREATE TABLE role_permissions (
                    role VARCHAR(50) PRIMARY KEY,
                    label VARCHAR(120) NOT NULL,
                    description TEXT,
                    sections_json TEXT NOT NULL DEFAULT '[]',
                    permissions_json TEXT NOT NULL DEFAULT '[]',
                    is_system INTEGER DEFAULT 0,
                    created_at TIMESTAMP DEFAULT NOW(),
                    updated_at TIMESTAMP DEFAULT NOW()
                )
            """))
            db.commit()
            print("[MIGRATION] Table role_permissions creee")
    except Exception as e:
        db.rollback()
        print(f"[MIGRATION] role_permissions schema: {e}")

    defaults = {
        "super_admin": {
            "label": "Super Admin",
            "description": "Acces total multi-ateliers",
            "sections": ["dashboard", "rdv", "planning", "ponts", "or", "suivi", "motos", "clients", "espace-meca", "admin"],
            "permissions": ["billing.view", "billing.edit", "billing.pay", "billing.pdf", "travaux_supp.review", "users.manage", "ateliers.manage", "roles.manage", "config.manage", "prestations.manage", "rdv.select_atelier", "rdv.edit", "workflow.manage", "or.manage", "workshop.manage", "motos.manage", "horaires.manage", "clients.edit", "stats.view"],
        },
        "admin": {
            "label": "Admin Atelier",
            "description": "Administration atelier courant",
            "sections": ["dashboard", "rdv", "planning", "ponts", "or", "suivi", "motos", "clients", "espace-meca", "admin"],
            "permissions": ["billing.view", "billing.edit", "billing.pay", "billing.pdf", "travaux_supp.review", "users.manage", "config.manage", "prestations.manage", "rdv.select_atelier", "rdv.edit", "workflow.manage", "or.manage", "workshop.manage", "motos.manage", "horaires.manage", "clients.edit", "stats.view"],
        },
        "manager": {
            "label": "Manager Atelier",
            "description": "Supervision operationnelle sans acces super_admin",
            "sections": ["dashboard", "rdv", "planning", "ponts", "or", "suivi", "motos", "clients", "espace-meca", "admin"],
            "permissions": ["billing.view", "travaux_supp.review", "config.manage", "prestations.manage", "rdv.select_atelier", "rdv.edit", "workflow.manage", "or.manage", "workshop.manage", "motos.manage", "horaires.manage", "clients.edit", "stats.view"],
        },
        "receptionnaire": {
            "label": "Reception",
            "description": "Gestion operationnelle reception",
            "sections": ["dashboard", "rdv", "planning", "ponts", "or", "suivi", "motos", "clients", "espace-meca"],
            "permissions": ["billing.view", "billing.edit", "billing.pay", "billing.pdf", "travaux_supp.review", "rdv.select_atelier", "rdv.edit", "workflow.manage", "or.manage", "clients.edit"],
        },
        "service_client": {
            "label": "Service Client (SRC)",
            "description": "Version simple sans facturation",
            "sections": ["dashboard", "rdv", "planning", "ponts", "or", "suivi", "motos", "clients", "espace-meca"],
            "permissions": ["travaux_supp.review", "rdv.select_atelier", "rdv.edit", "clients.edit"],
        },
        "mecanicien": {
            "label": "Mecanicien",
            "description": "Execution atelier",
            "sections": ["dashboard", "planning", "or", "motos", "espace-meca"],
            "permissions": [],
        },
    }
    try:
        for role, cfg in defaults.items():
            existing = db.query(RolePermission).filter(RolePermission.role == role).first()
            if not existing:
                db.add(
                    RolePermission(
                        role=role,
                        label=cfg["label"],
                        description=cfg["description"],
                        sections_json=json.dumps(cfg["sections"]),
                        permissions_json=json.dumps(cfg["permissions"]),
                        is_system=1,
                    )
                )
                continue

            updated = False
            try:
                sections = json.loads(existing.sections_json or "[]")
            except Exception:
                sections = []
            try:
                permissions = json.loads(existing.permissions_json or "[]")
            except Exception:
                permissions = []

            for section in cfg["sections"]:
                if section not in sections:
                    sections.append(section)
                    updated = True
            for permission in cfg["permissions"]:
                if permission not in permissions:
                    permissions.append(permission)
                    updated = True

            if updated:
                existing.sections_json = json.dumps(sections)
                existing.permissions_json = json.dumps(permissions)

            if not existing.label:
                existing.label = cfg["label"]
                updated = True
            if not existing.description:
                existing.description = cfg["description"]
                updated = True
            if existing.is_system is None:
                existing.is_system = 1
                updated = True

            if updated:
                db.add(existing)
        db.commit()
    except Exception as e:
        db.rollback()
        print(f"[MIGRATION] role_permissions seed: {e}")


def migrate_vehicule_client_id(db: Session):
    """Migration: ajouter client_id a vehicules et backfill depuis rendez_vous."""
    try:
        inspector = inspect(db.bind)
        columns = [c["name"] for c in inspector.get_columns("vehicules")]
        if "client_id" not in columns:
            db.execute(text("ALTER TABLE vehicules ADD COLUMN client_id INTEGER REFERENCES clients(id)"))
            db.commit()
            print("[MIGRATION] Colonne client_id ajoutee a vehicules")

        orphans = db.execute(text("SELECT id FROM vehicules WHERE client_id IS NULL")).fetchall()
        migrated = 0
        for (vid,) in orphans:
            row = db.execute(
                text(
                    "SELECT client_id FROM rendez_vous WHERE vehicule_id = :vid AND statut != 'annule' ORDER BY date_rdv DESC LIMIT 1"
                ),
                {"vid": vid},
            ).fetchone()
            if not row:
                row = db.execute(
                    text(
                        "SELECT client_id FROM rendez_vous WHERE vehicule_id = :vid ORDER BY date_rdv DESC LIMIT 1"
                    ),
                    {"vid": vid},
                ).fetchone()
            if row:
                db.execute(text("UPDATE vehicules SET client_id = :cid WHERE id = :vid"), {"cid": row[0], "vid": vid})
                migrated += 1
        if migrated:
            db.commit()
            print(f"[MIGRATION] {migrated} vehicules associes a leur client")

        cleaned = db.execute(text("""
            DELETE FROM rendez_vous WHERE type_intervention = 'Enregistrement vehicule' AND statut = 'annule'
            AND vehicule_id IN (SELECT id FROM vehicules WHERE client_id IS NOT NULL)
        """)).rowcount
        if cleaned:
            db.commit()
            print(f"[MIGRATION] {cleaned} faux RDV supprimes")
    except Exception as e:
        print(f"[MIGRATION] Erreur (non bloquante): {e}")
        db.rollback()


def migrate_mecanicien_user_link(db: Session):
    """Migration: ajoute user_id sur mecaniciens pour lier 1 login = 1 mecanicien."""
    try:
        inspector = inspect(db.bind)
        tables = set(inspector.get_table_names())
        if "mecaniciens" not in tables:
            return
        columns = [c["name"] for c in inspector.get_columns("mecaniciens")]
        if "user_id" not in columns:
            db.execute(text("ALTER TABLE mecaniciens ADD COLUMN user_id INTEGER REFERENCES users(id)"))
            db.commit()
            print("[MIGRATION] Colonne user_id ajoutee a mecaniciens")
        db.execute(text("CREATE INDEX IF NOT EXISTS idx_mecaniciens_user_id ON mecaniciens(user_id)"))
        db.commit()
    except Exception as e:
        print(f"[MIGRATION] mecanicien_user_link: {e}")
        db.rollback()


def migrate_atelier_categorie_motos(db: Session):
    """Migration: table atelier_categorie_motos + backfill (toutes catégories actives par défaut)."""
    try:
        inspector = inspect(db.bind)
        tables = set(inspector.get_table_names())
        if "atelier_categorie_motos" not in tables:
            db.execute(text("""
                CREATE TABLE atelier_categorie_motos (
                    id SERIAL PRIMARY KEY,
                    atelier_id INTEGER NOT NULL REFERENCES ateliers(id),
                    categorie_moto_id INTEGER NOT NULL REFERENCES categorie_motos(id),
                    is_active BOOLEAN DEFAULT TRUE,
                    created_at TIMESTAMP DEFAULT NOW(),
                    updated_at TIMESTAMP DEFAULT NOW()
                )
            """))
            db.execute(text("CREATE INDEX IF NOT EXISTS idx_acm_atelier ON atelier_categorie_motos(atelier_id)"))
            db.execute(text("CREATE INDEX IF NOT EXISTS idx_acm_categorie ON atelier_categorie_motos(categorie_moto_id)"))
            db.commit()
            print("[MIGRATION] Table atelier_categorie_motos creee")

        ateliers = db.query(Atelier).filter(Atelier.actif == True).all()
        categories = db.query(CategorieMoto).all()
        for atelier in ateliers:
            for cat in categories:
                existing = db.query(AtelierCategorieMoto).filter(
                    AtelierCategorieMoto.atelier_id == atelier.id,
                    AtelierCategorieMoto.categorie_moto_id == cat.id,
                ).first()
                if not existing:
                    db.add(
                        AtelierCategorieMoto(
                            atelier_id=atelier.id,
                            categorie_moto_id=cat.id,
                            is_active=True,
                        )
                    )
        db.commit()
    except Exception as e:
        db.rollback()
        print(f"[MIGRATION] atelier_categorie_motos: {e}")


def migrate_moto_technical_specs(db: Session):
    """Migration: table des fiches techniques moto détaillées."""
    try:
        inspector = inspect(db.bind)
        tables = set(inspector.get_table_names())
        if "moto_technical_specs" not in tables:
            db.execute(text("""
                CREATE TABLE moto_technical_specs (
                    id SERIAL PRIMARY KEY,
                    modele_moto_id INTEGER NOT NULL REFERENCES modele_motos(id),
                    variante VARCHAR(150),
                    annee_debut INTEGER NOT NULL,
                    annee_fin INTEGER,
                    source VARCHAR(255),
                    general_json TEXT NOT NULL DEFAULT '{}',
                    moteur_json TEXT NOT NULL DEFAULT '{}',
                    pneumatique_json TEXT NOT NULL DEFAULT '{}',
                    freinage_json TEXT NOT NULL DEFAULT '{}',
                    suspension_json TEXT NOT NULL DEFAULT '{}',
                    systemes_electriques_json TEXT NOT NULL DEFAULT '{}',
                    entretien_json TEXT NOT NULL DEFAULT '{}',
                    notes TEXT,
                    created_at TIMESTAMP DEFAULT NOW(),
                    updated_at TIMESTAMP DEFAULT NOW()
                )
            """))
            db.execute(text("CREATE INDEX IF NOT EXISTS idx_moto_specs_modele ON moto_technical_specs(modele_moto_id)"))
            db.execute(text("CREATE INDEX IF NOT EXISTS idx_moto_specs_annee ON moto_technical_specs(annee_debut, annee_fin)"))
            db.commit()
            print("[MIGRATION] Table moto_technical_specs creee")
    except Exception as e:
        db.rollback()
        print(f"[MIGRATION] moto_technical_specs: {e}")


__all__ = [
    "migrate_atelier_categorie_motos",
    "migrate_moto_technical_specs",
    "migrate_demandes_travaux_supp",
    "migrate_mecanicien_user_link",
    "migrate_multitenant_schema",
    "migrate_role_permissions",
    "migrate_user_atelier_roles",
    "migrate_vehicule_client_id",
]
