"""Status and intervention business email templates."""

from services.email_layout import (
    STATUS_COLORS,
    cta_button,
    email_footer,
    email_header,
    info_table,
    status_badge,
    suivi_url,
    wrap_email,
)


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
    subject = f"{titre} - {atelier_nom}"
    suivi_link = suivi_url(token_suivi)

    rows = []
    if vehicule_info:
        rows.append(("Vehicule", vehicule_info))
    rows.append(("Statut", STATUS_COLORS.get(statut, ("#999", statut))[1]))
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
    <div style="text-align:center;margin:20px 0">{status_badge(statut)}</div>
    <p style="color:#555;font-size:15px">{message_principal}</p>
    {info_table(rows) if rows else ""}
    {cta_button(suivi_link, "Suivre l'avancement") if suivi_link else ""}
    {"<p style='color:#555'>Une question ? Contactez-nous au <strong>" + atelier_telephone + "</strong>.</p>" if atelier_telephone else ""}"""

    header = email_header(atelier_nom, logo_url)
    footer = email_footer(atelier_nom, atelier_telephone, atelier_adresse, atelier_email)
    html_body = wrap_email(header, body, footer)

    text_body = (
        f"Bonjour {client_prenom},\n\n"
        f"{titre}\n{message_principal}\n"
        + (f"Suivi: {suivi_link}\n" if suivi_link else "")
        + f"\nCordialement,\n{atelier_nom}"
    )
    return subject, html_body, text_body


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
    """Email pour demande de signature OR complementaire."""
    subject = f"Travaux complementaires a valider - {atelier_nom}"
    suivi_link = suivi_url(token_suivi)

    urgence_colors = {"normal": "#3b82f6", "urgent": "#f59e0b", "critique": "#ef4444"}
    urgence_color = urgence_colors.get(urgence, "#3b82f6")
    urgence_label = urgence.capitalize()

    rows = []
    if vehicule_info:
        rows.append(("Vehicule", vehicule_info))
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
    {info_table(rows)}
    <p style="color:#555;font-weight:600">Votre accord est nécessaire pour poursuivre ces travaux.</p>
    {cta_button(signature_url or suivi_link, "Consulter et signer", "#1a1a2e") if (signature_url or suivi_link) else ""}
    {"<p style='color:#555'>Questions ? Contactez-nous au <strong>" + atelier_telephone + "</strong>.</p>" if atelier_telephone else ""}"""

    header = email_header(atelier_nom, logo_url)
    footer = email_footer(atelier_nom, atelier_telephone, atelier_adresse, atelier_email)
    html_body = wrap_email(header, body, footer)

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
    """Email de compte rendu a la cloture de l'intervention."""
    subject = f"Compte rendu d'intervention - {atelier_nom}"
    suivi_link = suivi_url(token_suivi)

    rows = []
    if vehicule_info:
        rows.append(("Vehicule", vehicule_info))
    if date_rdv:
        rows.append(("Date RDV", date_rdv))
    if heure_rdv:
        rows.append(("Heure", heure_rdv))
    if type_intervention:
        rows.append(("Intervention", type_intervention))
    if kilometrage:
        rows.append(("Kilometrage", f"{kilometrage} km"))
    if travaux_realises:
        rows.append(("Travaux realises", travaux_realises.replace("\n", "<br>")))
    if recommandations:
        rows.append(("Recommandations", recommandations.replace("\n", "<br>")))
    if photos_count > 0:
        rows.append(("Photos", f"{photos_count} photo(s) disponible(s) en ligne"))

    body = f"""
    <p style="color:#333;font-size:16px">Bonjour <strong>{client_prenom}</strong>,</p>
    <p style="color:#8b5cf6;font-weight:700;font-size:18px">✓ Intervention terminée</p>
    <p style="color:#555">Voici le résumé de l'intervention réalisée sur votre véhicule :</p>
    {info_table(rows)}
    {cta_button(suivi_link, "Voir les details et photos") if suivi_link else ""}
    {"<p style='color:#555'>Pour toute question, contactez-nous au <strong>" + atelier_telephone + "</strong>.</p>" if atelier_telephone else ""}"""

    header = email_header(atelier_nom, logo_url)
    footer = email_footer(atelier_nom, atelier_telephone, atelier_adresse, atelier_email)
    html_body = wrap_email(header, body, footer)

    text_body = (
        f"Bonjour {client_prenom},\n\n"
        f"Intervention terminee.\n"
        + (f"Vehicule : {vehicule_info}\n" if vehicule_info else "")
        + (f"Km : {kilometrage}\n" if kilometrage else "")
        + (f"Travaux : {travaux_realises}\n" if travaux_realises else "")
        + (f"Recommandations : {recommandations}\n" if recommandations else "")
        + (f"Photos et details : {suivi_link}\n" if suivi_link else "")
        + f"\nCordialement,\n{atelier_nom}"
    )
    return subject, html_body, text_body
