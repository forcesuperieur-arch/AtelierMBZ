"""Service d'envoi d'emails via SMTP.

Supporte MailHog (dev) et tout serveur SMTP (production).
Modes de connexion : plain, STARTTLS (port 587), SSL (port 465).
"""

import json
import logging
import os
import smtplib
from email.mime.base import MIMEBase
from email.mime.multipart import MIMEMultipart
from email.mime.text import MIMEText
from email import encoders
from dataclasses import dataclass

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
    """Envoie un email. Retourne True si envoyé, False sinon.

    attachments: list of {"filename": str, "content": bytes, "mime_type": str}
    """
    cfg = get_smtp_config(atelier_id)
    if not cfg.host:
        logger.warning("SMTP_HOST non configuré, email non envoyé")
        return False

    msg = MIMEMultipart("mixed")
    msg["From"] = cfg.from_addr
    msg["To"] = to
    msg["Subject"] = subject

    # Body (alternative text/html)
    body_part = MIMEMultipart("alternative")
    if text_body:
        body_part.attach(MIMEText(text_body, "plain", "utf-8"))
    body_part.attach(MIMEText(html_body, "html", "utf-8"))
    msg.attach(body_part)

    # Attachments
    for att in (attachments or []):
        part = MIMEBase("application", "octet-stream")
        part.set_payload(att["content"])
        encoders.encode_base64(part)
        part.add_header("Content-Disposition", f'attachment; filename="{att["filename"]}"')
        msg.attach(part)

    try:
        if cfg.use_ssl:
            # Implicit SSL (port 465 — OVH, etc.)
            server = smtplib.SMTP_SSL(cfg.host, cfg.port, timeout=15)
        elif cfg.use_tls:
            # STARTTLS (port 587)
            server = smtplib.SMTP(cfg.host, cfg.port, timeout=15)
            server.ehlo()
            server.starttls()
            server.ehlo()
        else:
            # Plain SMTP (dev/MailHog)
            server = smtplib.SMTP(cfg.host, cfg.port, timeout=10)

        if cfg.user:
            server.login(cfg.user, cfg.password)

        server.sendmail(cfg.from_addr, [to], msg.as_string())
        server.quit()
        logger.info("Email envoyé à %s: %s", to, subject)
        return True
    except Exception:
        logger.exception("Erreur envoi email à %s", to)
        return False


def _email_header(atelier_nom: str, logo_url: str = "") -> str:
    """Shared HTML header with optional logo for all emails."""
    base_url = os.getenv("BASE_URL", "http://localhost:3000")
    logo_html = ""
    if logo_url:
        # Ensure absolute URL for email clients
        src = logo_url if logo_url.startswith("http") else f"{base_url}{logo_url}"
        logo_html = (
            f'<img src="{src}" alt="{atelier_nom}" '
            f'style="max-height:48px;max-width:180px;margin-right:14px;vertical-align:middle;border-radius:6px" />'
        )
    return (
        f'<div style="background:#1a1a2e;padding:24px 30px;display:flex;align-items:center">'
        f'{logo_html}'
        f'<h1 style="margin:0;color:#FB923C;font-size:20px">{atelier_nom}</h1>'
        f'</div>'
    )


def _email_footer(
    atelier_nom: str,
    atelier_telephone: str = "",
    atelier_adresse: str = "",
    atelier_email: str = "",
) -> str:
    """Shared HTML footer with atelier contact info."""
    parts = [atelier_nom]
    if atelier_adresse:
        parts.append(atelier_adresse)
    if atelier_telephone:
        parts.append(f"Tél : {atelier_telephone}")
    if atelier_email:
        parts.append(atelier_email)
    separator = " — "
    return (
        f'<div style="background:#f4f4f8;padding:16px 30px;text-align:center;color:#999;font-size:12px">'
        f'{separator.join(parts)}<br>'
        f'Email automatique, merci de ne pas répondre.'
        f'</div>'
    )


