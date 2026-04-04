from typing import Optional

from fastapi import APIRouter, Depends, HTTPException
from sqlalchemy.orm import Session

from auth import get_current_user
from models import CommandeFournisseur, Fournisseur, PieceDetachee, User, get_db
from schemas.inventory import (
    FournisseurCreate,
    FournisseurUpdate,
    PieceDetacheeCreate,
    PieceDetacheeUpdate,
)

router = APIRouter(tags=["inventory"])


def _atelier_id_or_default(current_user: User) -> int:
    return int(getattr(current_user, "atelier_id", None) or 1)


@router.get("/api/pieces")
def get_pieces(
    search: Optional[str] = None,
    categorie: Optional[str] = None,
    stock_bas: Optional[bool] = None,
    skip: int = 0,
    limit: int = 100,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    """Récupère la liste des pièces détachées avec filtres."""
    atelier_id = _atelier_id_or_default(current_user)
    query = db.query(PieceDetachee).filter(
        PieceDetachee.is_active == 1,
        PieceDetachee.atelier_id == atelier_id,
    )

    if search:
        search_filter = f"%{search}%"
        query = query.filter(
            (PieceDetachee.nom.ilike(search_filter))
            | (PieceDetachee.reference.ilike(search_filter))
            | (PieceDetachee.reference_fournisseur.ilike(search_filter))
        )

    if categorie:
        query = query.filter(PieceDetachee.categorie == categorie)

    if stock_bas is not None:
        if stock_bas:
            query = query.filter(PieceDetachee.quantite_stock <= PieceDetachee.quantite_minimale)
        else:
            query = query.filter(PieceDetachee.quantite_stock > PieceDetachee.quantite_minimale)

    pieces = query.order_by(PieceDetachee.nom).offset(skip).limit(limit).all()
    return [
        {
            "id": piece.id,
            "reference": piece.reference,
            "reference_fournisseur": piece.reference_fournisseur,
            "nom": piece.nom,
            "description": piece.description,
            "categorie": piece.categorie,
            "quantite_stock": piece.quantite_stock,
            "quantite_minimale": piece.quantite_minimale,
            "quantite_maximale": piece.quantite_maximale,
            "emplacement": piece.emplacement,
            "prix_achat_ht": piece.prix_achat_ht,
            "prix_vente_ht": piece.prix_vente_ht,
            "prix_vente_ttc": piece.prix_vente_ttc,
            "tva_taux": piece.tva_taux,
            "stock_bas": piece.stock_bas,
            "fournisseur": {
                "id": piece.fournisseur.id,
                "nom": piece.fournisseur.nom,
            }
            if piece.fournisseur
            else None,
        }
        for piece in pieces
    ]


@router.get("/api/pieces/alertes")
def get_alertes_stock(
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    """Récupère les pièces en alerte stock bas."""
    atelier_id = _atelier_id_or_default(current_user)
    pieces = db.query(PieceDetachee).filter(
        PieceDetachee.is_active == 1,
        PieceDetachee.atelier_id == atelier_id,
        PieceDetachee.quantite_stock <= PieceDetachee.quantite_minimale,
    ).order_by(PieceDetachee.quantite_stock).all()

    return [
        {
            "id": p.id,
            "reference": p.reference,
            "nom": p.nom,
            "quantite_stock": p.quantite_stock,
            "quantite_minimale": p.quantite_minimale,
            "quantite_manquante": p.quantite_minimale - p.quantite_stock,
            "fournisseur": p.fournisseur.nom if p.fournisseur else None,
        }
        for p in pieces
    ]


@router.get("/api/pieces/categories")
def get_categories_pieces(
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    """Récupère la liste des catégories de pièces."""
    atelier_id = _atelier_id_or_default(current_user)
    categories = db.query(PieceDetachee.categorie).filter(
        PieceDetachee.is_active == 1,
        PieceDetachee.atelier_id == atelier_id,
        PieceDetachee.categorie.isnot(None),
    ).distinct().all()
    return [categorie for (categorie,) in categories if categorie]


@router.get("/api/pieces/{piece_id}")
def get_piece(
    piece_id: int,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    """Récupère les détails d'une pièce."""
    atelier_id = _atelier_id_or_default(current_user)
    piece = db.query(PieceDetachee).filter(
        PieceDetachee.id == piece_id,
        PieceDetachee.atelier_id == atelier_id,
    ).first()
    if not piece:
        raise HTTPException(status_code=404, detail="Pièce non trouvée")

    historique_commandes = []
    for ligne in piece.lignes_commande:
        if ligne.commande.statut in ["receptionnee", "expediee"]:
            historique_commandes.append(
                {
                    "date": ligne.commande.date_commande.isoformat() if ligne.commande.date_commande else None,
                    "quantite": ligne.quantite_recue or ligne.quantite_demandee,
                    "prix_unitaire": ligne.prix_unitaire_ht,
                    "fournisseur": ligne.commande.fournisseur.nom,
                }
            )

    utilisations = []
    for util in piece.utilisations:
        utilisations.append(
            {
                "date": util.rendez_vous.date_rdv.isoformat(),
                "client": f"{util.rendez_vous.client.prenom} {util.rendez_vous.client.nom}",
                "vehicule": f"{util.rendez_vous.vehicule.marque} {util.rendez_vous.vehicule.modele}",
                "quantite": util.quantite,
                "prix_vente": util.prix_vente_unitaire,
            }
        )

    return {
        "id": piece.id,
        "reference": piece.reference,
        "reference_fournisseur": piece.reference_fournisseur,
        "nom": piece.nom,
        "description": piece.description,
        "categorie": piece.categorie,
        "quantite_stock": piece.quantite_stock,
        "quantite_minimale": piece.quantite_minimale,
        "quantite_maximale": piece.quantite_maximale,
        "emplacement": piece.emplacement,
        "prix_achat_ht": piece.prix_achat_ht,
        "prix_vente_ht": piece.prix_vente_ht,
        "prix_vente_ttc": piece.prix_vente_ttc,
        "tva_taux": piece.tva_taux,
        "stock_bas": piece.stock_bas,
        "fournisseur": {
            "id": piece.fournisseur.id,
            "nom": piece.fournisseur.nom,
            "telephone": piece.fournisseur.telephone,
            "email": piece.fournisseur.email,
        }
        if piece.fournisseur
        else None,
        "historique_commandes": historique_commandes[:10],
        "utilisations_recentes": utilisations[:10],
    }


@router.post("/api/pieces")
def create_piece(
    piece: PieceDetacheeCreate,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    """Crée une nouvelle pièce détachée."""
    atelier_id = _atelier_id_or_default(current_user)
    existing = db.query(PieceDetachee).filter(
        PieceDetachee.reference == piece.reference,
        PieceDetachee.atelier_id == atelier_id,
    ).first()
    if existing:
        raise HTTPException(status_code=400, detail="Une pièce avec cette référence existe déjà")

    new_piece = PieceDetachee(**piece.dict(), atelier_id=atelier_id)
    db.add(new_piece)
    db.commit()
    db.refresh(new_piece)
    return {"message": "Pièce créée", "id": new_piece.id}


@router.put("/api/pieces/{piece_id}")
def update_piece(
    piece_id: int,
    piece_data: PieceDetacheeUpdate,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    """Met à jour une pièce détachée."""
    atelier_id = _atelier_id_or_default(current_user)
    piece = db.query(PieceDetachee).filter(
        PieceDetachee.id == piece_id,
        PieceDetachee.atelier_id == atelier_id,
    ).first()
    if not piece:
        raise HTTPException(status_code=404, detail="Pièce non trouvée")

    if piece_data.reference and piece_data.reference != piece.reference:
        existing = db.query(PieceDetachee).filter(
            PieceDetachee.reference == piece_data.reference,
            PieceDetachee.atelier_id == atelier_id,
            PieceDetachee.id != piece.id,
        ).first()
        if existing:
            raise HTTPException(status_code=400, detail="Une pièce avec cette référence existe déjà")

    for key, value in piece_data.dict(exclude_unset=True).items():
        setattr(piece, key, value)

    db.commit()
    db.refresh(piece)
    return {"message": "Pièce mise à jour", "id": piece.id}


@router.delete("/api/pieces/{piece_id}")
def delete_piece(
    piece_id: int,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    """Supprime une pièce en soft delete."""
    atelier_id = _atelier_id_or_default(current_user)
    piece = db.query(PieceDetachee).filter(
        PieceDetachee.id == piece_id,
        PieceDetachee.atelier_id == atelier_id,
    ).first()
    if not piece:
        raise HTTPException(status_code=404, detail="Pièce non trouvée")

    piece.is_active = 0
    db.commit()
    return {"message": "Pièce supprimée"}


@router.post("/api/pieces/{piece_id}/ajuster-stock")
def ajuster_stock(
    piece_id: int,
    quantite: int,
    raison: str = "Ajustement manuel",
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    """Ajuste le stock d'une pièce."""
    atelier_id = _atelier_id_or_default(current_user)
    piece = db.query(PieceDetachee).filter(
        PieceDetachee.id == piece_id,
        PieceDetachee.atelier_id == atelier_id,
    ).first()
    if not piece:
        raise HTTPException(status_code=404, detail="Pièce non trouvée")

    piece.quantite_stock += quantite
    if piece.quantite_stock < 0:
        piece.quantite_stock = 0

    db.commit()
    return {
        "message": "Stock ajusté",
        "piece_id": piece.id,
        "nouveau_stock": piece.quantite_stock,
        "ajustement": quantite,
        "raison": raison,
    }


@router.get("/api/fournisseurs")
def get_fournisseurs(
    search: Optional[str] = None,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    """Récupère la liste des fournisseurs."""
    atelier_id = _atelier_id_or_default(current_user)
    query = db.query(Fournisseur).filter(
        Fournisseur.is_active == 1,
        Fournisseur.atelier_id == atelier_id,
    )

    if search:
        search_filter = f"%{search}%"
        query = query.filter(Fournisseur.nom.ilike(search_filter))

    fournisseurs = query.order_by(Fournisseur.nom).all()
    return [
        {
            "id": f.id,
            "nom": f.nom,
            "contact": f.contact,
            "telephone": f.telephone,
            "email": f.email,
            "adresse": f.adresse,
            "siret": f.siret,
            "delai_livraison_jours": f.delai_livraison_jours,
            "notes": f.notes,
        }
        for f in fournisseurs
    ]


@router.get("/api/fournisseurs/{fournisseur_id}")
def get_fournisseur(
    fournisseur_id: int,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    """Récupère les détails d'un fournisseur."""
    atelier_id = _atelier_id_or_default(current_user)
    fournisseur = db.query(Fournisseur).filter(
        Fournisseur.id == fournisseur_id,
        Fournisseur.atelier_id == atelier_id,
    ).first()
    if not fournisseur:
        raise HTTPException(status_code=404, detail="Fournisseur non trouvé")

    pieces = db.query(PieceDetachee).filter(
        PieceDetachee.fournisseur_id == fournisseur_id,
        PieceDetachee.atelier_id == atelier_id,
        PieceDetachee.is_active == 1,
    ).all()
    commandes = db.query(CommandeFournisseur).filter(
        CommandeFournisseur.fournisseur_id == fournisseur_id,
        CommandeFournisseur.atelier_id == atelier_id,
    ).order_by(CommandeFournisseur.date_commande.desc()).limit(10).all()

    return {
        "id": fournisseur.id,
        "nom": fournisseur.nom,
        "contact": fournisseur.contact,
        "telephone": fournisseur.telephone,
        "email": fournisseur.email,
        "adresse": fournisseur.adresse,
        "siret": fournisseur.siret,
        "delai_livraison_jours": fournisseur.delai_livraison_jours,
        "notes": fournisseur.notes,
        "pieces_count": len(pieces),
        "pieces": [{"id": p.id, "reference": p.reference, "nom": p.nom} for p in pieces],
        "commandes_recentes": [
            {
                "id": c.id,
                "numero": c.numero_commande,
                "statut": c.statut,
                "date": c.date_commande.isoformat() if c.date_commande else None,
                "total_ttc": c.total_ttc,
            }
            for c in commandes
        ],
    }


@router.post("/api/fournisseurs")
def create_fournisseur(
    fournisseur: FournisseurCreate,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    """Crée un nouveau fournisseur."""
    atelier_id = _atelier_id_or_default(current_user)
    new_fournisseur = Fournisseur(**fournisseur.dict(), atelier_id=atelier_id)
    db.add(new_fournisseur)
    db.commit()
    db.refresh(new_fournisseur)
    return {"message": "Fournisseur créé", "id": new_fournisseur.id}


@router.put("/api/fournisseurs/{fournisseur_id}")
def update_fournisseur(
    fournisseur_id: int,
    fournisseur_data: FournisseurUpdate,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    """Met à jour un fournisseur."""
    atelier_id = _atelier_id_or_default(current_user)
    fournisseur = db.query(Fournisseur).filter(
        Fournisseur.id == fournisseur_id,
        Fournisseur.atelier_id == atelier_id,
    ).first()
    if not fournisseur:
        raise HTTPException(status_code=404, detail="Fournisseur non trouvé")

    for key, value in fournisseur_data.dict(exclude_unset=True).items():
        setattr(fournisseur, key, value)

    db.commit()
    db.refresh(fournisseur)
    return {"message": "Fournisseur mis à jour", "id": fournisseur.id}


@router.delete("/api/fournisseurs/{fournisseur_id}")
def delete_fournisseur(
    fournisseur_id: int,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    """Supprime un fournisseur en soft delete."""
    atelier_id = _atelier_id_or_default(current_user)
    fournisseur = db.query(Fournisseur).filter(
        Fournisseur.id == fournisseur_id,
        Fournisseur.atelier_id == atelier_id,
    ).first()
    if not fournisseur:
        raise HTTPException(status_code=404, detail="Fournisseur non trouvé")

    fournisseur.is_active = 0
    db.commit()
    return {"message": "Fournisseur supprimé"}
