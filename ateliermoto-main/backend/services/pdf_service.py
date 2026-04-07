from datetime import datetime
import logging
from fastapi import HTTPException
from fastapi.responses import StreamingResponse
from sqlalchemy.orm import Session, joinedload

from models import Atelier, RendezVous, RapportTechnicien, Mecanicien, OrdreReparation, Pont

logger = logging.getLogger("ateliermoto.pdf")


def generate_ordre_reparation_pdf(rdv_id: int, db: Session, or_id: int | None = None):
    """Génère un ordre de réparation au format PDF avec design professionnel depuis l'OR stocké en base."""
    from reportlab.lib.pagesizes import A4
    from reportlab.lib import colors
    from reportlab.lib.units import cm, mm
    from reportlab.platypus import SimpleDocTemplate, Table, TableStyle, Paragraph, Spacer, PageBreak, HRFlowable
    from reportlab.lib.styles import getSampleStyleSheet, ParagraphStyle
    from reportlab.lib.enums import TA_LEFT, TA_CENTER, TA_RIGHT
    from reportlab.platypus import Image as RLImage
    from reportlab.graphics.shapes import Circle, Drawing, Line, Path as ShapePath, Rect, String
    from io import BytesIO
    from pathlib import Path as FsPath
    import base64
    import json

    rdv = db.query(RendezVous).options(
        joinedload(RendezVous.vehicule)
    ).filter(RendezVous.id == rdv_id).first()
    if not rdv:
        raise HTTPException(status_code=404, detail="Rendez-vous non trouvé")

    ordre_source = None
    if or_id is not None:
        ordre_source = db.query(OrdreReparation).filter(
            OrdreReparation.id == or_id,
            OrdreReparation.rendez_vous_id == rdv_id,
        ).first()
        if not ordre_source:
            raise HTTPException(status_code=404, detail="Ordre de réparation non trouvé")
    else:
        ordre_source = db.query(OrdreReparation).filter(
            OrdreReparation.rendez_vous_id == rdv_id,
        ).order_by(OrdreReparation.created_at.desc()).first()

    rapport = db.query(RapportTechnicien).filter(RapportTechnicien.rendez_vous_id == rdv_id).first()

    # Mécanicien assigné
    meca = None
    if rdv.mecanicien_id:
        meca = db.query(Mecanicien).filter(Mecanicien.id == rdv.mecanicien_id).first()

    # Pont assigné
    pont = None
    if rdv.pont_id:
        pont = db.query(Pont).filter(Pont.id == rdv.pont_id).first()

    atelier = None
    if rdv.atelier_id:
        atelier = db.query(Atelier).filter(Atelier.id == rdv.atelier_id).first()

    def _parse_json(raw_value, default):
        if not raw_value:
            return default
        try:
            value = json.loads(raw_value) if isinstance(raw_value, str) else raw_value
            return value if value is not None else default
        except Exception:
            return default

    etat_meta = _parse_json((ordre_source.etat_vehicule if ordre_source and ordre_source.etat_vehicule else rdv.etat_vehicule), {})
    if not isinstance(etat_meta, dict):
        etat_meta = {"observations": str(etat_meta)}
    photos_list = _parse_json(rdv.photos_etat, [])
    if not isinstance(photos_list, list):
        photos_list = []

    priority_value = str(etat_meta.get("priority") or etat_meta.get("priorite") or "standard").lower()
    priority_label = {
        "basse": "Basse",
        "low": "Basse",
        "standard": "Standard",
        "normal": "Standard",
        "urgent": "Flash / Urgent",
        "critique": "Critique",
    }.get(priority_value, "Standard")
    fuel_level = etat_meta.get("fuel_level", etat_meta.get("niveau_carburant"))
    try:
        fuel_level = int(fuel_level) if fuel_level is not None else None
    except Exception:
        fuel_level = None
    body_damages = etat_meta.get("body_damages") if isinstance(etat_meta.get("body_damages"), list) else []
    schema_notes = etat_meta.get("schema_notes") or ""
    estimate_rows = etat_meta.get("estimate_rows") if isinstance(etat_meta.get("estimate_rows"), list) else []
    observations = (ordre_source.travaux if ordre_source and ordre_source.travaux else None) or etat_meta.get("observations") or rdv.commentaire or ""

    damage_labels = {
        "avant": "Avant",
        "reservoir": "Reservoir",
        "flanc_gauche": "Flanc gauche",
        "flanc_droit": "Flanc droit",
        "arriere": "Arriere",
        "roue_av": "Roue AV",
        "roue_ar": "Roue AR",
        "selle": "Selle",
    }

    if not estimate_rows:
        estimate_rows = [{
            "label": (ordre_source.travaux if ordre_source and ordre_source.travaux else rdv.type_intervention) or "Intervention atelier",
            "qty": 1,
            "amount": float(rdv.prix_final or rdv.prix_estime or 0) if (rdv.prix_final or rdv.prix_estime) is not None else None,
        }]

    buffer = BytesIO()
    W, H = A4  # 595, 842
    doc = SimpleDocTemplate(buffer, pagesize=A4, rightMargin=10*mm, leftMargin=10*mm, topMargin=6*mm, bottomMargin=6*mm)
    usable_w = W - 20*mm  # ~190mm

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

    def build_moto_diagram(width=150*mm, height=72*mm):
        drawing = Drawing(width, height)
        sx = width / 512.0
        sy = height / 320.0

        def tx(x): return x * sx
        def ty(y): return height - (y * sy)

        stroke = colors.HexColor('#334155')
        fill_light = colors.HexColor('#F8FAFC')

        def add_circle(cx, cy, r, dash=None):
            c = Circle(tx(cx), ty(cy), r * sx)
            c.strokeColor = stroke
            c.fillColor = None
            c.strokeWidth = 1.2
            if dash:
                c.strokeDashArray = dash
            drawing.add(c)

        add_circle(110, 230, 65)
        add_circle(110, 230, 55, [4, 4])
        add_circle(400, 230, 65)
        add_circle(400, 230, 55, [4, 4])
        add_circle(110, 230, 30)

        drawing.add(Line(tx(110), ty(230), tx(180), ty(80), strokeColor=stroke, strokeWidth=1.5))
        drawing.add(Line(tx(180), ty(80), tx(200), ty(85), strokeColor=stroke, strokeWidth=1.5))
        drawing.add(Line(tx(200), ty(85), tx(130), ty(235), strokeColor=stroke, strokeWidth=1.5))
        drawing.add(Line(tx(300), ty(200), tx(400), ty(230), strokeColor=stroke, strokeWidth=3))
        drawing.add(Line(tx(250), ty(220), tx(380), ty(260), strokeColor=stroke, strokeWidth=4))
        drawing.add(Line(tx(380), ty(260), tx(420), ty(240), strokeColor=stroke, strokeWidth=4))
        drawing.add(Line(tx(180), ty(80), tx(160), ty(70), strokeColor=stroke, strokeWidth=1.5))
        drawing.add(Line(tx(180), ty(80), tx(200), ty(65), strokeColor=stroke, strokeWidth=1.5))

        frame = ShapePath(strokeColor=stroke, fillColor=None, strokeWidth=1.5)
        frame.moveTo(tx(180), ty(120))
        frame.lineTo(tx(300), ty(120))
        frame.lineTo(tx(350), ty(180))
        frame.lineTo(tx(350), ty(230))
        frame.lineTo(tx(220), ty(230))
        frame.closePath()
        drawing.add(frame)

        moteur = Rect(tx(220), ty(220), 80 * sx, 60 * sy, rx=5, ry=5)
        moteur.strokeColor = stroke
        moteur.fillColor = None
        moteur.strokeWidth = 1.5
        drawing.add(moteur)

        reservoir = ShapePath(strokeColor=stroke, fillColor=fill_light, strokeWidth=1.3)
        reservoir.moveTo(tx(180), ty(120))
        reservoir.curveTo(tx(220), ty(70), tx(320), ty(90), tx(340), ty(100))
        reservoir.lineTo(tx(340), ty(130))
        reservoir.lineTo(tx(300), ty(130))
        reservoir.closePath()
        drawing.add(reservoir)

        selle = ShapePath(strokeColor=stroke, fillColor=fill_light, strokeWidth=1.3)
        selle.moveTo(tx(340), ty(130))
        selle.curveTo(tx(380), ty(120), tx(440), ty(140), tx(430), ty(160))
        selle.curveTo(tx(380), ty(150), tx(340), ty(160), tx(340), ty(160))
        selle.closePath()
        drawing.add(selle)

        phare = Circle(tx(165), ty(100), 12 * sx)
        phare.strokeColor = stroke
        phare.fillColor = None
        phare.strokeWidth = 1.2
        drawing.add(phare)
        drawing.add(String(tx(410), ty(24), 'Vue de profil (D)', fontName='Helvetica-Bold', fontSize=6, fillColor=colors.HexColor('#94A3B8')))
        return drawing

    styles = getSampleStyleSheet()

    # ===== STYLES =====
    s_header_w = ParagraphStyle('hw', parent=styles['Normal'], fontSize=9, textColor=BLANC, fontName='Helvetica')
    s_header_w_bold = ParagraphStyle('hwb', parent=styles['Normal'], fontSize=10, textColor=BLANC, fontName='Helvetica-Bold')
    s_or_title = ParagraphStyle('ort', parent=styles['Normal'], fontSize=22, textColor=ORANGE, fontName='Helvetica-Bold', alignment=TA_RIGHT)
    s_or_num = ParagraphStyle('orn', parent=styles['Normal'], fontSize=11, textColor=BLANC, fontName='Helvetica-Bold', alignment=TA_RIGHT)
    s_section = ParagraphStyle('sec', parent=styles['Normal'], fontSize=9.5, textColor=NOIR, fontName='Helvetica-Bold', leading=11, spaceBefore=4, spaceAfter=3)
    s_label = ParagraphStyle('lbl', parent=styles['Normal'], fontSize=7.5, textColor=GRIS, fontName='Helvetica')
    s_val = ParagraphStyle('val', parent=styles['Normal'], fontSize=9.5, textColor=GRIS_FONCE, fontName='Helvetica-Bold')
    s_val_sm = ParagraphStyle('vsm', parent=styles['Normal'], fontSize=8.5, textColor=GRIS_FONCE, fontName='Helvetica', leading=10)
    s_text = ParagraphStyle('txt', parent=styles['Normal'], fontSize=8.2, textColor=GRIS_FONCE, leading=10.5)
    s_th = ParagraphStyle('th', parent=styles['Normal'], fontSize=8, textColor=GRIS, fontName='Helvetica-Bold', alignment=TA_CENTER)
    s_td = ParagraphStyle('td', parent=styles['Normal'], fontSize=9, textColor=GRIS_FONCE, fontName='Helvetica')
    s_td_r = ParagraphStyle('tdr', parent=styles['Normal'], fontSize=9, textColor=GRIS_FONCE, fontName='Helvetica', alignment=TA_RIGHT)
    s_cgv = ParagraphStyle('cgv', parent=styles['Normal'], fontSize=7, textColor=GRIS, leading=10)
    s_footer = ParagraphStyle('ft', parent=styles['Normal'], fontSize=7, textColor=GRIS, alignment=TA_CENTER)
    s_badge = ParagraphStyle('bdg', parent=styles['Normal'], fontSize=8, textColor=BLANC, fontName='Helvetica-Bold', alignment=TA_CENTER)

    elements = []
    year = rdv.date_rdv.year if rdv.date_rdv else datetime.now().year
    or_number = ordre_source.numero_or if ordre_source and ordre_source.numero_or else f"OR-{year}-{str(rdv.id).zfill(3)}"
    ref_date = ordre_source.created_at if ordre_source and ordre_source.created_at else rdv.date_rdv
    date_emission = ref_date.strftime('%d/%m/%Y') if ref_date else '-'
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
    atelier_nom = getattr(atelier, 'nom', None) or "PRO MOTO SERVICE"
    atelier_adresse = getattr(atelier, 'adresse', None) or "12 Rue du Circuit"
    atelier_ville = getattr(atelier, 'ville', None) or "Le Mans"
    atelier_tel = getattr(atelier, 'telephone', None) or "02 43 00 00 00"
    atelier_email = getattr(atelier, 'email', None) or "contact@atelier-moto.fr"
    atelier_siret = getattr(atelier, 'siret', None) or "123 456 789 0001"

    header_left = Paragraph(
        f"<font size='22'><b>PRO <font color='#FB923C'>MOTO</font> WORKSHOP</b></font><br/>"
        f"<font size='7' color='#CBD5E1'><b>ENGINEERING &amp; PERFORMANCE DIVISION</b></font><br/>"
        f"<font size='7'>{atelier_nom} • {atelier_adresse}, {atelier_ville}</font><br/>"
        f"<font size='7'>Tel: {atelier_tel} | {atelier_email} | SIRET: {atelier_siret}</font>",
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
        ('TOPPADDING', (0, 0), (-1, -1), 7),
        ('BOTTOMPADDING', (0, 0), (-1, -1), 7),
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
    elements.append(Spacer(1, 8))

    # ===== CLIENT & VEHICULE - 2 colonnes encadrées =====
    def info_cell(label, value):
        return Paragraph(f"<font size='7' color='#999999'>{label}</font><br/><font size='10'><b>{value or '-'}</b></font>", s_val_sm)

    # Client
    client_cells = [
        [Paragraph("<font color='#E8480A'><b>CLIENT</b></font>", s_section)],
        [info_cell("Nom complet", f"{rdv.client.prenom} {rdv.client.nom}")],
        [info_cell("Telephone", rdv.client.telephone)],
        [info_cell("Email", rdv.client.email or '-')],
        [info_cell("Adresse", getattr(rdv.client, 'adresse', None) or '-')],
    ]
    client_table = Table(client_cells, colWidths=[usable_w * 0.48])
    client_table.setStyle(TableStyle([
        ('BOX', (0, 0), (-1, -1), 1, GRIS_BORDER),
        ('BACKGROUND', (0, 0), (-1, 0), ORANGE_LIGHT),
        ('TOPPADDING', (0, 0), (-1, -1), 3),
        ('BOTTOMPADDING', (0, 0), (-1, -1), 3),
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
    kilometrage_value = ordre_source.kilometrage if ordre_source and ordre_source.kilometrage is not None else rdv.kilometrage
    if kilometrage_value:
        vehicule_cells.append([info_cell("Kilometrage", f"{kilometrage_value} km")])

    vehicule_table = Table(vehicule_cells, colWidths=[usable_w * 0.48])
    vehicule_table.setStyle(TableStyle([
        ('BOX', (0, 0), (-1, -1), 1, GRIS_BORDER),
        ('BACKGROUND', (0, 0), (-1, 0), ORANGE_LIGHT),
        ('TOPPADDING', (0, 0), (-1, -1), 3),
        ('BOTTOMPADDING', (0, 0), (-1, -1), 3),
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
    elements.append(Spacer(1, 6))

    quick_state_data = [[
        Paragraph(f"<b>Priorite</b><br/>{priority_label}", s_text),
        Paragraph(f"<b>Niveau carburant</b><br/>{str(fuel_level) + '/4' if fuel_level is not None else 'Non renseigne'}", s_text),
        Paragraph(f"<b>Zones signalees</b><br/>{', '.join([damage_labels.get(d, d) for d in body_damages]) if body_damages else 'Aucune'}", s_text),
    ]]
    quick_state = Table(quick_state_data, colWidths=[usable_w * 0.33, usable_w * 0.22, usable_w * 0.45])
    quick_state.setStyle(TableStyle([
        ('BACKGROUND', (0, 0), (-1, -1), colors.HexColor('#F8FAFC')),
        ('BOX', (0, 0), (-1, -1), 1, GRIS_BORDER),
        ('VALIGN', (0, 0), (-1, -1), 'MIDDLE'),
        ('LEFTPADDING', (0, 0), (-1, -1), 10),
        ('RIGHTPADDING', (0, 0), (-1, -1), 10),
        ('TOPPADDING', (0, 0), (-1, -1), 4),
        ('BOTTOMPADDING', (0, 0), (-1, -1), 4),
    ]))
    elements.append(quick_state)
    elements.append(Spacer(1, 8))

    # ===== ETAT A L'ARRIVEE =====
    if etat_meta:
        parts = []
        if etat_meta.get("points"):
            for pt in etat_meta.get("points", []):
                if isinstance(pt, str):
                    label = next((item["label"] for item in [
                        {"key": "carrosserie_ok", "label": "Carrosserie OK"},
                        {"key": "rayures", "label": "Rayures visibles"},
                        {"key": "bosses", "label": "Bosses / chocs"},
                        {"key": "freins_ok", "label": "Freins OK"},
                        {"key": "pneus_av_ok", "label": "Pneu avant OK"},
                        {"key": "pneus_ar_ok", "label": "Pneu arriere OK"},
                        {"key": "eclairage_ok", "label": "Eclairage OK"},
                        {"key": "retros_ok", "label": "Retroviseurs OK"},
                        {"key": "clignotants_ok", "label": "Clignotants OK"},
                        {"key": "compteur_ok", "label": "Compteur OK"},
                        {"key": "fuite_visible", "label": "Fuite visible"},
                    ] if item["key"] == pt), pt)
                    parts.append(f"• {label}")
                elif isinstance(pt, dict):
                    label = pt.get("label") or pt.get("nom") or pt.get("key") or pt.get("code") or "Point de controle"
                    parts.append(f"• {label}")
        if etat_meta.get("observations"):
            parts.append(f"<b>Observations :</b> {etat_meta.get('observations')}")
        if parts:
            etat_box = Table([[Paragraph("<font color='#E8480A'><b>ETAT DU VEHICULE A L'ARRIVEE</b></font>", s_section)] , [Paragraph("<br/>".join(parts), s_text)]], colWidths=[usable_w])
            etat_box.setStyle(TableStyle([
                ('BACKGROUND', (0, 0), (-1, 0), ORANGE_LIGHT),
                ('BACKGROUND', (0, 1), (-1, -1), GRIS_CLAIR),
                ('BOX', (0, 0), (-1, -1), 1, GRIS_BORDER),
                ('LEFTPADDING', (0, 0), (-1, -1), 10),
                ('RIGHTPADDING', (0, 0), (-1, -1), 10),
                ('TOPPADDING', (0, 0), (-1, -1), 8),
                ('BOTTOMPADDING', (0, 0), (-1, -1), 8),
            ]))
            elements.append(etat_box)
            elements.append(Spacer(1, 10))

    # ===== WORK ZONE (comme la page master) =====
    intervention_items = []
    for row in estimate_rows:
        label = row.get('label') or row.get('designation') or 'Intervention atelier'
        if label not in intervention_items:
            intervention_items.append(label)
    if not intervention_items:
        intervention_items = [rdv.type_intervention or 'Intervention atelier']
    if len(intervention_items) % 2 != 0:
        intervention_items.append('')

    checklist_rows = []
    for i in range(0, min(6, len(intervention_items)), 2):
        left_label = intervention_items[i]
        right_label = intervention_items[i + 1] if i + 1 < len(intervention_items) else ''
        left = Paragraph(("☑ " + left_label) if left_label else "", s_text)
        right = Paragraph(("☑ " + right_label) if right_label else "", s_text)
        checklist_rows.append([left, right])

    left_block_data = [
        [Paragraph("<font color='#E8480A'><b>INTERVENTIONS PROGRAMMEES</b></font>", s_section)],
        [Table(checklist_rows, colWidths=[usable_w * 0.30, usable_w * 0.30])],
        [Paragraph("<font size='8' color='#999999'><b>Observations Atelier &amp; Symptomes Clients</b></font>", s_label)],
        [Table([[Paragraph(observations or 'Notez ici les bruits, fuites ou comportements anormaux...', s_text)]], colWidths=[usable_w * 0.60])],
    ]
    left_block = Table(left_block_data, colWidths=[usable_w * 0.62])
    left_block.setStyle(TableStyle([
        ('VALIGN', (0, 0), (-1, -1), 'TOP'),
        ('LEFTPADDING', (0, 0), (-1, -1), 0),
        ('RIGHTPADDING', (0, 0), (-1, -1), 0),
        ('TOPPADDING', (0, 0), (-1, -1), 2),
        ('BOTTOMPADDING', (0, 0), (-1, -1), 6),
        ('BOX', (0, 3), (-1, 3), 1, GRIS_BORDER),
        ('BACKGROUND', (0, 3), (-1, 3), colors.HexColor('#FCFCFD')),
    ]))

    diagram_note = Paragraph("INSTRUCTIONS : Marquez précisément les impacts, rayures ou fissures avant toute intervention.", ParagraphStyle('diagNote', parent=styles['Normal'], fontSize=7, textColor=colors.HexColor('#9A3412'), leading=10))
    diagram_box = Table([
        [Paragraph("<font color='#1F2937'><b>CONTROLE CARROSSERIE</b></font>", s_section)],
        [build_moto_diagram(54*mm, 36*mm)],
        [diagram_note],
    ], colWidths=[usable_w * 0.34])
    diagram_box.setStyle(TableStyle([
        ('BOX', (0, 1), (-1, 1), 1, GRIS_BORDER),
        ('BACKGROUND', (0, 1), (-1, 1), colors.white),
        ('BACKGROUND', (0, 2), (-1, 2), colors.HexColor('#FFF7ED')),
        ('BOX', (0, 2), (-1, 2), 1, colors.HexColor('#FDBA74')),
        ('LEFTPADDING', (0, 0), (-1, -1), 6),
        ('RIGHTPADDING', (0, 0), (-1, -1), 6),
        ('TOPPADDING', (0, 0), (-1, -1), 4),
        ('BOTTOMPADDING', (0, 0), (-1, -1), 4),
        ('VALIGN', (0, 0), (-1, -1), 'TOP'),
    ]))

    work_zone = Table([[left_block, diagram_box]], colWidths=[usable_w * 0.64, usable_w * 0.34])
    work_zone.setStyle(TableStyle([
        ('VALIGN', (0, 0), (-1, -1), 'TOP'),
        ('LEFTPADDING', (0, 0), (-1, -1), 0),
        ('RIGHTPADDING', (0, 0), (-1, -1), 0),
    ]))
    elements.append(work_zone)
    elements.append(Spacer(1, 8))

    # ===== INTERVENTIONS & ESTIMATION =====
    elements.append(Paragraph("<font color='#E8480A'><b>ESTIMATION ATELIER</b></font>", s_section))

    estimate_table_rows = [[
        Paragraph("<b>Operation / Reference</b>", s_th),
        Paragraph("<b>Qte</b>", s_th),
        Paragraph("<b>Montant EST.</b>", s_th),
    ]]
    total_estime = 0.0
    for row in estimate_rows:
        label = row.get("label") or row.get("designation") or "Intervention atelier"
        qty = row.get("qty", row.get("quantite", 1)) or 1
        amount = row.get("amount", row.get("montant"))
        try:
            qty_val = float(qty)
        except Exception:
            qty_val = 1.0
        try:
            amount_val = float(amount) if amount is not None else None
        except Exception:
            amount_val = None
        if amount_val is not None:
            total_estime += qty_val * amount_val
        estimate_table_rows.append([
            Paragraph(str(label), s_td),
            Paragraph(str(int(qty_val) if qty_val.is_integer() else qty_val), s_td),
            Paragraph(f"{amount_val:.2f} EUR" if amount_val is not None else "A chiffrer", s_td_r),
        ])

    if rdv.commentaire:
        estimate_table_rows.append([
            Paragraph("Commentaire client", s_td),
            Paragraph("-", s_td),
            Paragraph("-", s_td_r),
        ])
        estimate_table_rows.append([
            Paragraph(rdv.commentaire, s_text),
            Paragraph("", s_td),
            Paragraph("", s_td_r),
        ])

    total_display = f"{total_estime:.2f} EUR" if total_estime else (f"{float(rdv.prix_final or rdv.prix_estime):.2f} EUR" if (rdv.prix_final or rdv.prix_estime) is not None else "A chiffrer")
    estimate_table_rows.append([
        Paragraph("<b>Total Estimatif TTC</b>", ParagraphStyle('tot_est', parent=styles['Normal'], fontSize=9, textColor=BLANC, fontName='Helvetica-Bold', alignment=TA_RIGHT)),
        Paragraph("", s_td),
        Paragraph(f"<b>{total_display}</b>", ParagraphStyle('tot_est_r', parent=styles['Normal'], fontSize=10, textColor=ORANGE, fontName='Helvetica-Bold', alignment=TA_RIGHT)),
    ])

    est_table = Table(estimate_table_rows, colWidths=[usable_w * 0.62, usable_w * 0.12, usable_w * 0.26])
    est_table.setStyle(TableStyle([
        ('BACKGROUND', (0, 0), (-1, 0), NOIR),
        ('TEXTCOLOR', (0, 0), (-1, 0), BLANC),
        ('BACKGROUND', (0, -1), (-1, -1), NOIR),
        ('LINEBELOW', (0, 0), (-1, -2), 0.5, GRIS_BORDER),
        ('BOX', (0, 0), (-1, -1), 1, GRIS_BORDER),
        ('VALIGN', (0, 0), (-1, -1), 'MIDDLE'),
        ('LEFTPADDING', (0, 0), (-1, -1), 6),
        ('RIGHTPADDING', (0, 0), (-1, -1), 6),
        ('TOPPADDING', (0, 0), (-1, -1), 6),
        ('BOTTOMPADDING', (0, 0), (-1, -1), 6),
    ]))
    elements.append(est_table)

    if body_damages or schema_notes or photos_list:
        elements.append(Spacer(1, 10))
        elements.append(Paragraph("<font color='#E8480A'><b>CONTROLE CARROSSERIE</b></font>", s_section))
        damages_text = ", ".join([damage_labels.get(d, d) for d in body_damages]) if body_damages else "Aucun impact note"
        content_items = [Paragraph(f"<b>Zones notees :</b> {damages_text}", s_text)]
        if schema_notes:
            content_items.append(Spacer(1, 4))
            content_items.append(Paragraph(f"<b>Notes schema :</b> {schema_notes}", s_text))
        if photos_list:
            photo_cells = []
            for photo in photos_list[:3]:
                try:
                    data = photo.split(',')[1] if isinstance(photo, str) and ',' in photo else photo
                    img = RLImage(BytesIO(base64.b64decode(data)), width=42*mm, height=28*mm)
                    photo_cells.append(img)
                except Exception as e:
                    logger.warning("Erreur chargement photo etat rdv_id=%s: %s", rdv.id, e)
            if photo_cells:
                content_items.append(Spacer(1, 6))
                photo_table = Table([photo_cells], colWidths=[42*mm] * len(photo_cells))
                photo_table.setStyle(TableStyle([
                    ('VALIGN', (0, 0), (-1, -1), 'MIDDLE'),
                    ('LEFTPADDING', (0, 0), (-1, -1), 2),
                    ('RIGHTPADDING', (0, 0), (-1, -1), 6),
                ]))
                content_items.append(photo_table)
        carrosserie_box = Table([[content_items]], colWidths=[usable_w])
        carrosserie_box.setStyle(TableStyle([
            ('BACKGROUND', (0, 0), (-1, -1), colors.HexColor('#FFF7ED')),
            ('BOX', (0, 0), (-1, -1), 1, colors.HexColor('#FDBA74')),
            ('LEFTPADDING', (0, 0), (-1, -1), 10),
            ('RIGHTPADDING', (0, 0), (-1, -1), 10),
            ('TOPPADDING', (0, 0), (-1, -1), 8),
            ('BOTTOMPADDING', (0, 0), (-1, -1), 8),
        ]))
        elements.append(carrosserie_box)

    # ===== NOTES ATELIER (version compacte pour rester sur une page) =====
    atelier_notes = []
    if rapport and rapport.travaux_realises:
        atelier_notes.append(f"<b>Travaux realises :</b> {rapport.travaux_realises}")
    if rapport and rapport.alertes:
        atelier_notes.append(f"<b>Alerte :</b> {rapport.alertes}")
    if rapport and rapport.recommandations:
        atelier_notes.append(f"<b>Recommandation :</b> {rapport.recommandations}")

    if atelier_notes:
        elements.append(Spacer(1, 6))
        atelier_note_box = Table([[Paragraph("<br/>".join(atelier_notes[:2]), s_text)]], colWidths=[usable_w])
        atelier_note_box.setStyle(TableStyle([
            ('BACKGROUND', (0, 0), (-1, -1), colors.HexColor('#F8FAFC')),
            ('BOX', (0, 0), (-1, -1), 1, GRIS_BORDER),
            ('LEFTPADDING', (0, 0), (-1, -1), 8),
            ('RIGHTPADDING', (0, 0), (-1, -1), 8),
            ('TOPPADDING', (0, 0), (-1, -1), 5),
            ('BOTTOMPADDING', (0, 0), (-1, -1), 5),
        ]))
        elements.append(atelier_note_box)

    # ===== LEGAL & SIGNATURES =====
    elements.append(Spacer(1, 10))

    or_initial = ordre_source or db.query(OrdreReparation).filter(
        OrdreReparation.rendez_vous_id == rdv.id,
        OrdreReparation.type_or == "initial"
    ).order_by(OrdreReparation.created_at.desc()).first()

    sig_client = ''
    if or_initial and or_initial.signature_client:
        try:
            import base64
            data = or_initial.signature_client.split(',')[1] if ',' in or_initial.signature_client else or_initial.signature_client
            img_bytes = base64.b64decode(data)
            sig_client = RLImage(BytesIO(img_bytes), width=46*mm, height=20*mm)
        except Exception as e:
            logger.warning("Erreur chargement signature BDD rdv_id=%s: %s", rdv.id, e)
            sig_client = ''
    else:
        signature_path = FsPath("signatures") / f"rdv_{rdv.id}_signature.png"
        if signature_path.exists():
            try:
                sig_client = RLImage(str(signature_path), width=46*mm, height=20*mm)
            except Exception as e:
                logger.warning("Erreur chargement signature fichier rdv_id=%s: %s", rdv.id, e)
                sig_client = ''

    legal_text = (
        "• Le client accepte le devis estimatif et autorise les essais sur route.<br/>"
        "• Le garage n'est pas responsable des objets laisses dans les sacoches ou coffres.<br/>"
        "• Toute piece non reclamee lors de la reprise pourra etre recyclee apres 48h."
    )
    legal_style = ParagraphStyle('legalBox', parent=styles['Normal'], fontSize=7.5, textColor=GRIS, leading=10)
    pill_style = ParagraphStyle('legalPill', parent=styles['Normal'], fontSize=7.5, textColor=colors.HexColor('#9A3412'), fontName='Helvetica-Bold')

    legal_box = Table([
        [Paragraph("<font color='#1F2937'><b>ENGAGEMENT &amp; DECHARGE</b></font>", s_section)],
        [Paragraph(legal_text, legal_style)],
        [Paragraph("Récupération des pièces usagées", pill_style)],
    ], colWidths=[usable_w * 0.44])
    legal_box.setStyle(TableStyle([
        ('BACKGROUND', (0, 2), (-1, 2), colors.HexColor('#FFF7ED')),
        ('BOX', (0, 0), (-1, -1), 1, GRIS_BORDER),
        ('BOX', (0, 2), (-1, 2), 1, colors.HexColor('#FDBA74')),
        ('LEFTPADDING', (0, 0), (-1, -1), 10),
        ('RIGHTPADDING', (0, 0), (-1, -1), 10),
        ('TOPPADDING', (0, 0), (-1, -1), 8),
        ('BOTTOMPADDING', (0, 0), (-1, -1), 8),
        ('VALIGN', (0, 0), (-1, -1), 'TOP'),
    ]))

    sig_data = [
        [Paragraph("<b>Le Client</b><br/><font size='7' color='#999'>Bon pour accord</font>", s_label),
         Paragraph("<b>Atelier / Expert</b><br/><font size='7' color='#999'>Validation</font>", s_label)],
        [sig_client or Spacer(1, 24*mm), Spacer(1, 24*mm)],
    ]
    sig_table = Table(sig_data, colWidths=[usable_w * 0.25, usable_w * 0.25], rowHeights=[8*mm, 22*mm])
    sig_table.setStyle(TableStyle([
        ('ALIGN', (0, 0), (-1, -1), 'CENTER'),
        ('VALIGN', (0, 0), (-1, -1), 'TOP'),
        ('BOX', (0, 0), (0, -1), 1, GRIS_BORDER),
        ('BOX', (1, 0), (1, -1), 1, GRIS_BORDER),
        ('LEFTPADDING', (0, 0), (-1, -1), 8),
        ('RIGHTPADDING', (0, 0), (-1, -1), 8),
        ('TOPPADDING', (0, 0), (-1, -1), 6),
        ('BOTTOMPADDING', (0, 0), (-1, -1), 6),
        ('BACKGROUND', (0, 0), (-1, -1), colors.HexColor('#FCFCFD')),
    ]))

    legal_sig = Table([[legal_box, sig_table]], colWidths=[usable_w * 0.46, usable_w * 0.52])
    legal_sig.setStyle(TableStyle([
        ('VALIGN', (0, 0), (-1, -1), 'TOP'),
        ('LEFTPADDING', (0, 0), (-1, -1), 0),
        ('RIGHTPADDING', (0, 0), (-1, -1), 0),
    ]))
    elements.append(legal_sig)
    elements.append(Spacer(1, 10))

    footer_left = Paragraph(
        f"<b>{atelier_nom}</b><br/><font size='7' color='#CBD5E1'>SIRET {atelier_siret} - APE 4540Z</font>",
        ParagraphStyle('footerLeft', parent=styles['Normal'], fontSize=8.5, textColor=BLANC, fontName='Helvetica-Bold')
    )
    footer_right = Paragraph(
        f"<font size='8'><b>Adresse :</b> {atelier_adresse}, {atelier_ville}<br/><b>Tél :</b> {atelier_tel}</font>",
        ParagraphStyle('footerRight', parent=styles['Normal'], fontSize=8, textColor=BLANC, alignment=TA_RIGHT)
    )
    footer_band = Table([[footer_left, footer_right]], colWidths=[usable_w * 0.46, usable_w * 0.54])
    footer_band.setStyle(TableStyle([
        ('BACKGROUND', (0, 0), (-1, -1), NOIR),
        ('LINEABOVE', (0, 0), (-1, 0), 3, ORANGE),
        ('LEFTPADDING', (0, 0), (-1, -1), 12),
        ('RIGHTPADDING', (0, 0), (-1, -1), 12),
        ('TOPPADDING', (0, 0), (-1, -1), 6),
        ('BOTTOMPADDING', (0, 0), (-1, -1), 6),
        ('VALIGN', (0, 0), (-1, -1), 'MIDDLE'),
    ]))
    elements.append(footer_band)

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
