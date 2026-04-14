"""Appointment update and move email templates."""

from services.email_layout import cta_button, email_footer, email_header, info_table, suivi_url, wrap_email


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
    """Rappel RDV ameliore avec lien suivi (48h/24h/4h)."""
    subject = f"Rappel : votre rendez-vous {delai} - {atelier_nom}"

    suivi_link = suivi_url(token_suivi)
    horaire_label = heure_rdv
    if heure_fin:
        horaire_label += f" -> {heure_fin} (fin estimee)"
    rows = [("Date", date_rdv), ("Horaire", horaire_label), ("Intervention", type_intervention)]
    if vehicule_info:
        rows.append(("Vehicule", vehicule_info))
    if mecanicien_nom:
        rows.append(("Mecanicien", mecanicien_nom))
    if atelier_adresse:
        rows.append(("Adresse", atelier_adresse))

    body = f"""
    <p style="color:#333;font-size:16px">Bonjour <strong>{client_prenom}</strong>,</p>
    <p style="color:#555">Nous vous rappelons votre rendez-vous :</p>
    {info_table(rows)}
    <p style="color:#555">Merci de vous présenter <strong>5 minutes</strong> avant l'heure prévue.</p>
    {"<p style='color:#555'>En cas d'empêchement, contactez-nous au <strong>" + atelier_telephone + "</strong>.</p>" if atelier_telephone else ""}
    {cta_button(suivi_link, "Suivre mon rendez-vous") if suivi_link else ""}"""

    header = email_header(atelier_nom, logo_url)
    footer = email_footer(atelier_nom, atelier_telephone, atelier_adresse, atelier_email)
    html_body = wrap_email(header, body, footer)

    text_body = (
        f"Bonjour {client_prenom},\n\n"
        f"Rappel de votre rendez-vous :\n"
        f"Date: {date_rdv} a {heure_rdv}\n"
        f"Intervention: {type_intervention}\n"
        + (f"Suivi: {suivi_link}\n" if suivi_link else "")
        + f"\nCordialement,\n{atelier_nom}"
    )
    return subject, html_body, text_body


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
    """Email de notification de deplacement de rendez-vous."""
    subject = f"Votre rendez-vous a ete deplace - {atelier_nom}"
    suivi_link = suivi_url(token_suivi)

    nouvelle_horaire = nouvelle_heure
    if heure_fin:
        nouvelle_horaire += f" -> {heure_fin} (fin estimee)"

    rows = [
        ("Ancienne date", ancienne_date),
        ("Ancienne heure", ancienne_heure),
        ("Nouvelle date", f"<strong style='color:#FB923C'>{nouvelle_date}</strong>"),
        ("Nouvelle heure", f"<strong style='color:#FB923C'>{nouvelle_horaire}</strong>"),
        ("Intervention", type_intervention),
    ]
    if vehicule_info:
        rows.append(("Vehicule", vehicule_info))

    text_body = (
        f"Bonjour {client_prenom},\n\n"
        f"Votre rendez-vous a ete deplace.\n\n"
        f"  Ancien creneau : {ancienne_date} a {ancienne_heure}\n"
        f"  Nouveau creneau : {nouvelle_date} a {nouvelle_heure}"
        + (f" -> fin estimee {heure_fin}" if heure_fin else "")
        + "\n"
        f"  Intervention : {type_intervention}\n"
        + (f"  Vehicule : {vehicule_info}\n" if vehicule_info else "")
        + "\n"
        + (f"Suivez votre rendez-vous en ligne : {suivi_link}\n\n" if suivi_link else "")
        + (f"En cas d'empechement, contactez-nous au {atelier_telephone}.\n\n" if atelier_telephone else "")
        + f"Cordialement,\n{atelier_nom}"
    )

    body = f"""
    <p style="color:#333;font-size:16px">Bonjour <strong>{client_prenom}</strong>,</p>
    <p style="color:#f59e0b;font-weight:700;font-size:18px">📅 Rendez-vous déplacé</p>
    <p style="color:#555">Votre rendez-vous a été reprogrammé aux nouvelles dates ci-dessous :</p>
    {info_table(rows)}
    {cta_button(suivi_link, "Suivre mon rendez-vous") if suivi_link else ""}
    {"<p style='color:#555'>En cas d'empêchement, contactez-nous au <strong>" + atelier_telephone + "</strong>.</p>" if atelier_telephone else ""}"""

    header = email_header(atelier_nom, logo_url)
    footer = email_footer(atelier_nom, atelier_telephone, atelier_adresse, atelier_email)
    html_body = wrap_email(header, body, footer)

    return subject, html_body, text_body
