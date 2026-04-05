from pathlib import Path
import os

from fastapi import APIRouter, FastAPI, HTTPException
from fastapi.responses import HTMLResponse, RedirectResponse
from fastapi.staticfiles import StaticFiles

router = APIRouter(tags=["frontend"])

ALLOWED_PAGES = {
    "index",
    "planning",
    "admin",
    "rendez-vous",
    "dashboard",
    "factures",
    "devis",
    "tarifs",
    "statistiques",
    "mecaniciens",
    "mecaniciens-v2",
    "technicien",
    "clients",
    "motos",
    "login",
}


def resolve_static_dir() -> str:
    """Resolve the frontend static directory in local/dev and Docker modes."""
    backend_dir = Path(__file__).resolve().parents[1]
    static_dir = os.getenv("STATIC_DIR")
    if static_dir:
        candidate = Path(static_dir)
        if not candidate.is_absolute():
            candidate = backend_dir / candidate
        return str(candidate.resolve())

    candidates = [
        (backend_dir.parent / "frontend").resolve(),
        Path("/app/static"),
        Path("/app/frontend"),
    ]
    existing = next((path for path in candidates if path.exists()), candidates[0])
    return str(existing)


STATIC_DIR = resolve_static_dir()


def mount_static_files(app: FastAPI) -> None:
    """Mount static files once when the resolved frontend directory exists."""
    already_mounted = any(getattr(route, "name", None) == "static" for route in app.routes)
    if Path(STATIC_DIR).exists() and not already_mounted:
        app.mount("/static", StaticFiles(directory=STATIC_DIR), name="static")


def _read_html_page(filename: str, fallback_html: str) -> str:
    page_path = Path(STATIC_DIR) / filename
    try:
        return page_path.read_text(encoding="utf-8")
    except Exception:
        return fallback_html


@router.get("/api/health")
def health_check():
    return {
        "status": "ok",
        "service": "atelier-moto-api",
        "version": "2.0.0",
    }


@router.get("/", response_class=HTMLResponse)
def serve_frontend():
    return _read_html_page(
        "index.html",
        """
        <html>
        <body>
            <h1>Atelier Moto API Pro</h1>
            <p>Version 2.0.0 - Online</p>
            <p>API disponible sur /api/*, documentation sur /docs</p>
        </body>
        </html>
        """,
    )


@router.get("/{page_name}.html", response_class=HTMLResponse)
def serve_page(page_name: str):
    if page_name not in ALLOWED_PAGES:
        raise HTTPException(status_code=404, detail="Page not found")

    if page_name == "dashboard":
        return RedirectResponse(url="/", status_code=307)

    page_path = Path(STATIC_DIR) / f"{page_name}.html"
    if not page_path.exists():
        raise HTTPException(status_code=404, detail="Page not found")
    return page_path.read_text(encoding="utf-8")


__all__ = ["ALLOWED_PAGES", "STATIC_DIR", "mount_static_files", "resolve_static_dir", "router"]
