import os
from pathlib import Path

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
