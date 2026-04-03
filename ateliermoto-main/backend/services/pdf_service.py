from datetime import datetime
import logging
from fastapi import HTTPException
from fastapi.responses import StreamingResponse
from sqlalchemy.orm import Session, joinedload

from models import RendezVous, RapportTechnicien, Mecanicien, OrdreReparation, Pont

logger = logging.getLogger("ateliermoto.pdf")


def generate_ordre_reparation_pdf(rdv_id: int, db: Session):
    """Génère un ordre de réparation au format PDF avec design professionnel"""
    from reportlab.lib.pagesizes import A4
    from reportlab.lib import colors
    from reportlab.lib.units import cm, mm
    from reportlab.platypus import SimpleDocTemplate, Table, TableStyle, Paragraph, Spacer, PageBreak, HRFlowable
    from reportlab.lib.styles import getSampleStyleSheet, ParagraphStyle
    from reportlab.lib.enums import TA_LEFT, TA_CENTER, TA_RIGHT
    from reportlab.platypus import Image as RLImage
    from io import BytesIO
    from pathlib import Path
    import json

    rdv = db.query(RendezVous).options(
        joinedload(RendezVous.vehicule)
    ).filter(RendezVous.id == rdv_id).first()
    if not rdv:
        raise HTTPException(status_code=404, detail="Rendez-vous non trouvé")

    rapport = db.query(RapportTechnicien).filter(RapportTechnicien.rendez_vous_id == rdv_id).first()

    # Mécanicien assigné
    meca = None
    if rdv.mecanicien_id:
        meca = db.query(Mecanicien).filter(Mecanicien.id == rdv.mecanicien_id).first()

    # Pont assigné
    pont = None
    if rdv.pont_id:
        pont = db.query(Pont).filter(Pont.id == rdv.pont_id).first()

    buffer = BytesIO()
    W, H = A4  # 595, 842
    doc = SimpleDocTemplate(buffer, pagesize=A4, rightMargin=12*mm, leftMargin=12*mm, topMargin=10*mm, bottomMargin=10*mm)
    usable_w = W - 24*mm  # ~171mm

    # ===== COULEURS =====
    NOIR = colors.HexColor('#1a1a1a')
    ORANGE = colors.HexColor('#E8480A')
    ORANGE_LIGHT = colors.HexColor('#FFF4F0')
    GRIS_FONCE = colors.HexColor('#333333')
    GRIS = colors.HexColor('#666666')
    GRIS_CLAIR = colors.HexColor('#F7F7F7')
    GRIS_BORDER = colors.HexColor('#E0E0E0')
    BLANC = colors.white
    VERT = colors.HexColor('#22C55E')
    ROUGE = colors.HexColor('#EF4444')
    BLEU = colors.HexColor('#3B82F6')

    styles = getSampleStyleSheet()

    # ===== STYLES =====
    s_header_w = ParagraphStyle('hw', parent=styles['Normal'], fontSize=9, textColor=BLANC, fontName='Helvetica')
    s_header_w_bold = ParagraphStyle('hwb', parent=styles['Normal'], fontSize=10, textColor=BLANC, fontName='Helvetica-Bold')
    s_or_title = ParagraphStyle('ort', parent=styles['Normal'], fontSize=22, textColor=ORANGE, fontName='Helvetica-Bold', alignment=TA_RIGHT)
    s_or_num = ParagraphStyle('orn', parent=styles['Normal'], fontSize=11, textColor=BLANC, fontName='Helvetica-Bold', alignment=TA_RIGHT)
    s_section = ParagraphStyle('sec', parent=styles['Normal'], fontSize=11, textColor=NOIR, fontName='Helvetica-Bold', spaceBefore=12, spaceAfter=6)
    s_label = ParagraphStyle('lbl', parent=styles['Normal'], fontSize=8, textColor=GRIS, fontName='Helvetica')
    s_val = ParagraphStyle('val', parent=styles['Normal'], fontSize=10, textColor=GRIS_FONCE, fontName='Helvetica-Bold')
    s_val_sm = ParagraphStyle('vsm', parent=styles['Normal'], fontSize=9, textColor=GRIS_FONCE, fontName='Helvetica')
    s_text = ParagraphStyle('txt', parent=styles['Normal'], fontSize=9, textColor=GRIS_FONCE, leading=13)
    s_th = ParagraphStyle('th', parent=styles['Normal'], fontSize=8, textColor=GRIS, fontName='Helvetica-Bold', alignment=TA_CENTER)
    s_td = ParagraphStyle('td', parent=styles['Normal'], fontSize=9, textColor=GRIS_FONCE, fontName='Helvetica')
    s_td_r = ParagraphStyle('tdr', parent=styles['Normal'], fontSize=9, textColor=GRIS_FONCE, fontName='Helvetica', alignment=TA_RIGHT)
    s_cgv = ParagraphStyle('cgv', parent=styles['Normal'], fontSize=7, textColor=GRIS, leading=10)
    s_footer = ParagraphStyle('ft', parent=styles['Normal'], fontSize=7, textColor=GRIS, alignment=TA_CENTER)
    s_badge = ParagraphStyle('bdg', parent=styles['Normal'], fontSize=8, textColor=BLANC, fontName='Helvetica-Bold', alignment=TA_CENTER)

    elements = []
    or_number = f"OR-{rdv.id:06d}"
    date_emission = rdv.date_rdv.strftime('%d/%m/%Y') if rdv.date_rdv else '-'
    heure_rdv = rdv.heure_rdv.strftime('%H:%M') if rdv.heure_rdv else '-'

    # Statut
    statut_labels = {
        'reserve': 'RESERVE', 'en_attente': 'EN ATTENTE', 'confirme': 'CONFIRME', 'en_cours': 'EN COURS',
        'termine': 'TERMINE', 'annule': 'ANNULE', 'facture': 'FACTURE'
    }
    statut_colors = {
        'reserve': '#F59E0B', 'en_attente': '#F59E0B', 'confirme': '#3B82F6', 'en_cours': '#E8480A',
        'termine': '#22C55E', 'annule': '#EF4444', 'facture': '#8B5CF6'
    }
    statut_text = statut_labels.get(rdv.statut, rdv.statut or '-')
    statut_color = colors.HexColor(statut_colors.get(rdv.statut, '#666666'))

    # ===== EN-TETE BANDEAU =====
    header_left = Paragraph(
        f"<b>ATELIER MOTO PRO</b><br/>"
        f"<font size='7'>123 Rue de l'Atelier, 75000 Paris</font><br/>"
        f"<font size='7'>Tel: 01 23 45 67 89 | contact@atelier-moto.fr</font><br/>"
        f"<font size='7'>SIRET: XXX XXX XXX 00012</font>",
        s_header_w
    )
    header_right_content = (
        f"<b>ORDRE DE REPARATION</b><br/>"
        f"<font size='16' color='#FFFFFF'><b>{or_number}</b></font><br/>"
        f"<font size='8'>Date: {date_emission} | Heure: {heure_rdv}</font>"
    )
    header_right = Paragraph(header_right_content, ParagraphStyle('hr', parent=styles['Normal'], fontSize=10, textColor=ORANGE, fontName='Helvetica-Bold', alignment=TA_RIGHT))

    header_table = Table([[header_left, header_right]], colWidths=[usable_w * 0.5, usable_w * 0.5])
    header_table.setStyle(TableStyle([
        ('BACKGROUND', (0, 0), (-1, -1), NOIR),
        ('VALIGN', (0, 0), (-1, -1), 'MIDDLE'),
        ('TOPPADDING', (0, 0), (-1, -1), 10),
        ('BOTTOMPADDING', (0, 0), (-1, -1), 10),
        ('LEFTPADDING', (0, 0), (0, 0), 12),
        ('RIGHTPADDING', (-1, 0), (-1, 0), 12),
        ('ROUNDEDCORNERS', [4, 4, 0, 0]),
    ]))
    elements.append(header_table)

    # Barre statut sous le header
    statut_bar = Table([[
        Paragraph(f"Statut: <b>{statut_text}</b>", ParagraphStyle('st', parent=styles['Normal'], fontSize=8, textColor=BLANC, fontName='Helvetica')),
        Paragraph(f"Mecanicien: <b>{meca.prenom + ' ' + meca.nom if meca else 'Non assigne'}</b>", ParagraphStyle('st2', parent=styles['Normal'], fontSize=8, textColor=BLANC, fontName='Helvetica', alignment=TA_CENTER)),
        Paragraph(f"Pont: <b>{pont.nom if pont else '-'}</b>", ParagraphStyle('st3', parent=styles['Normal'], fontSize=8, textColor=BLANC, fontName='Helvetica', alignment=TA_RIGHT)),
    ]], colWidths=[usable_w * 0.33, usable_w * 0.34, usable_w * 0.33])
    statut_bar.setStyle(TableStyle([
        ('BACKGROUND', (0, 0), (-1, -1), statut_color),
        ('VALIGN', (0, 0), (-1, -1), 'MIDDLE'),
        ('TOPPADDING', (0, 0), (-1, -1), 4),
        ('BOTTOMPADDING', (0, 0), (-1, -1), 4),
        ('LEFTPADDING', (0, 0), (0, 0), 12),
        ('RIGHTPADDING', (-1, 0), (-1, 0), 12),
        ('ROUNDEDCORNERS', [0, 0, 4, 4]),
    ]))
    elements.append(statut_bar)
    elements.append(Spacer(1, 12))

    # ===== CLIENT & VEHICULE - 2 colonnes encadrées =====
    def info_cell(label, value):
        return Paragraph(f"<font size='7' color='#999999'>{label}</font><br/><font size='10'><b>{value or '-'}</b></font>", s_val_sm)

    # Client
    client_cells = [
        [Paragraph("<font color='#E8480A'><b>CLIENT</b></font>", s_section)],
        [info_cell("Nom complet", f"{rdv.client.prenom} {rdv.client.nom}")],
        [info_cell("Telephone", rdv.client.telephone)],
        [info_cell("Email", rdv.client.email or '-')],
    ]
    client_table = Table(client_cells, colWidths=[usable_w * 0.48])
    client_table.setStyle(TableStyle([
        ('BOX', (0, 0), (-1, -1), 1, GRIS_BORDER),
        ('BACKGROUND', (0, 0), (-1, 0), ORANGE_LIGHT),
        ('TOPPADDING', (0, 0), (-1, -1), 5),
        ('BOTTOMPADDING', (0, 0), (-1, -1), 5),
        ('LEFTPADDING', (0, 0), (-1, -1), 10),
        ('RIGHTPADDING', (0, 0), (-1, -1), 10),
        ('ROUNDEDCORNERS', [6, 6, 6, 6]),
    ]))

    # Vehicule
    veh = rdv.vehicule
    veh_desc = f"{veh.marque or '-'} {veh.modele or '-'}"
    if veh.annee:
        veh_desc += f" ({veh.annee})"
    veh_details = f"{veh.cylindree or '-'}"
    if veh.type_moto:
        veh_details += f" | {veh.type_moto}"

    vehicule_cells = [
        [Paragraph("<font color='#E8480A'><b>VEHICULE</b></font>", s_section)],
        [info_cell("Moto", veh_desc)],
        [info_cell("Cylindree / Type", veh_details)],
        [info_cell("Immatriculation", veh.plaque)],
    ]
    if rdv.kilometrage:
        vehicule_cells.append([info_cell("Kilometrage", f"{rdv.kilometrage} km")])

    vehicule_table = Table(vehicule_cells, colWidths=[usable_w * 0.48])
    vehicule_table.setStyle(TableStyle([
        ('BOX', (0, 0), (-1, -1), 1, GRIS_BORDER),
        ('BACKGROUND', (0, 0), (-1, 0), ORANGE_LIGHT),
        ('TOPPADDING', (0, 0), (-1, -1), 5),
        ('BOTTOMPADDING', (0, 0), (-1, -1), 5),
        ('LEFTPADDING', (0, 0), (-1, -1), 10),
        ('RIGHTPADDING', (0, 0), (-1, -1), 10),
        ('ROUNDEDCORNERS', [6, 6, 6, 6]),
    ]))

    cv_table = Table([[client_table, vehicule_table]], colWidths=[usable_w * 0.49, usable_w * 0.49], hAlign='CENTER')
    cv_table.setStyle(TableStyle([
        ('VALIGN', (0, 0), (-1, -1), 'TOP'),
        ('LEFTPADDING', (0, 0), (-1, -1), 0),
        ('RIGHTPADDING', (0, 0), (-1, -1), 0),
    ]))
    elements.append(cv_table)
    elements.append(Spacer(1, 10))

    # ===== ETAT A L'ARRIVEE =====
    if rdv.etat_vehicule:
        # Parser le JSON de l'etat du vehicule
        etat_content = ""
        try:
            import json
            etat_data = json.loads(rdv.etat_vehicule)
            parts = []
            # Points de controle
            if etat_data.get("points"):
                for pt in etat_data["points"]:
                    label = pt.get("label", pt.get("nom", ""))
                    status = pt.get("status", pt.get("etat", ""))
                    icon = "OK" if status in ("ok", "bon", "OK") else "NOK" if status in ("nok", "mauvais", "NOK", "defaut") else status.upper()
                    parts.append(f"<b>{label}</b> : {icon}")
            # Observations
            obs = etat_data.get("observations", "")
            if obs:
                parts.append(f"<b>Observations :</b> {obs}")
            etat_content = "<br/>".join(parts) if parts else "Aucune observation"
        except (json.JSONDecodeError, TypeError, AttributeError):
            etat_content = rdv.etat_vehicule if rdv.etat_vehicule else "Aucune observation"

        if etat_content and etat_content != "Aucune observation":
            elements.append(Paragraph("<font color='#E8480A'><b>ETAT DU VEHICULE A L'ARRIVEE</b></font>", s_section))
            etat_box = Table([[Paragraph(etat_content, s_text)]], colWidths=[usable_w])
            etat_box.setStyle(TableStyle([
                ('BACKGROUND', (0, 0), (-1, -1), GRIS_CLAIR),
                ('BOX', (0, 0), (-1, -1), 1, GRIS_BORDER),
                ('LEFTPADDING', (0, 0), (-1, -1), 10),
                ('RIGHTPADDING', (0, 0), (-1, -1), 10),
                ('TOPPADDING', (0, 0), (-1, -1), 8),
                ('BOTTOMPADDING', (0, 0), (-1, -1), 8),
                ('ROUNDEDCORNERS', [4, 4, 4, 4]),
            ]))
            elements.append(etat_box)
            elements.append(Spacer(1, 10))

    # ===== INTERVENTION DEMANDEE =====
    elements.append(Paragraph("<font color='#E8480A'><b>INTERVENTION DEMANDEE</b></font>", s_section))

    interv_data = [
        [Paragraph("Type", s_label), Paragraph(f"<b>{rdv.type_intervention or '-'}</b>", s_val)],
        [Paragraph("Temps estime", s_label), Paragraph(f"{rdv.temps_estime or '-'} min", s_val_sm)],
    ]
    if rdv.commentaire:
        interv_data.append([Paragraph("Commentaire", s_label), Paragraph(rdv.commentaire, s_text)])

    interv_table = Table(interv_data, colWidths=[35*mm, usable_w - 35*mm])
    interv_table.setStyle(TableStyle([
        ('VALIGN', (0, 0), (-1, -1), 'TOP'),
        ('LEFTPADDING', (0, 0), (-1, -1), 8),
        ('BOTTOMPADDING', (0, 0), (-1, -1), 4),
        ('LINEBELOW', (0, 0), (-1, -2), 0.5, GRIS_BORDER),
    ]))
    elements.append(interv_table)

    # ===== RAPPORT TECHNICIEN =====
    if rapport:
        elements.append(Spacer(1, 10))
        elements.append(Paragraph("<font color='#E8480A'><b>RAPPORT D'INTERVENTION</b></font>", s_section))

        # Points de contrôle en grille
        if rapport.points_controle:
            try:
                points = json.loads(rapport.points_controle)
                labels_map = {
                    'niveau_huile': "Huile", 'pression_pneus': "Pneus",
                    'freins_avant': "Freins AV", 'freins_arriere': "Freins AR",
                    'eclairage': "Eclairage", 'clignotants': "Clignotants",
                    'batterie': "Batterie", 'chaine_courroie': "Chaine",
                    'liquide_refroidissement': "Refroid.", 'filtre_air': "Filtre air"
                }
                checked = []
                for key, label in labels_map.items():
                    state = points.get(key, 'non_verifie')
                    if state == 'ok':
                        checked.append([Paragraph(f"<font color='#22C55E'>OK</font>", ParagraphStyle('ok', parent=styles['Normal'], fontSize=8, textColor=VERT, fontName='Helvetica-Bold', alignment=TA_CENTER)), Paragraph(label, s_label)])
                    elif state == 'a_remplacer' or state == 'defaillant':
                        checked.append([Paragraph(f"<font color='#EF4444'>NOK</font>", ParagraphStyle('nok', parent=styles['Normal'], fontSize=8, textColor=ROUGE, fontName='Helvetica-Bold', alignment=TA_CENTER)), Paragraph(label, s_label)])

                if checked:
                    # Layout en rangées de 5
                    rows = []
                    for i in range(0, len(checked), 5):
                        row_cells = []
                        for j in range(5):
                            if i + j < len(checked):
                                mini = Table([checked[i + j]], colWidths=[12*mm, 22*mm])
                                mini.setStyle(TableStyle([('VALIGN', (0, 0), (-1, -1), 'MIDDLE'), ('LEFTPADDING', (0, 0), (-1, -1), 2)]))
                                row_cells.append(mini)
                            else:
                                row_cells.append('')
                        rows.append(row_cells)

                    col_w = usable_w / 5
                    pts_table = Table(rows, colWidths=[col_w] * 5)
                    pts_table.setStyle(TableStyle([
                        ('VALIGN', (0, 0), (-1, -1), 'MIDDLE'),
                        ('BOTTOMPADDING', (0, 0), (-1, -1), 4),
                    ]))
                    elements.append(pts_table)
            except Exception as e:
                logger.warning("Erreur rendu points controle rdv_id=%s: %s", rdv.id, e)

        # Travaux réalisés
        if rapport.travaux_realises:
            elements.append(Spacer(1, 6))
            elements.append(Paragraph("<font size='8' color='#999999'>Travaux realises</font>", s_label))
            travaux_box = Table([[Paragraph(rapport.travaux_realises, s_text)]], colWidths=[usable_w])
            travaux_box.setStyle(TableStyle([
                ('BACKGROUND', (0, 0), (-1, -1), GRIS_CLAIR),
                ('BOX', (0, 0), (-1, -1), 1, GRIS_BORDER),
                ('LEFTPADDING', (0, 0), (-1, -1), 10), ('RIGHTPADDING', (0, 0), (-1, -1), 10),
                ('TOPPADDING', (0, 0), (-1, -1), 6), ('BOTTOMPADDING', (0, 0), (-1, -1), 6),
                ('ROUNDEDCORNERS', [4, 4, 4, 4]),
            ]))
            elements.append(travaux_box)

        # Pièces utilisées
        if rapport.pieces_utilisees:
            try:
                pieces = json.loads(rapport.pieces_utilisees)
                if pieces:
                    elements.append(Spacer(1, 6))
                    elements.append(Paragraph("<font size='8' color='#999999'>Pieces utilisees</font>", s_label))
                    p_rows = [[Paragraph("<b>Designation</b>", s_th), Paragraph("<b>Ref</b>", s_th), Paragraph("<b>Qte</b>", s_th)]]
                    for p in pieces:
                        qty = p.get('quantite', 1)
                        p_rows.append([
                            Paragraph(p.get('nom', '-'), s_td),
                            Paragraph(p.get('reference', '-'), s_td),
                            Paragraph(str(qty), s_td),
                        ])
                    p_table = Table(p_rows, colWidths=[usable_w * 0.55, usable_w * 0.3, usable_w * 0.15])
                    p_table.setStyle(TableStyle([
                        ('BACKGROUND', (0, 0), (-1, 0), GRIS_CLAIR),
                        ('LINEBELOW', (0, 0), (-1, 0), 1, GRIS_BORDER),
                        ('LINEBELOW', (0, -1), (-1, -1), 1, GRIS_BORDER),
                        ('VALIGN', (0, 0), (-1, -1), 'MIDDLE'),
                        ('TOPPADDING', (0, 0), (-1, -1), 3), ('BOTTOMPADDING', (0, 0), (-1, -1), 3),
                        ('LEFTPADDING', (0, 0), (-1, -1), 4),
                    ]))
                    elements.append(p_table)
            except Exception as e:
                logger.warning("Erreur rendu pieces utilisees rdv_id=%s: %s", rdv.id, e)

        # Alertes / Recommandations
        if rapport.alertes:
            elements.append(Spacer(1, 6))
            alert_box = Table([[Paragraph(f"<b>ALERTES :</b> {rapport.alertes}", s_text)]], colWidths=[usable_w])
            alert_box.setStyle(TableStyle([
                ('BACKGROUND', (0, 0), (-1, -1), colors.HexColor('#FEF2F2')),
                ('BOX', (0, 0), (-1, -1), 1, ROUGE),
                ('LEFTPADDING', (0, 0), (-1, -1), 10), ('RIGHTPADDING', (0, 0), (-1, -1), 10),
                ('TOPPADDING', (0, 0), (-1, -1), 6), ('BOTTOMPADDING', (0, 0), (-1, -1), 6),
                ('ROUNDEDCORNERS', [4, 4, 4, 4]),
            ]))
            elements.append(alert_box)

        if rapport.recommandations:
            elements.append(Spacer(1, 4))
            reco_box = Table([[Paragraph(f"<b>RECOMMANDATIONS :</b> {rapport.recommandations}", s_text)]], colWidths=[usable_w])
            reco_box.setStyle(TableStyle([
                ('BACKGROUND', (0, 0), (-1, -1), colors.HexColor('#EFF6FF')),
                ('BOX', (0, 0), (-1, -1), 1, BLEU),
                ('LEFTPADDING', (0, 0), (-1, -1), 10), ('RIGHTPADDING', (0, 0), (-1, -1), 10),
                ('TOPPADDING', (0, 0), (-1, -1), 6), ('BOTTOMPADDING', (0, 0), (-1, -1), 6),
                ('ROUNDEDCORNERS', [4, 4, 4, 4]),
            ]))
            elements.append(reco_box)

    # ===== SIGNATURES =====
    elements.append(Spacer(1, 16))
    elements.append(Paragraph("<font color='#E8480A'><b>SIGNATURES</b></font>", s_section))

    or_initial = db.query(OrdreReparation).filter(
        OrdreReparation.rendez_vous_id == rdv.id,
        OrdreReparation.type_or == "initial"
    ).order_by(OrdreReparation.created_at.desc()).first()

    sig_client = ''
    if or_initial and or_initial.signature_client:
        try:
            import base64
            data = or_initial.signature_client.split(',')[1] if ',' in or_initial.signature_client else or_initial.signature_client
            img_bytes = base64.b64decode(data)
            sig_client = RLImage(BytesIO(img_bytes), width=50*mm, height=25*mm)
        except Exception as e:
            logger.warning("Erreur chargement signature BDD rdv_id=%s: %s", rdv.id, e)
            sig_client = ''
    else:
        signature_path = Path("signatures") / f"rdv_{rdv.id}_signature.png"
        if signature_path.exists():
            try:
                sig_client = RLImage(str(signature_path), width=50*mm, height=25*mm)
            except Exception as e:
                logger.warning("Erreur chargement signature fichier rdv_id=%s: %s", rdv.id, e)
                sig_client = ''

    sig_data = [
        [Paragraph("<b>Client</b><br/><font size='7' color='#999'>Signature avant intervention</font>", s_label),
         Paragraph("<b>Atelier</b><br/><font size='7' color='#999'>Cachet et signature</font>", s_label)],
        [sig_client or Spacer(1, 30*mm), Spacer(1, 30*mm)],
    ]
    sig_table = Table(sig_data, colWidths=[usable_w * 0.5, usable_w * 0.5])
    sig_table.setStyle(TableStyle([
        ('ALIGN', (0, 0), (-1, -1), 'CENTER'),
        ('VALIGN', (0, 0), (-1, -1), 'TOP'),
        ('BOX', (0, 0), (0, -1), 1, GRIS_BORDER),
        ('BOX', (1, 0), (1, -1), 1, GRIS_BORDER),
        ('TOPPADDING', (0, 0), (-1, -1), 6),
        ('BOTTOMPADDING', (0, 0), (-1, -1), 6),
        ('LEFTPADDING', (0, 0), (-1, -1), 8),
    ]))
    elements.append(sig_table)

    # ===== PAGE 2 - CGV =====
    elements.append(PageBreak())

    cgv_header = Table([[Paragraph("<b>CONDITIONS GENERALES DE VENTE ET DE REPARATION</b>", ParagraphStyle('cgvh', parent=styles['Normal'], fontSize=10, textColor=BLANC, fontName='Helvetica-Bold', alignment=TA_CENTER))]], colWidths=[usable_w])
    cgv_header.setStyle(TableStyle([
        ('BACKGROUND', (0, 0), (-1, -1), NOIR),
        ('TOPPADDING', (0, 0), (-1, -1), 8), ('BOTTOMPADDING', (0, 0), (-1, -1), 8),
        ('ROUNDEDCORNERS', [4, 4, 4, 4]),
    ]))
    elements.append(cgv_header)
    elements.append(Spacer(1, 10))

    cgv_text = (
        "<b>1. Devis et acceptation</b><br/>"
        "Toute reparation necessite l'etablissement d'un devis prealable. L'acceptation du devis par le client "
        "(signature ou accord ecrit) est obligatoire avant toute intervention. Les prix indiques sont en euros TTC.<br/><br/>"
        "<b>2. Garantie</b><br/>"
        "Les pieces remplacees beneficient d'une garantie de 12 mois (hors usure normale). La main d'oeuvre est "
        "garantie 3 mois. Cette garantie ne couvre pas les dommages resultant d'une mauvaise utilisation.<br/><br/>"
        "<b>3. Responsabilite</b><br/>"
        "L'atelier est responsable des dommages causes aux biens confies pendant la duree de l'intervention, "
        "dans la limite de la valeur declaree du vehicule.<br/><br/>"
        "<b>4. Paiement</b><br/>"
        "Le paiement est du a la livraison du vehicule. Moyens de paiement acceptes : especes, carte bancaire, "
        "cheque. Un acompte de 30% peut etre demande pour les interventions importantes.<br/><br/>"
        "<b>5. Abandon de vehicule</b><br/>"
        "En cas de non-paiement et apres mise en demeure restee sans effet pendant 30 jours, l'atelier se reserve "
        "le droit de vendre le vehicule aux encheres pour recouvrer les sommes dues.<br/><br/>"
        "<b>6. Litiges</b><br/>"
        "En cas de litige, les parties s'engagent a rechercher une solution amiable. A defaut, le tribunal competent "
        "est celui du lieu de l'atelier.<br/><br/>"
        "<b>7. Protection des donnees</b><br/>"
        "Les informations collectees sont utilisees uniquement pour la gestion des reparations et des relations clients, "
        "conformement au RGPD. Le client dispose d'un droit d'acces et de rectification sur ses donnees."
    )
    elements.append(Paragraph(cgv_text, s_cgv))
    elements.append(Spacer(1, 20))

    # Footer
    elements.append(HRFlowable(width="100%", thickness=0.5, color=GRIS_BORDER))
    elements.append(Spacer(1, 6))
    elements.append(Paragraph(
        f"<b>Atelier Moto Pro</b> | SIRET: XXX XXX XXX 00012 | 123 Rue de l'Atelier, 75000 Paris<br/>"
        f"Tel: 01 23 45 67 89 | Email: contact@atelier-moto.fr | Document genere le {datetime.now().strftime('%d/%m/%Y a %H:%M')}",
        s_footer
    ))

    doc.build(elements)
    buffer.seek(0)

    return StreamingResponse(buffer, media_type="application/pdf", headers={
        "Content-Disposition": f"inline; filename={or_number}.pdf"
    })

