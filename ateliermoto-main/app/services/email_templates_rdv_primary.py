"""Appointment reminder and confirmation email templates."""

from services.email_layout import cta_button, email_footer, email_header, info_table, suivi_url, wrap_email


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
    """Genere sujet + body HTML + body texte pour un rappel de RDV."""
    subject = f"Rappel : votre rendez-vous du {date_rdv} a {heure_rdv} - {atelier_nom}"
    suivi_link = suivi_url(token_suivi)

    horaire_label = heure_rdv
    if heure_fin:
        horaire_label += f" -> {heure_fin} (fin estimee)"
    rows = [("Date", date_rdv), ("Horaire", horaire_label), ("Intervention", type_intervention)]
    if vehicule_info:
        rows.append(("Vehicule", vehicule_info))
    if atelier_adresse:
        rows.append(("Adresse", atelier_adresse))

    text_body = (
        f"Bonjour {client_prenom},\n\n"
        f"Nous vous rappelons votre rendez-vous :\n\n"
        f"  Date : {date_rdv}\n"
        f"  Heure : {heure_rdv}\n"
        f"  Intervention : {type_intervention}\n"
        + (f"  Vehicule : {vehicule_info}\n" if vehicule_info else "")
        + "\n"
        f"Merci de vous presenter 5 minutes avant l'heure prevue.\n\n"
        f"En cas d'empechement, contactez-nous"
        + (f" au {atelier_telephone}" if atelier_telephone else "")
        + ".\n\n"
        + (f"Suivi en ligne : {suivi_link}\n\n" if suivi_link else "")
        + f"Cordialement,\n{atelier_nom}"
    )

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
    """Genere sujet + body HTML + body texte pour une confirmation de RDV."""
    subject = f"Confirmation de votre rendez-vous - {atelier_nom}"
    suivi_link = suivi_url(token_suivi)

    horaire_label = heure_rdv
    if heure_fin:
        horaire_label += f" -> {heure_fin} (fin estimee)"
    rows = [("Date", date_rdv), ("Horaire", horaire_label), ("Intervention", type_intervention)]
    if vehicule_info:
        rows.append(("Vehicule", vehicule_info))

    text_body = (
        f"Bonjour {client_prenom},\n\n"
        f"Votre rendez-vous est confirme :\n\n"
        f"  Date : {date_rdv}\n"
        f"  Heure : {heure_rdv}"
        + (f" -> fin estimee {heure_fin}" if heure_fin else "")
        + "\n"
        f"  Intervention : {type_intervention}\n"
        + (f"  Vehicule : {vehicule_info}\n" if vehicule_info else "")
        + "\n"
        + (f"Suivez votre rendez-vous en ligne : {suivi_link}\n\n" if suivi_link else "")
        + f"Cordialement,\n{atelier_nom}"
    )

    body = f"""
    <p style="color:#333;font-size:16px">Bonjour <strong>{client_prenom}</strong>,</p>
    <p style="color:#22c55e;font-weight:700;font-size:18px">✓ Rendez-vous confirmé</p>
    {info_table(rows)}
    {cta_button(suivi_link, "Suivre mon rendez-vous") if suivi_link else ""}
    {"<p style='color:#555'>En cas d'empêchement, contactez-nous au <strong>" + atelier_telephone + "</strong>.</p>" if atelier_telephone else ""}"""

    header = email_header(atelier_nom, logo_url)
    footer = email_footer(atelier_nom, atelier_telephone, atelier_adresse, atelier_email)
    html_body = wrap_email(header, body, footer)

    return subject, html_body, text_body
