# Facturation & Encaissement API - Atelier Moto
from fastapi import APIRouter, Depends, HTTPException
from sqlalchemy.orm import Session, joinedload
from sqlalchemy import func, desc
from datetime import datetime, date, timedelta
from typing import Optional, List
from pydantic import BaseModel
import json
import logging

from models import (
    get_db, RendezVous, Client, Vehicule, ConfigAtelier,
    PieceUtilisee, PieceDetachee, Facture, LigneFacture, Paiement, RolePermission,
    Prestation, GrilleTarifaire
)
from auth import get_current_user, User

router = APIRouter(tags=["facturation"])
logger = logging.getLogger("ateliermoto.facturation")


def _tenant_id(current_user: User) -> int:
    return int(getattr(current_user, "atelier_id", 1) or 1)


def _has_permission(current_user: User, db: Session, permission: str) -> bool:
    if current_user.role == "super_admin":
        return True
    rp = db.query(RolePermission).filter(RolePermission.role == (current_user.role or "")).first()
    if not rp:
        legacy = {
            "admin": {"billing.view", "billing.edit", "billing.pay", "billing.pdf"},
            "receptionnaire": {"billing.view", "billing.edit", "billing.pay", "billing.pdf"},
            "service_client": set(),
            "mecanicien": set(),
        }
        return permission in legacy.get(current_user.role or "", set())
    try:
        permissions = json.loads(rp.permissions_json or "[]")
    except Exception:
        permissions = []
    return permission in permissions


def _ensure_billing_allowed(current_user: User, db: Session, permission: str) -> None:
    if not _has_permission(current_user, db, permission):
        raise HTTPException(status_code=403, detail="Acces refuse")


# ========== PYDANTIC MODELS ==========

class FacturerRequest(BaseModel):
    remise_pourcentage: float = 0.0
    notes: Optional[str] = None

class EncaisserRequest(BaseModel):
    montant: float
    mode_paiement: str  # cb, especes, cheque, virement, differe
    reference: Optional[str] = None
    notes: Optional[str] = None


# ========== HELPERS ==========

MODES_PAIEMENT = ["cb", "especes", "cheque", "virement", "differe"]
MODE_LABELS = {
    "cb": "Carte bancaire",
    "especes": "Espèces",
    "cheque": "Chèque",
    "virement": "Virement bancaire",
    "differe": "Paiement différé"
}


def _as_float(value, default=0.0):
    if value is None:
        return default
    return float(value)


def generer_numero_facture(db: Session, atelier_id: int) -> str:
    annee = datetime.now().year
    count = db.query(Facture).filter(
        Facture.atelier_id == atelier_id,
        Facture.numero_facture.like(f"F-{annee}-%")
    ).count()
    return f"F-{annee}-{count + 1:04d}"


def resolve_prestations(rdv, db):
    """Résout les prestations associées au RDV via type_intervention (nom)"""
    if not rdv.type_intervention:
        return []
    # type_intervention peut être "Prestation A, Prestation B"
    noms = [n.strip() for n in rdv.type_intervention.split(",") if n.strip()]
    prestations = []
    for nom in noms:
        p = db.query(Prestation).filter(
            Prestation.nom == nom,
            Prestation.is_active == 1
        ).first()
        if p:
            prestations.append(p)
    return prestations


def compute_facturation(rdv, config, db):
    """Calcule le breakdown facturation en respectant forfait (prix_estime) vs taux horaire"""
    taux_horaire = _as_float(config.taux_horaire_mo_standard, 65.0) if config else 65.0
    tva_mo_taux = _as_float(config.tva_mo_taux, 20.0) if config else 20.0
    tva_pieces_taux = _as_float(config.tva_pieces_taux, 20.0) if config else 20.0

    # MO : max(temps effectif, temps estimé)
    temps_effectif = rdv.temps_effectif_minutes or rdv.temps_final or 0
    temps_estime = rdv.temps_estime or 0
    temps_facture = max(temps_effectif, temps_estime)
    if temps_facture == 0:
        temps_facture = 60  # minimum 1h

    # Déterminer si on utilise un forfait ou le taux horaire
    # Un forfait est défini si le RDV a un prix_estime (prix TTC convenu à la prise de RDV)
    prix_estime = _as_float(rdv.prix_estime)
    is_forfait = rdv.prix_estime is not None and prix_estime > 0

    if is_forfait:
        # Mode forfait : le prix_estime est TTC, on back-calcule le HT
        prix_forfait_ttc = prix_estime
        total_mo_ht = round(prix_forfait_ttc / (1 + tva_mo_taux / 100), 2)
    else:
        # Mode horaire : calcul classique temps x taux
        total_mo_ht = round((temps_facture / 60) * taux_horaire, 2)

    # Pièces depuis table PieceUtilisee
    pieces_utilisees = db.query(PieceUtilisee).options(
        joinedload(PieceUtilisee.piece)
    ).filter(
        PieceUtilisee.rendez_vous_id == rdv.id
    ).all()
    total_pieces_ht = round(
        sum(pu.quantite * _as_float(pu.prix_vente_unitaire) for pu in pieces_utilisees),
        2,
    )

    # TVA séparée
    tva_mo = round(total_mo_ht * (tva_mo_taux / 100), 2)
    tva_pieces = round(total_pieces_ht * (tva_pieces_taux / 100), 2)

    total_ht = round(total_mo_ht + total_pieces_ht, 2)
    total_tva = round(tva_mo + tva_pieces, 2)
    total_ttc = round(total_ht + total_tva, 2)

    return {
        "temps_facture_minutes": temps_facture,
        "taux_horaire": taux_horaire,
        "tva_mo_taux": tva_mo_taux,
        "tva_pieces_taux": tva_pieces_taux,
        "total_mo_ht": total_mo_ht,
        "total_pieces_ht": total_pieces_ht,
        "total_ht": total_ht,
        "tva_mo": tva_mo,
        "tva_pieces": tva_pieces,
        "total_tva": total_tva,
        "total_ttc": total_ttc,
        "is_forfait": is_forfait,
        "forfait_designation": rdv.type_intervention if is_forfait else None,
        "pieces": [{
            "nom": pu.piece.nom if pu.piece else "Pièce",
            "reference": pu.piece.reference if pu.piece else "",
            "quantite": pu.quantite,
            "prix_unitaire_ht": _as_float(pu.prix_vente_unitaire),
            "total_ht": round(pu.quantite * _as_float(pu.prix_vente_unitaire), 2)
        } for pu in pieces_utilisees]
    }


