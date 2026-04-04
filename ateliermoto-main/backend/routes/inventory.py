from typing import Optional

from fastapi import APIRouter, Depends, HTTPException
from sqlalchemy.orm import Session

from auth import get_current_user
from models import (
    CommandeFournisseur,
    Fournisseur,
    LigneCommandeFournisseur,
    PieceDetachee,
    PieceUtilisee,
    RendezVous,
    User,
    get_db,
)
from schemas.inventory import (
    CommandeFournisseurCreate,
    CommandeFournisseurUpdate,
    FournisseurCreate,
    FournisseurUpdate,
    PieceDetacheeCreate,
    PieceDetacheeUpdate,
    PieceUtiliseeCreate,
    ReceptionCommande,
)

router = APIRouter(tags=["inventory"])


def _atelier_id_or_default(current_user: User) -> int:
    return int(getattr(current_user, "atelier_id", None) or 1)


def generate_numero_commande(db: Session, atelier_id: int) -> str:
    """Génère un numéro de commande unique."""
    import datetime

    today = datetime.datetime.now()
    prefix = f"CMD-{today.strftime('%Y%m%d')}"
    count = db.query(CommandeFournisseur).filter(
        CommandeFournisseur.atelier_id == atelier_id,
        CommandeFournisseur.numero_commande.like(f"{prefix}%"),
    ).count()
    return f"{prefix}-{count + 1:03d}"


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


