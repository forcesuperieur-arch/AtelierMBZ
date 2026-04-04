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
import httpx
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
    ConfigAtelier, ForfaitMO, Devis, LigneDevis,
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
    get_vehicule_by_plaque_handler,
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
    FournisseurCreate,
    FournisseurUpdate,
    PieceDetacheeCreate,
    PieceDetacheeUpdate,
)

class LigneCommandeCreate(BaseModel):
    piece_id: int
    quantite_demandee: int
    prix_unitaire_ht: float

class CommandeFournisseurCreate(BaseModel):
    fournisseur_id: int
    lignes: List[LigneCommandeCreate]
    notes: Optional[str] = None

class CommandeFournisseurUpdate(BaseModel):
    statut: Optional[str] = None
    date_prevue_livraison: Optional[str] = None
    notes: Optional[str] = None

class ReceptionLigne(BaseModel):
    ligne_id: int
    quantite_recue: int

class ReceptionCommande(BaseModel):
    lignes: List[ReceptionLigne]

class PieceUtiliseeCreate(BaseModel):
    piece_id: int
    quantite: int
    prix_vente_unitaire: Optional[float] = None


# ========== MODÈLES PYDANTIC POUR FORFAITS MO ==========

class ConfigAtelierUpdate(BaseModel):
    taux_horaire_mo_standard: Optional[float] = None
    taux_horaire_mo_complexe: Optional[float] = None
    taux_horaire_mo_expert: Optional[float] = None
    marge_pieces_standard: Optional[float] = None
    marge_pieces_consommable: Optional[float] = None
    marge_pieces_pneumatique: Optional[float] = None
    forfait_mo_minimum: Optional[float] = None
    tva_mo_taux: Optional[float] = None
    tva_pieces_taux: Optional[float] = None
    validite_devis_jours: Optional[int] = None
    accompte_pourcentage: Optional[float] = None


class ForfaitMOCreate(BaseModel):
    code: str
    nom: str
    description: Optional[str] = None
    categorie: Optional[str] = None
    temps_base_minutes: int
    taux_horaire_applique: Optional[str] = "standard"
    prix_forfait_mo_ht: float
    prix_forfait_mo_ttc: float
    inclut_pieces: Optional[int] = 0
    description_pieces_incluses: Optional[str] = None
    prix_pieces_incluses_ht: Optional[float] = 0.0
    type_vehicule: Optional[str] = "tous"
    cylindree_min: Optional[int] = None
    cylindree_max: Optional[int] = None
    is_active: Optional[int] = 1
    is_promo: Optional[int] = 0
    prix_promo_mo_ttc: Optional[float] = None


class ForfaitMOUpdate(BaseModel):
    code: Optional[str] = None
    nom: Optional[str] = None
    description: Optional[str] = None
    categorie: Optional[str] = None
    temps_base_minutes: Optional[int] = None
    taux_horaire_applique: Optional[str] = None
    prix_forfait_mo_ht: Optional[float] = None
    prix_forfait_mo_ttc: Optional[float] = None
    inclut_pieces: Optional[int] = None
    description_pieces_incluses: Optional[str] = None
    prix_pieces_incluses_ht: Optional[float] = None
    type_vehicule: Optional[str] = None
    cylindree_min: Optional[int] = None
    cylindree_max: Optional[int] = None
    is_active: Optional[int] = None
    is_promo: Optional[int] = None
    prix_promo_mo_ttc: Optional[float] = None


class LigneDevisCreate(BaseModel):
    type_ligne: str  # forfait_mo, piece, main_oeuvre_libre
    forfait_mo_id: Optional[int] = None
    piece_id: Optional[int] = None
    designation: str
    description_detail: Optional[str] = None
    quantite: int = 1
    prix_unitaire_ht: float
    taux_tva: Optional[float] = 20.0


class DevisCreate(BaseModel):
    client_id: int
    vehicule_id: Optional[int] = None
    kilometrage: Optional[int] = None
    notes_client: Optional[str] = None
    notes_internes: Optional[str] = None
    lignes: List[LigneDevisCreate]
    remise_pourcentage: Optional[float] = 0.0


class DevisUpdate(BaseModel):
    statut: Optional[str] = None
    notes_client: Optional[str] = None
    notes_internes: Optional[str] = None


class CalculDevisRequest(BaseModel):
    lignes: List[LigneDevisCreate]
    remise_pourcentage: Optional[float] = 0.0


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

async def fetch_api_plaque_immatriculation(plaque: str) -> dict:
    """Appelle l'API apiplaqueimmatriculation.com pour récupérer les infos d'une plaque"""
    api_key = os.getenv("API_PLAQUE_IMMATRICULATION_KEY")
    if not api_key:
        return None
    
    try:
        async with httpx.AsyncClient() as client:
            response = await client.post(
                f"https://api.apiplaqueimmatriculation.com/plaque?immatriculation={plaque}&token={api_key}&pays=FR",
                headers={"Accept": "application/json"},
                timeout=10.0
            )
            
            if response.status_code == 200:
                data = response.json()
                # Vérifier si la réponse contient des données
                if data and len(data) > 0:
                    vehicule = data[0] if isinstance(data, list) else data
                    # Mapper les données vers notre format
                    return {
                        "plaque": plaque,
                        "marque": vehicule.get("marque", "Inconnu"),
                        "modele": vehicule.get("modele", "Inconnu"),
                        "annee": vehicule.get("annee") or 2020,
                        "cylindree": vehicule.get("cylindree") or f"{vehicule.get('puissance_fiscale', 7)} CV",
                        "type_moto": vehicule.get("carburant", "Essence"),
                        "source": "api-plaque-immatriculation"
                    }
                return None
            elif response.status_code == 404:
                return None  # Véhicule non trouvé
            else:
                print(f"Erreur API Plaque Immatriculation: {response.status_code}")
                return None
    except Exception as e:
        print(f"Exception API Plaque Immatriculation: {e}")
        return None


@app.get("/api/vehicules/{vehicule_id}")
def get_vehicule_by_id(vehicule_id: int, db: Session = Depends(get_db), current_user: User = Depends(get_current_user)):
    """Récupère un véhicule par son ID"""
    vehicule = db.query(Vehicule).filter(Vehicule.id == vehicule_id).first()
    if not vehicule:
        raise HTTPException(status_code=404, detail="Véhicule non trouvé")
    return {
        "id": vehicule.id,
        "plaque": vehicule.plaque,
        "marque": vehicule.marque,
        "modele": vehicule.modele,
        "annee": vehicule.annee,
        "cylindree": vehicule.cylindree,
        "type_moto": vehicule.type_moto
    }


@app.get("/api/vehicule/{plaque}")
async def get_vehicule_by_plaque(plaque: str, db: Session = Depends(get_db)):
    """Récupère les informations d'un véhicule par sa plaque"""
    return await get_vehicule_by_plaque_handler(plaque, db)


@app.post("/api/vehicule")
def create_vehicule_manuel(vehicule_data: dict, db: Session = Depends(get_db)):
    """Crée un véhicule manuellement (pour les plaques non trouvées)"""
    plaque = vehicule_data.get("plaque", "").upper().replace(" ", "").replace("-", "")
    
    # Vérifier si le véhicule existe déjà
    existing = db.query(Vehicule).filter(Vehicule.plaque == plaque).first()
    if existing:
        return {
            "id": existing.id,
            "plaque": existing.plaque,
            "marque": existing.marque,
            "modele": existing.modele,
            "annee": existing.annee,
            "cylindree": existing.cylindree,
            "type_moto": existing.type_moto,
            "source": "database",
            "message": "Véhicule déjà existant"
        }
    
    # Créer le nouveau véhicule
    new_vehicule = Vehicule(
        plaque=plaque,
        marque=vehicule_data.get("marque", "Non spécifié"),
        modele=vehicule_data.get("modele", "Non spécifié"),
        annee=vehicule_data.get("annee"),
        cylindree=vehicule_data.get("cylindree"),
        type_moto=vehicule_data.get("type_moto", "Non spécifié")
    )
    db.add(new_vehicule)
    db.commit()
    db.refresh(new_vehicule)
    
    return {
        "id": new_vehicule.id,
        "plaque": new_vehicule.plaque,
        "marque": new_vehicule.marque,
        "modele": new_vehicule.modele,
        "annee": new_vehicule.annee,
        "cylindree": new_vehicule.cylindree,
        "type_moto": new_vehicule.type_moto,
        "source": "manual",
        "message": "Véhicule créé avec succès"
    }