def facture_to_dict(facture, include_lignes=False, include_paiements=False):
    """Serialize une facture en dict"""
    montant_paye = (
        sum(_as_float(p.montant) for p in facture.paiements) if facture.paiements else 0.0
    )
    total_ttc = _as_float(facture.total_ttc)
    result = {
        "id": facture.id,
        "numero_facture": facture.numero_facture,
        "rendez_vous_id": facture.rendez_vous_id,
        "client_id": facture.client_id,
        "client_nom": facture.client.nom if facture.client else "",
        "client_prenom": facture.client.prenom if facture.client else "",
        "client_telephone": facture.client.telephone if facture.client else "",
        "vehicule_desc": f"{facture.vehicule.marque} {facture.vehicule.modele}" if facture.vehicule else "",
        "vehicule_plaque": facture.vehicule.plaque if facture.vehicule else "",
        "total_mo_ht": facture.total_mo_ht,
        "total_pieces_ht": facture.total_pieces_ht,
        "total_ht": facture.total_ht,
        "tva_mo": facture.tva_mo,
        "tva_pieces": facture.tva_pieces,
        "total_tva": facture.total_tva,
        "total_ttc": facture.total_ttc,
        "remise_pourcentage": facture.remise_pourcentage,
        "remise_montant": facture.remise_montant,
        "temps_facture_minutes": facture.temps_facture_minutes,
        "taux_horaire": facture.taux_horaire,
        "tva_mo_taux": facture.tva_mo_taux,
        "tva_pieces_taux": facture.tva_pieces_taux,
        "statut": facture.statut,
        "date_creation": facture.date_creation.isoformat() if facture.date_creation else None,
        "date_echeance": facture.date_echeance.isoformat() if facture.date_echeance else None,
        "notes": facture.notes,
        "montant_paye": round(montant_paye, 2),
        "montant_restant": round(total_ttc - montant_paye, 2)
    }
    if include_lignes:
        result["lignes"] = [{
            "id": l.id,
            "type_ligne": l.type_ligne,
            "designation": l.designation,
            "reference": l.reference,
            "quantite": l.quantite,
            "prix_unitaire_ht": l.prix_unitaire_ht,
            "taux_tva": l.taux_tva,
            "total_ligne_ht": l.total_ligne_ht,
            "total_ligne_ttc": l.total_ligne_ttc
        } for l in facture.lignes]
    if include_paiements:
        result["paiements"] = [{
            "id": p.id,
            "montant": p.montant,
            "mode_paiement": p.mode_paiement,
            "mode_label": MODE_LABELS.get(p.mode_paiement, p.mode_paiement),
            "reference": p.reference,
            "date_paiement": p.date_paiement.isoformat() if p.date_paiement else None,
            "notes": p.notes
        } for p in facture.paiements]
    return result


# ========== ENDPOINTS ==========

