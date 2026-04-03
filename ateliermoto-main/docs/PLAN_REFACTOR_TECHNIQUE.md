# Plan de refactor technique — Atelier Moto Pro

> Basé sur la revue réelle du code du 03/04/2026

## Objectif

Réduire le risque technique **sans casser le flux métier actuel** :
- sécuriser le cycle `RDV -> réception -> intervention -> clôture`
- sortir la logique critique des fichiers monolithiques
- garder la compatibilité fonctionnelle pendant la phase Soft

---

## Constats vérifiés

- `backend/main.py` : **5153 lignes**
- `frontend/app.js` : **5319 lignes**
- `backend/routes/rendez_vous.py` : **640 lignes**
- Docker nécessitait une attente de disponibilité PostgreSQL
- le seed paramètres avait un bug réel `Decimal * float`

---

## Phase 1 — Durcissement métier immédiat (priorité haute)

### 1.1 Verrouiller les actions RDV
**Fichiers :** `backend/routes/rendez_vous.py`, `backend/main.py`, `backend/tests/test_critical_routes.py`

À faire :
- imposer `rdv.edit` sur :
  - `DELETE /api/rendez-vous/{rdv_id}`
  - `POST /api/rendez-vous/{rdv_id}/demarrer-travail`
  - `POST /api/rendez-vous/{rdv_id}/terminer-travail`
  - `POST /api/rendez-vous/{rdv_id}/reception`
- limiter `rapport-technicien` aux rôles autorisés

### 1.2 Formaliser les transitions de statuts
**Fichier :** `backend/routes/rendez_vous.py`

Créer une table simple de transitions autorisées :
- `reserve -> confirme|annule|non_presente|reception`
- `confirme -> reception|annule|non_presente`
- `reception -> en_cours|annule`
- `en_cours -> termine`
- `termine -> facture|paye`

Bloquer les transitions invalides avec message explicite.

### 1.3 Sécuriser les cookies auth
**Fichiers :** `backend/main.py`, `backend/auth.py`

À faire :
- rendre `secure` configurable par variable d’environnement
- éviter `secure=False` codé en dur

**Livrable attendu :** aucun changement UX visible, mais flux RDV plus robuste.

---

## Phase 2 — Démonolithiser le backend sans rupture

### 2.1 Extraire les routes de `main.py`
**Nouveaux modules cibles :**
- `backend/routes/auth_api.py`
- `backend/routes/admin_users.py`
- `backend/routes/ateliers.py`
- `backend/routes/public_booking.py`
- `backend/routes/travaux_supplementaires.py`

### 2.2 Garder `main.py` comme composition root
`main.py` doit idéalement ne conserver que :
- création `FastAPI`
- middlewares
- `include_router(...)`
- startup minimal
- routes statiques SPA

### 2.3 Sortir les migrations runtime
**Aujourd’hui :** `migrate_*()` dans `startup_event()`

**Cible :**
- conserver uniquement l’initialisation safe au démarrage
- transférer les vrais changements de schéma vers `alembic/`

---

## Phase 3 — Découpage progressif du frontend

### 3.1 Garder la SPA vanilla, mais découper les responsabilités
**Découpage recommandé :**
- `frontend/app-core.js` → boot + navigation + état global
- `frontend/modules/dashboard.js`
- `frontend/modules/rdv.js`
- `frontend/modules/planning.js`
- `frontend/modules/or.js`
- `frontend/modules/clients.js`
- `frontend/modules/admin.js`
- `frontend/modules/mecanicien.js`

### 3.2 Première cible prioritaire
Extraire d’abord :
- `loadPlanning()` + `renderPlanningGrid()`
- `loadOrdresReparation()` + `showOrDetail()`
- `loadEspaceMeca()` + `renderEspaceMeca()`

### 3.3 Nettoyage progressif
- réduire les `onclick="..."` inline
- limiter les accès directs à `APP`
- isoler les utilitaires de rendu HTML répétitifs

---

## Phase 4 — Fiabilisation infra et données

### 4.1 Signatures et fichiers
**Fichier :** `backend/routes/rendez_vous.py`

Aujourd’hui les signatures sont sauvées dans `signatures/` dans le conteneur.

À faire :
- monter un volume dédié ou
- stocker la donnée uniquement en base

### 4.2 Restauration / backup
**Scripts :** `scripts/db-backup.sh`, `scripts/db-restore.sh`

À standardiser :
- messages d’erreur plus clairs
- exemple d’usage dans `README.md`
- vérification du conteneur `db` avant restore

---

## Phase 5 — Tests de non-régression

### Backend
Créer ou compléter les tests pour :
- transitions de statut invalides
- permissions refusées sur actions critiques
- conflits planning pont/mécano
- réception obligatoire avant `en_cours`

### Frontend manuel
Checklist rapide :
1. créer un RDV public
2. confirmer côté atelier
3. faire la réception + signature
4. démarrer le travail
5. terminer l’intervention
6. vérifier historique client

---

## Ordre d’exécution recommandé

### Sprint A — immédiat
- permissions RDV
- machine d’états RDV
- cookies auth configurables
- tests critiques

### Sprint B — structure backend
- extraction des routes de `main.py`
- réduction du startup runtime

### Sprint C — structure frontend
- extraction `planning`, `OR`, `espace mécano`

---

## Prochaine action conseillée

Commencer par un **lot très court et sûr** :
1. verrouiller permissions et transitions dans `backend/routes/rendez_vous.py`
2. ajouter les tests backend associés
3. seulement ensuite attaquer le découpage de `main.py`
