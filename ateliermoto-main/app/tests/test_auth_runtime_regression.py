import json
import os

os.environ.setdefault("SECRET_KEY", "test-secret-key-for-auth-runtime")

from auth import get_password_hash, verify_password


def test_verify_password_uses_real_bcrypt_hash():
    password = "TestPass123"
    hashed = get_password_hash(password)

    assert verify_password(password, hashed) is True
    assert verify_password("WrongPass999", hashed) is False


def test_verify_password_returns_false_for_invalid_hashes():
    assert verify_password("password", "") is False
    assert verify_password("password", "invalid_hash") is False


def test_migrate_role_permissions_backfills_motos_section_for_existing_roles(db_session):
    from models import RolePermission
    from services.runtime_migrations import migrate_role_permissions

    RolePermission.__table__.create(bind=db_session.get_bind(), checkfirst=True)
    migrate_role_permissions(db_session)
    admin_role = db_session.query(RolePermission).filter(RolePermission.role == "admin").first()
    assert admin_role is not None

    admin_role.sections_json = json.dumps(["dashboard", "rdv", "planning", "admin"])
    db_session.commit()

    migrate_role_permissions(db_session)
    db_session.expire_all()

    admin_role = db_session.query(RolePermission).filter(RolePermission.role == "admin").first()
    assert admin_role is not None
    assert "motos" in json.loads(admin_role.sections_json or "[]")


def test_migrate_role_permissions_backfills_new_permissions_and_manager_role(db_session):
    from models import RolePermission
    from services.runtime_migrations import migrate_role_permissions

    RolePermission.__table__.create(bind=db_session.get_bind(), checkfirst=True)
    migrate_role_permissions(db_session)

    admin_role = db_session.query(RolePermission).filter(RolePermission.role == "admin").first()
    assert admin_role is not None
    admin_role.permissions_json = json.dumps(["rdv.edit"])
    db_session.commit()

    migrate_role_permissions(db_session)
    db_session.expire_all()

    admin_role = db_session.query(RolePermission).filter(RolePermission.role == "admin").first()
    manager_role = db_session.query(RolePermission).filter(RolePermission.role == "manager").first()

    assert admin_role is not None
    assert manager_role is not None

    admin_permissions = json.loads(admin_role.permissions_json or "[]")
    manager_permissions = json.loads(manager_role.permissions_json or "[]")
    expected_permissions = {
        "workflow.manage",
        "or.manage",
        "workshop.manage",
        "motos.manage",
        "horaires.manage",
        "clients.edit",
        "stats.view",
    }

    assert expected_permissions.issubset(set(admin_permissions))
    assert expected_permissions.issubset(set(manager_permissions))
