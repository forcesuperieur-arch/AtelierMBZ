from fastapi import FastAPI, HTTPException, Depends, status, Request
from fastapi.middleware.cors import CORSMiddleware
from fastapi.responses import FileResponse, StreamingResponse, RedirectResponse
from pydantic import BaseModel
import json
from typing import Optional, List
from datetime import datetime, date, time, timedelta
from sqlalchemy.orm import Session, joinedload
from sqlalchemy import or_, and_
from sqlalchemy.exc import OperationalError
import os
import time as time_module
import logging
from dotenv import load_dotenv

# Charger les variables d'environnement
load_dotenv()

LOG_LEVEL = os.getenv("LOG_LEVEL", "INFO").upper()
logging.basicConfig(
    level=getattr(logging, LOG_LEVEL, logging.INFO),
    format="%(asctime)s %(levelname)s [%(name)s] %(message)s"
)
logger = logging.getLogger("ateliermoto.api")

COOKIE_SECURE = os.getenv("COOKIE_SECURE", "false").strip().lower() in {"1", "true", "yes", "on"}
COOKIE_SAMESITE = os.getenv("COOKIE_SAMESITE", "lax").strip().lower()
if COOKIE_SAMESITE not in {"lax", "strict", "none"}:
    COOKIE_SAMESITE = "lax"

from models import (
    get_db, init_db, SessionLocal,
    Atelier,
    Client, Vehicule, RendezVous, User,
    Pont, Mecanicien, Absence, PieceDetachee, Fournisseur,
    CommandeFournisseur, LigneCommandeFournisseur, PieceUtilisee,
    ConfigAtelier, ForfaitMO,
    Prestation, GrilleTarifaire, CalculTarif, GrilleTarifs,
    CategorieMoto, ModeleMoto, AtelierCategorieMoto, RapportTechnicien,
    TempsIntervention, HoraireAtelier, PontEquipement,
    DemandeTravauxSupp, OrdreReparation,
    Facture, LigneFacture, Paiement
)
from auth import get_current_user, create_default_users
from seed import init_intervention_types, init_base_moto, init_prestations
from seed_parametres import init_parametres
from statistiques import router as statistiques_router
from facturation_api import router as facturation_router
from routes.auth_api import _get_role_permissions, router as auth_router, user_has_permission
from routes.tenant_admin import router as tenant_admin_router
from services.pdf_service import generate_ordre_reparation_pdf, generate_facture_pdf
from routes.public_booking import (
    create_rendez_vous_public_handler,
    get_creneaux_avec_ponts_handler,
    get_creneaux_disponibles_handler,
    get_delais_intervention_handler,
    get_prestations_public_handler,
)

app = FastAPI(title="Atelier Moto API Pro", version="2.0.0")

# Inclusion du router statistiques
app.include_router(statistiques_router)

# Inclusion du router facturation
app.include_router(facturation_router)

# Import et inclusion du router tarifs
from tarifs_api import router as tarifs_router
app.include_router(tarifs_router)

# Import et inclusion du router configuration
from config_api import router as config_router
app.include_router(config_router)
app.include_router(auth_router)
app.include_router(tenant_admin_router)

# Configuration CORS sécurisée depuis les variables d'environnement
cors_origins_str = os.getenv("CORS_ORIGINS", "http://localhost:3000")
CORS_ORIGINS = [origin.strip() for origin in cors_origins_str.split(",")]

app.add_middleware(
    CORSMiddleware,
    allow_origins=CORS_ORIGINS,
    allow_credentials=True,
    allow_methods=["GET", "POST", "PUT", "DELETE", "OPTIONS"],
    allow_headers=["Authorization", "Content-Type", "Accept"],
)


@app.middleware("http")
async def log_requests(request: Request, call_next):
    start = time_module.perf_counter()
    try:
        response = await call_next(request)
    except Exception:
        duration_ms = (time_module.perf_counter() - start) * 1000
        logger.exception(
            "HTTP %s %s failed in %.2fms",
            request.method,
            request.url.path,
            duration_ms
        )
        raise

    duration_ms = (time_module.perf_counter() - start) * 1000
    logger.info(
        "HTTP %s %s -> %s in %.2fms",
        request.method,
        request.url.path,
        response.status_code,
        duration_ms
    )
    return response


def _resolve_target_atelier_id_for_config(
    db: Session,
    current_user: User,
    atelier_id: Optional[int] = None
) -> int:
    """Resolve atelier scope for config endpoints.
    - super_admin can target any existing atelier via query param
    - other roles are restricted to their own atelier
    """
    if current_user.role == "super_admin" and atelier_id is not None:
        target = db.query(Atelier.id).filter(Atelier.id == atelier_id).first()
        if not target:
            raise HTTPException(status_code=404, detail="Atelier non trouvé")
        return int(atelier_id)
    return int(getattr(current_user, "atelier_id", None) or 1)


@app.get("/api/health")
def health_check():
    return {
        "status": "ok",
        "service": "atelier-moto-api",
        "version": app.version
    }

# ========== MODÈLES PYDANTIC POUR TARIFICATION ==========

class GrilleTarifCreate(BaseModel):
    categorie_moto_id: int
    type_intervention: str
    nom: str
    description: Optional[str] = None
    temps_minutes: int
    prix_mo_ht: float
    prix_mo_ttc: float
    pieces_incluses: bool = False

class GrilleTarifResponse(BaseModel):
    id: int
    categorie_moto_id: int
    type_intervention: str
    nom: str
    description: Optional[str]
    temps_minutes: int
    prix_mo_ht: float
    prix_mo_ttc: float
    pieces_incluses: bool
    actif: bool

class CalculDevisRequest(BaseModel):
    vehicule_id: int
    prestations: List[dict]  # [{"type_intervention": "vidange"}, {"type_intervention": "freins_avant"}]

class CalculDevisResponse(BaseModel):
    vehicule_id: int
    prestations: List[dict]
    temps_total_minutes: int
    temps_total_heures: float
    prix_mo_total_ht: float
    prix_mo_total_ttc: float
    total_ht: float
    total_ttc: float

class CreneauAvecDuree(BaseModel):
    date: str
    heure: str
    disponible: bool
    duree_requise_minutes: int
    creneaux_suivants_disponibles: bool