def render_rappel_rdv(
    client_prenom: str,
    client_nom: str,
    date_rdv: str,
    heure_rdv: str,
    type_intervention: str,
    atelier_nom: str,
    atelier_telephone: str = "",
    atelier_adresse: str = "",
    logo_url: str = "",
    atelier_email: str = "",
    vehicule_info: str = "",
    token_suivi: str = "",
    heure_fin: str = "",
) -> tuple[str, str, str]:
    """Génère sujet + body HTML + body texte pour un rappel de RDV.

    Returns (subject, html_body, text_body)
    """
    subject = f"Rappel : votre rendez-vous du {date_rdv} à {heure_rdv} — {atelier_nom}"
    suivi_link = _suivi_url(token_suivi)

    horaire_label = heure_rdv
    if heure_fin:
        horaire_label += f" → {heure_fin} (fin estimée)"
    rows = [("Date", date_rdv), ("Horaire", horaire_label), ("Intervention", type_intervention)]
    if vehicule_info:
        rows.append(("Véhicule", vehicule_info))
    if atelier_adresse:
        rows.append(("Adresse", atelier_adresse))

    text_body = (
        f"Bonjour {client_prenom},\n\n"
        f"Nous vous rappelons votre rendez-vous :\n\n"
        f"  Date : {date_rdv}\n"
        f"  Heure : {heure_rdv}\n"
        f"  Intervention : {type_intervention}\n"
        + (f"  Véhicule : {vehicule_info}\n" if vehicule_info else "")
        + "\n"
        f"Merci de vous présenter 5 minutes avant l'heure prévue.\n\n"
        f"En cas d'empêchement, contactez-nous"
        + (f" au {atelier_telephone}" if atelier_telephone else "")
        + ".\n\n"
        + (f"Suivi en ligne : {suivi_link}\n\n" if suivi_link else "")
        + f"Cordialement,\n{atelier_nom}"
    )

    body = f"""
    <p style="color:#333;font-size:16px">Bonjour <strong>{client_prenom}</strong>,</p>
    <p style="color:#555">Nous vous rappelons votre rendez-vous :</p>
    {_info_table(rows)}
    <p style="color:#555">Merci de vous présenter <strong>5 minutes</strong> avant l'heure prévue.</p>
    {"<p style='color:#555'>En cas d'empêchement, contactez-nous au <strong>" + atelier_telephone + "</strong>.</p>" if atelier_telephone else ""}
    {_cta_button(suivi_link, "Suivre mon rendez-vous") if suivi_link else ""}"""

    header = _email_header(atelier_nom, logo_url)
    footer = _email_footer(atelier_nom, atelier_telephone, atelier_adresse, atelier_email)
    html_body = _wrap_email(header, body, footer)

    return subject, html_body, text_body


