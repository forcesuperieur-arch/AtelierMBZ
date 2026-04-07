# Plan de refactor technique — Atelier Moto Pro

> Mise à jour au **07/04/2026** — documentation et stratégie BDD Git réalignées avec l’état réel du dépôt

## Objectif

Réduire le risque technique **sans casser le flux métier** :
- sécuriser le cycle `RDV -> réception -> intervention -> facturation`
- maintenir le backend modulaire et testable
- poursuivre le nettoyage du frontend et de la documentation
- garder une stratégie BDD **compatible Git** via migrations + seeds

---

## État actuel vérifié

- `backend/main.py` joue désormais un vrai rôle de **composition root**
- le backend métier est réparti dans des routers dédiés (`auth_api`, `rendez_vous`, `workshop`, `inventory`, `devis`, `moto_base`, etc.)
- le frontend est découpé en modules spécialisés sous `frontend/modules/`
- la réception OR a été enrichie avec **priorité, carburant, dommages carrosserie, photos, lignes d’estimation et impression/PDF**
- l’autocomplete de la base moto a été renforcé pour mieux gérer les variantes de modèles
- la documentation active a été réalignée ; `docs/TECHNICAL.md` devient la **référence technique canonique**
- la stratégie BDD Git est désormais explicitée autour de **`alembic` + `seed.py` + `seed_parametres.py`**

---

## Avancement par phase

### Phase 1 — Durcissement métier immédiat ✅

**Validé :**
- permissions `rdv.edit` renforcées sur les actions sensibles
- transitions de statuts RDV contrôlées côté backend
- sécurité cookie pilotée par variables d’environnement
- tests de régression métiers enrichis

### Phase 2 — Démonolithisation backend ✅

**Routers et services sortis du noyau :**
- `routes/auth_api.py`
- `routes/tenant_admin.py`
- `routes/public_booking.py`
- `routes/clients.py`
- `routes/vehicles.py`
- `routes/rendez_vous.py`
- `routes/workshop.py`
- `routes/travaux_supp.py`
- `routes/devis.py`
- `routes/inventory.py`
- `routes/forfaits_mo.py`
- `routes/moto_base.py`
- `routes/prestations_tarifs.py`
- `routes/frontend_pages.py`
- `services/startup_service.py`
- `services/runtime_migrations.py`
- `services/pdf_service.py`

**Résultat :**
- bootstrap plus lisible
- logique métier plus localisée
- compatibilité legacy conservée

### Phase 3 — Découpage progressif du frontend ✅ structure en place

**Modules actuellement présents :**
- `frontend/modules/absences.js`
- `frontend/modules/admin.js`
- `frontend/modules/app-core.js`
- `frontend/modules/billing.js`
- `frontend/modules/clients.js`
- `frontend/modules/dashboard.js`
- `frontend/modules/debug-tools.js`
- `frontend/modules/mecanicien.js`
- `frontend/modules/or.js`
- `frontend/modules/planning-utils.js`
- `frontend/modules/planning.js`
- `frontend/modules/rdv-actions.js`
- `frontend/modules/rdv.js`
- `frontend/modules/suivi.js`
- `frontend/modules/workshop.js`

**À poursuivre sans urgence :**
- réduire les `onclick="..."` inline restants
- limiter les accès globaux à `APP`
- factoriser certains helpers HTML encore dupliqués

### Phase 4 — Infra / données / exploitation ✅

**Déjà fiabilisé :**
- scripts `db-backup.sh` / `db-restore.sh` stabilisés
- dossier `backups/` confirmé comme emplacement des dumps d’exploitation
- documentation d’exploitation remise à jour
- persistance locale assurée par le volume Docker `postgres_data`

**Décision structurante retenue :**
- **Git versionne le schéma et les seeds**
- **Git ne versionne pas** la base vivante, les volumes Docker ou les dumps SQL de travail

### Phase 5 — Documentation & nettoyage ✅ en cours de stabilisation

**Fait dans cette passe :**
- `README.md` remis à niveau
- `docs/TECHNICAL.md`, `docs/GUIDE_UTILISATEUR.md`, `docs/OPERATIONS.md` réalignés
- audit des fichiers legacy / compatibles encore servis via `frontend_pages.py`
- identification des doublons documentaires à supprimer ou archiver

---

## Dette technique restante

1. poursuivre la bascule des backfills historiques de `runtime_migrations.py` vers **Alembic**
2. fiabiliser encore la persistance / archivage des signatures si besoin métier
3. poursuivre le nettoyage des pages legacy une fois les redirections devenues inutiles
4. garder la couverture de tests sur les flux critiques RDV / OR / facturation / travaux supp

---

## Niveau de risque actuel

- **Métier** : faible
- **Structure backend** : faible
- **Frontend** : faible à modéré tant que la couche de compatibilité globale reste présente
- **Infra / BDD** : faible, avec un point de vigilance restant sur l’alignement complet Alembic