class PlanningRdvResponse(BaseModel):
    id: int
    date_rdv: date
    heure_rdv: time
    type_intervention: str
    statut: str
    prix_estime: Optional[float]
    temps_estime: Optional[int]
    pont_id: Optional[int]
    mecanicien_id: Optional[int]
    client: dict
    vehicule: dict
    pont: Optional[dict] = None
    mecanicien: Optional[dict] = None


# ========== MODÈLES PYDANTIC POUR PIÈCES ==========
from schemas.inventory import (
    CommandeFournisseurCreate,
    CommandeFournisseurUpdate,
    FournisseurCreate,
    FournisseurUpdate,
    PieceDetacheeCreate,
    PieceDetacheeUpdate,
    PieceUtiliseeCreate,
    ReceptionCommande,
)


# ========== MODÈLES PYDANTIC POUR FORFAITS MO ==========

from schemas.forfaits_mo import ForfaitMOCreate, ForfaitMOUpdate


# Les schémas et routes devis sont désormais centralisés dans `routes.devis`.


# Initialisation au démarrage
@app.on_event("startup")
def startup_event():
    init_db()
    db = SessionLocal()
    try:
        migrate_vehicule_client_id(db)
        migrate_demandes_travaux_supp(db)
        create_default_users(db)
        migrate_multitenant_schema(db)
        migrate_user_atelier_roles(db)
        migrate_role_permissions(db)
        migrate_mecanicien_user_link(db)
        migrate_atelier_categorie_motos(db)
        init_intervention_types(db)
        init_base_moto(db)
        init_prestations(db)
        init_parametres(db)
        logger.info("Startup initialization completed")
    finally:
        db.close()

def migrate_demandes_travaux_supp(db):
    """Migration: ajouter colonnes prestations_demandees, decision_client"""
    from sqlalchemy import inspect, text
    try:
        inspector = inspect(db.bind)
        columns = [c['name'] for c in inspector.get_columns('demandes_travaux_supp')]
        for col, typ in [("prestations_demandees", "TEXT"), ("decision_client", "VARCHAR(50)"), ("decision_client_at", "TIMESTAMP")]:
            if col not in columns:
                db.execute(text(f"ALTER TABLE demandes_travaux_supp ADD COLUMN {col} {typ}"))
                db.commit()
                print(f"[MIGRATION] Colonne {col} ajoutee a demandes_travaux_supp")
    except Exception as e:
        db.rollback()
        print(f"[MIGRATION] demandes_travaux_supp: {e}")

def migrate_multitenant_schema(db):
    """Migration Lot 1 v2: table ateliers + colonnes atelier_id + backfill atelier #1."""
    from sqlalchemy import inspect, text

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

def migrate_user_atelier_roles(db):
    """Migration Lot 4 v2: table user_atelier_roles + backfill depuis users."""
    from sqlalchemy import inspect, text
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
        for u in users:
            aid = u.atelier_id or 1
            existing = db.query(UserAtelierRole).filter(
                UserAtelierRole.user_id == u.id,
                UserAtelierRole.atelier_id == aid
            ).first()
            if not existing:
                db.add(UserAtelierRole(user_id=u.id, atelier_id=aid, role=u.role or "receptionnaire"))
        db.commit()
    except Exception as e:
        db.rollback()
        print(f"[MIGRATION] user_atelier_roles: {e}")


def migrate_role_permissions(db):
    """Migration RBAC: table role_permissions + defaults."""
    from sqlalchemy import inspect, text
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
            "sections": ["dashboard", "rdv", "planning", "ponts", "or", "suivi", "clients", "espace-meca", "admin"],
            "permissions": ["billing.view", "billing.edit", "billing.pay", "billing.pdf", "travaux_supp.review", "users.manage", "ateliers.manage", "roles.manage", "config.manage", "prestations.manage", "equipements.manage", "rdv.select_atelier", "rdv.edit"]
        },
        "admin": {
            "label": "Admin Atelier",
            "description": "Administration atelier courant",
            "sections": ["dashboard", "rdv", "planning", "ponts", "or", "suivi", "clients", "espace-meca", "admin"],
            "permissions": ["billing.view", "billing.edit", "billing.pay", "billing.pdf", "travaux_supp.review", "users.manage", "config.manage", "prestations.manage", "equipements.manage", "rdv.select_atelier", "rdv.edit"]
        },
        "receptionnaire": {
            "label": "Reception",
            "description": "Gestion operationnelle reception",
            "sections": ["dashboard", "rdv", "planning", "ponts", "or", "suivi", "clients", "espace-meca"],
            "permissions": ["billing.view", "billing.edit", "billing.pay", "billing.pdf", "travaux_supp.review", "rdv.select_atelier", "rdv.edit"]
        },
        "service_client": {
            "label": "Service Client (SRC)",
            "description": "Version simple sans facturation",
            "sections": ["dashboard", "rdv", "planning", "ponts", "or", "suivi", "clients", "espace-meca"],
            "permissions": ["travaux_supp.review", "rdv.select_atelier", "rdv.edit"]
        },
        "mecanicien": {
            "label": "Mecanicien",
            "description": "Execution atelier",
            "sections": ["dashboard", "planning", "or", "espace-meca"],
            "permissions": []
        }
    }
    try:
        for role, cfg in defaults.items():
            existing = db.query(RolePermission).filter(RolePermission.role == role).first()
            if not existing:
                db.add(RolePermission(
                    role=role,
                    label=cfg["label"],
                    description=cfg["description"],
                    sections_json=json.dumps(cfg["sections"]),
                    permissions_json=json.dumps(cfg["permissions"]),
                    is_system=1
                ))
        db.commit()
    except Exception as e:
        db.rollback()
        print(f"[MIGRATION] role_permissions seed: {e}")

