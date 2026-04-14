"""Statistiques API router aggregator."""

from fastapi import APIRouter

from statistiques_ca import router as ca_router
from statistiques_dashboard import router as dashboard_router
from statistiques_mecaniciens import router as mecaniciens_router
from statistiques_performance import router as performance_router
from statistiques_ponts_clients import router as ponts_clients_router

router = APIRouter(prefix="/api/statistiques", tags=["statistiques"])
router.include_router(ca_router)
router.include_router(ponts_clients_router)
router.include_router(mecaniciens_router)
router.include_router(performance_router)
router.include_router(dashboard_router)
