"""Service d'envoi d'emails via SMTP.

Supporte MailHog (dev) et tout serveur SMTP (production).
Modes de connexion : plain, STARTTLS (port 587), SSL (port 465).
"""

import json
import logging
import os
import smtplib
from dataclasses import dataclass
from email import encoders
from email.mime.base import MIMEBase
from email.mime.multipart import MIMEMultipart
from email.mime.text import MIMEText

from services.email_templates_business_billing import render_facture_email, render_relance_client
from services.email_templates_business_status import (
    render_compte_rendu,
    render_signature_or,
    render_suivi_statut,
)
from services.email_templates_rdv_primary import render_confirmation_rdv, render_rappel_rdv
from services.email_templates_rdv_updates import render_deplacement_rdv, render_rappel_rdv_enhanced

logger = logging.getLogger("ateliermoto.email")


@dataclass
class SmtpConfig:
    host: str
    port: int
    user: str
    password: str
    from_addr: str
    use_tls: bool
    use_ssl: bool = False


def get_smtp_config(atelier_id: int | None = None) -> SmtpConfig:
    """Read SMTP config from DB (Atelier.config_json) then env vars as fallback."""
    db_cfg = _load_smtp_from_db(atelier_id)
    if db_cfg:
        return db_cfg
    return SmtpConfig(
        host=os.getenv("SMTP_HOST", "mailhog"),
        port=int(os.getenv("SMTP_PORT", "1025")),
        user=os.getenv("SMTP_USER", ""),
        password=os.getenv("SMTP_PASSWORD", ""),
        from_addr=os.getenv("SMTP_FROM", "noreply@atelier-moto.local"),
        use_tls=os.getenv("SMTP_TLS", "false").lower() in ("true", "1", "yes"),
        use_ssl=os.getenv("SMTP_SSL", "false").lower() in ("true", "1", "yes"),
    )


def save_smtp_to_db(atelier_id: int, smtp_data: dict) -> None:
    """Persist SMTP config into Atelier.config_json."""
    from models import Atelier, SessionLocal

    db = SessionLocal()
    try:
        atelier = db.query(Atelier).filter(Atelier.id == atelier_id).first()
        if not atelier:
            return
        cfg = json.loads(atelier.config_json) if atelier.config_json else {}
        cfg["smtp"] = smtp_data
        atelier.config_json = json.dumps(cfg)
        db.commit()
    finally:
        db.close()


def _load_smtp_from_db(atelier_id: int | None = None) -> SmtpConfig | None:
    """Load SMTP config from Atelier.config_json. Returns None if not stored."""
    try:
        from models import Atelier, SessionLocal

        db = SessionLocal()
        try:
            aid = atelier_id or 1
            atelier = db.query(Atelier).filter(Atelier.id == aid).first()
            if not atelier or not atelier.config_json:
                return None
            cfg = json.loads(atelier.config_json)
            smtp = cfg.get("smtp")
            if not smtp or not smtp.get("host"):
                return None
            return SmtpConfig(
                host=smtp["host"],
                port=int(smtp.get("port", 587)),
                user=smtp.get("user", ""),
                password=smtp.get("password", ""),
                from_addr=smtp.get("from_addr", ""),
                use_tls=bool(smtp.get("use_tls", False)),
                use_ssl=bool(smtp.get("use_ssl", False)),
            )
        finally:
            db.close()
    except Exception:
        logger.debug("Could not load SMTP from DB, using env vars")
        return None


def send_email(
    to: str,
    subject: str,
    html_body: str,
    text_body: str | None = None,
    atelier_id: int | None = None,
    attachments: list[dict] | None = None,
) -> bool:
    """Envoie un email. Retourne True si envoye, False sinon.

    attachments: list of {"filename": str, "content": bytes, "mime_type": str}
    """
    cfg = get_smtp_config(atelier_id)
    if not cfg.host:
        logger.warning("SMTP_HOST non configure, email non envoye")
        return False

    msg = MIMEMultipart("mixed")
    msg["From"] = cfg.from_addr
    msg["To"] = to
    msg["Subject"] = subject

    body_part = MIMEMultipart("alternative")
    if text_body:
        body_part.attach(MIMEText(text_body, "plain", "utf-8"))
    body_part.attach(MIMEText(html_body, "html", "utf-8"))
    msg.attach(body_part)

    for att in (attachments or []):
        part = MIMEBase("application", "octet-stream")
        part.set_payload(att["content"])
        encoders.encode_base64(part)
        part.add_header("Content-Disposition", f'attachment; filename="{att["filename"]}"')
        msg.attach(part)

    try:
        if cfg.use_ssl:
            server = smtplib.SMTP_SSL(cfg.host, cfg.port, timeout=15)
        elif cfg.use_tls:
            server = smtplib.SMTP(cfg.host, cfg.port, timeout=15)
            server.ehlo()
            server.starttls()
            server.ehlo()
        else:
            server = smtplib.SMTP(cfg.host, cfg.port, timeout=10)

        if cfg.user:
            server.login(cfg.user, cfg.password)

        server.sendmail(cfg.from_addr, [to], msg.as_string())
        server.quit()
        logger.info("Email envoye a %s: %s", to, subject)
        return True
    except Exception:
        logger.exception("Erreur envoi email a %s", to)
        return False


__all__ = [
    "SmtpConfig",
    "get_smtp_config",
    "save_smtp_to_db",
    "send_email",
    "render_rappel_rdv",
    "render_confirmation_rdv",
    "render_rappel_rdv_enhanced",
    "render_suivi_statut",
    "render_signature_or",
    "render_compte_rendu",
    "render_facture_email",
    "render_deplacement_rdv",
    "render_relance_client",
]
