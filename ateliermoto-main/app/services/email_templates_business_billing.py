"""Billing and inactive customer email templates."""

import os

from services.email_layout import cta_button, email_footer, email_header, info_table, suivi_url, wrap_email


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
    """Email d'envoi de facture (PDF joint separement)."""
    if is_paid:
        subject = f"Paiement recu - Merci ! - {atelier_nom}"
        titre = "Paiement confirme"
        color = "#22c55e"
        msg = "Nous avons bien recu votre paiement. Vous trouverez votre facture acquittee en piece jointe."
    else:
        subject = f"Votre facture {numero_facture} - {atelier_nom}"
        titre = "Votre facture est disponible"
        color = "#6366f1"
        msg = "Vous trouverez votre facture en piece jointe."

    suivi_link = suivi_url(token_suivi)
    rows = [("N° Facture", numero_facture), ("Montant TTC", f"{montant_ttc} €")]
    if vehicule_info:
        rows.append(("Vehicule", vehicule_info))
    if date_rdv:
        rows.append(("Date RDV", date_rdv))
    if type_intervention:
        rows.append(("Intervention", type_intervention))

    body = f"""
    <p style="color:#333;font-size:16px">Bonjour <strong>{client_prenom}</strong>,</p>
    <p style="color:{color};font-weight:700;font-size:18px">{"✓ " if is_paid else ""}{titre}</p>
    <p style="color:#555">{msg}</p>
    {info_table(rows)}
    <p style="color:#888;font-size:13px">📎 Facture PDF jointe à cet email.</p>
    {cta_button(suivi_link, "Voir le detail de l'intervention") if suivi_link else ""}
    {"<p style='color:#555'>Pour toute question, contactez-nous au <strong>" + atelier_telephone + "</strong>.</p>" if atelier_telephone else ""}"""

    header = email_header(atelier_nom, logo_url)
    footer = email_footer(atelier_nom, atelier_telephone, atelier_adresse, atelier_email)
    html_body = wrap_email(header, body, footer)

    text_body = (
        f"Bonjour {client_prenom},\n\n"
        f"{titre}\n"
        f"Facture : {numero_facture}\n"
        f"Montant : {montant_ttc} €\n\n"
        f"La facture PDF est jointe a cet email.\n\n"
        f"Cordialement,\n{atelier_nom}"
    )
    return subject, html_body, text_body


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
            "subject": f"Ca fait un moment ! Votre moto va bien ? - {atelier_nom}",
            "titre": "On prend des nouvelles",
            "message": "Cela fait quelques mois que nous ne vous avons pas vu a l'atelier. "
                       "N'oubliez pas qu'un entretien regulier est essentiel pour la longevite de votre moto.",
        },
        2: {
            "subject": f"Pensez a l'entretien de votre moto - {atelier_nom}",
            "titre": "Entretien recommande",
            "message": "Vidange, plaquettes de frein, pneus… Un controle regulier permet d'eviter "
                       "les mauvaises surprises. Prenez rendez-vous pour un check-up !",
        },
        3: {
            "subject": f"On vous attend a l'atelier - {atelier_nom}",
            "titre": "Derniere visite il y a plus d'un an",
            "message": "Votre moto merite une attention particuliere. Passez nous voir pour un bilan complet. "
                       "Nous serons ravis de vous accueillir !",
        },
    }
    tpl = messages.get(niveau_relance, messages[1])

    rows = []
    if vehicule_info:
        rows.append(("Votre vehicule", vehicule_info))

    base_url = os.getenv("BASE_URL", "http://localhost:3000")
    booking_url = f"{base_url}/rendez-vous.html"

    body = f"""
    <p style="color:#333;font-size:16px">Bonjour <strong>{client_prenom}</strong>,</p>
    <p style="color:#FB923C;font-weight:700;font-size:18px">{tpl['titre']}</p>
    <p style="color:#555;font-size:15px">{tpl['message']}</p>
    {info_table(rows) if rows else ""}
    {cta_button(booking_url, "Prendre rendez-vous")}
    {"<p style='color:#555'>Ou appelez-nous au <strong>" + atelier_telephone + "</strong>.</p>" if atelier_telephone else ""}"""

    header = email_header(atelier_nom, logo_url)
    footer = email_footer(atelier_nom, atelier_telephone, atelier_adresse, atelier_email)
    html_body = wrap_email(header, body, footer)

    text_body = (
        f"Bonjour {client_prenom},\n\n"
        f"{tpl['message']}\n\n"
        + (f"Vehicule : {vehicule_info}\n" if vehicule_info else "")
        + f"Prendre rendez-vous : {booking_url}\n"
        + f"\nCordialement,\n{atelier_nom}"
    )
    return tpl["subject"], html_body, text_body
