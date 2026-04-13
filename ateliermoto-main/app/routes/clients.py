from fastapi import APIRouter, Depends, HTTPException, Request
from sqlalchemy.orm import Session

from auth import get_current_user
from models import Atelier, Client, Mecanicien, RapportTechnicien, RendezVous, User, Vehicule, get_db
from routes.auth_api import user_has_permission
from schemas.clients import ClientCreate, ClientUpdate, VehiculeCreate, VehiculeUpdate

router = APIRouter(tags=["clients"])


def _atelier_id_or_403(current_user: User) -> int:
    # Compatibilite temporaire: users legacy sans atelier_id -> atelier par defaut
    return int(current_user.atelier_id or 1)


def _ensure_clients_edit(current_user: User, db: Session) -> None:
    if not user_has_permission(current_user, db, "clients.edit"):
        raise HTTPException(status_code=403, detail="Permission clients.edit requise")


@router.get("/api/clients/stats")
def get_clients_stats(
    db: Session = Depends(get_db), current_user: User = Depends(get_current_user)
):
    """Statistiques globales clients"""
    from sqlalchemy import func
    atelier_id = _atelier_id_or_403(current_user)
    total_clients = db.query(Client).filter(Client.atelier_id == atelier_id).count()
    clients_avec_rdv = db.query(Client.id).join(RendezVous).filter(
        Client.atelier_id == atelier_id,
        RendezVous.statut != 'annule'
    ).distinct().count()
    total_vehicules = db.query(Vehicule).filter(Vehicule.atelier_id == atelier_id).count()
    ca_result = db.query(func.coalesce(func.sum(
        func.coalesce(RendezVous.prix_final, RendezVous.prix_estime, 0)
    ), 0)).filter(
        RendezVous.atelier_id == atelier_id,
        RendezVous.statut.in_(['termine', 'facture', 'paye'])
    ).scalar()
    return {
        "total": total_clients,
        "avec_rdv": clients_avec_rdv,
        "vehicules": total_vehicules,
        "ca_total": float(ca_result or 0)
    }

