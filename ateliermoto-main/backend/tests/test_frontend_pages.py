import os
from pathlib import Path

from fastapi.testclient import TestClient

os.environ.setdefault("SECRET_KEY", "test-secret-key-for-frontend-pages")


def test_resolve_static_dir_prefers_explicit_env_path(monkeypatch, tmp_path):
    static_dir = tmp_path / "static"
    static_dir.mkdir()
    monkeypatch.setenv("STATIC_DIR", str(static_dir))

    from routes.frontend_pages import resolve_static_dir

    assert resolve_static_dir().endswith("static")
    assert Path(resolve_static_dir()) == static_dir.resolve()


def test_allowed_pages_keeps_legacy_public_entries():
    from routes.frontend_pages import ALLOWED_PAGES

    assert "login" in ALLOWED_PAGES
    assert "rendez-vous" in ALLOWED_PAGES
    assert "dashboard" in ALLOWED_PAGES


def test_serve_page_uses_tarifs_fallback_for_legacy_rendez_vous(monkeypatch, tmp_path):
    static_dir = tmp_path / "static"
    static_dir.mkdir()
    (static_dir / "tarifs.html").write_text("<html><body>Public RDV</body></html>", encoding="utf-8")

    import routes.frontend_pages as frontend_pages

    monkeypatch.setattr(frontend_pages, "STATIC_DIR", str(static_dir))

    html = frontend_pages.serve_page("rendez-vous")

    assert "Public RDV" in html


def test_public_prestations_endpoint_is_accessible_without_auth():
    from main import app

    with TestClient(app) as client:
        response = client.get("/api/prestations/public", params={"atelier_slug": "default"})

    assert response.status_code == 200
    assert isinstance(response.json(), list)