def render_confirmation_rdv(
    client_prenom: str,
    date_rdv: str,
    heure_rdv: str,
    type_intervention: str,
    atelier_nom: str,
    atelier_telephone: str = "",
    token_suivi: str = "",
    logo_url: str = "",
    atelier_adresse: str = "",
    atelier_email: str = "",
    vehicule_info: str = "",
    heure_fin: str = "",
) -> tuple[str, str, str]:
    """Génère sujet + body HTML + body texte pour une confirmation de RDV."""
    subject = f"Confirmation de votre rendez-vous — {atelier_nom}"
    suivi_link = _suivi_url(token_suivi)

    horaire_label = heure_rdv
    if heure_fin:
        horaire_label += f" → {heure_fin} (fin estimée)"
    rows = [("Date", date_rdv), ("Horaire", horaire_label), ("Intervention", type_intervention)]
    if vehicule_info:
        rows.append(("Véhicule", vehicule_info))

    text_body = (
        f"Bonjour {client_prenom},\n\n"
        f"Votre rendez-vous est confirmé :\n\n"
        f"  Date : {date_rdv}\n"
        f"  Heure : {heure_rdv}"
        + (f" → fin estimée {heure_fin}" if heure_fin else "")
        + "\n"
        f"  Intervention : {type_intervention}\n"
        + (f"  Véhicule : {vehicule_info}\n" if vehicule_info else "")
        + "\n"
        + (f"Suivez votre rendez-vous en ligne : {suivi_link}\n\n" if suivi_link else "")
        + f"Cordialement,\n{atelier_nom}"
    )

    body = f"""
    <p style="color:#333;font-size:16px">Bonjour <strong>{client_prenom}</strong>,</p>
    <p style="color:#22c55e;font-weight:700;font-size:18px">✓ Rendez-vous confirmé</p>
    {_info_table(rows)}
    {_cta_button(suivi_link, "Suivre mon rendez-vous") if suivi_link else ""}
    {"<p style='color:#555'>En cas d'empêchement, contactez-nous au <strong>" + atelier_telephone + "</strong>.</p>" if atelier_telephone else ""}"""

    header = _email_header(atelier_nom, logo_url)
    footer = _email_footer(atelier_nom, atelier_telephone, atelier_adresse, atelier_email)
    html_body = _wrap_email(header, body, footer)

    return subject, html_body, text_body


# ── Helpers partagés ──────────────────────────────────────────────

def _suivi_url(token_suivi: str) -> str:
    base_url = os.getenv("BASE_URL", "http://localhost:3000")
    return f"{base_url}/suivi.html?token={token_suivi}" if token_suivi else ""


def _info_table(rows: list[tuple[str, str]]) -> str:
    """Build a branded info table from label/value pairs."""
    trs = []
    for i, (label, value) in enumerate(rows):
        bg = "background:#f9f9fb;" if i % 2 == 0 else ""
        trs.append(
            f'<tr><td style="padding:10px 14px;{bg}font-weight:600;width:140px;border:1px solid #eee">{label}</td>'
            f'<td style="padding:10px 14px;{bg}border:1px solid #eee">{value}</td></tr>'
        )
    return f'<table style="width:100%;border-collapse:collapse;margin:18px 0">{"".join(trs)}</table>'


def _cta_button(href: str, label: str, color: str = "#FB923C") -> str:
    return (
        f'<div style="margin:24px 0;text-align:center">'
        f'<a href="{href}" style="display:inline-block;background:{color};color:#fff;'
        f'text-decoration:none;padding:14px 32px;border-radius:8px;font-weight:600;font-size:15px">{label}</a>'
        f'</div>'
    )


def _wrap_email(header: str, body_html: str, footer: str) -> str:
    return f"""<!DOCTYPE html>
<html><head><meta charset="utf-8"></head>
<body style="font-family:Arial,sans-serif;background:#f4f4f8;padding:20px;margin:0">
<div style="max-width:560px;margin:0 auto;background:#fff;border-radius:10px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.08)">
  {header}
  <div style="padding:28px 30px">
    {body_html}
  </div>
  {footer}
</div>
</body></html>"""


# ── Status badge colors ──────────────────────────────────────────

_STATUS_COLORS = {
    "confirme": ("#22c55e", "Confirmé"),
    "reception": ("#3b82f6", "Réceptionné"),
    "en_cours": ("#f59e0b", "En cours"),
    "termine": ("#8b5cf6", "Terminé"),
    "restitue": ("#06b6d4", "Restitué"),
    "facture": ("#6366f1", "Facturé"),
    "paye": ("#22c55e", "Payé"),
    "annule": ("#ef4444", "Annulé"),
}


def _status_badge(statut: str) -> str:
    color, label = _STATUS_COLORS.get(statut, ("#999", statut))
    return (
        f'<span style="display:inline-block;background:{color};color:#fff;'
        f'padding:6px 16px;border-radius:20px;font-weight:600;font-size:14px">{label}</span>'
    )


