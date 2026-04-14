"""Shared email layout helpers for HTML templates."""

import os


def suivi_url(token_suivi: str) -> str:
    base_url = os.getenv("BASE_URL", "http://localhost:3000")
    return f"{base_url}/suivi.html?token={token_suivi}" if token_suivi else ""


def info_table(rows: list[tuple[str, str]]) -> str:
    """Build a branded info table from label/value pairs."""
    trs = []
    for i, (label, value) in enumerate(rows):
        bg = "background:#f9f9fb;" if i % 2 == 0 else ""
        trs.append(
            f'<tr><td style="padding:10px 14px;{bg}font-weight:600;width:140px;border:1px solid #eee">{label}</td>'
            f'<td style="padding:10px 14px;{bg}border:1px solid #eee">{value}</td></tr>'
        )
    return f'<table style="width:100%;border-collapse:collapse;margin:18px 0">{"".join(trs)}</table>'


def cta_button(href: str, label: str, color: str = "#FB923C") -> str:
    return (
        f'<div style="margin:24px 0;text-align:center">'
        f'<a href="{href}" style="display:inline-block;background:{color};color:#fff;'
        f'text-decoration:none;padding:14px 32px;border-radius:8px;font-weight:600;font-size:15px">{label}</a>'
        f'</div>'
    )


def wrap_email(header: str, body_html: str, footer: str) -> str:
    return f"""<!DOCTYPE html>
<html><head><meta charset=\"utf-8\"></head>
<body style=\"font-family:Arial,sans-serif;background:#f4f4f8;padding:20px;margin:0\">
<div style=\"max-width:560px;margin:0 auto;background:#fff;border-radius:10px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.08)\">
  {header}
  <div style=\"padding:28px 30px\">
    {body_html}
  </div>
  {footer}
</div>
</body></html>"""


def email_header(atelier_nom: str, logo_url: str = "") -> str:
    """Shared HTML header with optional logo for all emails."""
    base_url = os.getenv("BASE_URL", "http://localhost:3000")
    logo_html = ""
    if logo_url:
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


def email_footer(
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


STATUS_COLORS = {
    "confirme": ("#22c55e", "Confirmé"),
    "reception": ("#3b82f6", "Réceptionné"),
    "en_cours": ("#f59e0b", "En cours"),
    "termine": ("#8b5cf6", "Terminé"),
    "restitue": ("#06b6d4", "Restitué"),
    "facture": ("#6366f1", "Facturé"),
    "paye": ("#22c55e", "Payé"),
    "annule": ("#ef4444", "Annulé"),
}


def status_badge(statut: str) -> str:
    color, label = STATUS_COLORS.get(statut, ("#999", statut))
    return (
        f'<span style="display:inline-block;background:{color};color:#fff;'
        f'padding:6px 16px;border-radius:20px;font-weight:600;font-size:14px">{label}</span>'
    )