def migrate_vehicule_client_id(db):
    """Migration: ajouter client_id a vehicules et backfill depuis rendez_vous"""
    from sqlalchemy import inspect, text
    try:
        inspector = inspect(db.bind)
        columns = [c['name'] for c in inspector.get_columns('vehicules')]
        if 'client_id' not in columns:
            db.execute(text("ALTER TABLE vehicules ADD COLUMN client_id INTEGER REFERENCES clients(id)"))
            db.commit()
            print("[MIGRATION] Colonne client_id ajoutee a vehicules")

        # Backfill: vehicules sans client_id
        orphans = db.execute(text("SELECT id FROM vehicules WHERE client_id IS NULL")).fetchall()
        migrated = 0
        for (vid,) in orphans:
            row = db.execute(text(
                "SELECT client_id FROM rendez_vous WHERE vehicule_id = :vid AND statut != 'annule' ORDER BY date_rdv DESC LIMIT 1"
            ), {"vid": vid}).fetchone()
            if not row:
                row = db.execute(text(
                    "SELECT client_id FROM rendez_vous WHERE vehicule_id = :vid ORDER BY date_rdv DESC LIMIT 1"
                ), {"vid": vid}).fetchone()
            if row:
                db.execute(text("UPDATE vehicules SET client_id = :cid WHERE id = :vid"), {"cid": row[0], "vid": vid})
                migrated += 1
        if migrated:
            db.commit()
            print(f"[MIGRATION] {migrated} vehicules associes a leur client")

        # Nettoyer les faux RDV "Enregistrement vehicule"
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


def migrate_mecanicien_user_link(db):
    """Migration: ajoute user_id sur mecaniciens pour lier 1 login = 1 mecanicien."""
    from sqlalchemy import inspect, text
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


def migrate_atelier_categorie_motos(db):
    """Migration: table atelier_categorie_motos + backfill (toutes catégories actives par défaut)."""
    from sqlalchemy import inspect, text
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

        # Backfill: pour chaque atelier × catégorie, créer une entrée active si absente
        ateliers = db.query(Atelier).filter(Atelier.actif == True).all()
        categories = db.query(CategorieMoto).all()
        for atelier in ateliers:
            for cat in categories:
                existing = db.query(AtelierCategorieMoto).filter(
                    AtelierCategorieMoto.atelier_id == atelier.id,
                    AtelierCategorieMoto.categorie_moto_id == cat.id
                ).first()
                if not existing:
                    db.add(AtelierCategorieMoto(
                        atelier_id=atelier.id,
                        categorie_moto_id=cat.id,
                        is_active=True
                    ))
        db.commit()
    except Exception as e:
        db.rollback()
        print(f"[MIGRATION] atelier_categorie_motos: {e}")

# Servir les fichiers statiques
from fastapi.staticfiles import StaticFiles
from fastapi.responses import HTMLResponse
import os

# Déterminer le chemin des fichiers statiques
static_dir = os.getenv("STATIC_DIR")
if static_dir:
    if not os.path.isabs(static_dir):
        static_dir = os.path.join(os.path.dirname(__file__), static_dir)
    static_dir = os.path.abspath(static_dir)
else:
    candidates = [
        os.path.abspath(os.path.join(os.path.dirname(__file__), "../frontend")),
        "/app/static",  # docker-compose mount
        "/app/frontend",
    ]
    static_dir = next((p for p in candidates if os.path.exists(p)), candidates[0])

if os.path.exists(static_dir):
    app.mount("/static", StaticFiles(directory=static_dir), name="static")

@app.get("/", response_class=HTMLResponse)
def serve_frontend():
    try:
        frontend_path = os.path.join(static_dir, "index.html")
        with open(frontend_path, "r") as f:
            return f.read()
    except Exception as e:
        return f"""
        <html>
        <body>
            <h1>Atelier Moto API Pro</h1>
            <p>Version 2.0.0 - Online</p>
            <p>API disponible sur /api/*, documentation sur /docs</p>
        </body>
        </html>
        """

@app.get("/{page_name}.html", response_class=HTMLResponse)
def serve_page(page_name: str):
    allowed_pages = {
        "index", "planning", "admin", "rendez-vous", "dashboard", "factures", "devis",
        "tarifs", "statistiques", "mecaniciens", "mecaniciens-v2", "technicien",
        "clients", "motos", "login"
    }
    if page_name not in allowed_pages:
        raise HTTPException(status_code=404, detail="Page not found")

    # Compatibilité: dashboard unifié dans index SPA
    if page_name == "dashboard":
        return RedirectResponse(url="/", status_code=307)

    frontend_path = os.path.join(static_dir, f"{page_name}.html")
    if not os.path.exists(frontend_path):
        raise HTTPException(status_code=404, detail="Page not found")
    with open(frontend_path, "r") as f:
        return f.read()

@app.get("/planning.html", response_class=HTMLResponse)
def serve_planning():
    try:
        frontend_path = os.path.join(static_dir, "planning.html")
        with open(frontend_path, "r") as f:
            return f.read()
    except Exception as e:
        return f"""
        <html>
        <body>
            <h1>Planning Mécanicien</h1>
            <p>Page non disponible</p>
        </body>
        </html>
        """

@app.get("/admin.html", response_class=HTMLResponse)
def serve_admin():
    """Page admin complète"""
    try:
        frontend_path = os.path.join(static_dir, "admin.html")
        with open(frontend_path, "r") as f:
            return f.read()
    except Exception as e:
        return f"""
        <html>
        <body>
            <h1>Admin</h1>
            <p>Page non disponible</p>
        </body>
        </html>
        """

@app.get("/rendez-vous.html", response_class=HTMLResponse)
def serve_rendez_vous():
    """Page publique de prise de rendez-vous (sans authentification)"""
    try:
        frontend_path = os.path.join(static_dir, "rendez-vous.html")
        with open(frontend_path, "r") as f:
            return f.read()
    except Exception as e:
        return f"""
        <html>
        <body>
            <h1>Prise de Rendez-vous</h1>
            <p>Erreur chargement: {str(e)}</p>
        </body>
        </html>
        """

# ========== CLIENTS ==========
from routes.clients import router as clients_router
app.include_router(clients_router)

# ========== AUTHENTIFICATION / ADMIN / MULTI-ATELIER ==========
# Routes extraites vers `routes/auth_api.py` et `routes/tenant_admin.py`.

# ========== INTERVENTIONS ==========

@app.get("/api/interventions")
def get_interventions(db: Session = Depends(get_db)):
    """Backward-compat: retourne les Prestations au format InterventionType"""
    prestations = db.query(Prestation).filter(Prestation.is_active == 1).order_by(Prestation.categorie, Prestation.nom).all()
    return [{
        "id": p.id,
        "nom": p.nom,
        "description": p.description,
        "prix_base": p.prix_base_ttc or 0,
        "temps_estime": p.temps_estime_minutes or 30
    } for p in prestations]


