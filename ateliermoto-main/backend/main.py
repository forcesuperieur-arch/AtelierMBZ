from contextlib import asynccontextmanager
import logging
import os
import time as time_module

from dotenv import load_dotenv
from fastapi import FastAPI, HTTPException, Request
from fastapi.middleware.cors import CORSMiddleware
from fastapi.responses import HTMLResponse, RedirectResponse
from fastapi.staticfiles import StaticFiles

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

from models import SessionLocal, init_db
from statistiques import router as statistiques_router
from facturation_api import router as facturation_router
from routes.auth_api import _get_role_permissions, router as auth_router, user_has_permission
from routes.tenant_admin import router as tenant_admin_router
from services.pdf_service import generate_ordre_reparation_pdf, generate_facture_pdf
from routes.public_booking import router as public_booking_router
from services.startup_service import run_startup_tasks

@asynccontextmanager
async def lifespan(_: FastAPI):
    init_db()
    db = SessionLocal()
    try:
        run_startup_tasks(db)
        yield
    finally:
        db.close()


app = FastAPI(title="Atelier Moto API Pro", version="2.0.0", lifespan=lifespan)

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


@app.get("/api/health")
def health_check():
    return {
        "status": "ok",
        "service": "atelier-moto-api",
        "version": app.version
    }

# ========== SCHÉMAS DÉPLACÉS ==========
# Les schémas tarifaires/devis historiques sont désormais portés par
# `tarifs_api.py` et `routes.devis`; `main.py` reste centré sur la composition.


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


# Servir les fichiers statiques

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

# ========== INTERVENTIONS / PRESTATIONS PUBLIQUES ==========
# Les endpoints `/api/interventions`, `/api/prestations/public` et
# `/api/config/prestations*` sont désormais centralisés dans
# `routes.prestations_tarifs` et `routes.public_booking`.

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

from routes.prestations_tarifs import router as prestations_tarifs_router
app.include_router(prestations_tarifs_router)
app.include_router(public_booking_router)


# ========== ENDPOINTS CONFIGURATION ATELIER ==========
# Les routes configuration sont désormais centralisées dans `config_api.py`.


# La route publique `/api/config/taux-mo` est désormais gérée par
# `routes.prestations_tarifs`.


# ========== ENDPOINTS DEVIS ==========
from routes.devis import router as devis_router
app.include_router(devis_router)


# ==================== ENDPOINTS PUBLICS / TARIFS ====================
# Les endpoints publics de prise de rendez-vous sont désormais
# centralisés dans `routes.public_booking`.


# ==================== ENDPOINTS GESTION DES TARIFS ====================
# Les endpoints `api/prestations`, `api/grilles-tarifaires` et la synthèse tarifaire
# sont désormais centralisés dans `routes.prestations_tarifs`.


# ========== BASE MOTO / TRAVAUX SUPPLÉMENTAIRES ==========
# Les routes correspondantes sont désormais centralisées dans
# `routes.moto_base` et `routes.travaux_supp`.


if __name__ == "__main__":
    import uvicorn
    uvicorn.run(app, host="0.0.0.0", port=8000)