# ── RAPPEL RDV AMÉLIORÉ (avec lien suivi) ────────────────────────

def render_rappel_rdv_enhanced(
    client_prenom: str,
    date_rdv: str,
    heure_rdv: str,
    type_intervention: str,
    atelier_nom: str,
    atelier_telephone: str = "",
    atelier_adresse: str = "",
    logo_url: str = "",
    atelier_email: str = "",
    token_suivi: str = "",
    delai: str = "",
    vehicule_info: str = "",
    mecanicien_nom: str = "",
    heure_fin: str = "",
) -> tuple[str, str, str]:
    """Rappel RDV amélioré avec lien suivi (48h/24h/4h)."""
    subject = f"Rappel : votre rendez-vous {delai} — {atelier_nom}"

    suivi_link = _suivi_url(token_suivi)
    horaire_label = heure_rdv
    if heure_fin:
        horaire_label += f" → {heure_fin} (fin estimée)"
    rows = [("Date", date_rdv), ("Horaire", horaire_label), ("Intervention", type_intervention)]
    if vehicule_info:
        rows.append(("Véhicule", vehicule_info))
    if mecanicien_nom:
        rows.append(("Mécanicien", mecanicien_nom))
    if atelier_adresse:
        rows.append(("Adresse", atelier_adresse))

    body = f"""
    <p style="color:#333;font-size:16px">Bonjour <strong>{client_prenom}</strong>,</p>
    <p style="color:#555">Nous vous rappelons votre rendez-vous :</p>
    {_info_table(rows)}
    <p style="color:#555">Merci de vous présenter <strong>5 minutes</strong> avant l'heure prévue.</p>
    {"<p style='color:#555'>En cas d'empêchement, contactez-nous au <strong>" + atelier_telephone + "</strong>.</p>" if atelier_telephone else ""}
    {_cta_button(suivi_link, "Suivre mon rendez-vous") if suivi_link else ""}"""

    header = _email_header(atelier_nom, logo_url)
    footer = _email_footer(atelier_nom, atelier_telephone, atelier_adresse, atelier_email)
    html_body = _wrap_email(header, body, footer)

    text_body = (
        f"Bonjour {client_prenom},\n\n"
        f"Rappel de votre rendez-vous :\n"
        f"Date: {date_rdv} à {heure_rdv}\n"
        f"Intervention: {type_intervention}\n"
        + (f"Suivi: {suivi_link}\n" if suivi_link else "")
        + f"\nCordialement,\n{atelier_nom}"
    )
    return subject, html_body, text_body


# ── SUIVI INTERVENTION (changements de statut) ───────────────────

def render_suivi_statut(
    client_prenom: str,
    statut: str,
    titre: str,
    message_principal: str,
    atelier_nom: str,
    atelier_telephone: str = "",
    atelier_adresse: str = "",
    logo_url: str = "",
    atelier_email: str = "",
    token_suivi: str = "",
    vehicule_info: str = "",
    details_extra: list[tuple[str, str]] | None = None,
    date_rdv: str = "",
    heure_rdv: str = "",
    type_intervention: str = "",
) -> tuple[str, str, str]:
    """Email de suivi pour tout changement de statut."""
    subject = f"{titre} — {atelier_nom}"
    suivi_link = _suivi_url(token_suivi)

    rows = []
    if vehicule_info:
        rows.append(("Véhicule", vehicule_info))
    rows.append(("Statut", _STATUS_COLORS.get(statut, ("#999", statut))[1]))
    if date_rdv:
        rows.append(("Date RDV", date_rdv))
    if heure_rdv:
        rows.append(("Heure", heure_rdv))
    if type_intervention:
        rows.append(("Intervention", type_intervention))
    for label, value in (details_extra or []):
        rows.append((label, value))

    body = f"""
    <p style="color:#333;font-size:16px">Bonjour <strong>{client_prenom}</strong>,</p>
    <div style="text-align:center;margin:20px 0">{_status_badge(statut)}</div>
    <p style="color:#555;font-size:15px">{message_principal}</p>
    {_info_table(rows) if rows else ""}
    {_cta_button(suivi_link, "Suivre l'avancement") if suivi_link else ""}
    {"<p style='color:#555'>Une question ? Contactez-nous au <strong>" + atelier_telephone + "</strong>.</p>" if atelier_telephone else ""}"""

    header = _email_header(atelier_nom, logo_url)
    footer = _email_footer(atelier_nom, atelier_telephone, atelier_adresse, atelier_email)
    html_body = _wrap_email(header, body, footer)

    text_body = (
        f"Bonjour {client_prenom},\n\n"
        f"{titre}\n{message_principal}\n"
        + (f"Suivi: {suivi_link}\n" if suivi_link else "")
        + f"\nCordialement,\n{atelier_nom}"
    )
    return subject, html_body, text_body


