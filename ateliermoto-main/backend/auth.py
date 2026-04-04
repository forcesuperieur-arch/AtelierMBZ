import bcrypt
import os
import secrets
from pathlib import Path
from datetime import datetime, timedelta
from typing import Optional
from jose import JWTError, jwt
from fastapi import Depends, HTTPException, Request, status
from fastapi.security import HTTPBearer, HTTPAuthorizationCredentials
from sqlalchemy.orm import Session
from models import User, RevokedToken, Atelier, get_db
from dotenv import load_dotenv

# Charger les variables d'environnement
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


# Configuration sécurisée: env prioritaire, sinon clé persistée auto-générée
SECRET_KEY = _load_or_create_secret_key()

ALGORITHM = "HS256"
ACCESS_TOKEN_EXPIRE_MINUTES = 30
REFRESH_TOKEN_EXPIRE_DAYS = 7

security = HTTPBearer(auto_error=False)

def verify_password(plain_password: str, hashed_password: str) -> bool:
    """Vérifie un mot de passe avec bcrypt"""
    return bcrypt.checkpw(
        plain_password.encode('utf-8'),
        hashed_password.encode('utf-8')
    )

def get_password_hash(password: str) -> str:
    """Hash un mot de passe avec bcrypt (salt automatique)"""
    salt = bcrypt.gensalt(rounds=12)
    hashed = bcrypt.hashpw(password.encode('utf-8'), salt)
    return hashed.decode('utf-8')

def is_password_strong(password: str) -> bool:
    if len(password) < 8:
        return False
    if not any(c.isupper() for c in password):
        return False
    if not any(c.isdigit() for c in password):
        return False
    return True

def create_access_token(data: dict, expires_delta: timedelta = None):
    to_encode = data.copy()
    if expires_delta:
        expire = datetime.utcnow() + expires_delta
    else:
        expire = datetime.utcnow() + timedelta(minutes=15)
    to_encode.update({
        "exp": expire,
        "jti": secrets.token_urlsafe(16),
        "typ": "access",
    })
    encoded_jwt = jwt.encode(to_encode, SECRET_KEY, algorithm=ALGORITHM)
    return encoded_jwt

def create_refresh_token(data: dict, expires_delta: timedelta = None):
    to_encode = data.copy()
    if expires_delta:
        expire = datetime.utcnow() + expires_delta
    else:
        expire = datetime.utcnow() + timedelta(days=REFRESH_TOKEN_EXPIRE_DAYS)
    to_encode.update({
        "exp": expire,
        "jti": secrets.token_urlsafe(16),
        "typ": "refresh",
    })
    encoded_jwt = jwt.encode(to_encode, SECRET_KEY, algorithm=ALGORITHM)
    return encoded_jwt

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
    token = None
    if credentials:
        token = credentials.credentials
    else:
        token = request.cookies.get("access_token")
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
        payload = decode_token(token)
        if payload.get("typ") != "access":
            raise credentials_exception
        jti = payload.get("jti")
        if not jti:
            raise credentials_exception
        revoked = db.query(RevokedToken).filter(RevokedToken.jti == jti).first()
        if revoked:
            raise credentials_exception
        username: str = payload.get("sub")
        token_atelier_id = payload.get("atelier_id")
        if username is None:
            raise credentials_exception
    except JWTError:
        raise credentials_exception
    
    user = db.query(User).filter(User.username == username).first()
    if user is None:
        raise credentials_exception
    if token_atelier_id is not None and user.atelier_id is not None and int(token_atelier_id) != int(user.atelier_id):
        raise credentials_exception
    return user

def get_optional_current_user(
    request: Request,
    credentials: Optional[HTTPAuthorizationCredentials] = Depends(security),
    db: Session = Depends(get_db),
):
    token = None
    if credentials:
        token = credentials.credentials
    else:
        token = request.cookies.get("access_token")
    if not token:
        return None
    try:
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
    except JWTError:
        return None

    user = db.query(User).filter(User.username == username).first()
    if user is None:
        return None
    if token_atelier_id is not None and user.atelier_id is not None and int(token_atelier_id) != int(user.atelier_id):
        return None
    return user


def get_current_atelier_id(current_user: User = Depends(get_current_user)) -> int:
    if not getattr(current_user, "atelier_id", None):
        raise HTTPException(status_code=403, detail="Utilisateur non associe a un atelier")
    return int(current_user.atelier_id)

def create_default_users(db: Session):
    """Crée un admin initial seulement si explicitement configuré via ENV"""
    bootstrap_username = os.getenv("ADMIN_USERNAME")
    bootstrap_password = os.getenv("ADMIN_PASSWORD")
    bootstrap_email = os.getenv("ADMIN_EMAIL", "admin@atelier-moto.fr")

    if not bootstrap_username or not bootstrap_password:
        return

    if not is_password_strong(bootstrap_password):
        raise ValueError("ADMIN_PASSWORD doit avoir 8+ caracteres, 1 majuscule et 1 chiffre")

    existing = db.query(User).filter(User.username == bootstrap_username).first()
    if existing:
        return

    default_atelier = db.query(Atelier).filter(Atelier.slug == "default").first()
    admin = User(
        username=bootstrap_username,
        email=bootstrap_email,
        hashed_password=get_password_hash(bootstrap_password),
        role="admin",
        atelier_id=default_atelier.id if default_atelier else None
    )
    db.add(admin)
    db.commit()