@app.get("/api/prestations/public")
def get_prestations_public(atelier_slug: Optional[str] = "default", db: Session = Depends(get_db)):
    """Liste les prestations actives avec grille tarifaire par type moto (sans auth)"""
    return get_prestations_public_handler(atelier_slug, db)


@app.get("/api/config/prestations")
def get_config_prestations(
    atelier_id: Optional[int] = None,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """Liste toutes les prestations avec grille complete (admin)"""
    target_atelier_id = _resolve_target_atelier_id_for_config(db, current_user, atelier_id)
    prestations = db.query(Prestation).filter(
        Prestation.atelier_id == target_atelier_id
    ).order_by(Prestation.categorie, Prestation.nom).all()

    # Charger grilles
    grilles = db.query(GrilleTarifaire, CategorieMoto.nom).outerjoin(
        CategorieMoto, GrilleTarifaire.categorie_moto_id == CategorieMoto.id
    ).filter(
        GrilleTarifaire.is_active == 1,
        GrilleTarifaire.categorie_moto_id.isnot(None),
        GrilleTarifaire.atelier_id == target_atelier_id
    ).all()

    grilles_par_presta = {}
    for g, cat_nom in grilles:
        if g.prestation_id not in grilles_par_presta:
            grilles_par_presta[g.prestation_id] = {}
        grilles_par_presta[g.prestation_id][cat_nom] = {
            "id": g.id,
            "categorie_moto_id": g.categorie_moto_id,
            "prix_ht": g.prix_ht,
            "prix_ttc": g.prix_ttc,
            "temps_minutes": g.temps_minutes
        }

    return [{
        "id": p.id,
        "code": p.code,
        "nom": p.nom,
        "description": p.description,
        "categorie": p.categorie,
        "sous_categorie": p.sous_categorie,
        "prix_base_ht": p.prix_base_ht,
        "prix_base_ttc": p.prix_base_ttc,
        "temps_estime_minutes": p.temps_estime_minutes,
        "type_tarif": p.type_tarif,
        "taux_horaire_applique": p.taux_horaire_applique,
        "is_active": p.is_active,
        "is_forfait": p.is_forfait,
        "is_promo": p.is_promo,
        "prix_promo_ttc": p.prix_promo_ttc,
        "grille": grilles_par_presta.get(p.id, {})
    } for p in prestations]


class PrestationCreate(BaseModel):
    code: str
    nom: str
    description: Optional[str] = None
    categorie: str = "entretien"
    sous_categorie: Optional[str] = None
    prix_base_ht: float
    prix_base_ttc: float
    temps_estime_minutes: int = 30
    type_tarif: str = "forfait"
    taux_horaire_applique: str = "standard"
    is_forfait: int = 0


class PrestationUpdate(BaseModel):
    code: Optional[str] = None
    nom: Optional[str] = None
    description: Optional[str] = None
    categorie: Optional[str] = None
    sous_categorie: Optional[str] = None
    prix_base_ht: Optional[float] = None
    prix_base_ttc: Optional[float] = None
    temps_estime_minutes: Optional[int] = None
    type_tarif: Optional[str] = None
    taux_horaire_applique: Optional[str] = None
    is_forfait: Optional[int] = None
    is_active: Optional[int] = None
    is_promo: Optional[int] = None
    prix_promo_ttc: Optional[float] = None


@app.post("/api/config/prestations")
def create_config_prestation(
    data: PrestationCreate,
    atelier_id: Optional[int] = None,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """Creer une nouvelle prestation (admin)"""
    target_atelier_id = _resolve_target_atelier_id_for_config(db, current_user, atelier_id)
    existing = db.query(Prestation).filter(Prestation.code == data.code).first()
    if existing:
        raise HTTPException(status_code=400, detail="Ce code prestation existe deja")
    new_presta = Prestation(**data.dict(), atelier_id=target_atelier_id)
    db.add(new_presta)
    db.commit()
    db.refresh(new_presta)
    return {"id": new_presta.id, "message": "Prestation creee"}


@app.put("/api/config/prestations/{prestation_id}")
def update_config_prestation(
    prestation_id: int,
    data: PrestationUpdate,
    atelier_id: Optional[int] = None,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """Modifier une prestation (admin)"""
    target_atelier_id = _resolve_target_atelier_id_for_config(db, current_user, atelier_id)
    presta = db.query(Prestation).filter(
        Prestation.id == prestation_id,
        Prestation.atelier_id == target_atelier_id
    ).first()
    if not presta:
        raise HTTPException(status_code=404, detail="Prestation non trouvee")
    for field, value in data.dict(exclude_unset=True).items():
        setattr(presta, field, value)
    db.commit()
    return {"message": "Prestation modifiee"}


@app.delete("/api/config/prestations/{prestation_id}")
def delete_config_prestation(
    prestation_id: int,
    atelier_id: Optional[int] = None,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """Desactiver une prestation (admin)"""
    target_atelier_id = _resolve_target_atelier_id_for_config(db, current_user, atelier_id)
    presta = db.query(Prestation).filter(
        Prestation.id == prestation_id,
        Prestation.atelier_id == target_atelier_id
    ).first()
    if not presta:
        raise HTTPException(status_code=404, detail="Prestation non trouvee")
    presta.is_active = 0
    db.commit()
    return {"message": "Prestation desactivee"}


class GrilleEntry(BaseModel):
    categorie_moto_id: int
    prix_ttc: float
    prix_ht: float
    temps_minutes: int


class GrilleBulkUpdate(BaseModel):
    entries: List[GrilleEntry]


@app.put("/api/config/prestations/{prestation_id}/grille")
def update_grille_prestation(
    prestation_id: int,
    data: GrilleBulkUpdate,
    atelier_id: Optional[int] = None,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """Sauver la grille prix par type moto pour une prestation (bulk upsert)"""
    target_atelier_id = _resolve_target_atelier_id_for_config(db, current_user, atelier_id)
    presta = db.query(Prestation).filter(
        Prestation.id == prestation_id,
        Prestation.atelier_id == target_atelier_id
    ).first()
    if not presta:
        raise HTTPException(status_code=404, detail="Prestation non trouvee")

    for entry in data.entries:
        existing = db.query(GrilleTarifaire).filter(
            GrilleTarifaire.prestation_id == prestation_id,
            GrilleTarifaire.categorie_moto_id == entry.categorie_moto_id,
            GrilleTarifaire.atelier_id == target_atelier_id,
            GrilleTarifaire.is_active == 1
        ).first()

        if existing:
            existing.prix_ttc = entry.prix_ttc
            existing.prix_ht = entry.prix_ht
            existing.temps_minutes = entry.temps_minutes
        else:
            new_grille = GrilleTarifaire(
                atelier_id=target_atelier_id,
                prestation_id=prestation_id,
                categorie_moto_id=entry.categorie_moto_id,
                prix_ht=entry.prix_ht,
                prix_ttc=entry.prix_ttc,
                temps_minutes=entry.temps_minutes,
                delai_jours=presta.delai_intervention_jours or 1,
                is_active=1
            )
            db.add(new_grille)

    db.commit()
    return {"message": "Grille tarifaire mise a jour"}

# ========== PONTS / MÉCANICIENS / ABSENCES / PLANNING ==========
from routes.workshop import router as workshop_router
app.include_router(workshop_router)

# ========== VÉHICULES ==========
from routes.vehicles import router as vehicles_router
app.include_router(vehicles_router)


# ========== RENDEZ-VOUS ==========
from routes.rendez_vous import router as rendez_vous_router
app.include_router(rendez_vous_router)

# ========== GESTION DES PIÈCES DÉTACHÉES ==========
from routes.inventory import router as inventory_router
app.include_router(inventory_router)
from routes.forfaits_mo import router as forfaits_mo_router
app.include_router(forfaits_mo_router)
from routes.moto_base import router as moto_base_router
app.include_router(moto_base_router)
from routes.travaux_supp import router as travaux_supp_router
app.include_router(travaux_supp_router)


# ========== INVENTAIRE / COMMANDES / STOCK ==========
# Les routes pièces, fournisseurs, commandes et statistiques de stock
# sont désormais centralisées dans `routes.inventory`.


# ========== ENDPOINTS CONFIGURATION ATELIER ==========
# Les routes configuration sont désormais centralisées dans `config_api.py`.


@app.get("/api/config/taux-mo")
def get_taux_mo(
    db: Session = Depends(get_db)
):
    """Récupère les taux horaires MO (public pour calculs frontend)."""
    config = db.query(ConfigAtelier).first()
    if not config:
        return {
            "standard": 65.0,
            "complexe": 85.0,
            "expert": 95.0,
            "minimum": 25.0,
        }

    return {
        "standard": config.taux_horaire_mo_standard,
        "complexe": config.taux_horaire_mo_complexe,
        "expert": config.taux_horaire_mo_expert,
        "minimum": config.forfait_mo_minimum,
    }


# ========== ENDPOINTS DEVIS ==========
from routes.devis import router as devis_router
app.include_router(devis_router)


# ==================== ENDPOINTS PUBLICS (sans auth) ====================

class RendezVousPublicCreate(BaseModel):
    client: dict
    vehicule: dict
    prestations: List[int]
    date_heure: str
    montant_estime: float
    commentaires: Optional[str] = None
    pont_id: Optional[int] = None
    atelier_slug: Optional[str] = None

@app.post("/api/rendez-vous/public")
def create_rendez_vous_public(
    rdv_data: RendezVousPublicCreate,
    db: Session = Depends(get_db)
):
    """Crée un rendez-vous depuis l'interface publique (sans authentification)"""
    return create_rendez_vous_public_handler(rdv_data, db)


@app.get("/api/creneaux/disponibles")
def get_creneaux_disponibles(
    date_str: str,
    duree_minutes: int = 60,
    atelier_slug: Optional[str] = "default",
    db: Session = Depends(get_db)
):
    """Récupère les créneaux disponibles pour une date donnée avec gestion des absences"""
    return get_creneaux_disponibles_handler(date_str, duree_minutes, atelier_slug, db)


@app.get("/api/creneaux/avec-ponts")
def get_creneaux_avec_ponts(
    date_str: str,
    duree_minutes: int = 60,
    atelier_slug: Optional[str] = "default",
    db: Session = Depends(get_db)
):
    """Récupère les créneaux disponibles avec les ponts spécifiques libres, en tenant compte de la durée des RDV"""
    return get_creneaux_avec_ponts_handler(date_str, duree_minutes, atelier_slug, db)


# ==================== ENDPOINTS GESTION DES TARIFS ====================

# Modèles Pydantic pour les tarifs
class PrestationCreate(BaseModel):
    code: str
    nom: str
    description: Optional[str] = None
    categorie: str = "entretien"
    sous_categorie: Optional[str] = None
    prix_base_ht: float = 0.0
    prix_base_ttc: float = 0.0
    temps_estime_minutes: int = 30
    delai_intervention_jours: int = 1
    type_tarif: str = "forfait"
    taux_horaire_applique: str = "standard"
    type_vehicule: str = "tous"
    cylindree_min: Optional[int] = None
    cylindree_max: Optional[int] = None
    is_forfait: int = 0
    inclut_pieces: int = 0
    description_pieces_incluses: Optional[str] = None
    cout_pieces_incluses_ht: float = 0.0
    marge_pieces_pourcent: float = 30.0


class PrestationUpdate(BaseModel):
    code: Optional[str] = None
    nom: Optional[str] = None
    description: Optional[str] = None
    categorie: Optional[str] = None
    sous_categorie: Optional[str] = None
    prix_base_ht: Optional[float] = None
    prix_base_ttc: Optional[float] = None
    temps_estime_minutes: Optional[int] = None
    delai_intervention_jours: Optional[int] = None
    type_tarif: Optional[str] = None
    taux_horaire_applique: Optional[str] = None
    type_vehicule: Optional[str] = None
    cylindree_min: Optional[int] = None
    cylindree_max: Optional[int] = None
    is_active: Optional[int] = None
    is_forfait: Optional[int] = None
    is_promo: Optional[int] = None
    prix_promo_ttc: Optional[float] = None
    inclut_pieces: Optional[int] = None
    description_pieces_incluses: Optional[str] = None
    cout_pieces_incluses_ht: Optional[float] = None
    marge_pieces_pourcent: Optional[float] = None


class GrilleTarifaireCreate(BaseModel):
    prestation_id: int
    type_vehicule: str = "tous"
    cylindree_min: Optional[int] = None
    cylindree_max: Optional[int] = None
    prix_ht: float
    prix_ttc: float
    temps_minutes: int
    delai_jours: int = 1


class CalculTarifRequest(BaseModel):
    prestation_ids: List[int]
    piece_ids: Optional[List[int]] = []
    cylindree: Optional[int] = None
    type_vehicule: Optional[str] = "moto"


class CalculDetailleRequest(BaseModel):
    """Requête pour calcul détaillé MO + pièces + marge"""
    prestations: List[dict]  # [{"prestation_id": 1, "quantite": 1}]
    pieces: List[dict]  # [{"piece_id": 1, "quantite": 2, "prix_achat_ht": 50.0}]
    marge_pieces_pourcent: Optional[float] = 30.0
    remise_pourcent: Optional[float] = 0.0


# ========== ENDPOINTS PRESTATIONS ==========

@app.get("/api/prestations")
def get_prestations(
    categorie: Optional[str] = None,
    type_vehicule: Optional[str] = None,
    actif_only: bool = True,
    search: Optional[str] = None,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """Liste toutes les prestations avec filtres"""
    query = db.query(Prestation)
    
    if actif_only:
        query = query.filter(Prestation.is_active == 1)
    if categorie:
        query = query.filter(Prestation.categorie == categorie)
    if type_vehicule and type_vehicule != "tous":
        query = query.filter(
            (Prestation.type_vehicule == type_vehicule) | (Prestation.type_vehicule == "tous")
        )
    if search:
        search_filter = f"%{search}%"
        query = query.filter(
            (Prestation.nom.ilike(search_filter)) |
            (Prestation.code.ilike(search_filter)) |
            (Prestation.description.ilike(search_filter))
        )
    
    prestations = query.order_by(Prestation.categorie, Prestation.nom).all()
    
    return [{
        "id": p.id,
        "code": p.code,
        "nom": p.nom,
        "description": p.description,
        "categorie": p.categorie,
        "sous_categorie": p.sous_categorie,
        "prix_base_ht": p.prix_base_ht,
        "prix_base_ttc": p.prix_base_ttc,
        "prix_affichage": p.prix_promo_ttc if p.is_promo and p.prix_promo_ttc else p.prix_base_ttc,
        "temps_estime_minutes": p.temps_estime_minutes,
        "temps_formate": f"{p.temps_estime_minutes // 60}h{p.temps_estime_minutes % 60:02d}",
        "delai_intervention_jours": p.delai_intervention_jours,
        "type_tarif": p.type_tarif,
        "taux_horaire_applique": p.taux_horaire_applique,
        "type_vehicule": p.type_vehicule,
        "cylindree_min": p.cylindree_min,
        "cylindree_max": p.cylindree_max,
        "is_active": p.is_active,
        "is_forfait": p.is_forfait,
        "is_promo": p.is_promo,
        "prix_promo_ttc": p.prix_promo_ttc,
        "inclut_pieces": p.inclut_pieces,
        "description_pieces_incluses": p.description_pieces_incluses,
        "cout_pieces_incluses_ht": p.cout_pieces_incluses_ht,
        "marge_pieces_pourcent": p.marge_pieces_pourcent
    } for p in prestations]


@app.get("/api/prestations/{prestation_id}")
def get_prestation(
    prestation_id: int,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """Détail d'une prestation"""
    prestation = db.query(Prestation).filter(Prestation.id == prestation_id).first()
    if not prestation:
        raise HTTPException(status_code=404, detail="Prestation non trouvée")
    
    # Récupérer les grilles tarifaires associées
    grilles = db.query(GrilleTarifaire).filter(
        GrilleTarifaire.prestation_id == prestation_id,
        GrilleTarifaire.is_active == 1
    ).all()
    
    return {
        "id": prestation.id,
        "code": prestation.code,
        "nom": prestation.nom,
        "description": prestation.description,
        "categorie": prestation.categorie,
        "sous_categorie": prestation.sous_categorie,
        "prix_base_ht": prestation.prix_base_ht,
        "prix_base_ttc": prestation.prix_base_ttc,
        "temps_estime_minutes": prestation.temps_estime_minutes,
        "delai_intervention_jours": prestation.delai_intervention_jours,
        "type_tarif": prestation.type_tarif,
        "taux_horaire_applique": prestation.taux_horaire_applique,
        "type_vehicule": prestation.type_vehicule,
        "cylindree_min": prestation.cylindree_min,
        "cylindree_max": prestation.cylindree_max,
        "is_active": prestation.is_active,
        "is_forfait": prestation.is_forfait,
        "is_promo": prestation.is_promo,
        "prix_promo_ttc": prestation.prix_promo_ttc,
        "inclut_pieces": prestation.inclut_pieces,
        "description_pieces_incluses": prestation.description_pieces_incluses,
        "cout_pieces_incluses_ht": prestation.cout_pieces_incluses_ht,
        "marge_pieces_pourcent": prestation.marge_pieces_pourcent,
        "created_at": prestation.created_at.isoformat() if prestation.created_at else None,
        "grilles_tarifaires": [{
            "id": g.id,
            "type_vehicule": g.type_vehicule,
            "cylindree_min": g.cylindree_min,
            "cylindree_max": g.cylindree_max,
            "prix_ht": g.prix_ht,
            "prix_ttc": g.prix_ttc,
            "temps_minutes": g.temps_minutes,
            "delai_jours": g.delai_jours
        } for g in grilles]
    }


@app.post("/api/prestations")
def create_prestation(
    prestation_data: PrestationCreate,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """Crée une nouvelle prestation"""
    # Vérifier si le code existe déjà
    existing = db.query(Prestation).filter(Prestation.code == prestation_data.code).first()
    if existing:
        raise HTTPException(status_code=400, detail="Ce code prestation existe déjà")
    
    prestation = Prestation(**prestation_data.dict())
    db.add(prestation)
    db.commit()
    db.refresh(prestation)
    
    return {"message": "Prestation créée", "id": prestation.id}


@app.put("/api/prestations/{prestation_id}")
def update_prestation(
    prestation_id: int,
    prestation_data: PrestationUpdate,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """Met à jour une prestation"""
    prestation = db.query(Prestation).filter(Prestation.id == prestation_id).first()
    if not prestation:
        raise HTTPException(status_code=404, detail="Prestation non trouvée")
    
    # Vérifier le code si modifié
    if prestation_data.code and prestation_data.code != prestation.code:
        existing = db.query(Prestation).filter(Prestation.code == prestation_data.code).first()
        if existing:
            raise HTTPException(status_code=400, detail="Ce code prestation existe déjà")
    
    for field, value in prestation_data.dict(exclude_unset=True).items():
        setattr(prestation, field, value)
    
    db.commit()
    db.refresh(prestation)
    
    return {"message": "Prestation mise à jour", "prestation": get_prestation(prestation_id, db, current_user)}


@app.delete("/api/prestations/{prestation_id}")
def delete_prestation(
    prestation_id: int,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """Désactive une prestation"""
    prestation = db.query(Prestation).filter(Prestation.id == prestation_id).first()
    if not prestation:
        raise HTTPException(status_code=404, detail="Prestation non trouvée")
    
    prestation.is_active = 0
    db.commit()
    
    return {"message": "Prestation désactivée"}


@app.get("/api/prestations/categories/list")
def get_categories_prestations(
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """Liste les catégories de prestations disponibles"""
    categories = db.query(Prestation.categorie).distinct().filter(
        Prestation.is_active == 1,
        Prestation.categorie != None
    ).all()
    
    return [c[0] for c in categories if c[0]]


# ========== ENDPOINTS GRILLES TARIFAIRES ==========

@app.get("/api/grilles-tarifaires")
def get_grilles_tarifaires(
    prestation_id: Optional[int] = None,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """Liste les grilles tarifaires"""
    query = db.query(GrilleTarifaire).filter(GrilleTarifaire.is_active == 1)
    
    if prestation_id:
        query = query.filter(GrilleTarifaire.prestation_id == prestation_id)
    
    grilles = query.all()
    
    return [{
        "id": g.id,
        "prestation_id": g.prestation_id,
        "prestation_nom": g.prestation.nom if g.prestation else None,
        "type_vehicule": g.type_vehicule,
        "cylindree_min": g.cylindree_min,
        "cylindree_max": g.cylindree_max,
        "prix_ht": g.prix_ht,
        "prix_ttc": g.prix_ttc,
        "temps_minutes": g.temps_minutes,
        "delai_jours": g.delai_jours
    } for g in grilles]


@app.post("/api/grilles-tarifaires")
def create_grille_tarifaire(
    grille_data: GrilleTarifaireCreate,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """Crée une nouvelle grille tarifaire"""
    # Vérifier la prestation
    prestation = db.query(Prestation).filter(Prestation.id == grille_data.prestation_id).first()
    if not prestation:
        raise HTTPException(status_code=404, detail="Prestation non trouvée")
    
    grille = GrilleTarifaire(**grille_data.dict())
    db.add(grille)
    db.commit()
    db.refresh(grille)
    
    return {"message": "Grille tarifaire créée", "id": grille.id}


@app.delete("/api/grilles-tarifaires/{grille_id}")
def delete_grille_tarifaire(
    grille_id: int,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """Désactive une grille tarifaire"""
    grille = db.query(GrilleTarifaire).filter(GrilleTarifaire.id == grille_id).first()
    if not grille:
        raise HTTPException(status_code=404, detail="Grille tarifaire non trouvée")
    
    grille.is_active = 0
    db.commit()
    
    return {"message": "Grille tarifaire désactivée"}


# ========== ENDPOINTS CALCULS DE TARIFS ==========

@app.post("/api/tarifs/calculer")
def calculer_tarif(
    calcul_data: CalculTarifRequest,
    db: Session = Depends(get_db)
):
    """Calcule le tarif pour une liste de prestations"""
    config = db.query(ConfigAtelier).first()
    tva_taux = config.tva_mo_taux if config else 20.0
    
    total_ht = 0.0
    total_ttc = 0.0
    total_temps = 0
    delai_max = 1
    details = []
    
    for prestation_id in calcul_data.prestation_ids:
        prestation = db.query(Prestation).filter(Prestation.id == prestation_id).first()
        if not prestation:
            continue
        
        # Chercher une grille tarifaire spécifique si cylindrée fournie
        prix_applique = prestation.prix_base_ttc
        temps_applique = prestation.temps_estime_minutes
        
        if calcul_data.cylindree:
            grille = db.query(GrilleTarifaire).filter(
                GrilleTarifaire.prestation_id == prestation_id,
                GrilleTarifaire.is_active == 1,
                GrilleTarifaire.cylindree_min <= calcul_data.cylindree,
                GrilleTarifaire.cylindree_max >= calcul_data.cylindree
            ).first()
            
            if grille:
                prix_applique = grille.prix_ttc
                temps_applique = grille.temps_minutes
        
        total_ht += prestation.prix_base_ht
        total_ttc += prix_applique
        total_temps += temps_applique
        delai_max = max(delai_max, prestation.delai_intervention_jours)
        
        details.append({
            "prestation_id": prestation.id,
            "nom": prestation.nom,
            "prix_ht": prestation.prix_base_ht,
            "prix_ttc": prix_applique,
            "temps_minutes": temps_applique
        })
    
    return {
        "total_ht": round(total_ht, 2),
        "total_ttc": round(total_ttc, 2),
        "total_temps_minutes": total_temps,
        "total_temps_heures": round(total_temps / 60, 2),
        "delai_intervention_jours": delai_max,
        "details": details
    }


@app.post("/api/tarifs/calcul-detaille")
def calculer_tarif_detaille(
    calcul_data: CalculDetailleRequest,
    db: Session = Depends(get_db)
):
    """Calcule détaillé: MO + pièces + marge"""
    config = db.query(ConfigAtelier).first()
    tva_mo = config.tva_mo_taux if config else 20.0
    tva_pieces = config.tva_pieces_taux if config else 20.0
    
    # Calcul Main d'Œuvre
    total_mo_ht = 0.0
    details_mo = []
    
    for prest_item in calcul_data.prestations:
        prestation = db.query(Prestation).filter(Prestation.id == prest_item["prestation_id"]).first()
        if not prestation:
            continue
        
        quantite = prest_item.get("quantite", 1)
        ligne_ht = prestation.prix_base_ht * quantite
        ligne_ttc = prestation.prix_base_ttc * quantite
        
        total_mo_ht += ligne_ht
        details_mo.append({
            "prestation_id": prestation.id,
            "nom": prestation.nom,
            "quantite": quantite,
            "prix_unitaire_ht": prestation.prix_base_ht,
            "prix_unitaire_ttc": prestation.prix_base_ttc,
            "total_ligne_ht": ligne_ht,
            "total_ligne_ttc": ligne_ttc
        })
    
    # Calcul Pièces avec marge
    total_pieces_ht = 0.0
    total_pieces_achat = 0.0
    details_pieces = []
    marge_pourcent = calcul_data.marge_pieces_pourcent
    
    for piece_item in calcul_data.pieces:
        piece = db.query(PieceDetachee).filter(PieceDetachee.id == piece_item["piece_id"]).first()
        if not piece:
            continue
        
        quantite = piece_item.get("quantite", 1)
        prix_achat = piece_item.get("prix_achat_ht", piece.prix_achat_ht)
        
        # Calcul avec marge
        prix_vente_unitaire = prix_achat * (1 + marge_pourcent / 100)
        ligne_ht = prix_vente_unitaire * quantite
        
        total_pieces_achat += prix_achat * quantite
        total_pieces_ht += ligne_ht
        
        details_pieces.append({
            "piece_id": piece.id,
            "reference": piece.reference,
            "nom": piece.nom,
            "quantite": quantite,
            "prix_achat_unitaire": prix_achat,
            "marge_pourcent": marge_pourcent,
            "prix_vente_unitaire_ht": round(prix_vente_unitaire, 2),
            "total_ligne_ht": round(ligne_ht, 2)
        })
    
    # Totaux
    total_ht = total_mo_ht + total_pieces_ht
    marge_pieces = total_pieces_ht - total_pieces_achat
    
    # Remise
    remise_montant = total_ht * (calcul_data.remise_pourcent / 100)
    total_ht_remise = total_ht - remise_montant
    
    # TVA (simplifié - moyenne)
    total_ttc = total_ht_remise * (1 + tva_mo / 100)
    
    return {
        "main_oeuvre": {
            "total_ht": round(total_mo_ht, 2),
            "details": details_mo
        },
        "pieces": {
            "total_achat_ht": round(total_pieces_achat, 2),
            "total_vente_ht": round(total_pieces_ht, 2),
            "marge_ht": round(marge_pieces, 2),
            "marge_pourcent": marge_pourcent,
            "details": details_pieces
        },
        "totaux": {
            "total_ht": round(total_ht, 2),
            "remise_pourcent": calcul_data.remise_pourcent,
            "remise_montant": round(remise_montant, 2),
            "total_ht_remise": round(total_ht_remise, 2),
            "total_ttc": round(total_ttc, 2)
        }
    }


@app.get("/api/tarifs/forfaits-mo")
def get_forfaits_mo_actifs(
    categorie: Optional[str] = None,
    db: Session = Depends(get_db)
):
    """Récupère les forfaits MO actifs (public)"""
    query = db.query(ForfaitMO).filter(ForfaitMO.is_active == 1)
    
    if categorie:
        query = query.filter(ForfaitMO.categorie == categorie)
    
    forfaits = query.order_by(ForfaitMO.categorie, ForfaitMO.nom).all()
    
    return [{
        "id": f.id,
        "code": f.code,
        "nom": f.nom,
        "description": f.description,
        "categorie": f.categorie,
        "temps_base_minutes": f.temps_base_minutes,
        "prix_forfait_mo_ttc": f.prix_forfait_mo_ttc,
        "prix_affichage": f.prix_promo_mo_ttc if f.is_promo and f.prix_promo_mo_ttc else f.prix_forfait_mo_ttc,
        "inclut_pieces": f.inclut_pieces,
        "type_vehicule": f.type_vehicule,
        "is_promo": f.is_promo,
        "prix_promo_mo_ttc": f.prix_promo_mo_ttc
    } for f in forfaits]


@app.get("/api/tarifs/delais")
def get_delais_intervention(
    prestation_ids: Optional[str] = None,
    db: Session = Depends(get_db)
):
    """Récupère les délais d'intervention pour des prestations"""
    return get_delais_intervention_handler(prestation_ids, db)


# ========== ENDPOINTS SYNTHÈSE TARIFAIRE ==========

@app.get("/api/tarifs/synthese")
def get_synthese_tarifs(
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """Synthèse complète des tarifs et forfaits"""
    from sqlalchemy import func
    
    # Stats prestations
    total_prestations = db.query(Prestation).filter(Prestation.is_active == 1).count()
    prestations_par_categorie = db.query(
        Prestation.categorie,
        func.count(Prestation.id).label("count")
    ).filter(Prestation.is_active == 1).group_by(Prestation.categorie).all()
    
    # Stats forfaits MO
    total_forfaits = db.query(ForfaitMO).filter(ForfaitMO.is_active == 1).count()
    forfaits_promo = db.query(ForfaitMO).filter(
        ForfaitMO.is_active == 1,
        ForfaitMO.is_promo == 1
    ).count()
    
    # Prix moyens
    prix_moyen_prestation = db.query(func.avg(Prestation.prix_base_ttc)).filter(
        Prestation.is_active == 1
    ).scalar() or 0
    
    prix_moyen_forfait = db.query(func.avg(ForfaitMO.prix_forfait_mo_ttc)).filter(
        ForfaitMO.is_active == 1
    ).scalar() or 0
    
    return {
        "prestations": {
            "total_actives": total_prestations,
            "par_categorie": [{"categorie": c, "count": n} for c, n in prestations_par_categorie],
            "prix_moyen_ttc": round(prix_moyen_prestation, 2)
        },
        "forfaits_mo": {
            "total_actifs": total_forfaits,
            "en_promotion": forfaits_promo,
            "prix_moyen_ttc": round(prix_moyen_forfait, 2)
        },
        "taux_horaires": {
            "standard": config.taux_horaire_mo_standard if (config := db.query(ConfigAtelier).first()) else 65.0,
            "complexe": config.taux_horaire_mo_complexe if (config := db.query(ConfigAtelier).first()) else 85.0,
            "expert": config.taux_horaire_mo_expert if (config := db.query(ConfigAtelier).first()) else 95.0
        }
    }


# ========== BASE MOTO / TRAVAUX SUPPLÉMENTAIRES ==========
# Les routes correspondantes sont désormais centralisées dans
# `routes.moto_base` et `routes.travaux_supp`.


if __name__ == "__main__":
    import uvicorn
    uvicorn.run(app, host="0.0.0.0", port=8000)
