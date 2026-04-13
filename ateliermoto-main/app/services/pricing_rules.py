from __future__ import annotations

from dataclasses import dataclass
from decimal import Decimal, ROUND_HALF_UP
from typing import Optional

from sqlalchemy.orm import Session

from models import ConfigAtelier, GrilleTarifaire, ModeleMoto, Prestation, Vehicule


class PricingConfigError(ValueError):
    """Raised when pricing configuration is missing or inconsistent."""


@dataclass
class PricingResult:
    mode_tarification: str
    prix_ht: Optional[float]
    prix_ttc: Optional[float]
    temps_minutes: int
    source: str


def _to_float(value, default: float = 0.0) -> float:
    if value is None:
        return default
    try:
        return float(value)
    except Exception:
        return default


def _round_2(value: float) -> float:
    return float(Decimal(str(value)).quantize(Decimal("0.01"), rounding=ROUND_HALF_UP))


def normalize_mode_tarification(value: Optional[str]) -> str:
    raw = str(value or "forfait").strip().lower()
    aliases = {
        "forfait": "forfait",
        "horaire": "taux_horaire",
        "taux_horaire": "taux_horaire",
        "devis": "sur_devis",
        "sur_devis": "sur_devis",
    }
    mode = aliases.get(raw)
    if not mode:
        raise PricingConfigError(f"Mode de tarification invalide: {value}")
    return mode


def mode_to_legacy(mode_tarification: str) -> str:
    mapping = {
        "forfait": "forfait",
        "taux_horaire": "horaire",
        "sur_devis": "devis",
    }
    return mapping[mode_tarification]


def normalize_taux_profile(value: Optional[str]) -> str:
    raw = str(value or "standard").strip().lower()
    if raw not in {"standard", "complexe", "expert"}:
        raise PricingConfigError(f"Profil de taux horaire invalide: {value}")
    return raw


def _resolve_vehicule_categorie_id(db: Session, vehicule: Vehicule) -> Optional[int]:
    if vehicule.categorie_id:
        return int(vehicule.categorie_id)
    if vehicule.modele_id:
        modele = db.query(ModeleMoto).filter(ModeleMoto.id == vehicule.modele_id).first()
        if modele and modele.categorie_id:
            return int(modele.categorie_id)
    return None


def _resolve_hourly_rate(config: Optional[ConfigAtelier], profile: str) -> float:
    if not config:
        defaults = {"standard": 65.0, "complexe": 85.0, "expert": 95.0}
        return defaults[profile]

    if profile == "complexe":
        return _to_float(config.taux_horaire_mo_complexe, 85.0)
    if profile == "expert":
        return _to_float(config.taux_horaire_mo_expert, 95.0)
    return _to_float(config.taux_horaire_mo_standard, 65.0)


def _resolve_prestation_category_access(
    db: Session,
    *,
    atelier_id: int,
    prestation: Prestation,
    vehicule: Optional[Vehicule],
    strict: bool,
) -> tuple[bool, Optional[int], Optional[GrilleTarifaire]]:
    rules = db.query(GrilleTarifaire).filter(
        GrilleTarifaire.atelier_id == atelier_id,
        GrilleTarifaire.prestation_id == prestation.id,
        GrilleTarifaire.categorie_moto_id.isnot(None),
    ).all()
    if not rules:
        return False, None, None

    categorie_id = _resolve_vehicule_categorie_id(db, vehicule) if vehicule else None
    if categorie_id is None:
        if strict:
            raise PricingConfigError(
                f"Categorie vehicule manquante pour appliquer les regles de type moto de '{prestation.nom}'"
            )
        return True, None, None

    matching = [rule for rule in rules if int(rule.categorie_moto_id or 0) == int(categorie_id)]
    if not matching:
        if strict:
            raise PricingConfigError(
                f"La prestation '{prestation.nom}' n'est pas active pour cette categorie de vehicule"
            )
        return True, categorie_id, None

    active_rule = next((rule for rule in matching if int(rule.is_active or 0) == 1), None)
    if active_rule is None:
        raise PricingConfigError(
            f"La prestation '{prestation.nom}' est desactivee pour cette categorie de vehicule"
        )

    return True, categorie_id, active_rule


def resolve_prestation_pricing(
    db: Session,
    *,
    atelier_id: int,
    prestation: Prestation,
    vehicule: Optional[Vehicule] = None,
    strict: bool = True,
) -> PricingResult:
    if int(getattr(prestation, "is_active", 1) or 0) != 1:
        raise PricingConfigError(f"La prestation '{prestation.nom}' est desactivee")

    mode = normalize_mode_tarification(prestation.type_tarif)
    base_temps = int(prestation.temps_estime_minutes or 0)
    if base_temps <= 0:
        raise PricingConfigError(f"Temps estime manquant pour la prestation '{prestation.nom}'")

    has_category_rules, categorie_id, category_rule = _resolve_prestation_category_access(
        db,
        atelier_id=atelier_id,
        prestation=prestation,
        vehicule=vehicule,
        strict=strict,
    )

    if mode == "sur_devis":
        return PricingResult(
            mode_tarification=mode,
            prix_ht=None,
            prix_ttc=None,
            temps_minutes=base_temps,
            source="prestation:sur_devis",
        )

    if mode == "taux_horaire":
        profile = normalize_taux_profile(prestation.taux_horaire_applique)
        config = db.query(ConfigAtelier).filter(ConfigAtelier.atelier_id == atelier_id).first()
        if not config:
            config = db.query(ConfigAtelier).first()
        taux_horaire = _resolve_hourly_rate(config, profile)
        prix_ht = (base_temps / 60.0) * taux_horaire
        tva = _to_float(config.tva_mo_taux, 20.0) if config else 20.0
        prix_ttc = prix_ht * (1 + (tva / 100.0))
        return PricingResult(
            mode_tarification=mode,
            prix_ht=_round_2(prix_ht),
            prix_ttc=_round_2(prix_ttc),
            temps_minutes=base_temps,
            source=f"config_atelier:{profile}",
        )

    grille = category_rule
    if grille is None and categorie_id is not None:
        grille = db.query(GrilleTarifaire).filter(
            GrilleTarifaire.atelier_id == atelier_id,
            GrilleTarifaire.prestation_id == prestation.id,
            GrilleTarifaire.categorie_moto_id == categorie_id,
            GrilleTarifaire.is_active == 1,
        ).first()
    if not grille:
        if has_category_rules and strict:
            raise PricingConfigError(
                f"La prestation '{prestation.nom}' n'est pas active pour cette categorie de vehicule"
            )
        if strict:
            raise PricingConfigError(
                f"Aucune grille forfait configuree pour '{prestation.nom}' sur cette categorie de vehicule"
            )
        prix_ht = _to_float(prestation.prix_base_ht, 0.0)
        prix_ttc = _to_float(prestation.prix_base_ttc, 0.0)
        return PricingResult(
            mode_tarification=mode,
            prix_ht=_round_2(prix_ht),
            prix_ttc=_round_2(prix_ttc),
            temps_minutes=base_temps,
            source="prestation:base",
        )

    return PricingResult(
        mode_tarification=mode,
        prix_ht=_to_float(grille.prix_ht, 0.0),
        prix_ttc=_to_float(grille.prix_ttc, 0.0),
        temps_minutes=int(grille.temps_minutes or base_temps),
        source=f"grille_tarifaire:{grille.id}",
    )
