# Plan de refactor technique — Atelier Moto Pro

> Mise à jour au **05/04/2026** — état réel du chantier après les correctifs métier et les extractions backend/frontend

## Objectif

Réduire le risque technique **sans casser le flux métier actuel** :
- sécuriser le cycle `RDV -> réception -> intervention -> clôture`
- sortir la logique critique des fichiers monolithiques
- garder la compatibilité fonctionnelle pendant la phase soft

---

## État actuel vérifié

- `backend/main.py` est descendu à **176 lignes** (contre **5153** au départ du plan)
- le frontend est désormais découpé en `app-core.js` + `frontend/modules/*`
- les routes frontend publiques/legacy sont isolées dans `backend/routes/frontend_pages.py`
- le bootstrap de démarrage est isolé dans `backend/services/startup_service.py` et `backend/services/runtime_migrations.py`
- les cookies d’auth sont configurables via `COOKIE_SECURE` et `COOKIE_SAMESITE`
- les transitions de statuts RDV sont formalisées dans `backend/routes/rendez_vous.py`
- deux correctifs métier critiques ont été validés :
  - créneaux publics **multi-prestations** avec durée cumulée correcte
  - **travaux complémentaires / OR complémentaire** bien rattachés au RDV courant
- la suite backend la plus récente est verte : **`154 passed in 30.84s`**

---

## Avancement par phase

### Phase 1 — Durcissement métier immédiat ✅

**Terminé / durci :**
- permissions `rdv.edit` renforcées sur les actions RDV sensibles
- transitions de statuts explicites avec message d’erreur clair si transition invalide
- cookies d’auth sécurisés et pilotés par variable d’environnement
- tests critiques enrichis pour verrouiller les régressions principales

### Phase 2 — Démonolithisation backend 🟡 très avancée

**Déjà extrait de `backend/main.py` :**
- `routes/auth_api.py`
- `routes/tenant_admin.py`
- `routes/public_booking.py`
- `routes/travaux_supp.py`
- `routes/inventory.py`
- `routes/forfaits_mo.py`
- `routes/moto_base.py`
- `routes/devis.py`
- `routes/vehicles.py`
- `routes/prestations_tarifs.py`
- `routes/frontend_pages.py`
- `services/startup_service.py`
- `services/runtime_migrations.py`
- ainsi que les modules déjà en place : `clients.py`, `rendez_vous.py`, `workshop.py`

**Cible atteinte en grande partie :**
- `main.py` joue maintenant surtout le rôle de **composition root**
- il conserve encore quelques routes publiques / legacy et le startup minimal

**Reste à finir :**
- sortir davantage de logique de migration runtime du démarrage applicatif
- basculer les évolutions de schéma vers `alembic/`

### Phase 3 — Découpage progressif du frontend 🟡 bien engagé

**Modules déjà présents :**
- `frontend/app-core.js`
- `frontend/modules/dashboard.js`
- `frontend/modules/rdv.js`
- `frontend/modules/planning.js`
- `frontend/modules/or.js`
- `frontend/modules/clients.js`
- `frontend/modules/admin.js`
- `frontend/modules/mecanicien.js`
- `frontend/modules/billing.js`
- `frontend/modules/suivi.js`
- `frontend/modules/workshop.js`

**Reste à lisser :**
- réduire les `onclick="..."` inline résiduels
- limiter les accès globaux à `APP`
- centraliser les helpers de rendu HTML encore dupliqués

### Phase 4 — Fiabilisation infra et données 🔜

**Sujets encore ouverts :**
- stockage des signatures dans un volume dédié ou en base
- poursuite de l’alignement Alembic pour remplacer les migrations runtime historiques

**Déjà fiabilisé dans cette passe :**
- scripts `db-backup.sh` / `db-restore.sh` standardisés vers le vrai dossier `backups/`
- documentation d’exploitation ajoutée dans `docs/OPERATIONS.md`
- nettoyage du dépôt avec archivage des anciens fichiers plats dans `archive/legacy-root-2026-04-05/`

### Phase 5 — Tests de non-régression ✅ renforcés

**Déjà couvert côté backend :**
- transitions de statut invalides
- permissions refusées sur actions critiques
- conflits planning pont / mécano
- booking public avec durée cumulée
- approbation travaux supp et rattachement OR / RDV
- conversion `devis -> RDV`
- disponibilité des routes `prestations / tarifs / synthèse`

---

## Priorités restantes

### Sprint suivant recommandé
1. basculer progressivement les migrations runtime restantes vers `alembic/`
2. fiabiliser la persistance des signatures
3. poursuivre le nettoyage frontend sans régression UX
4. maintenir la couverture de non-régression sur les flux critiques

### Niveau de risque actuel
- **Métier :** faible à modéré, grâce aux tests de régression ajoutés
- **Structure backend :** en nette amélioration
- **Infra / exploitation :** encore quelques points à fiabiliser
