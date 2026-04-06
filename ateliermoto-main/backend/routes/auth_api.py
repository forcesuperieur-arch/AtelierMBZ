import json
import logging
import os
from datetime import datetime, timedelta
from functools import wraps
from typing import List, Optional

from fastapi import APIRouter, Depends, HTTPException, Request, Response, status
from fastapi.security import OAuth2PasswordRequestForm
from jose import JWTError
from pydantic import BaseModel
from sqlalchemy.orm import Session

from auth import (
    ACCESS_TOKEN_EXPIRE_MINUTES,
    REFRESH_TOKEN_EXPIRE_DAYS,
    create_access_token,
    create_refresh_token,
    decode_token,
    get_current_user,
    revoke_token,
    verify_password,
)
from models import Atelier, RevokedToken, RolePermission, User, UserAtelierRole, get_db

router = APIRouter(tags=["auth"])
logger = logging.getLogger("ateliermoto.api")

COOKIE_SECURE = os.getenv("COOKIE_SECURE", "false").strip().lower() in {"1", "true", "yes", "on"}
_cookie_samesite = os.getenv("COOKIE_SAMESITE", "lax").strip().lower()
COOKIE_SAMESITE = _cookie_samesite if _cookie_samesite in {"lax", "strict", "none"} else "lax"


class Token(BaseModel):
    access_token: str
    token_type: str
    role: str
    atelier_id: Optional[int] = None


class RefreshTokenPayload(BaseModel):
    refresh_token: Optional[str] = None


class AtelierSwitchRequest(BaseModel):
    atelier_id: int


class RolePermissionPayload(BaseModel):
    role: str
    label: Optional[str] = None
    description: Optional[str] = None
    sections: List[str] = []
    permissions: List[str] = []


def require_role(required_role: str):
    def decorator(func):
        @wraps(func)
        def wrapper(*args, **kwargs):
            current_user = kwargs.get("current_user")
            if current_user is None:
                raise HTTPException(status_code=500, detail="current_user dependency missing")
            if current_user.role == "super_admin":
                return func(*args, **kwargs)
            if current_user.role != required_role:
                raise HTTPException(status_code=403, detail="Accès réservé aux administrateurs")
            return func(*args, **kwargs)
        return wrapper
    return decorator


def _get_role_permissions(db: Session, role: str) -> dict:
    rp = db.query(RolePermission).filter(RolePermission.role == role).first()
    if not rp:
        legacy = {
            "super_admin": {
                "sections": ["dashboard", "rdv", "planning", "ponts", "or", "suivi", "motos", "clients", "espace-meca", "admin"],
                "permissions": ["billing.view", "billing.edit", "billing.pay", "billing.pdf", "travaux_supp.review", "users.manage", "ateliers.manage", "roles.manage", "config.manage", "prestations.manage", "equipements.manage", "rdv.select_atelier", "rdv.edit"]
            },
            "admin": {
                "sections": ["dashboard", "rdv", "planning", "ponts", "or", "suivi", "motos", "clients", "espace-meca", "admin"],
                "permissions": ["billing.view", "billing.edit", "billing.pay", "billing.pdf", "travaux_supp.review", "users.manage", "config.manage", "prestations.manage", "equipements.manage", "rdv.select_atelier", "rdv.edit"]
            },
            "receptionnaire": {
                "sections": ["dashboard", "rdv", "planning", "ponts", "or", "suivi", "motos", "clients", "espace-meca"],
                "permissions": ["billing.view", "billing.edit", "billing.pay", "billing.pdf", "travaux_supp.review", "rdv.select_atelier", "rdv.edit"]
            },
            "service_client": {
                "sections": ["dashboard", "rdv", "planning", "ponts", "or", "suivi", "motos", "clients", "espace-meca"],
                "permissions": ["travaux_supp.review", "rdv.select_atelier", "rdv.edit"]
            },
            "mecanicien": {
                "sections": ["dashboard", "planning", "or", "motos", "espace-meca"],
                "permissions": []
            },
        }
        return legacy.get(role, {"sections": [], "permissions": []})
    try:
        sections = json.loads(rp.sections_json or "[]")
    except Exception:
        sections = []
    try:
        permissions = json.loads(rp.permissions_json or "[]")
    except Exception:
        permissions = []
    return {"sections": sections, "permissions": permissions}


