import os

os.environ.setdefault("SECRET_KEY", "test-secret-key-for-startup-service")

import pytest


@pytest.mark.unit
def test_run_startup_tasks_executes_runtime_migrations_before_seeders(monkeypatch):
    from services import startup_service

    calls = []

    monkeypatch.setattr(startup_service, "migrate_vehicule_client_id", lambda db: calls.append("migrate_vehicule_client_id"))
    monkeypatch.setattr(startup_service, "migrate_demandes_travaux_supp", lambda db: calls.append("migrate_demandes_travaux_supp"))
    monkeypatch.setattr(startup_service, "migrate_multitenant_schema", lambda db: calls.append("migrate_multitenant_schema"))
    monkeypatch.setattr(startup_service, "create_default_users", lambda db: calls.append("create_default_users"))
    monkeypatch.setattr(startup_service, "migrate_user_atelier_roles", lambda db: calls.append("migrate_user_atelier_roles"))
    monkeypatch.setattr(startup_service, "migrate_role_permissions", lambda db: calls.append("migrate_role_permissions"))
    monkeypatch.setattr(startup_service, "migrate_mecanicien_user_link", lambda db: calls.append("migrate_mecanicien_user_link"))
    monkeypatch.setattr(startup_service, "migrate_atelier_categorie_motos", lambda db: calls.append("migrate_atelier_categorie_motos"))
    monkeypatch.setattr(startup_service, "migrate_moto_technical_specs", lambda db: calls.append("migrate_moto_technical_specs"))
    monkeypatch.setattr(startup_service, "init_intervention_types", lambda db: calls.append("init_intervention_types"))
    monkeypatch.setattr(startup_service, "init_base_moto", lambda db: calls.append("init_base_moto"))
    monkeypatch.setattr(startup_service, "init_moto_technical_specs", lambda db: calls.append("init_moto_technical_specs"))
    monkeypatch.setattr(startup_service, "init_prestations", lambda db: calls.append("init_prestations"))
    monkeypatch.setattr(startup_service, "init_parametres", lambda db: calls.append("init_parametres"))

    startup_service.run_startup_tasks(object())

    assert calls == [
        "migrate_vehicule_client_id",
        "migrate_demandes_travaux_supp",
        "migrate_multitenant_schema",
        "create_default_users",
        "migrate_user_atelier_roles",
        "migrate_role_permissions",
        "migrate_mecanicien_user_link",
        "migrate_atelier_categorie_motos",
        "migrate_moto_technical_specs",
        "init_intervention_types",
        "init_base_moto",
        "init_moto_technical_specs",
        "init_prestations",
        "init_parametres",
    ]
