from contextlib import asynccontextmanager
import logging
import os
import time as time_module

from dotenv import load_dotenv
from fastapi import FastAPI, Request
from fastapi.middleware.cors import CORSMiddleware

# Charger les variables d'environnement
load_dotenv()

LOG_LEVEL = os.getenv("LOG_LEVEL", "INFO").upper()
logging.basicConfig(
    level=getattr(logging, LOG_LEVEL, logging.INFO),
    format="%(asctime)s %(levelname)s [%(name)s] %(message)s"
)
logger = logging.getLogger("ateliermoto.api")

from models import SessionLocal, init_db
from statistiques import router as statistiques_router
from facturation_api import router as facturation_router
from routes.auth_api import router as auth_router
from routes.frontend_pages import mount_static_files, router as frontend_pages_router
from routes.public_booking import router as public_booking_router
from routes.tenant_admin import router as tenant_admin_router
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
mount_static_files(app)
app.include_router(frontend_pages_router)
# Les routes publiques doivent être montées tôt pour éviter qu'un chemin
# dynamique authentifié comme `/api/prestations/{prestation_id}` ne capture
# `/api/prestations/public`.
app.include_router(public_booking_router)

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


# `main.py` reste volontairement un point de composition léger.

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