@router.get("/api/commandes")
def get_commandes(
    statut: Optional[str] = None,
    fournisseur_id: Optional[int] = None,
    skip: int = 0,
    limit: int = 100,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    """Récupère la liste des commandes fournisseurs."""
    atelier_id = _atelier_id_or_default(current_user)
    query = db.query(CommandeFournisseur).filter(CommandeFournisseur.atelier_id == atelier_id)

    if statut:
        query = query.filter(CommandeFournisseur.statut == statut)
    if fournisseur_id:
        query = query.filter(CommandeFournisseur.fournisseur_id == fournisseur_id)

    commandes = query.order_by(CommandeFournisseur.date_commande.desc()).offset(skip).limit(limit).all()
    return [
        {
            "id": cmd.id,
            "numero_commande": cmd.numero_commande,
            "fournisseur": {"id": cmd.fournisseur.id, "nom": cmd.fournisseur.nom},
            "statut": cmd.statut,
            "date_commande": cmd.date_commande.isoformat() if cmd.date_commande else None,
            "date_prevue_livraison": cmd.date_prevue_livraison.isoformat() if cmd.date_prevue_livraison else None,
            "date_reception": cmd.date_reception.isoformat() if cmd.date_reception else None,
            "total_ht": cmd.total_ht,
            "total_ttc": cmd.total_ttc,
            "nb_lignes": len(cmd.lignes),
            "nb_pieces": sum(l.quantite_demandee for l in cmd.lignes),
        }
        for cmd in commandes
    ]


@router.get("/api/commandes/{commande_id}")
def get_commande(
    commande_id: int,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    """Récupère les détails d'une commande."""
    atelier_id = _atelier_id_or_default(current_user)
    cmd = db.query(CommandeFournisseur).filter(
        CommandeFournisseur.id == commande_id,
        CommandeFournisseur.atelier_id == atelier_id,
    ).first()
    if not cmd:
        raise HTTPException(status_code=404, detail="Commande non trouvée")

    return {
        "id": cmd.id,
        "numero_commande": cmd.numero_commande,
        "fournisseur": {
            "id": cmd.fournisseur.id,
            "nom": cmd.fournisseur.nom,
            "telephone": cmd.fournisseur.telephone,
            "email": cmd.fournisseur.email,
        },
        "statut": cmd.statut,
        "date_commande": cmd.date_commande.isoformat() if cmd.date_commande else None,
        "date_prevue_livraison": cmd.date_prevue_livraison.isoformat() if cmd.date_prevue_livraison else None,
        "date_reception": cmd.date_reception.isoformat() if cmd.date_reception else None,
        "total_ht": cmd.total_ht,
        "total_ttc": cmd.total_ttc,
        "notes": cmd.notes,
        "lignes": [
            {
                "id": ligne.id,
                "piece": {
                    "id": ligne.piece.id,
                    "reference": ligne.piece.reference,
                    "nom": ligne.piece.nom,
                },
                "quantite_demandee": ligne.quantite_demandee,
                "quantite_recue": ligne.quantite_recue,
                "prix_unitaire_ht": ligne.prix_unitaire_ht,
                "total_ligne_ht": ligne.quantite_demandee * ligne.prix_unitaire_ht,
            }
            for ligne in cmd.lignes
        ],
    }


@router.post("/api/commandes")
def create_commande(
    commande: CommandeFournisseurCreate,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    """Crée une nouvelle commande fournisseur."""
    from datetime import datetime, timedelta

    atelier_id = _atelier_id_or_default(current_user)
    fournisseur = db.query(Fournisseur).filter(
        Fournisseur.id == commande.fournisseur_id,
        Fournisseur.atelier_id == atelier_id,
    ).first()
    if not fournisseur:
        raise HTTPException(status_code=404, detail="Fournisseur non trouvé")

    total_ht = sum(ligne.quantite_demandee * ligne.prix_unitaire_ht for ligne in commande.lignes)
    total_ttc = total_ht * 1.20

    new_commande = CommandeFournisseur(
        atelier_id=atelier_id,
        numero_commande=generate_numero_commande(db, atelier_id),
        fournisseur_id=commande.fournisseur_id,
        statut="en_attente",
        date_prevue_livraison=datetime.now() + timedelta(days=fournisseur.delai_livraison_jours or 3),
        total_ht=total_ht,
        total_ttc=total_ttc,
        notes=commande.notes,
    )
    db.add(new_commande)
    db.flush()

    for ligne_data in commande.lignes:
        piece = db.query(PieceDetachee).filter(
            PieceDetachee.id == ligne_data.piece_id,
            PieceDetachee.atelier_id == atelier_id,
        ).first()
        if not piece:
            raise HTTPException(status_code=404, detail=f"Pièce {ligne_data.piece_id} non trouvée")

        ligne = LigneCommandeFournisseur(
            atelier_id=atelier_id,
            commande_id=new_commande.id,
            piece_id=ligne_data.piece_id,
            quantite_demandee=ligne_data.quantite_demandee,
            quantite_recue=0,
            prix_unitaire_ht=ligne_data.prix_unitaire_ht,
        )
        db.add(ligne)

    db.commit()
    db.refresh(new_commande)
    return {"message": "Commande créée", "id": new_commande.id, "numero": new_commande.numero_commande}


@router.put("/api/commandes/{commande_id}")
def update_commande(
    commande_id: int,
    commande_data: CommandeFournisseurUpdate,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    """Met à jour une commande fournisseur."""
    from datetime import datetime

    atelier_id = _atelier_id_or_default(current_user)
    cmd = db.query(CommandeFournisseur).filter(
        CommandeFournisseur.id == commande_id,
        CommandeFournisseur.atelier_id == atelier_id,
    ).first()
    if not cmd:
        raise HTTPException(status_code=404, detail="Commande non trouvée")

    if commande_data.statut:
        cmd.statut = commande_data.statut
        if commande_data.statut == "receptionnee":
            cmd.date_reception = datetime.now()

    if commande_data.date_prevue_livraison:
        cmd.date_prevue_livraison = datetime.fromisoformat(commande_data.date_prevue_livraison)
    if commande_data.notes is not None:
        cmd.notes = commande_data.notes

    db.commit()
    db.refresh(cmd)
    return {"message": "Commande mise à jour", "id": cmd.id}


@router.post("/api/commandes/{commande_id}/receptionner")
def receptionner_commande(
    commande_id: int,
    reception: ReceptionCommande,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    """Réceptionne une commande et met à jour les stocks."""
    from datetime import datetime

    atelier_id = _atelier_id_or_default(current_user)
    cmd = db.query(CommandeFournisseur).filter(
        CommandeFournisseur.id == commande_id,
        CommandeFournisseur.atelier_id == atelier_id,
    ).first()
    if not cmd:
        raise HTTPException(status_code=404, detail="Commande non trouvée")
    if cmd.statut == "receptionnee":
        raise HTTPException(status_code=400, detail="Cette commande a déjà été réceptionnée")

    for ligne_reception in reception.lignes:
        ligne = db.query(LigneCommandeFournisseur).filter(
            LigneCommandeFournisseur.id == ligne_reception.ligne_id,
            LigneCommandeFournisseur.commande_id == commande_id,
            LigneCommandeFournisseur.atelier_id == atelier_id,
        ).first()
        if ligne:
            ligne.quantite_recue = ligne_reception.quantite_recue
            ligne.piece.quantite_stock += ligne_reception.quantite_recue

    cmd.statut = "receptionnee"
    cmd.date_reception = datetime.now()
    db.commit()
    return {"message": "Commande réceptionnée", "id": cmd.id}


@router.delete("/api/commandes/{commande_id}")
def delete_commande(
    commande_id: int,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    """Supprime une commande si elle est encore en attente."""
    atelier_id = _atelier_id_or_default(current_user)
    cmd = db.query(CommandeFournisseur).filter(
        CommandeFournisseur.id == commande_id,
        CommandeFournisseur.atelier_id == atelier_id,
    ).first()
    if not cmd:
        raise HTTPException(status_code=404, detail="Commande non trouvée")
    if cmd.statut != "en_attente":
        raise HTTPException(status_code=400, detail="Impossible de supprimer une commande déjà validée")

    db.delete(cmd)
    db.commit()
    return {"message": "Commande supprimée"}


@router.get("/api/rendez-vous/{rdv_id}/pieces")
def get_pieces_intervention(
    rdv_id: int,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    """Récupère les pièces utilisées pour une intervention."""
    atelier_id = _atelier_id_or_default(current_user)
    rdv = db.query(RendezVous).filter(
        RendezVous.id == rdv_id,
        RendezVous.atelier_id == atelier_id,
    ).first()
    if not rdv:
        raise HTTPException(status_code=404, detail="Rendez-vous non trouvé")

    pieces = []
    total_pieces = 0
    for util in rdv.pieces_utilisees:
        total_ligne = util.quantite * (util.prix_vente_unitaire or 0)
        pieces.append(
            {
                "id": util.id,
                "piece_id": util.piece_id,
                "reference": util.piece.reference,
                "nom": util.piece.nom,
                "quantite": util.quantite,
                "prix_vente_unitaire": util.prix_vente_unitaire,
                "total_ligne": total_ligne,
            }
        )
        total_pieces += total_ligne

    return {
        "rendez_vous_id": rdv_id,
        "pieces": pieces,
        "total_pieces": total_pieces,
        "main_oeuvre": rdv.prix_final - total_pieces if rdv.prix_final else rdv.prix_estime,
    }


@router.post("/api/rendez-vous/{rdv_id}/pieces")
def add_piece_intervention(
    rdv_id: int,
    piece_data: PieceUtiliseeCreate,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    """Ajoute une pièce utilisée pour une intervention."""
    atelier_id = _atelier_id_or_default(current_user)
    rdv = db.query(RendezVous).filter(
        RendezVous.id == rdv_id,
        RendezVous.atelier_id == atelier_id,
    ).first()
    if not rdv:
        raise HTTPException(status_code=404, detail="Rendez-vous non trouvé")

    piece = db.query(PieceDetachee).filter(
        PieceDetachee.id == piece_data.piece_id,
        PieceDetachee.atelier_id == atelier_id,
    ).first()
    if not piece:
        raise HTTPException(status_code=404, detail="Pièce non trouvée")
    if piece.quantite_stock < piece_data.quantite:
        raise HTTPException(
            status_code=400,
            detail=f"Stock insuffisant. Disponible: {piece.quantite_stock}, Demandé: {piece_data.quantite}",
        )

    prix_vente = piece_data.prix_vente_unitaire or piece.prix_vente_ht
    utilisation = PieceUtilisee(
        rendez_vous_id=rdv_id,
        piece_id=piece_data.piece_id,
        quantite=piece_data.quantite,
        prix_vente_unitaire=prix_vente,
    )
    db.add(utilisation)
    piece.quantite_stock -= piece_data.quantite

    db.commit()
    db.refresh(utilisation)
    return {
        "message": "Pièce ajoutée à l'intervention",
        "id": utilisation.id,
        "stock_restant": piece.quantite_stock,
    }


@router.delete("/api/rendez-vous/{rdv_id}/pieces/{utilisation_id}")
def remove_piece_intervention(
    rdv_id: int,
    utilisation_id: int,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    """Retire une pièce d'une intervention et remet en stock."""
    atelier_id = _atelier_id_or_default(current_user)
    utilisation = db.query(PieceUtilisee).filter(
        PieceUtilisee.id == utilisation_id,
        PieceUtilisee.rendez_vous_id == rdv_id,
    ).first()
    if not utilisation:
        raise HTTPException(status_code=404, detail="Utilisation non trouvée")
    if utilisation.rendez_vous.atelier_id != atelier_id:
        raise HTTPException(status_code=404, detail="Utilisation non trouvée")

    utilisation.piece.quantite_stock += utilisation.quantite
    db.delete(utilisation)
    db.commit()
    return {"message": "Pièce retirée", "stock_restant": utilisation.piece.quantite_stock}


@router.get("/api/stats/stock")
def get_stats_stock(
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    """Statistiques du stock."""
    from sqlalchemy import func

    atelier_id = _atelier_id_or_default(current_user)
    total_pieces = db.query(PieceDetachee).filter(
        PieceDetachee.is_active == 1,
        PieceDetachee.atelier_id == atelier_id,
    ).count()
    stock_bas = db.query(PieceDetachee).filter(
        PieceDetachee.is_active == 1,
        PieceDetachee.atelier_id == atelier_id,
        PieceDetachee.quantite_stock <= PieceDetachee.quantite_minimale,
    ).count()
    valeur_stock = db.query(func.sum(
        PieceDetachee.quantite_stock * PieceDetachee.prix_achat_ht
    )).filter(
        PieceDetachee.is_active == 1,
        PieceDetachee.atelier_id == atelier_id,
    ).scalar() or 0
    valeur_vente = db.query(func.sum(
        PieceDetachee.quantite_stock * PieceDetachee.prix_vente_ht
    )).filter(
        PieceDetachee.is_active == 1,
        PieceDetachee.atelier_id == atelier_id,
    ).scalar() or 0
    commandes_en_cours = db.query(CommandeFournisseur).filter(
        CommandeFournisseur.atelier_id == atelier_id,
        CommandeFournisseur.statut.in_(["en_attente", "validee", "expediee"]),
    ).count()

    return {
        "total_references": total_pieces,
        "stock_bas": stock_bas,
        "valeur_stock_ht": round(valeur_stock, 2),
        "valeur_vente_ht": round(valeur_vente, 2),
        "marge_potentielle": round(valeur_vente - valeur_stock, 2),
        "commandes_en_cours": commandes_en_cours,
    }