# ========== SAUVEGARDE OR ==========



def generate_facture_pdf(rdv_id: int, db: Session):
    """Génère une facture au format PDF"""
    from reportlab.lib.pagesizes import A4
    from reportlab.lib import colors
    from reportlab.lib.units import cm
    from reportlab.platypus import SimpleDocTemplate, Table, TableStyle, Paragraph, Spacer
    from reportlab.lib.styles import getSampleStyleSheet, ParagraphStyle
    from io import BytesIO

    rdv = db.query(RendezVous).filter(RendezVous.id == rdv_id).first()
    if not rdv:
        raise HTTPException(status_code=404, detail="Rendez-vous non trouvé")

    buffer = BytesIO()
    doc = SimpleDocTemplate(buffer, pagesize=A4, rightMargin=2*cm, leftMargin=2*cm, topMargin=2*cm, bottomMargin=2*cm)

    styles = getSampleStyleSheet()
    elements = []

    # En-tête
    title_style = ParagraphStyle('CustomTitle', parent=styles['Heading1'], fontSize=24, textColor=colors.HexColor('#1a1a1a'), spaceAfter=20)
    elements.append(Paragraph("FACTURE", title_style))
    elements.append(Spacer(1, 10))

    # Numéro de facture et date
    facture_number = f"F-{rdv.id:04d}-{datetime.now().strftime('%Y%m%d')}"
    elements.append(Paragraph(f"<b>Facture N°:</b> {facture_number}", styles['Normal']))
    elements.append(Paragraph(f"<b>Date:</b> {datetime.now().strftime('%d/%m/%Y')}", styles['Normal']))
    elements.append(Spacer(1, 20))

    # Infos client
    elements.append(Paragraph("<b>CLIENT</b>", styles['Heading3']))
    elements.append(Paragraph(f"{rdv.client.prenom} {rdv.client.nom}", styles['Normal']))
    elements.append(Paragraph(f"Tél: {rdv.client.telephone}", styles['Normal']))
    if rdv.client.email:
        elements.append(Paragraph(f"Email: {rdv.client.email}", styles['Normal']))
    elements.append(Spacer(1, 20))

    # Infos véhicule
    elements.append(Paragraph("<b>VÉHICULE</b>", styles['Heading3']))
    elements.append(Paragraph(f"{rdv.vehicule.marque} {rdv.vehicule.modele}", styles['Normal']))
    elements.append(Paragraph(f"Plaque: {rdv.vehicule.plaque}", styles['Normal']))
    elements.append(Spacer(1, 20))

    # Détails de l'intervention
    elements.append(Paragraph("<b>DÉTAILS DE L'INTERVENTION</b>", styles['Heading3']))
    elements.append(Paragraph(f"Date d'intervention: {rdv.date_rdv.strftime('%d/%m/%Y')}", styles['Normal']))
    elements.append(Paragraph(f"Type: {rdv.type_intervention}", styles['Normal']))
    if rdv.kilometrage:
        elements.append(Paragraph(f"Kilométrage: {rdv.kilometrage} km", styles['Normal']))
    if rdv.temps_final:
        elements.append(Paragraph(f"Temps passé: {rdv.temps_final} minutes", styles['Normal']))
    elif rdv.temps_estime:
        elements.append(Paragraph(f"Temps estimé: {rdv.temps_estime} minutes", styles['Normal']))
    elements.append(Spacer(1, 10))
    
    # État du véhicule
    if rdv.etat_vehicule:
        elements.append(Paragraph("<b>État du véhicule à l'arrivée:</b>", styles['Normal']))
        elements.append(Paragraph(rdv.etat_vehicule, styles['Normal']))
        elements.append(Spacer(1, 10))
    
    if rdv.commentaire:
        elements.append(Paragraph("<b>Travaux réalisés:</b>", styles['Normal']))
        elements.append(Paragraph(rdv.commentaire, styles['Normal']))
    elements.append(Spacer(1, 20))

    # Tableau des montants
    elements.append(Paragraph("<b>DÉTAIL DES MONTANTS</b>", styles['Heading3']))
    data = [
        ['Description', 'Montant'],
        ['Main d\'œuvre et interventions', f"{rdv.prix_final or rdv.prix_estime or 0:.2f} €"],
    ]

    # Total
    total = rdv.prix_final or rdv.prix_estime or 0
    data.append(['', ''])
    data.append(['<b>TOTAL TTC</b>', f"<b>{total:.2f} €</b>"])

    table = Table(data, colWidths=[12*cm, 5*cm])
    table.setStyle(TableStyle([
        ('BACKGROUND', (0, 0), (-1, 0), colors.HexColor('#ffd700')),
        ('TEXTCOLOR', (0, 0), (-1, 0), colors.HexColor('#1a1a1a')),
        ('FONTNAME', (0, 0), (-1, 0), 'Helvetica-Bold'),
        ('FONTSIZE', (0, 0), (-1, -1), 10),
        ('ALIGN', (1, 0), (-1, -1), 'RIGHT'),
        ('GRID', (0, 0), (-1, -2), 1, colors.grey),
        ('LINEABOVE', (0, -1), (-1, -1), 2, colors.black),
        ('FONTNAME', (0, -1), (-1, -1), 'Helvetica-Bold'),
        ('BACKGROUND', (0, -1), (-1, -1), colors.lightgrey),
    ]))
    elements.append(table)
    elements.append(Spacer(1, 30))

    # Mentions légales
    elements.append(Paragraph("<b>MENTIONS LÉGALES</b>", styles['Heading3']))
    elements.append(Paragraph("Atelier Moto Pro - SIRET: 123 456 789 00010", styles['Normal']))
    elements.append(Paragraph("TVA intracommunautaire: FR12345678900", styles['Normal']))
    elements.append(Paragraph("Paiement dû à réception de la facture.", styles['Normal']))
    elements.append(Spacer(1, 30))
    
    # Signatures
    elements.append(Paragraph("<b>SIGNATURES</b>", styles['Heading3']))
    sig_data = [
        ['Client:', 'Pour l\'atelier:'],
        ['\n\n\n\n', '\n\n\n\n'],
    ]
    sig_table = Table(sig_data, colWidths=[7*cm, 7*cm])
    sig_table.setStyle(TableStyle([
        ('FONTNAME', (0, 0), (-1, 0), 'Helvetica-Bold'),
        ('FONTSIZE', (0, 0), (-1, -1), 10),
        ('ALIGN', (0, 0), (-1, -1), 'CENTER'),
        ('VALIGN', (0, 0), (-1, -1), 'BOTTOM'),
        ('LINEABOVE', (0, 1), (0, 1), 1, colors.black),
        ('LINEABOVE', (1, 1), (1, 1), 1, colors.black),
    ]))
    elements.append(sig_table)

    # Build PDF
    doc.build(elements)
    buffer.seek(0)

    return StreamingResponse(buffer, media_type="application/pdf", headers={
        "Content-Disposition": f"attachment; filename=FACTURE-{facture_number}.pdf"
    })
