"""Routes admin avancées: SMTP config, templates email, audit logs, backup/restore."""

import json
import logging
import os
import subprocess
from datetime import datetime
from pathlib import Path
from typing import Optional

from fastapi import APIRouter, Depends, File, HTTPException, UploadFile
from fastapi.responses import FileResponse
from pydantic import BaseModel
from sqlalchemy import desc
from sqlalchemy.orm import Session

from auth import get_current_user
from models import AuditLog, ConfigAtelier, EmailTemplate, User, get_db
from services.audit_service import log_action
from services.email_service import get_smtp_config, save_smtp_to_db, send_email

router = APIRouter(tags=["admin-avance"])
logger = logging.getLogger("ateliermoto.admin")

BACKUPS_DIR = Path("/app/backups")
BACKUPS_DIR.mkdir(parents=True, exist_ok=True)


# ========== SMTP CONFIG ==========

class SmtpConfigUpdate(BaseModel):
    smtp_host: Optional[str] = None
    smtp_port: Optional[int] = None
    smtp_user: Optional[str] = None
    smtp_password: Optional[str] = None
    smtp_from: Optional[str] = None
    smtp_tls: Optional[bool] = None
    smtp_ssl: Optional[bool] = None


@router.get("/api/admin/smtp")
def get_smtp_settings(
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    """Retourne la configuration SMTP actuelle."""
    if current_user.role not in ("admin", "super_admin"):
        raise HTTPException(status_code=403, detail="Accès refusé")

    cfg = get_smtp_config(getattr(current_user, "atelier_id", None))
    return {
        "smtp_host": cfg.host,
        "smtp_port": cfg.port,
        "smtp_user": cfg.user,
        "smtp_from": cfg.from_addr,
        "smtp_tls": cfg.use_tls,
        "smtp_ssl": cfg.use_ssl,
        # Ne pas renvoyer le mot de passe en clair
        "smtp_password_set": bool(cfg.password),
    }


@router.put("/api/admin/smtp")
def update_smtp_settings(
    data: SmtpConfigUpdate,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    """Met à jour la configuration SMTP (persisted in DB + runtime env)."""
    if current_user.role not in ("admin", "super_admin"):
        raise HTTPException(status_code=403, detail="Accès refusé")

    atelier_id = getattr(current_user, "atelier_id", None) or 1

    # Read current config from DB for merging
    current_cfg = get_smtp_config(atelier_id)
    smtp_data = {
        "host": data.smtp_host if data.smtp_host is not None else current_cfg.host,
        "port": data.smtp_port if data.smtp_port is not None else current_cfg.port,
        "user": data.smtp_user if data.smtp_user is not None else current_cfg.user,
        "password": data.smtp_password if data.smtp_password is not None else current_cfg.password,
        "from_addr": data.smtp_from if data.smtp_from is not None else current_cfg.from_addr,
        "use_tls": data.smtp_tls if data.smtp_tls is not None else current_cfg.use_tls,
        "use_ssl": data.smtp_ssl if data.smtp_ssl is not None else current_cfg.use_ssl,
    }

    # Persist to DB
    save_smtp_to_db(atelier_id, smtp_data)

    # Also update runtime env vars for immediate effect
    updates = {}
    if data.smtp_host is not None:
        os.environ["SMTP_HOST"] = data.smtp_host
        updates["SMTP_HOST"] = data.smtp_host
    if data.smtp_port is not None:
        os.environ["SMTP_PORT"] = str(data.smtp_port)
        updates["SMTP_PORT"] = str(data.smtp_port)
    if data.smtp_user is not None:
        os.environ["SMTP_USER"] = data.smtp_user
        updates["SMTP_USER"] = data.smtp_user
    if data.smtp_password is not None:
        os.environ["SMTP_PASSWORD"] = data.smtp_password
        updates["SMTP_PASSWORD"] = "***"
    if data.smtp_from is not None:
        os.environ["SMTP_FROM"] = data.smtp_from
        updates["SMTP_FROM"] = data.smtp_from
    if data.smtp_tls is not None:
        os.environ["SMTP_TLS"] = "true" if data.smtp_tls else "false"
        updates["SMTP_TLS"] = str(data.smtp_tls)
    if data.smtp_ssl is not None:
        os.environ["SMTP_SSL"] = "true" if data.smtp_ssl else "false"
        updates["SMTP_SSL"] = str(data.smtp_ssl)

    log_action(
        db, action="update_smtp", entity_type="config",
        details=updates, user_id=current_user.id, username=current_user.username,
        atelier_id=getattr(current_user, "atelier_id", None),
    )

    return {"message": "Configuration SMTP mise à jour"}


@router.post("/api/admin/smtp/test")
def test_smtp(
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    """Envoie un email de test avec la configuration SMTP actuelle."""
    if current_user.role not in ("admin", "super_admin"):
        raise HTTPException(status_code=403, detail="Accès refusé")

    success = send_email(
        to=current_user.email,
        subject="Test SMTP — Atelier Moto",
        html_body="<p>Configuration SMTP fonctionnelle ✓</p>",
        text_body="Configuration SMTP fonctionnelle.",
    )
    if success:
        return {"message": f"Email de test envoyé à {current_user.email}"}
    raise HTTPException(status_code=500, detail="Échec de l'envoi. Vérifiez la configuration SMTP.")


# ========== EMAIL TEMPLATES ==========

class EmailTemplateCreate(BaseModel):
    code: str
    nom: str
    sujet: str
    corps_html: str
    corps_texte: Optional[str] = None
    variables_disponibles: Optional[str] = None


class EmailTemplateUpdate(BaseModel):
    nom: Optional[str] = None
    sujet: Optional[str] = None
    corps_html: Optional[str] = None
    corps_texte: Optional[str] = None
    is_active: Optional[int] = None


@router.get("/api/admin/email-templates")
def list_email_templates(
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    """Liste tous les templates email."""
    if current_user.role not in ("admin", "super_admin"):
        raise HTTPException(status_code=403, detail="Accès refusé")

    atelier_id = getattr(current_user, "atelier_id", None) or 1
    templates = (
        db.query(EmailTemplate)
        .filter(EmailTemplate.atelier_id == atelier_id)
        .order_by(EmailTemplate.code)
        .all()
    )
    return [
        {
            "id": t.id,
            "code": t.code,
            "nom": t.nom,
            "sujet": t.sujet,
            "corps_html": t.corps_html,
            "corps_texte": t.corps_texte,
            "variables_disponibles": t.variables_disponibles,
            "is_active": t.is_active,
            "updated_at": t.updated_at.isoformat() if t.updated_at else None,
        }
        for t in templates
    ]


@router.post("/api/admin/email-templates")
def create_email_template(
    data: EmailTemplateCreate,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    """Crée un nouveau template email."""
    if current_user.role not in ("admin", "super_admin"):
        raise HTTPException(status_code=403, detail="Accès refusé")

    atelier_id = getattr(current_user, "atelier_id", None) or 1
    template = EmailTemplate(
        atelier_id=atelier_id,
        code=data.code,
        nom=data.nom,
        sujet=data.sujet,
        corps_html=data.corps_html,
        corps_texte=data.corps_texte,
        variables_disponibles=data.variables_disponibles,
    )
    db.add(template)
    db.commit()
    db.refresh(template)

    log_action(
        db, action="create", entity_type="email_template", entity_id=template.id,
        details={"code": data.code, "nom": data.nom},
        user_id=current_user.id, username=current_user.username,
        atelier_id=atelier_id,
    )

    return {"message": "Template créé", "id": template.id}


@router.put("/api/admin/email-templates/{template_id}")
def update_email_template(
    template_id: int,
    data: EmailTemplateUpdate,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    """Met à jour un template email."""
    if current_user.role not in ("admin", "super_admin"):
        raise HTTPException(status_code=403, detail="Accès refusé")

    template = db.query(EmailTemplate).filter(EmailTemplate.id == template_id).first()
    if not template:
        raise HTTPException(status_code=404, detail="Template non trouvé")

    for field, value in data.model_dump(exclude_unset=True).items():
        setattr(template, field, value)

    db.commit()

    log_action(
        db, action="update", entity_type="email_template", entity_id=template_id,
        details=data.model_dump(exclude_unset=True),
        user_id=current_user.id, username=current_user.username,
        atelier_id=getattr(current_user, "atelier_id", None),
    )

    return {"message": "Template mis à jour"}


@router.delete("/api/admin/email-templates/{template_id}")
def delete_email_template(
    template_id: int,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    """Supprime un template email."""
    if current_user.role not in ("admin", "super_admin"):
        raise HTTPException(status_code=403, detail="Accès refusé")

    template = db.query(EmailTemplate).filter(EmailTemplate.id == template_id).first()
    if not template:
        raise HTTPException(status_code=404, detail="Template non trouvé")

    db.delete(template)
    db.commit()

    log_action(
        db, action="delete", entity_type="email_template", entity_id=template_id,
        user_id=current_user.id, username=current_user.username,
        atelier_id=getattr(current_user, "atelier_id", None),
    )

    return {"message": "Template supprimé"}


# ========== AUDIT LOGS ==========

@router.get("/api/admin/audit-logs")
def list_audit_logs(
    page: int = 1,
    per_page: int = 50,
    action: Optional[str] = None,
    entity_type: Optional[str] = None,
    user_id: Optional[int] = None,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    """Liste les logs d'audit avec filtres et pagination."""
    if current_user.role not in ("admin", "super_admin"):
        raise HTTPException(status_code=403, detail="Accès refusé")

    query = db.query(AuditLog)

    atelier_id = getattr(current_user, "atelier_id", None)
    if atelier_id:
        query = query.filter(AuditLog.atelier_id == atelier_id)
    if action:
        query = query.filter(AuditLog.action == action)
    if entity_type:
        query = query.filter(AuditLog.entity_type == entity_type)
    if user_id:
        query = query.filter(AuditLog.user_id == user_id)

    total = query.count()
    logs = (
        query.order_by(desc(AuditLog.created_at))
        .offset((page - 1) * per_page)
        .limit(per_page)
        .all()
    )

    return {
        "total": total,
        "page": page,
        "per_page": per_page,
        "logs": [
            {
                "id": l.id,
                "user_id": l.user_id,
                "username": l.username,
                "action": l.action,
                "entity_type": l.entity_type,
                "entity_id": l.entity_id,
                "details": l.details,
                "ip_address": l.ip_address,
                "created_at": l.created_at.isoformat() if l.created_at else None,
            }
            for l in logs
        ],
    }


# ========== BACKUP / RESTORE ==========

@router.post("/api/admin/backup")
def create_backup(
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    """Crée une sauvegarde PostgreSQL."""
    if current_user.role not in ("admin", "super_admin"):
        raise HTTPException(status_code=403, detail="Accès refusé")

    timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
    filename = f"backup_{timestamp}.sql"
    filepath = BACKUPS_DIR / filename

    db_url = os.getenv("DATABASE_URL", "postgresql://atelier:atelier@db:5432/atelier_moto")
    # Parse DATABASE_URL
    # Format: postgresql://user:pass@host:port/dbname
    try:
        parts = db_url.replace("postgresql://", "").split("@")
        user_pass = parts[0].split(":")
        host_port_db = parts[1].split("/")
        host_port = host_port_db[0].split(":")
        
        env = os.environ.copy()
        env["PGPASSWORD"] = user_pass[1] if len(user_pass) > 1 else ""

        result = subprocess.run(
            [
                "pg_dump",
                "-h", host_port[0],
                "-p", host_port[1] if len(host_port) > 1 else "5432",
                "-U", user_pass[0],
                "-d", host_port_db[1] if len(host_port_db) > 1 else "atelier_moto",
                "-f", str(filepath),
                "--no-owner",
                "--no-acl",
            ],
            env=env,
            capture_output=True,
            text=True,
            timeout=120,
        )

        if result.returncode != 0:
            raise HTTPException(status_code=500, detail=f"Erreur pg_dump: {result.stderr[:500]}")

    except subprocess.TimeoutExpired:
        raise HTTPException(status_code=500, detail="Timeout lors de la sauvegarde")
    except FileNotFoundError:
        raise HTTPException(status_code=500, detail="pg_dump non disponible dans le conteneur")

    size = filepath.stat().st_size

    log_action(
        db, action="backup", entity_type="database",
        details={"filename": filename, "size": size},
        user_id=current_user.id, username=current_user.username,
        atelier_id=getattr(current_user, "atelier_id", None),
    )

    return {"message": "Sauvegarde créée", "filename": filename, "size": size}


@router.get("/api/admin/backups")
def list_backups(
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    """Liste les sauvegardes existantes."""
    if current_user.role not in ("admin", "super_admin"):
        raise HTTPException(status_code=403, detail="Accès refusé")

    backups = []
    for f in sorted(BACKUPS_DIR.glob("*.sql"), reverse=True):
        stat = f.stat()
        backups.append({
            "filename": f.name,
            "size": stat.st_size,
            "created_at": datetime.fromtimestamp(stat.st_mtime).isoformat(),
        })
    return backups


@router.get("/api/admin/backups/{filename}")
def download_backup(
    filename: str,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    """Télécharge un fichier de sauvegarde."""
    if current_user.role not in ("admin", "super_admin"):
        raise HTTPException(status_code=403, detail="Accès refusé")

    # Prevent path traversal
    safe_name = Path(filename).name
    filepath = BACKUPS_DIR / safe_name
    if not filepath.exists() or not str(filepath).startswith(str(BACKUPS_DIR)):
        raise HTTPException(status_code=404, detail="Fichier non trouvé")

    return FileResponse(str(filepath), filename=safe_name, media_type="application/sql")


@router.post("/api/admin/restore")
async def restore_backup(
    file: UploadFile = File(...),
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    """Restaure une sauvegarde PostgreSQL."""
    if current_user.role not in ("admin", "super_admin"):
        raise HTTPException(status_code=403, detail="Accès refusé")

    if not file.filename or not file.filename.endswith(".sql"):
        raise HTTPException(status_code=400, detail="Fichier SQL requis")

    # Save uploaded file temporarily
    content = await file.read()
    if len(content) > 100 * 1024 * 1024:  # 100 MB limit
        raise HTTPException(status_code=400, detail="Fichier trop volumineux (max 100 Mo)")

    tmp_path = BACKUPS_DIR / f"restore_tmp_{datetime.now().strftime('%Y%m%d_%H%M%S')}.sql"
    tmp_path.write_bytes(content)

    db_url = os.getenv("DATABASE_URL", "postgresql://atelier:atelier@db:5432/atelier_moto")
    try:
        parts = db_url.replace("postgresql://", "").split("@")
        user_pass = parts[0].split(":")
        host_port_db = parts[1].split("/")
        host_port = host_port_db[0].split(":")

        env = os.environ.copy()
        env["PGPASSWORD"] = user_pass[1] if len(user_pass) > 1 else ""

        result = subprocess.run(
            [
                "psql",
                "-h", host_port[0],
                "-p", host_port[1] if len(host_port) > 1 else "5432",
                "-U", user_pass[0],
                "-d", host_port_db[1] if len(host_port_db) > 1 else "atelier_moto",
                "-f", str(tmp_path),
            ],
            env=env,
            capture_output=True,
            text=True,
            timeout=300,
        )

        if result.returncode != 0 and "ERROR" in result.stderr:
            raise HTTPException(status_code=500, detail=f"Erreur restauration: {result.stderr[:500]}")

    except subprocess.TimeoutExpired:
        raise HTTPException(status_code=500, detail="Timeout lors de la restauration")
    except FileNotFoundError:
        raise HTTPException(status_code=500, detail="psql non disponible dans le conteneur")
    finally:
        if tmp_path.exists():
            tmp_path.unlink()

    log_action(
        db, action="restore", entity_type="database",
        details={"filename": file.filename},
        user_id=current_user.id, username=current_user.username,
        atelier_id=getattr(current_user, "atelier_id", None),
    )

    return {"message": "Restauration effectuée"}