def user_has_permission(current_user: User, db: Session, permission: str) -> bool:
    if current_user.role == "super_admin":
        return True
    cfg = _get_role_permissions(db, current_user.role or "")
    return permission in cfg.get("permissions", [])


def normalize_role_slug(role: Optional[str]) -> str:
    value = (role or "").strip().lower().replace("-", "_").replace(" ", "_")
    alias_map = {
        "serviceclient": "service_client",
        "service_clients": "service_client",
    }
    return alias_map.get(value, value)


def get_allowed_roles(db: Session) -> set[str]:
    system_roles = {"admin", "receptionnaire", "service_client", "mecanicien", "super_admin"}
    db_roles = {r.role for r in db.query(RolePermission.role).all()}
    return system_roles | db_roles


@router.post("/api/auth/login", response_model=Token)
def login(response: Response, request: Request, form_data: OAuth2PasswordRequestForm = Depends(), db: Session = Depends(get_db)):
    if not hasattr(request.app.state, "login_attempts"):
        request.app.state.login_attempts = {}
    ip = request.client.host if request.client else "unknown"
    now = datetime.utcnow()
    attempts = [t for t in request.app.state.login_attempts.get(ip, []) if now - t < timedelta(minutes=1)]
    if len(attempts) >= 5:
        logger.warning("Login rate limited for ip=%s", ip)
        raise HTTPException(status_code=429, detail="Trop de tentatives de connexion. Reessayez dans 1 minute.")

    user = db.query(User).filter(User.username == form_data.username).first()
    if not user or not verify_password(form_data.password, user.hashed_password):
        attempts.append(now)
        request.app.state.login_attempts[ip] = attempts
        logger.warning("Login failed for username=%s ip=%s", form_data.username, ip)
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Incorrect username or password",
            headers={"WWW-Authenticate": "Bearer"},
        )
    request.app.state.login_attempts[ip] = []
    access_token_expires = timedelta(minutes=ACCESS_TOKEN_EXPIRE_MINUTES)
    access_token = create_access_token(
        data={"sub": user.username, "role": user.role, "atelier_id": user.atelier_id}, expires_delta=access_token_expires
    )
    refresh_token = create_refresh_token(
        data={"sub": user.username, "role": user.role, "atelier_id": user.atelier_id},
        expires_delta=timedelta(days=REFRESH_TOKEN_EXPIRE_DAYS)
    )
    response.set_cookie(
        key="access_token",
        value=access_token,
        httponly=True,
        secure=COOKIE_SECURE,
        samesite=COOKIE_SAMESITE,
        max_age=ACCESS_TOKEN_EXPIRE_MINUTES * 60,
        path="/"
    )
    response.set_cookie(
        key="refresh_token",
        value=refresh_token,
        httponly=True,
        secure=COOKIE_SECURE,
        samesite=COOKIE_SAMESITE,
        max_age=REFRESH_TOKEN_EXPIRE_DAYS * 24 * 60 * 60,
        path="/api/auth"
    )
    logger.info("Login success for username=%s role=%s ip=%s", user.username, user.role, ip)
    return {"access_token": access_token, "token_type": "bearer", "role": user.role, "atelier_id": user.atelier_id}


@router.get("/api/auth/me")
def get_me(current_user: User = Depends(get_current_user), db: Session = Depends(get_db)):
    atelier = db.query(Atelier).filter(Atelier.id == current_user.atelier_id).first() if current_user.atelier_id else None
    ateliers = db.query(UserAtelierRole, Atelier).join(
        Atelier, Atelier.id == UserAtelierRole.atelier_id
    ).filter(
        UserAtelierRole.user_id == current_user.id
    ).all()
    return {
        "id": current_user.id,
        "username": current_user.username,
        "email": current_user.email,
        "role": current_user.role,
        "atelier_id": current_user.atelier_id,
        "atelier_slug": atelier.slug if atelier else None,
        "ateliers": [
            {"atelier_id": a.id, "nom": a.nom, "slug": a.slug, "role": ur.role}
            for ur, a in ateliers
        ],
        "permissions": _get_role_permissions(db, current_user.role or "").get("permissions", []),
        "sections": _get_role_permissions(db, current_user.role or "").get("sections", [])
    }


