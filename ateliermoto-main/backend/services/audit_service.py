"""Service d'audit logging pour tracer les actions critiques."""

import json
import logging
from typing import Optional

from sqlalchemy.orm import Session

from models import AuditLog

logger = logging.getLogger("ateliermoto.audit")


def log_action(
    db: Session,
    action: str,
    entity_type: str = None,
    entity_id: int = None,
    details: dict = None,
    user_id: int = None,
    username: str = None,
    atelier_id: int = None,
    ip_address: str = None,
):
    """Enregistre une action dans le journal d'audit."""
    try:
        entry = AuditLog(
            atelier_id=atelier_id,
            user_id=user_id,
            username=username,
            action=action,
            entity_type=entity_type,
            entity_id=entity_id,
            details=json.dumps(details, ensure_ascii=False, default=str) if details else None,
            ip_address=ip_address,
        )
        db.add(entry)
        db.commit()
    except Exception:
        db.rollback()
        logger.exception("Erreur lors de l'enregistrement audit")
