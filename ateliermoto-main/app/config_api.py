"""Configuration API router aggregator."""

from fastapi import APIRouter

from config_api_atelier import router as atelier_router
from config_api_categories_logo import router as categories_logo_router
from config_api_ponts import router as ponts_router
from config_api_temps import router as temps_router

router = APIRouter(prefix="/api/config", tags=["Configuration"])
router.include_router(atelier_router)
router.include_router(temps_router)
router.include_router(ponts_router)
router.include_router(categories_logo_router)