# ── SIGNATURE OR COMPLÉMENTAIRE ──────────────────────────────────

def render_signature_or(
    client_prenom: str,
    description_travaux: str,
    urgence: str,
    prix_estime: str,
    atelier_nom: str,
    atelier_telephone: str = "",
    atelier_adresse: str = "",
    logo_url: str = "",
    atelier_email: str = "",
    token_suivi: str = "",
    vehicule_info: str = "",
    signature_url: str = "",
    date_rdv: str = "",
    heure_rdv: str = "",
    type_intervention: str = "",
) -> tuple[str, str, str]:
    """Email pour demande de signature OR complémentaire."""
    subject = f"Travaux complémentaires à valider — {atelier_nom}"
    suivi_link = _suivi_url(token_suivi)

    urgence_colors = {"normal": "#3b82f6", "urgent": "#f59e0b", "critique": "#ef4444"}
    urgence_color = urgence_colors.get(urgence, "#3b82f6")
    urgence_label = urgence.capitalize()

    rows = []
    if vehicule_info:
        rows.append(("Véhicule", vehicule_info))
    if date_rdv:
        rows.append(("Date RDV", date_rdv))
    if heure_rdv:
        rows.append(("Heure", heure_rdv))
    if type_intervention:
        rows.append(("Intervention", type_intervention))
    rows.append(("Travaux", description_travaux))
    rows.append(("Urgence", f'<span style="color:{urgence_color};font-weight:600">{urgence_label}</span>'))
    if prix_estime:
        rows.append(("Estimation", f"{prix_estime} €"))

    body = f"""
    <p style="color:#333;font-size:16px">Bonjour <strong>{client_prenom}</strong>,</p>
    <p style="color:#555">Au cours de l'intervention sur votre véhicule, notre mécanicien a identifié des travaux complémentaires nécessaires :</p>
    {_info_table(rows)}
    <p style="color:#555;font-weight:600">Votre accord est nécessaire pour poursuivre ces travaux.</p>
    {_cta_button(signature_url or suivi_link, "Consulter et signer", "#1a1a2e") if (signature_url or suivi_link) else ""}
    {"<p style='color:#555'>Questions ? Contactez-nous au <strong>" + atelier_telephone + "</strong>.</p>" if atelier_telephone else ""}"""

    header = _email_header(atelier_nom, logo_url)
    footer = _email_footer(atelier_nom, atelier_telephone, atelier_adresse, atelier_email)
    html_body = _wrap_email(header, body, footer)

    text_body = (
        f"Bonjour {client_prenom},\n\n"
        f"Des travaux complémentaires ont été identifiés sur votre véhicule :\n"
        f"Travaux : {description_travaux}\n"
        f"Urgence : {urgence_label}\n"
        + (f"Estimation : {prix_estime} €\n" if prix_estime else "")
        + (f"Signer en ligne : {signature_url or suivi_link}\n" if (signature_url or suivi_link) else "")
        + f"\nCordialement,\n{atelier_nom}"
    )
    return subject, html_body, text_body