@router.get("/api/rendez-vous/{rdv_id}/preview-facture")
def preview_facture(
    rdv_id: int,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """Preview du calcul de facturation avant création"""
    _ensure_billing_allowed(current_user, db, "billing.view")
    atelier_id = _tenant_id(current_user)
    rdv = db.query(RendezVous).options(
        joinedload(RendezVous.vehicule)
    ).filter(RendezVous.id == rdv_id, RendezVous.atelier_id == atelier_id).first()
    if not rdv:
        raise HTTPException(status_code=404, detail="RDV non trouvé")
    if rdv.statut not in ("termine", "facture"):
        raise HTTPException(status_code=400, detail="Le RDV doit être terminé pour facturer")

    config = db.query(ConfigAtelier).filter(ConfigAtelier.atelier_id == atelier_id).first()
    breakdown = compute_facturation(rdv, config, db)
    breakdown["rdv_id"] = rdv.id
    breakdown["type_intervention"] = rdv.type_intervention
    return breakdown


@router.post("/api/rendez-vous/{rdv_id}/facturer")
def facturer_rdv(
    rdv_id: int,
    data: FacturerRequest,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """Crée une facture à partir d'un RDV terminé"""
    _ensure_billing_allowed(current_user, db, "billing.edit")

    atelier_id = _tenant_id(current_user)
    rdv = db.query(RendezVous).options(
        joinedload(RendezVous.client),
        joinedload(RendezVous.vehicule)
    ).filter(RendezVous.id == rdv_id, RendezVous.atelier_id == atelier_id).first()
    if not rdv:
        raise HTTPException(status_code=404, detail="RDV non trouvé")
    if rdv.statut != "termine":
        raise HTTPException(status_code=400, detail=f"Le RDV doit être au statut 'termine' (actuel: {rdv.statut})")

    # Vérifier qu'il n'y a pas déjà une facture
    existing = db.query(Facture).filter(Facture.rendez_vous_id == rdv_id, Facture.atelier_id == atelier_id).first()
    if existing:
        raise HTTPException(status_code=400, detail=f"Une facture existe déjà: {existing.numero_facture}")

    config = db.query(ConfigAtelier).filter(ConfigAtelier.atelier_id == atelier_id).first()
    breakdown = compute_facturation(rdv, config, db)

    # Appliquer remise
    remise_pct = data.remise_pourcentage or 0
    remise_montant = round(breakdown["total_ht"] * (remise_pct / 100), 2)
    total_ht_remise = round(breakdown["total_ht"] - remise_montant, 2)

    # Recalculer TVA sur base remisée (proportionnel)
    ratio = total_ht_remise / breakdown["total_ht"] if breakdown["total_ht"] > 0 else 1
    tva_mo = round(breakdown["tva_mo"] * ratio, 2)
    tva_pieces = round(breakdown["tva_pieces"] * ratio, 2)
    total_tva = round(tva_mo + tva_pieces, 2)
    total_ttc = round(total_ht_remise + total_tva, 2)

    # Créer la facture
    numero = generer_numero_facture(db, atelier_id)
    facture = Facture(
        atelier_id=atelier_id,
        numero_facture=numero,
        rendez_vous_id=rdv.id,
        client_id=rdv.client_id,
        vehicule_id=rdv.vehicule_id,
        total_mo_ht=breakdown["total_mo_ht"],
        total_pieces_ht=breakdown["total_pieces_ht"],
        total_ht=total_ht_remise,
        tva_mo=tva_mo,
        tva_pieces=tva_pieces,
        total_tva=total_tva,
        total_ttc=total_ttc,
        remise_pourcentage=remise_pct,
        remise_montant=remise_montant,
        temps_facture_minutes=breakdown["temps_facture_minutes"],
        taux_horaire=breakdown["taux_horaire"],
        tva_mo_taux=breakdown["tva_mo_taux"],
        tva_pieces_taux=breakdown["tva_pieces_taux"],
        statut="emise",
        notes=data.notes,
        date_echeance=date.today() + timedelta(days=30)
    )
    db.add(facture)
    db.flush()

    # Créer les lignes de facture
    ordre = 0

    # Ligne(s) MO
    if breakdown.get("is_forfait"):
        # Mode forfait : une seule ligne avec le prix fixe
        mo_ligne = LigneFacture(
            facture_id=facture.id,
            type_ligne="main_oeuvre",
            designation=f"Forfait - {breakdown.get('forfait_designation', rdv.type_intervention)}",
            quantite=1,
            prix_unitaire_ht=breakdown["total_mo_ht"],
            taux_tva=breakdown["tva_mo_taux"],
            total_ligne_ht=breakdown["total_mo_ht"],
            total_ligne_ttc=round(breakdown["total_mo_ht"] * (1 + breakdown["tva_mo_taux"] / 100), 2),
            ordre=ordre
        )
        db.add(mo_ligne)
        ordre += 1
    else:
        # Mode horaire : ligne classique temps x taux
        mo_ligne = LigneFacture(
            facture_id=facture.id,
            type_ligne="main_oeuvre",
            designation=f"Main d'oeuvre ({breakdown['temps_facture_minutes']} min)",
            quantite=round(breakdown["temps_facture_minutes"] / 60, 2),
            prix_unitaire_ht=breakdown["taux_horaire"],
            taux_tva=breakdown["tva_mo_taux"],
            total_ligne_ht=breakdown["total_mo_ht"],
            total_ligne_ttc=round(breakdown["total_mo_ht"] * (1 + breakdown["tva_mo_taux"] / 100), 2),
            ordre=ordre
        )
        db.add(mo_ligne)
        ordre += 1

    # Lignes pièces
    for p in breakdown["pieces"]:
        piece_ligne = LigneFacture(
            facture_id=facture.id,
            type_ligne="piece",
            designation=p["nom"],
            reference=p["reference"],
            quantite=p["quantite"],
            prix_unitaire_ht=p["prix_unitaire_ht"],
            taux_tva=breakdown["tva_pieces_taux"],
            total_ligne_ht=p["total_ht"],
            total_ligne_ttc=round(p["total_ht"] * (1 + breakdown["tva_pieces_taux"] / 100), 2),
            ordre=ordre
        )
        db.add(piece_ligne)
        ordre += 1

    # Mettre à jour le RDV
    rdv.statut = "facture"
    rdv.prix_final = total_ttc

    db.commit()
    db.refresh(facture)
    logger.info(
        "Facture created numero=%s rdv_id=%s total_ttc=%.2f user=%s",
        facture.numero_facture,
        rdv.id,
        total_ttc,
        current_user.username
    )

    return {
        "message": "Facture créée",
        "numero_facture": numero,
        "facture_id": facture.id,
        "total_ttc": total_ttc
    }


@router.post("/api/factures/{facture_id}/encaisser")
def encaisser(
    facture_id: int,
    data: EncaisserRequest,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """Enregistre un paiement sur une facture"""
    _ensure_billing_allowed(current_user, db, "billing.pay")

    if data.mode_paiement not in MODES_PAIEMENT:
        raise HTTPException(status_code=400, detail=f"Mode invalide. Autorisés: {', '.join(MODES_PAIEMENT)}")

    atelier_id = _tenant_id(current_user)
    facture = db.query(Facture).options(
        joinedload(Facture.paiements)
    ).filter(Facture.id == facture_id, Facture.atelier_id == atelier_id).first()
    if not facture:
        raise HTTPException(status_code=404, detail="Facture non trouvée")
    if facture.statut in ("payee", "annulee"):
        raise HTTPException(status_code=400, detail=f"Facture déjà {facture.statut}")

    montant_deja_paye = sum(_as_float(p.montant) for p in facture.paiements)
    total_ttc = _as_float(facture.total_ttc)
    montant_restant = round(total_ttc - montant_deja_paye, 2)

    if data.montant <= 0:
        raise HTTPException(status_code=400, detail="Le montant doit être positif")
    if data.montant > montant_restant + 0.01:  # tolérance arrondi
        raise HTTPException(status_code=400, detail=f"Montant trop élevé. Reste à payer: {montant_restant} EUR")

    # Créer le paiement
    paiement = Paiement(
        facture_id=facture.id,
        montant=data.montant,
        mode_paiement=data.mode_paiement,
        reference=data.reference,
        notes=data.notes
    )
    db.add(paiement)

    # Mettre à jour le statut
    nouveau_total_paye = montant_deja_paye + float(data.montant)
    if nouveau_total_paye >= total_ttc - 0.01:
        facture.statut = "payee"
        # Mettre à jour le RDV
        rdv = db.query(RendezVous).filter(RendezVous.id == facture.rendez_vous_id, RendezVous.atelier_id == atelier_id).first()
        if rdv:
            rdv.statut = "paye"
    else:
        facture.statut = "partiellement_payee"

    db.commit()
    logger.info(
        "Payment registered facture=%s montant=%.2f mode=%s user=%s",
        facture.numero_facture,
        data.montant,
        data.mode_paiement,
        current_user.username
    )

    return {
        "message": "Paiement enregistré",
        "montant_paye": round(nouveau_total_paye, 2),
        "montant_restant": round(total_ttc - nouveau_total_paye, 2),
        "statut": facture.statut
    }


@router.get("/api/factures")
def list_factures(
    date_debut: Optional[str] = None,
    date_fin: Optional[str] = None,
    statut: Optional[str] = None,
    search: Optional[str] = None,
    skip: int = 0,
    limit: int = 50,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """Liste des factures avec filtres"""
    _ensure_billing_allowed(current_user, db, "billing.view")
    atelier_id = _tenant_id(current_user)
    query = db.query(Facture).options(
        joinedload(Facture.client),
        joinedload(Facture.vehicule),
        joinedload(Facture.paiements)
    ).filter(Facture.atelier_id == atelier_id)

    if date_debut:
        query = query.filter(Facture.date_creation >= datetime.strptime(date_debut, "%Y-%m-%d"))
    if date_fin:
        query = query.filter(Facture.date_creation <= datetime.strptime(date_fin, "%Y-%m-%d") + timedelta(days=1))
    if statut:
        query = query.filter(Facture.statut == statut)
    if search:
        query = query.join(Client).filter(
            (Client.nom.ilike(f"%{search}%")) | (Client.prenom.ilike(f"%{search}%"))
        )

    total = query.count()
    factures = query.order_by(desc(Facture.date_creation)).offset(skip).limit(limit).all()

    return {
        "total": total,
        "factures": [facture_to_dict(f) for f in factures]
    }


@router.get("/api/factures/stats")
def factures_stats(
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """Statistiques de facturation"""
    _ensure_billing_allowed(current_user, db, "billing.view")
    now = datetime.now()
    debut_mois = now.replace(day=1, hour=0, minute=0, second=0, microsecond=0)

    # CA facturé ce mois
    atelier_id = _tenant_id(current_user)
    ca_facture = db.query(func.coalesce(func.sum(Facture.total_ttc), 0.0)).filter(
        Facture.atelier_id == atelier_id,
        Facture.date_creation >= debut_mois,
        Facture.statut != "annulee"
    ).scalar() or 0

    # CA encaissé ce mois
    ca_encaisse = db.query(func.coalesce(func.sum(Paiement.montant), 0.0)).join(Facture).filter(
        Facture.atelier_id == atelier_id,
        Paiement.date_paiement >= debut_mois
    ).scalar() or 0

    # Impayés (factures émises ou partiellement payées)
    factures_impayees = db.query(Facture).options(
        joinedload(Facture.paiements)
    ).filter(
        Facture.atelier_id == atelier_id,
        Facture.statut.in_(["emise", "partiellement_payee"])
    ).all()
    total_impayes = sum(
        f.total_ttc - sum(p.montant for p in f.paiements)
        for f in factures_impayees
    )

    # Nb factures ce mois
    nb_factures = db.query(Facture).filter(
        Facture.atelier_id == atelier_id,
        Facture.date_creation >= debut_mois,
        Facture.statut != "annulee"
    ).count()

    # Répartition par mode de paiement (ce mois)
    repartition = {}
    paiements_mois = db.query(Paiement).join(Facture).filter(
        Facture.atelier_id == atelier_id,
        Paiement.date_paiement >= debut_mois
    ).all()
    for p in paiements_mois:
        label = MODE_LABELS.get(p.mode_paiement, p.mode_paiement)
        repartition[label] = round(repartition.get(label, 0) + p.montant, 2)

    return {
        "ca_facture_mois": round(ca_facture, 2),
        "ca_encaisse_mois": round(ca_encaisse, 2),
        "impayes": round(total_impayes, 2),
        "nb_factures_mois": nb_factures,
        "repartition_paiements": repartition
    }


@router.get("/api/factures/{facture_id}")
def get_facture(
    facture_id: int,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """Détail d'une facture"""
    _ensure_billing_allowed(current_user, db, "billing.view")
    atelier_id = _tenant_id(current_user)
    facture = db.query(Facture).options(
        joinedload(Facture.client),
        joinedload(Facture.vehicule),
        joinedload(Facture.lignes),
        joinedload(Facture.paiements)
    ).filter(Facture.id == facture_id, Facture.atelier_id == atelier_id).first()
    if not facture:
        raise HTTPException(status_code=404, detail="Facture non trouvée")
    return facture_to_dict(facture, include_lignes=True, include_paiements=True)


@router.get("/api/factures/par-rdv/{rdv_id}")
def get_facture_by_rdv(
    rdv_id: int,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """Récupère la facture associée à un RDV"""
    _ensure_billing_allowed(current_user, db, "billing.view")
    atelier_id = _tenant_id(current_user)
    facture = db.query(Facture).options(
        joinedload(Facture.client),
        joinedload(Facture.vehicule),
        joinedload(Facture.lignes),
        joinedload(Facture.paiements)
    ).filter(Facture.rendez_vous_id == rdv_id, Facture.atelier_id == atelier_id).first()
    if not facture:
        raise HTTPException(status_code=404, detail="Aucune facture pour ce RDV")
    return facture_to_dict(facture, include_lignes=True, include_paiements=True)


@router.post("/api/factures/{facture_id}/annuler")
def annuler_facture(
    facture_id: int,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """Annule une facture"""
    _ensure_billing_allowed(current_user, db, "billing.edit")

    atelier_id = _tenant_id(current_user)
    facture = db.query(Facture).filter(Facture.id == facture_id, Facture.atelier_id == atelier_id).first()
    if not facture:
        raise HTTPException(status_code=404, detail="Facture non trouvée")
    if facture.statut == "payee":
        raise HTTPException(status_code=400, detail="Impossible d'annuler une facture payée")

    facture.statut = "annulee"
    # Remettre le RDV en terminé
    rdv = db.query(RendezVous).filter(RendezVous.id == facture.rendez_vous_id, RendezVous.atelier_id == atelier_id).first()
    if rdv and rdv.statut == "facture":
        rdv.statut = "termine"

    db.commit()
    return {"message": "Facture annulée"}


# ========== PDF FACTURE ==========

@router.get("/api/factures/{facture_id}/pdf")
def generate_facture_pdf(
    facture_id: int,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """Génère le PDF de la facture"""
    _ensure_billing_allowed(current_user, db, "billing.pdf")
    from io import BytesIO
    from fastapi.responses import StreamingResponse
    from reportlab.lib.pagesizes import A4
    from reportlab.lib.units import mm
    from reportlab.lib import colors
    from reportlab.lib.enums import TA_RIGHT, TA_CENTER
    from reportlab.platypus import SimpleDocTemplate, Table, TableStyle, Paragraph, Spacer
    from reportlab.lib.styles import getSampleStyleSheet, ParagraphStyle

    atelier_id = _tenant_id(current_user)
    facture = db.query(Facture).options(
        joinedload(Facture.client),
        joinedload(Facture.vehicule),
        joinedload(Facture.lignes),
        joinedload(Facture.paiements),
        joinedload(Facture.rendez_vous)
    ).filter(Facture.id == facture_id, Facture.atelier_id == atelier_id).first()
    if not facture:
        raise HTTPException(status_code=404, detail="Facture non trouvée")

    buffer = BytesIO()
    W, H = A4
    doc = SimpleDocTemplate(buffer, pagesize=A4, rightMargin=12*mm, leftMargin=12*mm, topMargin=10*mm, bottomMargin=10*mm)
    usable_w = W - 24*mm

    # Couleurs
    NOIR = colors.HexColor('#1a1a1a')
    ORANGE = colors.HexColor('#E8480A')
    ORANGE_LIGHT = colors.HexColor('#FFF4F0')
    GRIS_FONCE = colors.HexColor('#333333')
    GRIS = colors.HexColor('#666666')
    GRIS_CLAIR = colors.HexColor('#F7F7F7')
    GRIS_BORDER = colors.HexColor('#E0E0E0')
    BLANC = colors.white
    VERT = colors.HexColor('#22C55E')
    PURPLE = colors.HexColor('#8B5CF6')

    styles = getSampleStyleSheet()
    s_hw = ParagraphStyle('hw', parent=styles['Normal'], fontSize=9, textColor=BLANC, fontName='Helvetica')
    s_hwb = ParagraphStyle('hwb', parent=styles['Normal'], fontSize=10, textColor=BLANC, fontName='Helvetica-Bold')
    s_title = ParagraphStyle('ft', parent=styles['Normal'], fontSize=22, textColor=ORANGE, fontName='Helvetica-Bold', alignment=TA_RIGHT)
    s_section = ParagraphStyle('sec', parent=styles['Normal'], fontSize=11, textColor=NOIR, fontName='Helvetica-Bold', spaceBefore=12, spaceAfter=6)
    s_label = ParagraphStyle('lbl', parent=styles['Normal'], fontSize=8, textColor=GRIS, fontName='Helvetica')
    s_val = ParagraphStyle('val', parent=styles['Normal'], fontSize=10, textColor=GRIS_FONCE, fontName='Helvetica-Bold')
    s_val_sm = ParagraphStyle('vsm', parent=styles['Normal'], fontSize=9, textColor=GRIS_FONCE, fontName='Helvetica')
    s_th = ParagraphStyle('th', parent=styles['Normal'], fontSize=8, textColor=GRIS, fontName='Helvetica-Bold', alignment=TA_CENTER)
    s_td = ParagraphStyle('td', parent=styles['Normal'], fontSize=9, textColor=GRIS_FONCE, fontName='Helvetica')
    s_td_r = ParagraphStyle('tdr', parent=styles['Normal'], fontSize=9, textColor=GRIS_FONCE, fontName='Helvetica', alignment=TA_RIGHT)
    s_td_b = ParagraphStyle('tdb', parent=styles['Normal'], fontSize=9, textColor=GRIS_FONCE, fontName='Helvetica-Bold', alignment=TA_RIGHT)
    s_total = ParagraphStyle('tot', parent=styles['Normal'], fontSize=11, textColor=BLANC, fontName='Helvetica-Bold', alignment=TA_RIGHT)
    s_badge = ParagraphStyle('bdg', parent=styles['Normal'], fontSize=8, textColor=BLANC, fontName='Helvetica-Bold', alignment=TA_CENTER)
    s_footer = ParagraphStyle('ftr', parent=styles['Normal'], fontSize=7, textColor=GRIS, alignment=TA_CENTER)
    s_text = ParagraphStyle('txt', parent=styles['Normal'], fontSize=9, textColor=GRIS_FONCE, leading=13)

    elements = []
    client = facture.client
    vehicule = facture.vehicule
    rdv = facture.rendez_vous
    date_str = facture.date_creation.strftime('%d/%m/%Y') if facture.date_creation else '-'

    # ===== HEADER =====
    header_left = [
        [Paragraph("<b>ATELIER MOTO PRO</b>", s_hwb)],
        [Paragraph("123 Rue de l'Atelier, 75000 Paris", s_hw)],
        [Paragraph("Tel: 01 23 45 67 89 | contact@atelier-moto.fr", s_hw)],
        [Paragraph("<font size='7'>SIRET: XXX XXX XXX 00012</font>", s_hw)]
    ]
    header_right = [
        [Paragraph("<font color='#E8480A'><b>FACTURE</b></font>", s_title)],
        [Paragraph(f"<b>{facture.numero_facture}</b>", ParagraphStyle('n', parent=s_hwb, fontSize=14, alignment=TA_RIGHT))],
        [Paragraph(f"<font color='#E8480A'>Date: {date_str}</font>", ParagraphStyle('d', parent=s_hw, alignment=TA_RIGHT, fontSize=8))]
    ]
    hl = Table(header_left, colWidths=[usable_w * 0.55])
    hr = Table(header_right, colWidths=[usable_w * 0.45])
    header = Table([[hl, hr]], colWidths=[usable_w * 0.55, usable_w * 0.45])
    header.setStyle(TableStyle([
        ('BACKGROUND', (0, 0), (-1, -1), NOIR),
        ('ROUNDEDCORNERS', [6, 6, 6, 6]),
        ('LEFTPADDING', (0, 0), (-1, -1), 12),
        ('RIGHTPADDING', (0, 0), (-1, -1), 12),
        ('TOPPADDING', (0, 0), (-1, -1), 10),
        ('BOTTOMPADDING', (0, 0), (-1, -1), 10),
        ('VALIGN', (0, 0), (-1, -1), 'TOP'),
    ]))
    elements.append(header)
    elements.append(Spacer(1, 6))

    # ===== STATUS BAR =====
    statut_colors = {'emise': PURPLE, 'payee': VERT, 'partiellement_payee': ORANGE, 'annulee': colors.HexColor('#EF4444')}
    statut_labels = {'emise': 'EMISE', 'payee': 'PAYEE', 'partiellement_payee': 'PARTIELLEMENT PAYEE', 'annulee': 'ANNULEE'}
    s_color = statut_colors.get(facture.statut, GRIS)
    echeance_str = facture.date_echeance.strftime('%d/%m/%Y') if facture.date_echeance else '-'
    status_bar = Table([
        [Paragraph(f"Statut: <b>{statut_labels.get(facture.statut, facture.statut)}</b>", ParagraphStyle('sb', parent=s_hw, fontSize=9)),
         Paragraph(f"Echeance: <b>{echeance_str}</b>", ParagraphStyle('sb2', parent=s_hw, fontSize=9, alignment=TA_RIGHT))]
    ], colWidths=[usable_w * 0.5, usable_w * 0.5])
    status_bar.setStyle(TableStyle([
        ('BACKGROUND', (0, 0), (-1, -1), s_color),
        ('ROUNDEDCORNERS', [4, 4, 4, 4]),
        ('LEFTPADDING', (0, 0), (-1, -1), 10),
        ('RIGHTPADDING', (0, 0), (-1, -1), 10),
        ('TOPPADDING', (0, 0), (-1, -1), 5),
        ('BOTTOMPADDING', (0, 0), (-1, -1), 5),
    ]))
    elements.append(status_bar)
    elements.append(Spacer(1, 12))

    # ===== CLIENT + VEHICULE CARDS =====
    client_data = [
        [Paragraph("<font color='#E8480A'><b>CLIENT</b></font>", s_val)],
        [Paragraph("Nom complet", s_label)],
        [Paragraph(f"<b>{client.prenom or ''} {client.nom or ''}</b>", s_val)],
        [Paragraph("Telephone", s_label)],
        [Paragraph(f"<b>{client.telephone or '-'}</b>", s_val)],
        [Paragraph("Email", s_label)],
        [Paragraph(f"{client.email or '-'}", s_val_sm)],
    ]
    veh_data = [
        [Paragraph("<font color='#E8480A'><b>VEHICULE</b></font>", s_val)],
        [Paragraph("Moto", s_label)],
        [Paragraph(f"<b>{vehicule.marque or ''} {vehicule.modele or ''}" + (f" ({vehicule.annee})" if vehicule and vehicule.annee else "") + "</b>", s_val)] if vehicule else [Paragraph("-", s_val)],
        [Paragraph("Immatriculation", s_label)],
        [Paragraph(f"<b>{vehicule.plaque or '-'}</b>", s_val)] if vehicule else [Paragraph("-", s_val)],
        [Paragraph("Kilometrage", s_label)],
        [Paragraph(f"<b>{rdv.kilometrage or '-'} km</b>", s_val)] if rdv else [Paragraph("-", s_val)],
    ]
    cl_table = Table(client_data, colWidths=[usable_w * 0.47])
    cl_table.setStyle(TableStyle([
        ('LEFTPADDING', (0, 0), (-1, -1), 10),
        ('TOPPADDING', (0, 0), (-1, -1), 2),
        ('BOTTOMPADDING', (0, 0), (-1, -1), 2),
    ]))
    vh_table = Table(veh_data, colWidths=[usable_w * 0.47])
    vh_table.setStyle(TableStyle([
        ('LEFTPADDING', (0, 0), (-1, -1), 10),
        ('TOPPADDING', (0, 0), (-1, -1), 2),
        ('BOTTOMPADDING', (0, 0), (-1, -1), 2),
    ]))
    cards = Table([[cl_table, vh_table]], colWidths=[usable_w * 0.5, usable_w * 0.5])
    cards.setStyle(TableStyle([
        ('BOX', (0, 0), (0, 0), 1, GRIS_BORDER),
        ('BOX', (1, 0), (1, 0), 1, GRIS_BORDER),
        ('ROUNDEDCORNERS', [6, 6, 6, 6]),
        ('VALIGN', (0, 0), (-1, -1), 'TOP'),
        ('TOPPADDING', (0, 0), (-1, -1), 6),
        ('BOTTOMPADDING', (0, 0), (-1, -1), 6),
    ]))
    elements.append(cards)
    elements.append(Spacer(1, 12))

    # ===== DETAIL LIGNES =====
    elements.append(Paragraph("<font color='#E8480A'><b>DETAIL FACTURATION</b></font>", s_section))

    col_widths = [usable_w * 0.38, usable_w * 0.12, usable_w * 0.10, usable_w * 0.15, usable_w * 0.10, usable_w * 0.15]
    fact_rows = [
        [Paragraph("<b>Designation</b>", s_th),
         Paragraph("<b>Ref</b>", s_th),
         Paragraph("<b>Qte</b>", s_th),
         Paragraph("<b>P.U. HT</b>", s_th),
         Paragraph("<b>TVA</b>", s_th),
         Paragraph("<b>Total HT</b>", s_th)]
    ]

    for ligne in facture.lignes:
        is_forfait_ligne = ligne.type_ligne == "main_oeuvre" and ligne.designation.startswith("Forfait")
        if is_forfait_ligne:
            qte_str = "1"
            pu_str = f"{ligne.prix_unitaire_ht:.2f} EUR"
        elif ligne.type_ligne == "main_oeuvre":
            qte_str = f"{ligne.quantite:.2f} h"
            pu_str = f"{ligne.prix_unitaire_ht:.2f} EUR/h"
        else:
            qte_str = str(int(ligne.quantite))
            pu_str = f"{ligne.prix_unitaire_ht:.2f} EUR"
        fact_rows.append([
            Paragraph(ligne.designation, s_td),
            Paragraph(ligne.reference or "", s_td),
            Paragraph(qte_str, s_td_r),
            Paragraph(pu_str, s_td_r),
            Paragraph(f"{ligne.taux_tva:.0f}%", s_td_r),
            Paragraph(f"{ligne.total_ligne_ht:.2f} EUR", s_td_r)
        ])

    fact_table = Table(fact_rows, colWidths=col_widths)
    fact_style = [
        ('BACKGROUND', (0, 0), (-1, 0), NOIR),
        ('TEXTCOLOR', (0, 0), (-1, 0), BLANC),
        ('FONTNAME', (0, 0), (-1, 0), 'Helvetica-Bold'),
        ('FONTSIZE', (0, 0), (-1, 0), 8),
        ('ALIGN', (2, 0), (-1, 0), 'CENTER'),
        ('LINEBELOW', (0, 0), (-1, -2), 0.5, GRIS_BORDER),
        ('TOPPADDING', (0, 0), (-1, -1), 5),
        ('BOTTOMPADDING', (0, 0), (-1, -1), 5),
        ('LEFTPADDING', (0, 0), (-1, -1), 6),
        ('RIGHTPADDING', (0, 0), (-1, -1), 6),
    ]
    fact_table.setStyle(TableStyle(fact_style))
    elements.append(fact_table)
    elements.append(Spacer(1, 6))

    # ===== TOTAUX =====
    totaux_col = [usable_w * 0.65, usable_w * 0.35]

    totaux_rows = []
    if facture.total_pieces_ht > 0:
        totaux_rows.append([Paragraph("Total Pieces HT", s_td), Paragraph(f"{facture.total_pieces_ht:.2f} EUR", s_td_b)])
    totaux_rows.append([Paragraph("Total Main d'oeuvre HT", s_td), Paragraph(f"{facture.total_mo_ht:.2f} EUR", s_td_b)])

    if facture.remise_montant > 0:
        totaux_rows.append([Paragraph(f"Remise ({facture.remise_pourcentage:.1f}%)", s_td), Paragraph(f"-{facture.remise_montant:.2f} EUR", s_td_b)])

    totaux_rows.append([Paragraph("<b>Total HT</b>", s_td), Paragraph(f"<b>{facture.total_ht:.2f} EUR</b>", s_td_b)])

    if facture.tva_mo > 0:
        totaux_rows.append([Paragraph(f"TVA MO ({facture.tva_mo_taux:.0f}%)", s_td), Paragraph(f"{facture.tva_mo:.2f} EUR", s_td_r)])
    if facture.tva_pieces > 0:
        totaux_rows.append([Paragraph(f"TVA Pieces ({facture.tva_pieces_taux:.0f}%)", s_td), Paragraph(f"{facture.tva_pieces:.2f} EUR", s_td_r)])

    totaux_table = Table(totaux_rows, colWidths=totaux_col)
    totaux_table.setStyle(TableStyle([
        ('LINEBELOW', (0, -1), (-1, -1), 0.5, GRIS_BORDER),
        ('TOPPADDING', (0, 0), (-1, -1), 3),
        ('BOTTOMPADDING', (0, 0), (-1, -1), 3),
    ]))
    elements.append(totaux_table)
    elements.append(Spacer(1, 4))

    # Bandeau TOTAL TTC
    ttc_row = Table(
        [[Paragraph("<b>TOTAL TTC</b>", ParagraphStyle('ttcl', parent=s_total, alignment=0)),
          Paragraph(f"<b>{facture.total_ttc:.2f} EUR</b>", s_total)]],
        colWidths=[usable_w * 0.5, usable_w * 0.5]
    )
    ttc_row.setStyle(TableStyle([
        ('BACKGROUND', (0, 0), (-1, -1), ORANGE),
        ('ROUNDEDCORNERS', [4, 4, 4, 4]),
        ('TOPPADDING', (0, 0), (-1, -1), 8),
        ('BOTTOMPADDING', (0, 0), (-1, -1), 8),
        ('LEFTPADDING', (0, 0), (-1, -1), 12),
        ('RIGHTPADDING', (0, 0), (-1, -1), 12),
    ]))
    elements.append(ttc_row)
    elements.append(Spacer(1, 12))

    # ===== PAIEMENTS =====
    montant_paye = sum(p.montant for p in facture.paiements) if facture.paiements else 0
    if facture.paiements:
        elements.append(Paragraph("<font color='#22C55E'><b>PAIEMENTS ENREGISTRES</b></font>", s_section))
        pay_rows = [[
            Paragraph("<b>Date</b>", s_th),
            Paragraph("<b>Mode</b>", s_th),
            Paragraph("<b>Reference</b>", s_th),
            Paragraph("<b>Montant</b>", s_th)
        ]]
        for p in facture.paiements:
            pay_rows.append([
                Paragraph(p.date_paiement.strftime('%d/%m/%Y %H:%M') if p.date_paiement else '-', s_td),
                Paragraph(MODE_LABELS.get(p.mode_paiement, p.mode_paiement), s_td),
                Paragraph(p.reference or '-', s_td),
                Paragraph(f"{p.montant:.2f} EUR", s_td_b)
            ])
        pay_rows.append([
            Paragraph("", s_td), Paragraph("", s_td),
            Paragraph("<b>Total paye</b>", s_td_b),
            Paragraph(f"<b>{montant_paye:.2f} EUR</b>", s_td_b)
        ])
        montant_restant = round(facture.total_ttc - montant_paye, 2)
        if montant_restant > 0:
            pay_rows.append([
                Paragraph("", s_td), Paragraph("", s_td),
                Paragraph("<b>Reste a payer</b>", ParagraphStyle('rap', parent=s_td_b, textColor=colors.HexColor('#EF4444'))),
                Paragraph(f"<b>{montant_restant:.2f} EUR</b>", ParagraphStyle('rap2', parent=s_td_b, textColor=colors.HexColor('#EF4444')))
            ])
        pay_table = Table(pay_rows, colWidths=[usable_w * 0.25, usable_w * 0.25, usable_w * 0.25, usable_w * 0.25])
        pay_table.setStyle(TableStyle([
            ('BACKGROUND', (0, 0), (-1, 0), colors.HexColor('#166534')),
            ('TEXTCOLOR', (0, 0), (-1, 0), BLANC),
            ('LINEBELOW', (0, 0), (-1, -2), 0.5, GRIS_BORDER),
            ('TOPPADDING', (0, 0), (-1, -1), 4),
            ('BOTTOMPADDING', (0, 0), (-1, -1), 4),
            ('LEFTPADDING', (0, 0), (-1, -1), 6),
            ('RIGHTPADDING', (0, 0), (-1, -1), 6),
        ]))
        elements.append(pay_table)
        elements.append(Spacer(1, 12))

    # ===== MENTIONS LEGALES =====
    elements.append(Spacer(1, 8))
    mentions = (
        "<font size='7' color='#999999'>"
        "Paiement du a reception de la facture. Pas d'escompte pour paiement anticipe. "
        "Penalites de retard: 3 fois le taux d'interet legal en vigueur. "
        "Indemnite forfaitaire de recouvrement: 40 EUR."
        "</font>"
    )
    elements.append(Paragraph(mentions, s_text))
    elements.append(Spacer(1, 8))

    # Footer
    footer_text = (
        f"<b>Atelier Moto Pro</b> | SIRET: XXX XXX XXX 00012 | 123 Rue de l'Atelier, 75000 Paris<br/>"
        f"Tel: 01 23 45 67 89 | Email: contact@atelier-moto.fr | "
        f"Document genere le {datetime.now().strftime('%d/%m/%Y a %H:%M')}"
    )
    elements.append(Table([[Paragraph("")]], colWidths=[usable_w]))
    elements.append(Spacer(1, 4))

    footer_t = Table([[Paragraph(footer_text, s_footer)]], colWidths=[usable_w])
    footer_t.setStyle(TableStyle([
        ('LINEABOVE', (0, 0), (-1, 0), 0.5, GRIS_BORDER),
        ('TOPPADDING', (0, 0), (-1, -1), 8),
    ]))
    elements.append(footer_t)

    doc.build(elements)
    buffer.seek(0)

    return StreamingResponse(
        buffer,
        media_type="application/pdf",
        headers={"Content-Disposition": f"inline; filename=facture_{facture.numero_facture}.pdf"}
    )


@router.get("/api/rendez-vous/{rdv_id}/facture-pdf")
def facture_pdf_par_rdv(
    rdv_id: int,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """Raccourci: génère le PDF facture à partir de l'ID du RDV"""
    _ensure_billing_allowed(current_user, db, "billing.pdf")
    atelier_id = _tenant_id(current_user)
    facture = db.query(Facture).filter(
        Facture.rendez_vous_id == rdv_id,
        Facture.atelier_id == atelier_id
    ).first()
    if not facture:
        raise HTTPException(status_code=404, detail="Aucune facture pour ce RDV")
    return generate_facture_pdf(facture.id, db, current_user)
