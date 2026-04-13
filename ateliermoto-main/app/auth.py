import bcrypt
import logging
import os
import secrets
from datetime import datetime, timedelta
from pathlib import Path
from typing import Optional

from fastapi import Depends, HTTPException, Request, status
from fastapi.security import HTTPBearer, HTTPAuthorizationCredentials
from jose import JWTError, jwt
from sqlalchemy.orm import Session
from dotenv import load_dotenv
from models import RevokedToken, User, get_db

logger = logging.getLogger("ateliermoto.auth")

load_dotenv()

def _load_or_create_secret_key() -> str:
    env_secret = os.getenv("SECRET_KEY")
    if env_secret:
        return env_secret

    secret_file = Path(os.getenv("SECRET_KEY_FILE", ".secret_key")).resolve()
    if secret_file.exists():
        value = secret_file.read_text(encoding="utf-8").strip()
        if value:
            return value

    secret_file.parent.mkdir(parents=True, exist_ok=True)
    generated = secrets.token_urlsafe(64)
    secret_file.write_text(generated, encoding="utf-8")
    try:
        os.chmod(secret_file, 0o600)
    except OSError:
        pass
    return generated


def _build_token(data: dict, expires_delta: timedelta | None, token_type: str, default_delta: timedelta) -> str:
    payload = data.copy()
    expire = datetime.utcnow() + (expires_delta or default_delta)
    payload.update({"exp": expire, "jti": secrets.token_urlsafe(16), "typ": token_type})
    return jwt.encode(payload, SECRET_KEY, algorithm=ALGORITHM)


def _get_request_token(request: Request, credentials: Optional[HTTPAuthorizationCredentials]) -> str | None:
    return credentials.credentials if credentials else request.cookies.get("access_token")


def _resolve_user_from_token(db: Session, token: str) -> User | None:
    payload = decode_token(token)
    if payload.get("typ") != "access":
        return None
    jti = payload.get("jti")
    username = payload.get("sub")
    token_atelier_id = payload.get("atelier_id")
    if not jti or not username:
        return None
    revoked = db.query(RevokedToken).filter(RevokedToken.jti == jti).first()
    if revoked:
        return None

    user = db.query(User).filter(User.username == username).first()
    if user is None:
        return None
    if token_atelier_id is not None and user.atelier_id is not None and int(token_atelier_id) != int(user.atelier_id):
        return None
    return user

SECRET_KEY = _load_or_create_secret_key()

ALGORITHM = "HS256"
ACCESS_TOKEN_EXPIRE_MINUTES = 30
REFRESH_TOKEN_EXPIRE_DAYS = 7

security = HTTPBearer(auto_error=False)

def verify_password(plain_password: str, hashed_password: str) -> bool:
    """Vérifie un mot de passe avec bcrypt sans casser l'API sur hash corrompu."""
    if not hashed_password:
        return False
    try:
        return bcrypt.checkpw(plain_password.encode('utf-8'), hashed_password.encode('utf-8'))
    except (ValueError, TypeError):
        logger.warning("Invalid password hash encountered during login")
        return False

def get_password_hash(password: str) -> str:
    """Hash un mot de passe avec bcrypt (salt automatique)"""
    return bcrypt.hashpw(password.encode('utf-8'), bcrypt.gensalt(rounds=12)).decode('utf-8')


def is_password_strong(password: str) -> bool:
    return len(password) >= 8 and any(c.isupper() for c in password) and any(c.isdigit() for c in password)


def create_access_token(data: dict, expires_delta: timedelta = None):
    return _build_token(data, expires_delta, "access", timedelta(minutes=15))


def create_refresh_token(data: dict, expires_delta: timedelta = None):
    return _build_token(data, expires_delta, "refresh", timedelta(days=REFRESH_TOKEN_EXPIRE_DAYS))


def decode_token(token: str):
    return jwt.decode(token, SECRET_KEY, algorithms=[ALGORITHM])


def revoke_token(db: Session, token: str, reason: str = "manual") -> None:
    try:
        payload = decode_token(token)
    except JWTError:
        return

    jti = payload.get("jti")
    exp = payload.get("exp")
    if not jti or not exp:
        return

    already = db.query(RevokedToken).filter(RevokedToken.jti == jti).first()
    if already:
        return

    expires_at = datetime.utcfromtimestamp(exp)
    db.add(RevokedToken(jti=jti, expires_at=expires_at, reason=reason))
    db.commit()


def get_current_user(
    request: Request,
    credentials: Optional[HTTPAuthorizationCredentials] = Depends(security),
    db: Session = Depends(get_db)
):
    token = _get_request_token(request, credentials)
    if not token:
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="Not authenticated",
            headers={"WWW-Authenticate": "Bearer"},
        )

    credentials_exception = HTTPException(
        status_code=status.HTTP_401_UNAUTHORIZED,
        detail="Could not validate credentials",
        headers={"WWW-Authenticate": "Bearer"},
    )
    try:
        user = _resolve_user_from_token(db, token)
    except JWTError:
        raise credentials_exception
    if user is None:
        raise credentials_exception
    return user


def get_optional_current_user(
    request: Request,
    credentials: Optional[HTTPAuthorizationCredentials] = Depends(security),
    db: Session = Depends(get_db),
):
    token = _get_request_token(request, credentials)
    if not token:
        return None
    try:
        return _resolve_user_from_token(db, token)
    except JWTError:
        return None

def get_current_atelier_id(current_user: User = Depends(get_current_user)) -> int:
    if not getattr(current_user, "atelier_id", None):
        raise HTTPException(status_code=403, detail="Utilisateur non associe a un atelier")
    return int(current_user.atelier_id)


def create_default_users(db: Session):
    """Crée un superadmin initial seulement si explicitement configuré via ENV"""
    bootstrap_username, bootstrap_password = os.getenv("SUPERADMIN_USERNAME"), os.getenv("SUPERADMIN_PASSWORD")
    bootstrap_email = os.getenv("SUPERADMIN_EMAIL", "superadmin@atelier-moto.fr")
    if not bootstrap_username or not bootstrap_password:
        logger.info("Aucune variable SUPERADMIN_USERNAME/PASSWORD définie — pas de superadmin créé")
        return
    if not is_password_strong(bootstrap_password):
        raise ValueError("SUPERADMIN_PASSWORD doit avoir 8+ caractères, 1 majuscule et 1 chiffre")
    if db.query(User).filter(User.username == bootstrap_username).first():
        logger.info(f"Utilisateur superadmin {bootstrap_username} existe déjà — pas de régénération")
        return
    db.add(User(username=bootstrap_username, email=bootstrap_email, hashed_password=get_password_hash(bootstrap_password), role="super_admin", atelier_id=None))
    db.commit()
    logger.info(f"✓ Superadmin créé: {bootstrap_username} ({bootstrap_email}) avec rôle super_admin")