# ── COMPTE RENDU INTERVENTION ────────────────────────────────────

def render_compte_rendu(
    client_prenom: str,
    atelier_nom: str,
    vehicule_info: str = "",
    travaux_realises: str = "",
    recommandations: str = "",
    kilometrage: str = "",
    photos_count: int = 0,
    atelier_telephone: str = "",
    atelier_adresse: str = "",
    logo_url: str = "",
    atelier_email: str = "",
    token_suivi: str = "",
    date_rdv: str = "",
    heure_rdv: str = "",
    type_intervention: str = "",
) -> tuple[str, str, str]:
    """Email de compte rendu à la clôture de l'intervention."""
    subject = f"Compte rendu d'intervention — {atelier_nom}"
    suivi_link = _suivi_url(token_suivi)

    rows = []
    if vehicule_info:
        rows.append(("Véhicule", vehicule_info))
    if date_rdv:
        rows.append(("Date RDV", date_rdv))
    if heure_rdv:
        rows.append(("Heure", heure_rdv))
    if type_intervention:
        rows.append(("Intervention", type_intervention))
    if kilometrage:
        rows.append(("Kilométrage", f"{kilometrage} km"))
    if travaux_realises:
        rows.append(("Travaux réalisés", travaux_realises.replace("\n", "<br>")))
    if recommandations:
        rows.append(("Recommandations", recommandations.replace("\n", "<br>")))
    if photos_count > 0:
        rows.append(("Photos", f"{photos_count} photo(s) disponible(s) en ligne"))

    body = f"""
    <p style="color:#333;font-size:16px">Bonjour <strong>{client_prenom}</strong>,</p>
    <p style="color:#8b5cf6;font-weight:700;font-size:18px">✓ Intervention terminée</p>
    <p style="color:#555">Voici le résumé de l'intervention réalisée sur votre véhicule :</p>
    {_info_table(rows)}
    {_cta_button(suivi_link, "Voir les détails et photos") if suivi_link else ""}
    {"<p style='color:#555'>Pour toute question, contactez-nous au <strong>" + atelier_telephone + "</strong>.</p>" if atelier_telephone else ""}"""

    header = _email_header(atelier_nom, logo_url)
    footer = _email_footer(atelier_nom, atelier_telephone, atelier_adresse, atelier_email)
    html_body = _wrap_email(header, body, footer)

    text_body = (
        f"Bonjour {client_prenom},\n\n"
        f"Intervention terminée.\n"
        + (f"Véhicule : {vehicule_info}\n" if vehicule_info else "")
        + (f"Km : {kilometrage}\n" if kilometrage else "")
        + (f"Travaux : {travaux_realises}\n" if travaux_realises else "")
        + (f"Recommandations : {recommandations}\n" if recommandations else "")
        + (f"Photos et détails : {suivi_link}\n" if suivi_link else "")
        + f"\nCordialement,\n{atelier_nom}"
    )
    return subject, html_body, text_body


# ── FACTURE / DEVIS (avec PDF en PJ) ─────────────────────────────