@router.get("/api/clients")
def get_clients(
    request: Request,
    search: str | None = None,
    page: int | None = None,
    limit: int | None = None,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    """Récupère la liste des clients avec recherche et pagination"""
    atelier_id = _atelier_id_or_403(current_user)
    query = db.query(Client).filter(Client.atelier_id == atelier_id)
    if search:
        search_filter = f"%{search}%"
        query = query.filter(
            (Client.nom.ilike(search_filter)) |
            (Client.prenom.ilike(search_filter)) |
            (Client.telephone.ilike(search_filter)) |
            (Client.email.ilike(search_filter))
        )
    total = query.count()
    legacy_workflow_mode = request.cookies.get("legacy_client_list") == "1"
    # Compatibilité mixte:
    # - listing principal auth => format paginé par défaut quand la liste devient "catalogue"
    # - recherche/autocomplete legacy => liste brute
    # - ancien workflow RDV public -> cookie de compatibilité => liste brute
    # - petites listes historiques (0/1 client) => liste brute
    use_pagination = (
        page is not None
        or limit is not None
        or (not search and not legacy_workflow_mode and total > 1)
    )
    if use_pagination:
        safe_page = max(1, page or 1)
        safe_limit = max(1, limit or 50)
        skip = (safe_page - 1) * safe_limit
        clients = query.order_by(Client.nom).offset(skip).limit(safe_limit).all()
    else:
        clients = query.order_by(Client.nom).all()

    import math
    result = []
    for client in clients:
        real_rdvs = [r for r in client.rendez_vous if r.statut != 'annule']
        nb_rdv = len(real_rdvs)
        dernier_rdv = real_rdvs[0] if real_rdvs else None
        nb_vehicules = len(client.vehicules) if client.vehicules else 0

        result.append({
            "id": client.id,
            "nom": client.nom,
            "prenom": client.prenom,
            "telephone": client.telephone,
            "email": client.email,
            "adresse": client.adresse,
            "notes": client.notes,
            "nb_rdv": nb_rdv,
            "nb_vehicules": nb_vehicules,
            "dernier_rdv": dernier_rdv.date_rdv.isoformat() if dernier_rdv else None,
            "created_at": client.created_at.isoformat() if client.created_at else None
        })
    if not use_pagination:
        # Compat legacy: plusieurs ecrans/tests attendent une liste brute
        return result
    return {
        "items": result,
        "total": total,
        "page": safe_page,
        "pages": math.ceil(total / safe_limit),
        "limit": safe_limit
    }

@router.post("/api/clients")
def create_client(client_data: ClientCreate, db: Session = Depends(get_db)):
    """Crée un nouveau client (public pour la prise de RDV en ligne)"""
    # Vérifier si un client avec ce téléphone existe déjà
    atelier = db.query(Atelier).filter(Atelier.slug == "default").first()
    atelier_id = atelier.id if atelier else 1
    existing = db.query(Client).filter(
        Client.telephone == client_data.telephone,
        Client.atelier_id == atelier_id
    ).first()
    if existing:
        return existing
    
    # Créer le nouveau client
    new_client = Client(
        atelier_id=atelier_id,
        nom=client_data.nom,
        prenom=client_data.prenom,
        telephone=client_data.telephone,
        email=client_data.email,
        adresse=client_data.adresse,
        notes=getattr(client_data, 'notes', None)
    )
    db.add(new_client)
    db.commit()
    db.refresh(new_client)
    
    return {
        "id": new_client.id,
        "nom": new_client.nom,
        "prenom": new_client.prenom,
        "telephone": new_client.telephone,
        "email": new_client.email
    }

@router.get("/api/clients/{client_id}")
def get_client(client_id: int, db: Session = Depends(get_db), current_user: User = Depends(get_current_user)):
    """Récupère les détails d'un client avec son historique complet"""
    atelier_id = _atelier_id_or_403(current_user)
    client = db.query(Client).filter(Client.id == client_id, Client.atelier_id == atelier_id).first()
    if not client:
        raise HTTPException(status_code=404, detail="Client non trouvé")
    
    # Historique des RDV
    historique = []
    for rdv in sorted(client.rendez_vous, key=lambda r: r.date_rdv, reverse=True):
        meca = rdv.mecanicien if rdv.mecanicien_id else None
        pont = rdv.pont if rdv.pont_id else None
        # Rapport technicien
        rapport = db.query(RapportTechnicien).filter(RapportTechnicien.rendez_vous_id == rdv.id).first()
        import json as json_lib
        rapport_data = None
        if rapport:
            rapport_data = {
                "alertes": rapport.alertes,
                "recommandations": rapport.recommandations,
                "travaux_realises": rapport.travaux_realises,
                "pieces_utilisees": json_lib.loads(rapport.pieces_utilisees) if rapport.pieces_utilisees else [],
                "points_controle": json_lib.loads(rapport.points_controle) if rapport.points_controle else {}
            }
        historique.append({
            "id": rdv.id,
            "date_rdv": rdv.date_rdv.isoformat(),
            "heure_rdv": str(rdv.heure_rdv),
            "type_intervention": rdv.type_intervention,
            "vehicule": {
                "plaque": rdv.vehicule.plaque,
                "marque": rdv.vehicule.marque,
                "modele": rdv.vehicule.modele
            },
            "prix_estime": rdv.prix_estime,
            "prix_final": rdv.prix_final,
            "statut": rdv.statut,
            "commentaire": rdv.commentaire,
            "kilometrage": rdv.kilometrage,
            "temps_estime": rdv.temps_estime,
            "temps_effectif_minutes": rdv.temps_effectif_minutes,
            "heure_debut_travail": rdv.heure_debut_travail.isoformat() if rdv.heure_debut_travail else None,
            "heure_fin_travail": rdv.heure_fin_travail.isoformat() if rdv.heure_fin_travail else None,
            "mecanicien": {"id": meca.id, "nom": meca.nom, "prenom": meca.prenom} if meca else None,
            "pont": {"id": pont.id, "nom": pont.nom} if pont else None,
            "rapport": rapport_data
        })
    
    # Véhicules associés (relation directe + fallback RDV)
    vehicules = []
    vehicule_ids = set()
    for v in (client.vehicules or []):
        vehicule_ids.add(v.id)
        vehicules.append({
            "id": v.id,
            "plaque": v.plaque,
            "marque": v.marque,
            "modele": v.modele,
            "annee": v.annee,
            "cylindree": v.cylindree,
            "type_moto": v.type_moto
        })
    for rdv in client.rendez_vous:
        if rdv.vehicule_id and rdv.vehicule_id not in vehicule_ids:
            vehicule_ids.add(rdv.vehicule_id)
            vehicules.append({
                "id": rdv.vehicule.id,
                "plaque": rdv.vehicule.plaque,
                "marque": rdv.vehicule.marque,
                "modele": rdv.vehicule.modele,
                "annee": rdv.vehicule.annee,
                "cylindree": rdv.vehicule.cylindree,
                "type_moto": rdv.vehicule.type_moto
            })

    # CA total du client
    ca_total = sum(
        (r.prix_final or r.prix_estime or 0)
        for r in client.rendez_vous
        if r.statut in ('termine', 'facture', 'paye')
    )
    
    return {
        "id": client.id,
        "nom": client.nom,
        "prenom": client.prenom,
        "telephone": client.telephone,
        "email": client.email,
        "adresse": client.adresse,
        "notes": client.notes,
        "created_at": client.created_at.isoformat() if client.created_at else None,
        "historique": historique,
        "vehicules": vehicules,
        "ca_total": ca_total
    }

@router.put("/api/clients/{client_id}")
def update_client(client_id: int, update_data: ClientUpdate, db: Session = Depends(get_db), current_user: User = Depends(get_current_user)):
    """Met à jour les informations d'un client"""
    _ensure_clients_edit(current_user, db)
    client = db.query(Client).filter(Client.id == client_id).first()
    if not client:
        raise HTTPException(status_code=404, detail="Client non trouvé")
    if current_user.role != "super_admin" and client.atelier_id != _atelier_id_or_403(current_user):
        raise HTTPException(status_code=404, detail="Client non trouvé")
    
    if update_data.nom:
        client.nom = update_data.nom
    if update_data.prenom:
        client.prenom = update_data.prenom
    if update_data.telephone:
        client.telephone = update_data.telephone
    if update_data.email is not None:
        client.email = update_data.email
    if update_data.adresse is not None:
        client.adresse = update_data.adresse
    if update_data.notes is not None:
        client.notes = update_data.notes
    
    db.commit()
    db.refresh(client)
    return {"message": "Client mis à jour", "id": client.id}

@router.post("/api/clients/{client_id}/vehicules")
def add_vehicule_to_client(client_id: int, vehicule_data: VehiculeCreate, db: Session = Depends(get_db), current_user: User = Depends(get_current_user)):
    """Ajoute un véhicule à un client via lien direct"""
    _ensure_clients_edit(current_user, db)
    atelier_id = _atelier_id_or_403(current_user)
    client = db.query(Client).filter(Client.id == client_id, Client.atelier_id == atelier_id).first()
    if not client:
        raise HTTPException(status_code=404, detail="Client non trouvé")

    plaque_clean = vehicule_data.plaque.upper().replace(" ", "").replace("-", "")
    vehicule = db.query(Vehicule).filter(Vehicule.plaque == plaque_clean, Vehicule.atelier_id == atelier_id).first()
    if not vehicule:
        vehicule = Vehicule(
            atelier_id=atelier_id,
            plaque=plaque_clean,
            marque=vehicule_data.marque,
            modele=vehicule_data.modele,
            annee=vehicule_data.annee,
            cylindree=vehicule_data.cylindree,
            type_moto=vehicule_data.type_moto,
            client_id=client_id
        )
        db.add(vehicule)
    else:
        if not vehicule.client_id:
            vehicule.client_id = client_id
        elif vehicule.client_id != client_id:
            raise HTTPException(status_code=409, detail="Ce vehicule est deja associe a un autre client")

    db.commit()
    db.refresh(vehicule)
    return {"message": "Véhicule ajouté", "id": vehicule.id}

@router.put("/api/vehicules/{vehicule_id}")
def update_vehicule(vehicule_id: int, update_data: VehiculeUpdate, db: Session = Depends(get_db), current_user: User = Depends(get_current_user)):
    """Met à jour les informations d'un véhicule"""
    _ensure_clients_edit(current_user, db)
    vehicule = db.query(Vehicule).filter(Vehicule.id == vehicule_id).first()
    if not vehicule:
        raise HTTPException(status_code=404, detail="Véhicule non trouvé")
    if current_user.role != "super_admin" and vehicule.atelier_id != _atelier_id_or_403(current_user):
        raise HTTPException(status_code=404, detail="Véhicule non trouvé")

    if update_data.plaque is not None:
        vehicule.plaque = update_data.plaque.upper().replace(" ", "").replace("-", "")
    if update_data.marque is not None:
        vehicule.marque = update_data.marque
    if update_data.modele is not None:
        vehicule.modele = update_data.modele
    if update_data.annee is not None:
        vehicule.annee = update_data.annee
    if update_data.cylindree is not None:
        vehicule.cylindree = update_data.cylindree
    if update_data.type_moto is not None:
        vehicule.type_moto = update_data.type_moto

    db.commit()
    db.refresh(vehicule)
    return {"message": "Véhicule mis à jour", "id": vehicule.id}

@router.delete("/api/vehicules/{vehicule_id}")
def delete_vehicule(vehicule_id: int, db: Session = Depends(get_db), current_user: User = Depends(get_current_user)):
    """Supprime un véhicule s'il n'a pas de vrais RDV"""
    _ensure_clients_edit(current_user, db)
    vehicule = db.query(Vehicule).filter(Vehicule.id == vehicule_id).first()
    if not vehicule:
        raise HTTPException(status_code=404, detail="Véhicule non trouvé")
    if current_user.role != "super_admin" and vehicule.atelier_id != _atelier_id_or_403(current_user):
        raise HTTPException(status_code=404, detail="Véhicule non trouvé")
    real_rdvs = [r for r in vehicule.rendez_vous if r.statut != 'annule']
    if real_rdvs:
        raise HTTPException(status_code=400, detail="Impossible de supprimer un vehicule avec des rendez-vous")
    for rdv in list(vehicule.rendez_vous):
        db.delete(rdv)
    db.delete(vehicule)
    db.commit()
    return {"message": "Véhicule supprimé"}

@router.delete("/api/clients/{client_id}")
def delete_client(client_id: int, db: Session = Depends(get_db), current_user: User = Depends(get_current_user)):
    """Supprime un client (si pas de RDV)"""
    _ensure_clients_edit(current_user, db)
    client = db.query(Client).filter(Client.id == client_id).first()
    if not client:
        raise HTTPException(status_code=404, detail="Client non trouvé")
    if current_user.role != "super_admin" and client.atelier_id != _atelier_id_or_403(current_user):
        raise HTTPException(status_code=404, detail="Client non trouvé")
    
    if client.rendez_vous:
        raise HTTPException(status_code=400, detail="Impossible de supprimer un client avec des rendez-vous")
    
    db.delete(client)
    db.commit()
    return {"message": "Client supprimé"}