@router.post("/api/auth/switch-atelier")
def switch_atelier(
    payload: AtelierSwitchRequest,
    response: Response,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    rel = db.query(UserAtelierRole).filter(
        UserAtelierRole.user_id == current_user.id,
        UserAtelierRole.atelier_id == payload.atelier_id
    ).first()
    if not rel:
        raise HTTPException(status_code=403, detail="Atelier non autorise pour cet utilisateur")
    current_user.atelier_id = payload.atelier_id
    current_user.role = rel.role
    db.commit()
    access_token = create_access_token(
        data={"sub": current_user.username, "role": current_user.role, "atelier_id": current_user.atelier_id},
        expires_delta=timedelta(minutes=ACCESS_TOKEN_EXPIRE_MINUTES)
    )
    response.set_cookie(
        key="access_token",
        value=access_token,
        httponly=True,
        secure=COOKIE_SECURE,
        samesite=COOKIE_SAMESITE,
        max_age=ACCESS_TOKEN_EXPIRE_MINUTES * 60,
        path="/"
    )
    return {
        "message": "Atelier actif mis a jour",
        "atelier_id": payload.atelier_id,
        "role": rel.role,
        "access_token": access_token,
        "token_type": "bearer",
        "permissions": _get_role_permissions(db, rel.role or "").get("permissions", []),
        "sections": _get_role_permissions(db, rel.role or "").get("sections", [])
    }


@router.post("/api/auth/refresh", response_model=Token)
def refresh_access_token(payload: RefreshTokenPayload, request: Request, response: Response, db: Session = Depends(get_db)):
    refresh_token = payload.refresh_token or request.cookies.get("refresh_token")
    if not refresh_token:
        raise HTTPException(status_code=401, detail="Refresh token manquant")

    try:
        decoded = decode_token(refresh_token)
    except JWTError:
        raise HTTPException(status_code=401, detail="Refresh token invalide")

    if decoded.get("typ") != "refresh":
        raise HTTPException(status_code=401, detail="Type de token invalide")

    jti = decoded.get("jti")
    if not jti:
        raise HTTPException(status_code=401, detail="Refresh token invalide")

    revoked = db.query(RevokedToken).filter(RevokedToken.jti == jti).first()
    if revoked:
        raise HTTPException(status_code=401, detail="Refresh token revoque")

    username = decoded.get("sub")
    if not username:
        raise HTTPException(status_code=401, detail="Refresh token invalide")

    user = db.query(User).filter(User.username == username).first()
    if not user:
        raise HTTPException(status_code=401, detail="Utilisateur introuvable")

    revoke_token(db, refresh_token, reason="refresh_rotation")
    new_refresh_token = create_refresh_token(
        data={"sub": user.username, "role": user.role, "atelier_id": user.atelier_id},
        expires_delta=timedelta(days=REFRESH_TOKEN_EXPIRE_DAYS)
    )
    response.set_cookie(
        key="refresh_token",
        value=new_refresh_token,
        httponly=True,
        secure=COOKIE_SECURE,
        samesite=COOKIE_SAMESITE,
        max_age=REFRESH_TOKEN_EXPIRE_DAYS * 24 * 60 * 60,
        path="/api/auth"
    )

    access_token = create_access_token(
        data={"sub": user.username, "role": user.role, "atelier_id": user.atelier_id},
        expires_delta=timedelta(minutes=ACCESS_TOKEN_EXPIRE_MINUTES)
    )
    response.set_cookie(
        key="access_token",
        value=access_token,
        httponly=True,
        secure=COOKIE_SECURE,
        samesite=COOKIE_SAMESITE,
        max_age=ACCESS_TOKEN_EXPIRE_MINUTES * 60,
        path="/"
    )
    logger.info("Token refreshed for username=%s", user.username)
    return {"access_token": access_token, "token_type": "bearer", "role": user.role, "atelier_id": user.atelier_id}


@router.get("/api/roles/permissions")
def list_roles_permissions(db: Session = Depends(get_db), current_user: User = Depends(get_current_user)):
    if not user_has_permission(current_user, db, "roles.manage"):
        raise HTTPException(status_code=403, detail="Acces reserve super_admin")
    roles = db.query(RolePermission).order_by(RolePermission.role).all()
    result = []
    for role_item in roles:
        try:
            sections = json.loads(role_item.sections_json or "[]")
        except Exception:
            sections = []
        try:
            permissions = json.loads(role_item.permissions_json or "[]")
        except Exception:
            permissions = []
        result.append({
            "role": role_item.role,
            "label": role_item.label,
            "description": role_item.description,
            "sections": sections,
            "permissions": permissions,
            "is_system": bool(role_item.is_system),
        })
    return result


@router.post("/api/roles/permissions")
def create_or_update_role_permissions(
    payload: RolePermissionPayload,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    if not user_has_permission(current_user, db, "roles.manage"):
        raise HTTPException(status_code=403, detail="Acces reserve super_admin")
    role = normalize_role_slug(payload.role)
    if not role:
        raise HTTPException(status_code=400, detail="Role obligatoire")
    if role == "super_admin":
        raise HTTPException(status_code=400, detail="Role super_admin non modifiable")
    allowed_sections = {"dashboard", "rdv", "planning", "ponts", "or", "suivi", "motos", "clients", "espace-meca", "admin"}
    allowed_permissions = {
        "billing.view", "billing.edit", "billing.pay", "billing.pdf",
        "travaux_supp.review", "users.manage", "ateliers.manage",
        "roles.manage", "config.manage", "prestations.manage", "equipements.manage", "rdv.select_atelier", "rdv.edit"
    }
    sections = [s for s in (payload.sections or []) if s in allowed_sections]
    permissions = [p for p in (payload.permissions or []) if p in allowed_permissions]
    if not payload.label:
        raise HTTPException(status_code=400, detail="Label obligatoire")
    existing = db.query(RolePermission).filter(RolePermission.role == role).first()
    if not existing:
        existing = RolePermission(
            role=role,
            label=payload.label.strip(),
            description=payload.description,
            sections_json=json.dumps(sections),
            permissions_json=json.dumps(permissions),
            is_system=0
        )
        db.add(existing)
    else:
        existing.label = payload.label.strip()
        existing.description = payload.description
        existing.sections_json = json.dumps(sections)
        existing.permissions_json = json.dumps(permissions)
    db.commit()
    return {"message": "Role enregistre", "role": role}


@router.delete("/api/roles/permissions/{role}")
def delete_role_permissions(
    role: str,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    if not user_has_permission(current_user, db, "roles.manage"):
        raise HTTPException(status_code=403, detail="Acces reserve super_admin")
    role = normalize_role_slug(role)
    existing = db.query(RolePermission).filter(RolePermission.role == role).first()
    if not existing:
        raise HTTPException(status_code=404, detail="Role non trouve")
    if existing.is_system == 1:
        raise HTTPException(status_code=400, detail="Role systeme non supprimable")
    in_use = db.query(User).filter(User.role == role).count()
    if in_use > 0:
        raise HTTPException(status_code=400, detail="Role utilise par des utilisateurs")
    in_use_rel = db.query(UserAtelierRole).filter(UserAtelierRole.role == role).count()
    if in_use_rel > 0:
        raise HTTPException(status_code=400, detail="Role utilise dans des affectations atelier")
    db.delete(existing)
    db.commit()
    return {"message": "Role supprime"}


@router.post("/api/auth/logout")
def logout(payload: RefreshTokenPayload, request: Request, response: Response, db: Session = Depends(get_db)):
    auth_header = request.headers.get("authorization", "")
    if auth_header.startswith("Bearer "):
        access_token = auth_header.split(" ", 1)[1].strip()
        if access_token:
            revoke_token(db, access_token, reason="logout")
    refresh_token = payload.refresh_token or request.cookies.get("refresh_token")
    if refresh_token:
        revoke_token(db, refresh_token, reason="logout")
    response.delete_cookie(key="access_token", path="/")
    response.delete_cookie(key="refresh_token", path="/api/auth")
    logger.info("Logout completed")
    return {"message": "Logout effectue"}