def render_facture_email(
    client_prenom: str,
    atelier_nom: str,
    numero_facture: str,
    montant_ttc: str,
    vehicule_info: str = "",
    atelier_telephone: str = "",
    atelier_adresse: str = "",
    logo_url: str = "",
    atelier_email: str = "",
    is_paid: bool = False,
    token_suivi: str = "",
    date_rdv: str = "",
    type_intervention: str = "",
) -> tuple[str, str, str]:
    """Email d'envoi de facture (PDF joint séparément)."""
    if is_paid:
        subject = f"Paiement reçu — Merci ! — {atelier_nom}"
        titre = "Paiement confirmé"
        color = "#22c55e"
        msg = "Nous avons bien reçu votre paiement. Vous trouverez votre facture acquittée en pièce jointe."
    else:
        subject = f"Votre facture {numero_facture} — {atelier_nom}"
        titre = "Votre facture est disponible"
        color = "#6366f1"
        msg = "Vous trouverez votre facture en pièce jointe."

    suivi_link = _suivi_url(token_suivi)
    rows = [("N° Facture", numero_facture), ("Montant TTC", f"{montant_ttc} €")]
    if vehicule_info:
        rows.append(("Véhicule", vehicule_info))
    if date_rdv:
        rows.append(("Date RDV", date_rdv))
    if type_intervention:
        rows.append(("Intervention", type_intervention))

    body = f"""
    <p style="color:#333;font-size:16px">Bonjour <strong>{client_prenom}</strong>,</p>
    <p style="color:{color};font-weight:700;font-size:18px">{"✓ " if is_paid else ""}{titre}</p>
    <p style="color:#555">{msg}</p>
    {_info_table(rows)}
    <p style="color:#888;font-size:13px">📎 Facture PDF jointe à cet email.</p>
    {_cta_button(suivi_link, "Voir le détail de l'intervention") if suivi_link else ""}
    {"<p style='color:#555'>Pour toute question, contactez-nous au <strong>" + atelier_telephone + "</strong>.</p>" if atelier_telephone else ""}"""

    header = _email_header(atelier_nom, logo_url)
    footer = _email_footer(atelier_nom, atelier_telephone, atelier_adresse, atelier_email)
    html_body = _wrap_email(header, body, footer)

    text_body = (
        f"Bonjour {client_prenom},\n\n"
        f"{titre}\n"
        f"Facture : {numero_facture}\n"
        f"Montant : {montant_ttc} €\n\n"
        f"La facture PDF est jointe à cet email.\n\n"
        f"Cordialement,\n{atelier_nom}"
    )
    return subject, html_body, text_body


# ── DÉPLACEMENT DE RDV ───────────────────────────────────────────

def render_deplacement_rdv(
    client_prenom: str,
    ancienne_date: str,
    ancienne_heure: str,
    nouvelle_date: str,
    nouvelle_heure: str,
    type_intervention: str,
    atelier_nom: str,
    atelier_telephone: str = "",
    atelier_adresse: str = "",
    logo_url: str = "",
    atelier_email: str = "",
    vehicule_info: str = "",
    token_suivi: str = "",
    heure_fin: str = "",
) -> tuple[str, str, str]:
    """Email de notification de déplacement de rendez-vous."""
    subject = f"Votre rendez-vous a été déplacé — {atelier_nom}"
    suivi_link = _suivi_url(token_suivi)

    nouvelle_horaire = nouvelle_heure
    if heure_fin:
        nouvelle_horaire += f" → {heure_fin} (fin estimée)"

    rows = [
        ("Ancienne date", ancienne_date),
        ("Ancienne heure", ancienne_heure),
        ("Nouvelle date", f"<strong style='color:#FB923C'>{nouvelle_date}</strong>"),
        ("Nouvelle heure", f"<strong style='color:#FB923C'>{nouvelle_horaire}</strong>"),
        ("Intervention", type_intervention),
    ]
    if vehicule_info:
        rows.append(("Véhicule", vehicule_info))

    text_body = (
        f"Bonjour {client_prenom},\n\n"
        f"Votre rendez-vous a été déplacé.\n\n"
        f"  Ancien créneau : {ancienne_date} à {ancienne_heure}\n"
        f"  Nouveau créneau : {nouvelle_date} à {nouvelle_heure}"
        + (f" → fin estimée {heure_fin}" if heure_fin else "")
        + "\n"
        f"  Intervention : {type_intervention}\n"
        + (f"  Véhicule : {vehicule_info}\n" if vehicule_info else "")
        + "\n"
        + (f"Suivez votre rendez-vous en ligne : {suivi_link}\n\n" if suivi_link else "")
        + (f"En cas d'empêchement, contactez-nous au {atelier_telephone}.\n\n" if atelier_telephone else "")
        + f"Cordialement,\n{atelier_nom}"
    )

    body = f"""
    <p style="color:#333;font-size:16px">Bonjour <strong>{client_prenom}</strong>,</p>
    <p style="color:#f59e0b;font-weight:700;font-size:18px">📅 Rendez-vous déplacé</p>
    <p style="color:#555">Votre rendez-vous a été reprogrammé aux nouvelles dates ci-dessous :</p>
    {_info_table(rows)}
    {_cta_button(suivi_link, "Suivre mon rendez-vous") if suivi_link else ""}
    {"<p style='color:#555'>En cas d'empêchement, contactez-nous au <strong>" + atelier_telephone + "</strong>.</p>" if atelier_telephone else ""}"""

    header = _email_header(atelier_nom, logo_url)
    footer = _email_footer(atelier_nom, atelier_telephone, atelier_adresse, atelier_email)
    html_body = _wrap_email(header, body, footer)

    return subject, html_body, text_body