# ========== RENDEZ-VOUS ==========
from routes.rendez_vous import router as rendez_vous_router
app.include_router(rendez_vous_router)

# ========== GESTION DES PIÈCES DÉTACHÉES ==========
from routes.inventory import router as inventory_router
app.include_router(inventory_router)


# ========== COMMANDES FOURNISSEURS ==========

def generate_numero_commande(db: Session, atelier_id: int) -> str:
    """Génère un numéro de commande unique"""
    import datetime
    today = datetime.datetime.now()
    prefix = f"CMD-{today.strftime('%Y%m%d')}"
    
    # Compter les commandes du jour
    count = db.query(CommandeFournisseur).filter(
        CommandeFournisseur.atelier_id == atelier_id,
        CommandeFournisseur.numero_commande.like(f"{prefix}%")
    ).count()
    
    return f"{prefix}-{count + 1:03d}"


@app.get("/api/commandes")
def get_commandes(
    statut: Optional[str] = None,
    fournisseur_id: Optional[int] = None,
    skip: int = 0,
    limit: int = 100,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """Récupère la liste des commandes fournisseurs"""
    atelier_id = current_user.atelier_id or 1
    query = db.query(CommandeFournisseur).filter(CommandeFournisseur.atelier_id == atelier_id)
    
    if statut:
        query = query.filter(CommandeFournisseur.statut == statut)
    
    if fournisseur_id:
        query = query.filter(CommandeFournisseur.fournisseur_id == fournisseur_id)
    
    commandes = query.order_by(CommandeFournisseur.date_commande.desc()).offset(skip).limit(limit).all()
    
    result = []
    for cmd in commandes:
        result.append({
            "id": cmd.id,
            "numero_commande": cmd.numero_commande,
            "fournisseur": {
                "id": cmd.fournisseur.id,
                "nom": cmd.fournisseur.nom
            },
            "statut": cmd.statut,
            "date_commande": cmd.date_commande.isoformat() if cmd.date_commande else None,
            "date_prevue_livraison": cmd.date_prevue_livraison.isoformat() if cmd.date_prevue_livraison else None,
            "date_reception": cmd.date_reception.isoformat() if cmd.date_reception else None,
            "total_ht": cmd.total_ht,
            "total_ttc": cmd.total_ttc,
            "nb_lignes": len(cmd.lignes),
            "nb_pieces": sum(l.quantite_demandee for l in cmd.lignes)
        })
    
    return result


@app.get("/api/commandes/{commande_id}")
def get_commande(
    commande_id: int,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """Récupère les détails d'une commande"""
    atelier_id = current_user.atelier_id or 1
    cmd = db.query(CommandeFournisseur).filter(
        CommandeFournisseur.id == commande_id,
        CommandeFournisseur.atelier_id == atelier_id
    ).first()
    if not cmd:
        raise HTTPException(status_code=404, detail="Commande non trouvée")
    
    return {
        "id": cmd.id,
        "numero_commande": cmd.numero_commande,
        "fournisseur": {
            "id": cmd.fournisseur.id,
            "nom": cmd.fournisseur.nom,
            "telephone": cmd.fournisseur.telephone,
            "email": cmd.fournisseur.email
        },
        "statut": cmd.statut,
        "date_commande": cmd.date_commande.isoformat() if cmd.date_commande else None,
        "date_prevue_livraison": cmd.date_prevue_livraison.isoformat() if cmd.date_prevue_livraison else None,
        "date_reception": cmd.date_reception.isoformat() if cmd.date_reception else None,
        "total_ht": cmd.total_ht,
        "total_ttc": cmd.total_ttc,
        "notes": cmd.notes,
        "lignes": [{
            "id": l.id,
            "piece": {
                "id": l.piece.id,
                "reference": l.piece.reference,
                "nom": l.piece.nom
            },
            "quantite_demandee": l.quantite_demandee,
            "quantite_recue": l.quantite_recue,
            "prix_unitaire_ht": l.prix_unitaire_ht,
            "total_ligne_ht": l.quantite_demandee * l.prix_unitaire_ht
        } for l in cmd.lignes]
    }


@app.post("/api/commandes")
def create_commande(
    commande: CommandeFournisseurCreate,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """Crée une nouvelle commande fournisseur"""
    from datetime import datetime, timedelta
    atelier_id = current_user.atelier_id or 1
    
    # Vérifier le fournisseur
    fournisseur = db.query(Fournisseur).filter(
        Fournisseur.id == commande.fournisseur_id,
        Fournisseur.atelier_id == atelier_id
    ).first()
    if not fournisseur:
        raise HTTPException(status_code=404, detail="Fournisseur non trouvé")
    
    # Calculer les totaux
    total_ht = 0
    for ligne in commande.lignes:
        total_ht += ligne.quantite_demandee * ligne.prix_unitaire_ht
    
    total_ttc = total_ht * 1.20  # TVA 20%
    
    # Créer la commande
    new_commande = CommandeFournisseur(
        atelier_id=atelier_id,
        numero_commande=generate_numero_commande(db, atelier_id),
        fournisseur_id=commande.fournisseur_id,
        statut="en_attente",
        date_prevue_livraison=datetime.now() + timedelta(days=fournisseur.delai_livraison_jours or 3),
        total_ht=total_ht,
        total_ttc=total_ttc,
        notes=commande.notes
    )
    db.add(new_commande)
    db.flush()  # Pour obtenir l'ID
    
    # Créer les lignes
    for ligne_data in commande.lignes:
        piece = db.query(PieceDetachee).filter(
            PieceDetachee.id == ligne_data.piece_id,
            PieceDetachee.atelier_id == atelier_id
        ).first()
        if not piece:
            raise HTTPException(status_code=404, detail=f"Pièce {ligne_data.piece_id} non trouvée")
        
        ligne = LigneCommandeFournisseur(
            atelier_id=atelier_id,
            commande_id=new_commande.id,
            piece_id=ligne_data.piece_id,
            quantite_demandee=ligne_data.quantite_demandee,
            quantite_recue=0,
            prix_unitaire_ht=ligne_data.prix_unitaire_ht
        )
        db.add(ligne)
    
    db.commit()
    db.refresh(new_commande)
    
    return {"message": "Commande créée", "id": new_commande.id, "numero": new_commande.numero_commande}


@app.put("/api/commandes/{commande_id}")
def update_commande(
    commande_id: int,
    commande_data: CommandeFournisseurUpdate,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """Met à jour une commande (statut, date, notes)"""
    from datetime import datetime
    
    atelier_id = current_user.atelier_id or 1
    cmd = db.query(CommandeFournisseur).filter(
        CommandeFournisseur.id == commande_id,
        CommandeFournisseur.atelier_id == atelier_id
    ).first()
    if not cmd:
        raise HTTPException(status_code=404, detail="Commande non trouvée")
    
    if commande_data.statut:
        cmd.statut = commande_data.statut
        if commande_data.statut == "receptionnee":
            cmd.date_reception = datetime.now()
    
    if commande_data.date_prevue_livraison:
        cmd.date_prevue_livraison = datetime.fromisoformat(commande_data.date_prevue_livraison)
    
    if commande_data.notes is not None:
        cmd.notes = commande_data.notes
    
    db.commit()
    db.refresh(cmd)
    
    return {"message": "Commande mise à jour", "id": cmd.id}


@app.post("/api/commandes/{commande_id}/receptionner")
def receptionner_commande(
    commande_id: int,
    reception: ReceptionCommande,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """Réceptionne une commande et met à jour les stocks"""
    from datetime import datetime
    
    atelier_id = current_user.atelier_id or 1
    cmd = db.query(CommandeFournisseur).filter(
        CommandeFournisseur.id == commande_id,
        CommandeFournisseur.atelier_id == atelier_id
    ).first()
    if not cmd:
        raise HTTPException(status_code=404, detail="Commande non trouvée")
    
    if cmd.statut == "receptionnee":
        raise HTTPException(status_code=400, detail="Cette commande a déjà été réceptionnée")
    
    # Mettre à jour les lignes et les stocks
    for ligne_reception in reception.lignes:
        ligne = db.query(LigneCommandeFournisseur).filter(
            LigneCommandeFournisseur.id == ligne_reception.ligne_id,
            LigneCommandeFournisseur.commande_id == commande_id,
            LigneCommandeFournisseur.atelier_id == atelier_id
        ).first()
        
        if ligne:
            ligne.quantite_recue = ligne_reception.quantite_recue
            
            # Mettre à jour le stock de la pièce
            piece = ligne.piece
            piece.quantite_stock += ligne_reception.quantite_recue
    
    # Mettre à jour le statut de la commande
    cmd.statut = "receptionnee"
    cmd.date_reception = datetime.now()
    
    db.commit()
    
    return {"message": "Commande réceptionnée", "id": cmd.id}


@app.delete("/api/commandes/{commande_id}")
def delete_commande(
    commande_id: int,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """Supprime une commande (uniquement si en_attente)"""
    atelier_id = current_user.atelier_id or 1
    cmd = db.query(CommandeFournisseur).filter(
        CommandeFournisseur.id == commande_id,
        CommandeFournisseur.atelier_id == atelier_id
    ).first()
    if not cmd:
        raise HTTPException(status_code=404, detail="Commande non trouvée")
    
    if cmd.statut != "en_attente":
        raise HTTPException(status_code=400, detail="Impossible de supprimer une commande déjà validée")
    
    db.delete(cmd)
    db.commit()
    
    return {"message": "Commande supprimée"}


# ========== PIÈCES UTILISÉES DANS LES INTERVENTIONS ==========

@app.get("/api/rendez-vous/{rdv_id}/pieces")
def get_pieces_intervention(
    rdv_id: int,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """Récupère les pièces utilisées pour une intervention"""
    atelier_id = current_user.atelier_id or 1
    rdv = db.query(RendezVous).filter(
        RendezVous.id == rdv_id,
        RendezVous.atelier_id == atelier_id
    ).first()
    if not rdv:
        raise HTTPException(status_code=404, detail="Rendez-vous non trouvé")
    
    pieces = []
    total_pieces = 0
    
    for util in rdv.pieces_utilisees:
        pieces.append({
            "id": util.id,
            "piece_id": util.piece_id,
            "reference": util.piece.reference,
            "nom": util.piece.nom,
            "quantite": util.quantite,
            "prix_vente_unitaire": util.prix_vente_unitaire,
            "total_ligne": util.quantite * (util.prix_vente_unitaire or 0)
        })
        total_pieces += util.quantite * (util.prix_vente_unitaire or 0)
    
    return {
        "rendez_vous_id": rdv_id,
        "pieces": pieces,
        "total_pieces": total_pieces,
        "main_oeuvre": rdv.prix_final - total_pieces if rdv.prix_final else rdv.prix_estime
    }


@app.post("/api/rendez-vous/{rdv_id}/pieces")
def add_piece_intervention(
    rdv_id: int,
    piece_data: PieceUtiliseeCreate,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """Ajoute une pièce utilisée pour une intervention"""
    atelier_id = current_user.atelier_id or 1
    rdv = db.query(RendezVous).filter(
        RendezVous.id == rdv_id,
        RendezVous.atelier_id == atelier_id
    ).first()
    if not rdv:
        raise HTTPException(status_code=404, detail="Rendez-vous non trouvé")
    
    piece = db.query(PieceDetachee).filter(
        PieceDetachee.id == piece_data.piece_id,
        PieceDetachee.atelier_id == atelier_id
    ).first()
    if not piece:
        raise HTTPException(status_code=404, detail="Pièce non trouvée")
    
    # Vérifier le stock
    if piece.quantite_stock < piece_data.quantite:
        raise HTTPException(
            status_code=400, 
            detail=f"Stock insuffisant. Disponible: {piece.quantite_stock}, Demandé: {piece_data.quantite}"
        )
    
    # Déterminer le prix de vente
    prix_vente = piece_data.prix_vente_unitaire or piece.prix_vente_ht
    
    # Créer l'utilisation
    utilisation = PieceUtilisee(
        rendez_vous_id=rdv_id,
        piece_id=piece_data.piece_id,
        quantite=piece_data.quantite,
        prix_vente_unitaire=prix_vente
    )
    db.add(utilisation)
    
    # Décrémenter le stock
    piece.quantite_stock -= piece_data.quantite
    
    db.commit()
    db.refresh(utilisation)
    
    return {
        "message": "Pièce ajoutée à l'intervention",
        "id": utilisation.id,
        "stock_restant": piece.quantite_stock
    }


@app.delete("/api/rendez-vous/{rdv_id}/pieces/{utilisation_id}")
def remove_piece_intervention(
    rdv_id: int,
    utilisation_id: int,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """Retire une pièce d'une intervention et remet en stock"""
    atelier_id = current_user.atelier_id or 1
    utilisation = db.query(PieceUtilisee).filter(
        PieceUtilisee.id == utilisation_id,
        PieceUtilisee.rendez_vous_id == rdv_id
    ).first()
    
    if not utilisation:
        raise HTTPException(status_code=404, detail="Utilisation non trouvée")
    if utilisation.rendez_vous.atelier_id != atelier_id:
        raise HTTPException(status_code=404, detail="Utilisation non trouvée")
    
    # Remettre en stock
    piece = utilisation.piece
    piece.quantite_stock += utilisation.quantite
    
    db.delete(utilisation)
    db.commit()
    
    return {"message": "Pièce retirée", "stock_restant": piece.quantite_stock}


@app.get("/api/stats/stock")
def get_stats_stock(
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """Statistiques du stock"""
    from sqlalchemy import func
    
    atelier_id = current_user.atelier_id or 1
    total_pieces = db.query(PieceDetachee).filter(
        PieceDetachee.is_active == 1,
        PieceDetachee.atelier_id == atelier_id
    ).count()
    stock_bas = db.query(PieceDetachee).filter(
        PieceDetachee.is_active == 1,
        PieceDetachee.atelier_id == atelier_id,
        PieceDetachee.quantite_stock <= PieceDetachee.quantite_minimale
    ).count()
    
    valeur_stock = db.query(func.sum(
        PieceDetachee.quantite_stock * PieceDetachee.prix_achat_ht
    )).filter(
        PieceDetachee.is_active == 1,
        PieceDetachee.atelier_id == atelier_id
    ).scalar() or 0
    
    valeur_vente = db.query(func.sum(
        PieceDetachee.quantite_stock * PieceDetachee.prix_vente_ht
    )).filter(
        PieceDetachee.is_active == 1,
        PieceDetachee.atelier_id == atelier_id
    ).scalar() or 0
    
    # Commandes en cours
    commandes_en_cours = db.query(CommandeFournisseur).filter(
        CommandeFournisseur.atelier_id == atelier_id,
        CommandeFournisseur.statut.in_(["en_attente", "validee", "expediee"])
    ).count()
    
    return {
        "total_references": total_pieces,
        "stock_bas": stock_bas,
        "valeur_stock_ht": round(valeur_stock, 2),
        "valeur_vente_ht": round(valeur_vente, 2),
        "marge_potentielle": round(valeur_vente - valeur_stock, 2),
        "commandes_en_cours": commandes_en_cours
    }


# ========== ENDPOINTS CONFIGURATION ATELIER ==========

@app.get("/api/config/atelier")
def get_config_atelier(
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """Récupère la configuration de l'atelier"""
    config = db.query(ConfigAtelier).first()
    if not config:
        # Créer une config par défaut
        config = ConfigAtelier()
        db.add(config)
        db.commit()
        db.refresh(config)
    
    return {
        "taux_horaire_mo_standard": config.taux_horaire_mo_standard,
        "taux_horaire_mo_complexe": config.taux_horaire_mo_complexe,
        "taux_horaire_mo_expert": config.taux_horaire_mo_expert,
        "marge_pieces_standard": config.marge_pieces_standard,
        "marge_pieces_consommable": config.marge_pieces_consommable,
        "marge_pieces_pneumatique": config.marge_pieces_pneumatique,
        "forfait_mo_minimum": config.forfait_mo_minimum,
        "tva_mo_taux": config.tva_mo_taux,
        "tva_pieces_taux": config.tva_pieces_taux,
        "validite_devis_jours": config.validite_devis_jours,
        "accompte_pourcentage": config.accompte_pourcentage,
        "updated_at": config.updated_at.isoformat() if config.updated_at else None
    }


@app.put("/api/config/atelier")
def update_config_atelier(
    config_data: ConfigAtelierUpdate,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """Met à jour la configuration de l'atelier"""
    config = db.query(ConfigAtelier).first()
    if not config:
        config = ConfigAtelier()
        db.add(config)
    
    for field, value in config_data.dict(exclude_unset=True).items():
        setattr(config, field, value)
    
    db.commit()
    db.refresh(config)
    
    return {"message": "Configuration mise à jour", "config": get_config_atelier(db=db, current_user=current_user)}


@app.get("/api/config/taux-mo")
def get_taux_mo(
    db: Session = Depends(get_db)
):
    """Récupère les taux horaires MO (public pour calculs frontend)"""
    config = db.query(ConfigAtelier).first()
    if not config:
        # Valeurs par défaut
        return {
            "standard": 65.0,
            "complexe": 85.0,
            "expert": 95.0,
            "minimum": 25.0
        }
    
    return {
        "standard": config.taux_horaire_mo_standard,
        "complexe": config.taux_horaire_mo_complexe,
        "expert": config.taux_horaire_mo_expert,
        "minimum": config.forfait_mo_minimum
    }


# ========== ENDPOINTS FORFAITS MO ==========

@app.get("/api/forfaits-mo")
def get_forfaits_mo(
    categorie: Optional[str] = None,
    type_vehicule: Optional[str] = None,
    actif_only: bool = True,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """Liste tous les forfaits MO"""
    query = db.query(ForfaitMO)
    
    if actif_only:
        query = query.filter(ForfaitMO.is_active == 1)
    if categorie:
        query = query.filter(ForfaitMO.categorie == categorie)
    if type_vehicule and type_vehicule != "tous":
        query = query.filter(
            (ForfaitMO.type_vehicule == type_vehicule) | (ForfaitMO.type_vehicule == "tous")
        )
    
    forfaits = query.order_by(ForfaitMO.categorie, ForfaitMO.nom).all()
    
    return [{
        "id": f.id,
        "code": f.code,
        "nom": f.nom,
        "description": f.description,
        "categorie": f.categorie,
        "temps_base_minutes": f.temps_base_minutes,
        "temps_formate": f"{f.temps_base_minutes // 60}h{f.temps_base_minutes % 60:02d}",
        "taux_horaire_applique": f.taux_horaire_applique,
        "prix_forfait_mo_ht": f.prix_forfait_mo_ht,
        "prix_forfait_mo_ttc": f.prix_forfait_mo_ttc,
        "prix_affichage": f.prix_promo_mo_ttc if f.is_promo and f.prix_promo_mo_ttc else f.prix_forfait_mo_ttc,
        "inclut_pieces": f.inclut_pieces,
        "description_pieces_incluses": f.description_pieces_incluses,
        "prix_pieces_incluses_ht": f.prix_pieces_incluses_ht,
        "type_vehicule": f.type_vehicule,
        "cylindree_min": f.cylindree_min,
        "cylindree_max": f.cylindree_max,
        "is_active": f.is_active,
        "is_promo": f.is_promo,
        "prix_promo_mo_ttc": f.prix_promo_mo_ttc
    } for f in forfaits]


@app.get("/api/forfaits-mo/{forfait_id}")
def get_forfait_mo(
    forfait_id: int,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """Détail d'un forfait MO"""
    forfait = db.query(ForfaitMO).filter(ForfaitMO.id == forfait_id).first()
    if not forfait:
        raise HTTPException(status_code=404, detail="Forfait non trouvé")
    
    return {
        "id": forfait.id,
        "code": forfait.code,
        "nom": forfait.nom,
        "description": forfait.description,
        "categorie": forfait.categorie,
        "temps_base_minutes": forfait.temps_base_minutes,
        "taux_horaire_applique": forfait.taux_horaire_applique,
        "prix_forfait_mo_ht": forfait.prix_forfait_mo_ht,
        "prix_forfait_mo_ttc": forfait.prix_forfait_mo_ttc,
        "inclut_pieces": forfait.inclut_pieces,
        "description_pieces_incluses": forfait.description_pieces_incluses,
        "prix_pieces_incluses_ht": forfait.prix_pieces_incluses_ht,
        "type_vehicule": forfait.type_vehicule,
        "cylindree_min": forfait.cylindree_min,
        "cylindree_max": forfait.cylindree_max,
        "is_active": forfait.is_active,
        "is_promo": forfait.is_promo,
        "prix_promo_mo_ttc": forfait.prix_promo_mo_ttc,
        "created_at": forfait.created_at.isoformat() if forfait.created_at else None
    }


@app.post("/api/forfaits-mo")
def create_forfait_mo(
    forfait_data: ForfaitMOCreate,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """Crée un nouveau forfait MO"""
    # Vérifier si le code existe déjà
    existing = db.query(ForfaitMO).filter(ForfaitMO.code == forfait_data.code).first()
    if existing:
        raise HTTPException(status_code=400, detail="Ce code forfait existe déjà")
    
    forfait = ForfaitMO(**forfait_data.dict())
    db.add(forfait)
    db.commit()
    db.refresh(forfait)
    
    return {"message": "Forfait créé", "id": forfait.id}


@app.put("/api/forfaits-mo/{forfait_id}")
def update_forfait_mo(
    forfait_id: int,
    forfait_data: ForfaitMOUpdate,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """Met à jour un forfait MO"""
    forfait = db.query(ForfaitMO).filter(ForfaitMO.id == forfait_id).first()
    if not forfait:
        raise HTTPException(status_code=404, detail="Forfait non trouvé")
    
    # Vérifier le code si modifié
    if forfait_data.code and forfait_data.code != forfait.code:
        existing = db.query(ForfaitMO).filter(ForfaitMO.code == forfait_data.code).first()
        if existing:
            raise HTTPException(status_code=400, detail="Ce code forfait existe déjà")
    
    for field, value in forfait_data.dict(exclude_unset=True).items():
        setattr(forfait, field, value)
    
    db.commit()
    db.refresh(forfait)
    
    return {"message": "Forfait mis à jour", "forfait": get_forfait_mo(forfait_id, db, current_user)}


@app.delete("/api/forfaits-mo/{forfait_id}")
def delete_forfait_mo(
    forfait_id: int,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """Supprime (désactive) un forfait MO"""
    forfait = db.query(ForfaitMO).filter(ForfaitMO.id == forfait_id).first()
    if not forfait:
        raise HTTPException(status_code=404, detail="Forfait non trouvé")
    
    # Désactiver plutôt que supprimer
    forfait.is_active = 0
    db.commit()
    
    return {"message": "Forfait désactivé"}


@app.get("/api/forfaits-mo/categories/list")
def get_categories_forfaits(
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """Liste les catégories de forfaits disponibles"""
    categories = db.query(ForfaitMO.categorie).distinct().filter(
        ForfaitMO.is_active == 1,
        ForfaitMO.categorie != None
    ).all()
    
    return [c[0] for c in categories if c[0]]


@app.post("/api/forfaits-mo/calcul-prix")
def calculer_prix_forfait(
    temps_minutes: int,
    taux_horaire: str = "standard",
    db: Session = Depends(get_db)
):
    """Calcule le prix d'un forfait selon le temps et le taux horaire"""
    config = db.query(ConfigAtelier).first()
    if not config:
        taux = {"standard": 65.0, "complexe": 85.0, "expert": 95.0}
    else:
        taux = {
            "standard": config.taux_horaire_mo_standard,
            "complexe": config.taux_horaire_mo_complexe,
            "expert": config.taux_horaire_mo_expert
        }
    
    taux_applique = taux.get(taux_horaire, taux["standard"])
    prix_ht = (temps_minutes / 60) * taux_applique
    
    # Appliquer le minimum
    if config and prix_ht < config.forfait_mo_minimum:
        prix_ht = config.forfait_mo_minimum
    elif not config and prix_ht < 25.0:
        prix_ht = 25.0
    
    tva_taux = config.tva_mo_taux if config else 20.0
    prix_ttc = prix_ht * (1 + tva_taux / 100)
    
    return {
        "temps_minutes": temps_minutes,
        "taux_horaire": taux_applique,
        "prix_ht": round(prix_ht, 2),
        "tva_taux": tva_taux,
        "prix_ttc": round(prix_ttc, 2)
    }


# ========== ENDPOINTS DEVIS ==========

@app.get("/api/devis")
def get_devis(
    statut: Optional[str] = None,
    client_id: Optional[int] = None,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """Liste les devis"""
    query = db.query(Devis).options(
        joinedload(Devis.client),
        joinedload(Devis.vehicule)
    )
    
    if statut:
        query = query.filter(Devis.statut == statut)
    if client_id:
        query = query.filter(Devis.client_id == client_id)
    
    devis_list = query.order_by(Devis.date_creation.desc()).all()
    
    return [{
        "id": d.id,
        "numero_devis": d.numero_devis,
        "client": {
            "id": d.client.id,
            "nom": d.client.nom,
            "prenom": d.client.prenom,
            "telephone": d.client.telephone
        },
        "vehicule": {
            "id": d.vehicule.id,
            "marque": d.vehicule.marque,
            "modele": d.vehicule.modele,
            "plaque": d.vehicule.plaque
        } if d.vehicule else None,
        "date_creation": d.date_creation.isoformat() if d.date_creation else None,
        "date_validite": d.date_validite.isoformat() if d.date_validite else None,
        "statut": d.statut,
        "total_ht": d.total_ht,
        "total_ttc": d.total_ttc,
        "nb_lignes": len(d.lignes)
    } for d in devis_list]


@app.get("/api/devis/{devis_id}")
def get_devis_detail(
    devis_id: int,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """Détail d'un devis avec ses lignes"""
    devis = db.query(Devis).options(
        joinedload(Devis.client),
        joinedload(Devis.vehicule),
        joinedload(Devis.lignes).joinedload(LigneDevis.forfait_mo),
        joinedload(Devis.lignes).joinedload(LigneDevis.piece)
    ).filter(Devis.id == devis_id).first()
    
    if not devis:
        raise HTTPException(status_code=404, detail="Devis non trouvé")
    
    lignes = []
    for ligne in sorted(devis.lignes, key=lambda x: x.ordre):
        lignes.append({
            "id": ligne.id,
            "type_ligne": ligne.type_ligne,
            "forfait_mo_id": ligne.forfait_mo_id,
            "forfait_mo_code": ligne.forfait_mo.code if ligne.forfait_mo else None,
            "piece_id": ligne.piece_id,
            "piece_reference": ligne.piece.reference if ligne.piece else None,
            "designation": ligne.designation,
            "description_detail": ligne.description_detail,
            "quantite": ligne.quantite,
            "prix_unitaire_ht": ligne.prix_unitaire_ht,
            "taux_tva": ligne.taux_tva,
            "total_ligne_ht": ligne.total_ligne_ht,
            "total_ligne_ttc": ligne.total_ligne_ttc
        })
    
    return {
        "id": devis.id,
        "numero_devis": devis.numero_devis,
        "client": {
            "id": devis.client.id,
            "nom": devis.client.nom,
            "prenom": devis.client.prenom,
            "telephone": devis.client.telephone,
            "email": devis.client.email,
            "adresse": devis.client.adresse
        },
        "vehicule": {
            "id": devis.vehicule.id,
            "marque": devis.vehicule.marque,
            "modele": devis.vehicule.modele,
            "plaque": devis.vehicule.plaque,
            "annee": devis.vehicule.annee,
            "cylindree": devis.vehicule.cylindree
        } if devis.vehicule else None,
        "date_creation": devis.date_creation.isoformat() if devis.date_creation else None,
        "date_validite": devis.date_validite.isoformat() if devis.date_validite else None,
        "statut": devis.statut,
        "kilometrage": devis.kilometrage,
        "total_mo_ht": devis.total_mo_ht,
        "total_pieces_ht": devis.total_pieces_ht,
        "total_ht": devis.total_ht,
        "total_ttc": devis.total_ttc,
        "remise_pourcentage": devis.remise_pourcentage,
        "remise_montant": devis.remise_montant,
        "acompte_demande": devis.acompte_demande,
        "notes_client": devis.notes_client,
        "notes_internes": devis.notes_internes,
        "rendez_vous_id": devis.rendez_vous_id,
        "lignes": lignes
    }


def generer_numero_devis(db: Session) -> str:
    """Génère un numéro de devis unique"""
    from datetime import datetime
    import random
    
    annee = datetime.now().year
    
    # Compter les devis de l'année
    count = db.query(Devis).filter(
        Devis.numero_devis.like(f"DEV-{annee}-%")
    ).count()
    
    numero = f"DEV-{annee}-{count + 1:04d}"
    return numero


@app.post("/api/devis")
def create_devis(
    devis_data: DevisCreate,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """Crée un nouveau devis"""
    # Récupérer la config
    config = db.query(ConfigAtelier).first()
    validite = config.validite_devis_jours if config else 30
    
    # Créer le devis
    date_validite = date.today() + timedelta(days=validite)
    
    devis = Devis(
        numero_devis=generer_numero_devis(db),
        client_id=devis_data.client_id,
        vehicule_id=devis_data.vehicule_id,
        date_validite=date_validite,
        kilometrage=devis_data.kilometrage,
        notes_client=devis_data.notes_client,
        notes_internes=devis_data.notes_internes,
        remise_pourcentage=devis_data.remise_pourcentage
    )
    db.add(devis)
    db.flush()  # Pour obtenir l'ID
    
    # Ajouter les lignes
    total_mo_ht = 0
    total_pieces_ht = 0
    
    for i, ligne_data in enumerate(devis_data.lignes):
        total_ligne_ht = ligne_data.quantite * ligne_data.prix_unitaire_ht
        total_ligne_ttc = total_ligne_ht * (1 + ligne_data.taux_tva / 100)
        
        ligne = LigneDevis(
            devis_id=devis.id,
            type_ligne=ligne_data.type_ligne,
            forfait_mo_id=ligne_data.forfait_mo_id,
            piece_id=ligne_data.piece_id,
            designation=ligne_data.designation,
            description_detail=ligne_data.description_detail,
            quantite=ligne_data.quantite,
            prix_unitaire_ht=ligne_data.prix_unitaire_ht,
            taux_tva=ligne_data.taux_tva,
            total_ligne_ht=total_ligne_ht,
            total_ligne_ttc=total_ligne_ttc,
            ordre=i
        )
        db.add(ligne)
        
        # Calculer les totaux
        if ligne_data.type_ligne == "forfait_mo" or ligne_data.type_ligne == "main_oeuvre_libre":
            total_mo_ht += total_ligne_ht
        else:
            total_pieces_ht += total_ligne_ht
    
    # Calculer les totaux
    total_ht = total_mo_ht + total_pieces_ht
    remise_montant = total_ht * (devis_data.remise_pourcentage / 100)
    total_ht_remise = total_ht - remise_montant
    
    # TVA moyenne (simplifié)
    tva_taux = config.tva_mo_taux if config else 20.0
    total_ttc = total_ht_remise * (1 + tva_taux / 100)
    
    # Acompte
    accompte = total_ttc * (config.accompte_pourcentage / 100) if config else total_ttc * 0.3
    
    devis.total_mo_ht = total_mo_ht
    devis.total_pieces_ht = total_pieces_ht
    devis.total_ht = total_ht_remise
    devis.total_ttc = total_ttc
    devis.remise_montant = remise_montant
    devis.acompte_demande = accompte
    
    db.commit()
    db.refresh(devis)
    
    return {"message": "Devis créé", "id": devis.id, "numero": devis.numero_devis}


@app.post("/api/devis/calculer")
def calculer_devis(
    calcul_data: CalculDevisRequest,
    db: Session = Depends(get_db)
):
    """Calcule les totaux d'un devis sans le sauvegarder"""
    config = db.query(ConfigAtelier).first()
    
    total_mo_ht = 0
    total_pieces_ht = 0
    
    for ligne in calcul_data.lignes:
        total_ligne_ht = ligne.quantite * ligne.prix_unitaire_ht
        if ligne.type_ligne == "forfait_mo" or ligne.type_ligne == "main_oeuvre_libre":
            total_mo_ht += total_ligne_ht
        else:
            total_pieces_ht += total_ligne_ht
    
    total_ht = total_mo_ht + total_pieces_ht
    remise_montant = total_ht * (calcul_data.remise_pourcentage / 100)
    total_ht_remise = total_ht - remise_montant
    
    tva_taux = config.tva_mo_taux if config else 20.0
    total_ttc = total_ht_remise * (1 + tva_taux / 100)
    
    return {
        "total_mo_ht": round(total_mo_ht, 2),
        "total_pieces_ht": round(total_pieces_ht, 2),
        "total_ht": round(total_ht, 2),
        "remise_pourcentage": calcul_data.remise_pourcentage,
        "remise_montant": round(remise_montant, 2),
        "total_ht_remise": round(total_ht_remise, 2),
        "total_ttc": round(total_ttc, 2)
    }


@app.put("/api/devis/{devis_id}")
def update_devis(
    devis_id: int,
    devis_data: DevisUpdate,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """Met à jour un devis (statut, notes)"""
    devis = db.query(Devis).filter(Devis.id == devis_id).first()
    if not devis:
        raise HTTPException(status_code=404, detail="Devis non trouvé")
    
    for field, value in devis_data.dict(exclude_unset=True).items():
        setattr(devis, field, value)
    
    db.commit()
    db.refresh(devis)
    
    return {"message": "Devis mis à jour"}


@app.post("/api/devis/{devis_id}/convertir-rdv")
def convertir_devis_en_rdv(
    devis_id: int,
    date_rdv: date,
    heure_rdv: time,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """Convertit un devis accepté en rendez-vous"""
    devis = db.query(Devis).filter(Devis.id == devis_id).first()
    if not devis:
        raise HTTPException(status_code=404, detail="Devis non trouvé")
    
    if devis.statut != "accepte":
        raise HTTPException(status_code=400, detail="Le devis doit être accepté avant conversion")
    
    # Créer le RDV
    types_intervention = ", ".join([l.designation for l in devis.lignes if l.type_ligne == "forfait_mo"])
    
    rdv = RendezVous(
        client_id=devis.client_id,
        vehicule_id=devis.vehicule_id,
        date_rdv=date_rdv,
        heure_rdv=heure_rdv,
        type_intervention=types_intervention or "Intervention diverses",
        prix_estime=devis.total_ttc,
        statut="confirme",
        commentaire=devis.notes_client,
        kilometrage=devis.kilometrage
    )
    db.add(rdv)
    db.flush()
    
    devis.rendez_vous_id = rdv.id
    devis.statut = "converti"
    
    db.commit()
    
    return {"message": "Devis converti en RDV", "rdv_id": rdv.id}


@app.delete("/api/devis/{devis_id}")
def delete_devis(
    devis_id: int,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """Supprime un devis"""
    devis = db.query(Devis).filter(Devis.id == devis_id).first()
    if not devis:
        raise HTTPException(status_code=404, detail="Devis non trouvé")
    
    if devis.statut == "converti":
        raise HTTPException(status_code=400, detail="Impossible de supprimer un devis déjà converti")
    
    db.delete(devis)
    db.commit()
    
    return {"message": "Devis supprimé"}


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


# ========== MODÈLES PYDANTIC POUR BASE MOTO ==========

class CategorieMotoCreate(BaseModel):
    nom: str
    description: Optional[str] = None


class CategorieMotoResponse(BaseModel):
    id: int
    nom: str
    description: Optional[str]
    nb_modeles: int = 0

    class Config:
        from_attributes = True


class ModeleMotoCreate(BaseModel):
    marque: str
    modele: str
    categorie_id: int
    cylindree_min: Optional[int] = None
    cylindree_max: Optional[int] = None
    annee_debut: Optional[int] = None
    annee_fin: Optional[int] = None


class ModeleMotoResponse(BaseModel):
    id: int
    marque: str
    modele: str
    categorie_id: int
    categorie_nom: str
    cylindree_min: Optional[int]
    cylindree_max: Optional[int]
    cylindree_display: str
    annee_debut: Optional[int]
    annee_fin: Optional[int]
    annees_display: str

    class Config:
        from_attributes = True


class ModeleMotoUpdate(BaseModel):
    marque: Optional[str] = None
    modele: Optional[str] = None
    categorie_id: Optional[int] = None
    cylindree_min: Optional[int] = None
    cylindree_max: Optional[int] = None
    annee_debut: Optional[int] = None
    annee_fin: Optional[int] = None


# ========== ENDPOINTS BASE MOTO (CATÉGORIES ET MODÈLES) ==========

@app.get("/api/motos/categories", response_model=List[CategorieMotoResponse])
def get_categories_moto(
    atelier_slug: Optional[str] = None,
    db: Session = Depends(get_db)
):
    """Liste les catégories de moto (filtrées par atelier si slug fourni)"""
    categories = db.query(CategorieMoto).order_by(CategorieMoto.nom).all()

    # Si un atelier_slug est fourni, ne retourner que les catégories actives
    active_ids = None
    if atelier_slug:
        atelier = db.query(Atelier).filter(
            Atelier.slug == atelier_slug.strip().lower(),
            Atelier.actif == True
        ).first()
        if atelier:
            active_rows = db.query(AtelierCategorieMoto).filter(
                AtelierCategorieMoto.atelier_id == atelier.id,
                AtelierCategorieMoto.is_active == True
            ).all()
            active_ids = {r.categorie_moto_id for r in active_rows}

    result = []
    for cat in categories:
        if active_ids is not None and cat.id not in active_ids:
            continue
        nb_modeles = db.query(ModeleMoto).filter(ModeleMoto.categorie_id == cat.id).count()
        result.append({
            "id": cat.id,
            "nom": cat.nom,
            "description": cat.description,
            "nb_modeles": nb_modeles
        })
    return result


@app.post("/api/motos/categories")
def create_categorie_moto(
    categorie: CategorieMotoCreate,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """Crée une nouvelle catégorie de moto (admin)"""
    # Vérifier si la catégorie existe déjà
    existing = db.query(CategorieMoto).filter(CategorieMoto.nom == categorie.nom).first()
    if existing:
        raise HTTPException(status_code=400, detail="Cette catégorie existe déjà")

    new_categorie = CategorieMoto(**categorie.dict())
    db.add(new_categorie)
    db.commit()
    db.refresh(new_categorie)
    return {"message": "Catégorie créée", "id": new_categorie.id}


@app.get("/api/motos/modeles")
def get_modeles_moto(
    categorie: Optional[int] = None,
    marque: Optional[str] = None,
    search: Optional[str] = None,
    db: Session = Depends(get_db)
):
    """Liste les modèles de moto avec filtres"""
    query = db.query(ModeleMoto).join(CategorieMoto)

    if categorie:
        query = query.filter(ModeleMoto.categorie_id == categorie)

    if marque:
        query = query.filter(ModeleMoto.marque.ilike(f"%{marque}%"))

    if search:
        search_filter = f"%{search}%"
        query = query.filter(
            (ModeleMoto.marque.ilike(search_filter)) |
            (ModeleMoto.modele.ilike(search_filter))
        )

    modeles = query.order_by(ModeleMoto.marque, ModeleMoto.modele).all()

    result = []
    for m in modeles:
        result.append({
            "id": m.id,
            "marque": m.marque,
            "modele": m.modele,
            "categorie_id": m.categorie_id,
            "categorie_nom": m.categorie.nom if m.categorie else None,
            "cylindree_min": m.cylindree_min,
            "cylindree_max": m.cylindree_max,
            "cylindree_display": m.cylindree_display,
            "annee_debut": m.annee_debut,
            "annee_fin": m.annee_fin,
            "annees_display": m.annees_display
        })
    return result


@app.get("/api/motos/modeles/{modele_id}")
def get_modele_moto_detail(modele_id: int, db: Session = Depends(get_db)):
    """Détail d'un modèle de moto"""
    modele = db.query(ModeleMoto).filter(ModeleMoto.id == modele_id).first()
    if not modele:
        raise HTTPException(status_code=404, detail="Modèle non trouvé")

    return {
        "id": modele.id,
        "marque": modele.marque,
        "modele": modele.modele,
        "categorie_id": modele.categorie_id,
        "categorie_nom": modele.categorie.nom if modele.categorie else None,
        "cylindree_min": modele.cylindree_min,
        "cylindree_max": modele.cylindree_max,
        "cylindree_display": modele.cylindree_display,
        "annee_debut": modele.annee_debut,
        "annee_fin": modele.annee_fin,
        "annees_display": modele.annees_display,
        "created_at": modele.created_at.isoformat() if modele.created_at else None
    }


@app.post("/api/motos/modeles")
def create_modele_moto(
    modele: ModeleMotoCreate,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """Crée un nouveau modèle de moto (admin)"""
    # Vérifier que la catégorie existe
    categorie = db.query(CategorieMoto).filter(CategorieMoto.id == modele.categorie_id).first()
    if not categorie:
        raise HTTPException(status_code=404, detail="Catégorie non trouvée")

    # Vérifier si le modèle existe déjà
    existing = db.query(ModeleMoto).filter(
        ModeleMoto.marque == modele.marque,
        ModeleMoto.modele == modele.modele,
        ModeleMoto.categorie_id == modele.categorie_id
    ).first()
    if existing:
        raise HTTPException(status_code=400, detail="Ce modèle existe déjà dans cette catégorie")

    new_modele = ModeleMoto(**modele.dict())
    db.add(new_modele)
    db.commit()
    db.refresh(new_modele)
    return {"message": "Modèle créé", "id": new_modele.id}


@app.put("/api/motos/modeles/{modele_id}")
def update_modele_moto(
    modele_id: int,
    modele_data: ModeleMotoUpdate,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """Met à jour un modèle de moto (admin)"""
    modele = db.query(ModeleMoto).filter(ModeleMoto.id == modele_id).first()
    if not modele:
        raise HTTPException(status_code=404, detail="Modèle non trouvé")

    # Vérifier la catégorie si modifiée
    if modele_data.categorie_id:
        categorie = db.query(CategorieMoto).filter(CategorieMoto.id == modele_data.categorie_id).first()
        if not categorie:
            raise HTTPException(status_code=404, detail="Catégorie non trouvée")

    for field, value in modele_data.dict(exclude_unset=True).items():
        setattr(modele, field, value)

    db.commit()
    db.refresh(modele)
    return {"message": "Modèle mis à jour", "id": modele.id}


@app.delete("/api/motos/modeles/{modele_id}")
def delete_modele_moto(
    modele_id: int,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """Supprime un modèle de moto (admin)"""
    modele = db.query(ModeleMoto).filter(ModeleMoto.id == modele_id).first()
    if not modele:
        raise HTTPException(status_code=404, detail="Modèle non trouvé")

    db.delete(modele)
    db.commit()
    return {"message": "Modèle supprimé"}


@app.get("/api/motos/marques")
def get_marques_moto(db: Session = Depends(get_db)):
    """Liste toutes les marques distinctes"""
    marques = db.query(ModeleMoto.marque).distinct().order_by(ModeleMoto.marque).all()
    return [m[0] for m in marques if m[0]]


@app.get("/api/motos/stats")
def get_stats_moto(db: Session = Depends(get_db)):
    """Statistiques sur la base moto"""
    from sqlalchemy import func

    total_modeles = db.query(ModeleMoto).count()
    total_categories = db.query(CategorieMoto).count()

    # Nombre de modèles par marque
    modeles_par_marque = db.query(
        ModeleMoto.marque,
        func.count(ModeleMoto.id).label("count")
    ).group_by(ModeleMoto.marque).order_by(func.count(ModeleMoto.id).desc()).all()

    # Nombre de modèles par catégorie
    modeles_par_categorie = db.query(
        CategorieMoto.nom,
        func.count(ModeleMoto.id).label("count")
    ).join(ModeleMoto).group_by(CategorieMoto.nom).order_by(func.count(ModeleMoto.id).desc()).all()

    return {
        "total_modeles": total_modeles,
        "total_categories": total_categories,
        "modeles_par_marque": [{"marque": m, "count": c} for m, c in modeles_par_marque],
        "modeles_par_categorie": [{"categorie": c, "count": n} for c, n in modeles_par_categorie]
    }


# ========== TRAVAUX SUPPLEMENTAIRES ==========

class DemandeTravauxSuppCreate(BaseModel):
    description: Optional[str] = ""
    prestations_demandees: Optional[list] = None
    urgence: Optional[str] = "normal"

class DemandeTravauxSuppUpdate(BaseModel):
    statut: str  # approuve ou refuse
    notes_receptionniste: Optional[str] = None
    prix_estime: Optional[float] = None
    temps_estime: Optional[int] = None
    signature: Optional[str] = None

@app.post("/api/rendez-vous/{rdv_id}/travaux-supplementaires")
def creer_demande_travaux_supp(
    rdv_id: int,
    demande: DemandeTravauxSuppCreate,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    atelier_id = current_user.atelier_id or 1
    rdv = db.query(RendezVous).filter(
        RendezVous.id == rdv_id,
        RendezVous.atelier_id == atelier_id
    ).first()
    if not rdv:
        raise HTTPException(status_code=404, detail="Rendez-vous non trouve")
    if rdv.statut != "en_cours":
        raise HTTPException(status_code=400, detail="Le RDV doit etre en cours pour signaler des travaux supplementaires")

    nouvelle_demande = DemandeTravauxSupp(
        rendez_vous_id=rdv_id,
        description=demande.description or "",
        prestations_demandees=json.dumps(demande.prestations_demandees) if demande.prestations_demandees else None,
        urgence=demande.urgence or "normal"
    )
    db.add(nouvelle_demande)
    db.commit()
    db.refresh(nouvelle_demande)
    return {"message": "Demande creee", "id": nouvelle_demande.id}

@app.get("/api/travaux-supplementaires/en-attente")
def get_demandes_en_attente(
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    if not user_has_permission(current_user, db, "travaux_supp.review"):
        raise HTTPException(status_code=403, detail="Acces refuse")
    atelier_id = current_user.atelier_id or 1
    demandes = db.query(DemandeTravauxSupp).filter(
        DemandeTravauxSupp.statut == "en_attente"
    ).all()
    result = []
    for d in demandes:
        rdv = db.query(RendezVous).filter(
            RendezVous.id == d.rendez_vous_id,
            RendezVous.atelier_id == atelier_id
        ).first()
        if not rdv:
            continue
        client = rdv.client if rdv else None
        vehicule = rdv.vehicule if rdv else None
        result.append({
            "id": d.id,
            "rendez_vous_id": d.rendez_vous_id,
            "description": d.description,
            "prestations_demandees": json.loads(d.prestations_demandees) if d.prestations_demandees else [],
            "temps_estime": d.temps_estime,
            "prix_estime": d.prix_estime,
            "urgence": d.urgence,
            "statut": d.statut,
            "created_at": d.created_at.isoformat() if d.created_at else None,
            "client": {"nom": client.nom, "prenom": client.prenom, "telephone": client.telephone} if client else None,
            "vehicule": {"plaque": vehicule.plaque, "marque": vehicule.marque, "modele": vehicule.modele} if vehicule else None,
            "or_numero": "OR-" + str(d.rendez_vous_id).zfill(6)
        })
    return result

@app.put("/api/travaux-supplementaires/{demande_id}")
def traiter_demande_travaux_supp(
    demande_id: int,
    update: DemandeTravauxSuppUpdate,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    demande = db.query(DemandeTravauxSupp).filter(DemandeTravauxSupp.id == demande_id).first()
    if not demande:
        raise HTTPException(status_code=404, detail="Demande non trouvee")

    demande.statut = update.statut
    if update.notes_receptionniste:
        demande.notes_receptionniste = update.notes_receptionniste
    if hasattr(update, 'prix_estime') and update.prix_estime is not None:
        demande.prix_estime = update.prix_estime
    if hasattr(update, 'temps_estime') and update.temps_estime is not None:
        demande.temps_estime = update.temps_estime
    demande.approved_by = current_user.id

    if update.statut == "approuve":
        from datetime import datetime as dt
        demande.approved_at = dt.now()
        # Creer un OR supplementaire
        rdv = db.query(RendezVous).filter(RendezVous.id == demande.rendez_vous_id).first()
        count_or = db.query(OrdreReparation).filter(
            OrdreReparation.rendez_vous_id == demande.rendez_vous_id,
            OrdreReparation.type_or == "supplementaire"
        ).count()
        year = dt.now().year
        travaux_desc = demande.description or ""
        if demande.prestations_demandees:
            try:
                prestas = json.loads(demande.prestations_demandees)
                noms = [p.get("nom", "") for p in prestas if p.get("nom")]
                if noms:
                    travaux_desc = ", ".join(noms) + (". " + travaux_desc if travaux_desc else "")
            except: pass
        or_supp = OrdreReparation(
            rendez_vous_id=demande.rendez_vous_id,
            numero_or="OR-" + str(year) + "-" + str(demande.rendez_vous_id).zfill(3) + "-S" + str(count_or + 1),
            type_or="supplementaire",
            travaux=travaux_desc,
            demande_travaux_supp_id=demande.id,
            signature_client=update.signature
        )
        db.add(or_supp)
        # Mettre a jour le prix/temps estime du RDV
        if rdv and demande.prix_estime:
            rdv.prix_estime = (rdv.prix_estime or 0) + demande.prix_estime
        if rdv and demande.temps_estime:
            rdv.temps_estime = (rdv.temps_estime or 0) + demande.temps_estime

    db.commit()
    return {"message": "Demande " + update.statut, "id": demande_id}

@app.get("/api/rendez-vous/{rdv_id}/travaux-supplementaires")
def get_travaux_supp_rdv(
    rdv_id: int,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    demandes = db.query(DemandeTravauxSupp).filter(
        DemandeTravauxSupp.rendez_vous_id == rdv_id
    ).order_by(DemandeTravauxSupp.created_at.desc()).all()
    return [{
        "id": d.id,
        "description": d.description,
        "temps_estime": d.temps_estime,
        "prix_estime": d.prix_estime,
        "urgence": d.urgence,
        "statut": d.statut,
        "notes_receptionniste": d.notes_receptionniste,
        "created_at": d.created_at.isoformat() if d.created_at else None,
        "approved_at": d.approved_at.isoformat() if d.approved_at else None
    } for d in demandes]

@app.get("/api/rendez-vous/{rdv_id}/ordres-reparation-archives")
def get_ordres_reparation_archives(
    rdv_id: int,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    ordres = db.query(OrdreReparation).filter(
        OrdreReparation.rendez_vous_id == rdv_id
    ).order_by(OrdreReparation.created_at).all()
    return [{
        "id": o.id,
        "numero_or": o.numero_or,
        "type_or": o.type_or,
        "kilometrage": o.kilometrage,
        "etat_vehicule": o.etat_vehicule,
        "travaux": o.travaux,
        "created_at": o.created_at.isoformat() if o.created_at else None
    } for o in ordres]

@app.post("/api/rendez-vous/{rdv_id}/reception")
def reception_vehicule(
    rdv_id: int,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """Endpoint reception: passe le RDV en statut 'reception' et cree l'OR initial"""
    if not user_has_permission(current_user, db, "rdv.edit"):
        raise HTTPException(status_code=403, detail="Permission rdv.edit requise")

    atelier_id = current_user.atelier_id or 1
    rdv = db.query(RendezVous).filter(
        RendezVous.id == rdv_id,
        RendezVous.atelier_id == atelier_id
    ).first()
    if not rdv:
        raise HTTPException(status_code=404, detail="Rendez-vous non trouve")
    if rdv.statut not in {"reserve", "confirme", "reception"}:
        raise HTTPException(status_code=400, detail="Statut incompatible avec la reception du vehicule")

    # Creer l'OR initial archive (idempotent)
    year = rdv.date_rdv.year if rdv.date_rdv else datetime.now().year
    numero_or = f"OR-{year}-{str(rdv_id).zfill(3)}"
    or_initial = db.query(OrdreReparation).filter(
        OrdreReparation.rendez_vous_id == rdv_id,
        OrdreReparation.type_or == "initial"
    ).order_by(OrdreReparation.created_at.desc()).first()
    if not or_initial:
        or_initial = OrdreReparation(
            rendez_vous_id=rdv_id,
            numero_or=numero_or,
            type_or="initial",
        )
        db.add(or_initial)

    or_initial.kilometrage = rdv.kilometrage
    or_initial.etat_vehicule = rdv.etat_vehicule
    or_initial.travaux = rdv.commentaire
    if not or_initial.signature_client:
        raise HTTPException(status_code=400, detail="Signature client obligatoire avant validation de la reception")

    rdv.statut = "reception"
    db.commit()
    return {"message": "Reception validee et OR cree", "id": rdv_id, "statut": "reception"}


if __name__ == "__main__":
    import uvicorn
    uvicorn.run(app, host="0.0.0.0", port=8000)