# ── RELANCE CLIENT INACTIF ───────────────────────────────────────

def render_relance_client(
    client_prenom: str,
    atelier_nom: str,
    niveau_relance: int,
    vehicule_info: str = "",
    atelier_telephone: str = "",
    atelier_adresse: str = "",
    logo_url: str = "",
    atelier_email: str = "",
) -> tuple[str, str, str]:
    """Email de relance pour client inactif (3 niveaux)."""
    messages = {
        1: {
            "subject": f"Ça fait un moment ! Votre moto va bien ? — {atelier_nom}",
            "titre": "On prend des nouvelles",
            "message": "Cela fait quelques mois que nous ne vous avons pas vu à l'atelier. "
                       "N'oubliez pas qu'un entretien régulier est essentiel pour la longévité de votre moto.",
        },
        2: {
            "subject": f"Pensez à l'entretien de votre moto — {atelier_nom}",
            "titre": "Entretien recommandé",
            "message": "Vidange, plaquettes de frein, pneus… Un contrôle régulier permet d'éviter "
                       "les mauvaises surprises. Prenez rendez-vous pour un check-up !",
        },
        3: {
            "subject": f"On vous attend à l'atelier — {atelier_nom}",
            "titre": "Dernière visite il y a plus d'un an",
            "message": "Votre moto mérite une attention particulière. Passez nous voir pour un bilan complet. "
                       "Nous serons ravis de vous accueillir !",
        },
    }
    tpl = messages.get(niveau_relance, messages[1])

    rows = []
    if vehicule_info:
        rows.append(("Votre véhicule", vehicule_info))

    base_url = os.getenv("BASE_URL", "http://localhost:3000")
    booking_url = f"{base_url}/rendez-vous.html"

    body = f"""
    <p style="color:#333;font-size:16px">Bonjour <strong>{client_prenom}</strong>,</p>
    <p style="color:#FB923C;font-weight:700;font-size:18px">{tpl['titre']}</p>
    <p style="color:#555;font-size:15px">{tpl['message']}</p>
    {_info_table(rows) if rows else ""}
    {_cta_button(booking_url, "Prendre rendez-vous")}
    {"<p style='color:#555'>Ou appelez-nous au <strong>" + atelier_telephone + "</strong>.</p>" if atelier_telephone else ""}"""

    header = _email_header(atelier_nom, logo_url)
    footer = _email_footer(atelier_nom, atelier_telephone, atelier_adresse, atelier_email)
    html_body = _wrap_email(header, body, footer)

    text_body = (
        f"Bonjour {client_prenom},\n\n"
        f"{tpl['message']}\n\n"
        + (f"Véhicule : {vehicule_info}\n" if vehicule_info else "")
        + f"Prendre rendez-vous : {booking_url}\n"
        + f"\nCordialement,\n{atelier_nom}"
    )
    return tpl["subject"], html_body, text_body
