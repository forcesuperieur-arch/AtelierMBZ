# PLAN D'ATTAQUE POST-MVP — « Du MVP au réseau »

> Date : 2026-06-12 — issu de la session de cadrage produit avec cmoreau
> Point de départ : tag `mvp-rc1`, suite E2E verte (179 passed), `main` basculé
> Vision : Paddock équipe à terme un **réseau d'ateliers avec un SRC central**.
> Stratégie : **1 atelier pilote d'abord**, le réseau et le SRC suivent.

---

## Décisions de cadrage (cmoreau, 2026-06-12)

| Sujet | Décision |
|---|---|
| Destinée de l'app | Réseau multi-ateliers + SRC (Service Relation Client) central |
| Déploiement | 1 atelier pilote d'abord, extension ensuite |
| Friction n°1 à tuer | Les appels « c'est prêt ? » |
| KPI de succès à 3 mois | **Zéro litige restitution** (état des lieux + OR signé + photos horodatées) |
| Transparence client | **Maximale** : timeline détaillée + photos d'intervention au fil de l'eau |
| Missions SRC (les 4 retenues) | « Où en est ma moto ? » / prise de RDV téléphone / annulations & réclamations / relances devis & travaux supp |
| Ordre des chantiers | **Lot A** (tuer les appels) → **Lot B** (zéro litige) → **Lot C** (cockpit SRC) |

---

## Piste parallèle — Déploiement pilote (dès que l'hébergement est choisi)

Tout est prêt (`DEPLOIEMENT.md`, `.env.prod.example`, `PUBLIC_DOMAIN`). Bloqué sur :
- [ ] Choix hébergement — **décision cmoreau 2026-07-12 : repoussé, on avance sur le reste en attendant** (comparatif VPS OVH/Hetzner/Infomaniak à re-proposer quand prêt)
- [ ] Nom de domaine à communiquer (cmoreau en possède un)
- [ ] SIRET/TVA réels dans Admin → Ateliers + bloc hébergeur des mentions légales

> Les lots A et B se construisent **sans attendre** le serveur. Mais les KPI
> ne se mesurent qu'en conditions réelles : déployer le pilote tôt, même avec
> le seul périmètre MVP.

---

## Lot A — Tuer les appels « c'est prêt ? » (~5-7 jours)

**Principe : le client n'appelle plus parce qu'il en sait PLUS que ce qu'il
demanderait au téléphone.** On muscle l'existant, pas de nouveau module.

### A1. Timeline du RDV dans l'espace client ✅ FAIT (2026-06-12)
- [x] Historique horodaté : `AuditLog` ne capturait PAS les transitions (audit trail workflow = mémoire seulement) → table `rdv_statut_historique` + `RdvStatutHistoriqueListener` sur l'événement générique `completed`
- [x] Timeline intégrée à la réponse `GET /api/client/rdvs/{id}` (entrée « création » + chaque transition) ; libellés humains côté front (`useRdvStatut` complété : pause, pièces, gardiennage, no-show…)
- [x] Composant `RdvTimeline.vue` (point jaune = étape courante) + polling 30 s onglet visible

### A2. Photos d'intervention au fil de l'eau ✅ FAIT (2026-06-12)
- [x] Endpoint authentifié `GET /api/client/photos/{id}` (contrôle d'appartenance) — au passage, l'affichage photos du portail était MORT (chemin `/uploads/photos` vide) : réparé
- [x] Durcissement : `/uploads/photos/*` retiré de la whitelist publique Caddy (les photos passent par les endpoints contrôlés uniquement)
- [x] Indicateur « visible par le client » côté PDA : reporté (les photos sont toutes visibles — à réévaluer au pilote)

### A3. Notification à chaque étape signifiante ✅ FAIT (2026-06-12)
- [x] `RdvWorkflowListener` étendu : réception, début travaux, reprise après pièces (en plus de confirmation/attente pièces/terminé/no-show). Étapes intermédiaires = email/SMS client seulement, pas de cloche staff. Essai routier : pas une transition de workflow → non notifié (à réévaluer)
- [x] Interrupteurs par étape : `ConfigAtelier.notificationsEtapes` (défaut : tout activé) + UI Admin → Configuration → « Notifications client par étape »
- [x] Templates `rdv_reception`, `travaux_demarres`, `demande_relance` ajoutés au catalogue (auto-créés par atelier au premier envoi)

### A4. Travaux supplémentaires : approbation dans l'espace client + relance ✅ FAIT (2026-06-12)
- [x] Bloc « Travaux supplémentaires » dans le détail RDV du portail : prestations + prix, Refuser (confirmation) / Accepter et signer (canvas `SignatureModal`) ; la page tokenisée publique reste pour les clients sans compte
- [x] Logique partagée extraite en `DemandeTravauxSuppDecisionService` (signature obligatoire, OR complémentaire figé, trace IP/UA) — utilisée par les deux chemins
- [x] Relance automatique : `app:relance-demandes-travaux` (cron horaire, H+4 sans décision, une seule fois, fenêtre 8h-19h) + champs `sentAt`/`relanceAt`
- [x] Notification staff à la décision (cloche + Mercure, sévérité selon accepté/refusé)

### A5. Recette ✅ FAIT (2026-06-12)
- [x] `lot-a.spec.mjs` : 9 tests — transition→historique+email MailHog, interrupteur coupe l'email mais pas l'historique, timeline sans jargon, photos propriétaire/404 autrui, acceptation signée→OR+notif staff, refus, isolation inter-clients, relance unique

**Critère de sortie : un client suit sa moto en temps quasi réel, reçoit un email à chaque étape, et approuve un travail supplémentaire en ligne sans appel téléphonique.**

---

## Lot B — Zéro litige : check-in / état des lieux au dépôt ✅ FAIT (2026-07-05)

**Principe : réutiliser la mécanique éprouvée du document unique OR**
(snapshot PDF gelé + hash + signature) pour l'état des lieux d'entrée.

### B1. Le flux de check-in (mode tablette, comme le PDA mécano) ✅ FAIT
- [x] Vue « Réception du matin » (`/reception`) : RDV du jour, badges d'état, bouton Check-in par moto
- [x] Formulaire : km compteur, jauge carburant 5 segments, observations, photos périphériques (`PhotoIntervention` type `checkin`, min 4 — au passage : les uploads posent enfin le `type`, la garde « 4 photos réception » historiquement morte est réparée)
- [x] Signature client sur tablette (`SignatureModal` partagé, porté du portail — fin des copies inline ; tap sans tracé refusé sur les 8 canvases de l'app)
- [x] Entité `EtatDesLieux` gelée : snapshot + hash sha256 (canon VO) + PDF archivé à nom aléatoire, jamais régénéré ; signature atomique (verrou) ; photos servies depuis le snapshot signé et verrouillées après signature
- [x] Garde workflow : transition `reception` exige l'état des lieux signé (`ConfigAtelier.checkinObligatoire`, toggle admin, défaut actif)

### B2. Boucle du litige fermée ✅ FAIT
- [x] État des lieux consultable dans l'espace client dès la signature (bloc + PDF) et dans le suivi public
- [x] À la restitution : comparatif avant/après (km, carburant, observations, photos entrée/sortie) sur la page de signature
- [x] Signalement de litige/réserve à la signature de restitution (commentaire 2000 c) → champs sur le RDV, cloche staff temps réel, badge + commentaire dans le planning, compteur KPI
- [x] Mention dans l'email « travaux terminés » : lien vers l'état des lieux ({{etat_des_lieux_bloc}}, migration des templates non personnalisés)

### B3. Recette ✅ FAIT
- [x] `lot-b.spec.mjs` : 22 tests — flux complet, validations, gardes, snapshot vs live, OR principal, isolation client, comparatif + litige, emails MailHog, KPI. Suite complète : 209 verts.
- [x] Revue croisée 4 dimensions + vérification contradictoire : 19 défauts confirmés et corrigés (dont CRITICAL upload inter-RDV, falsification de galerie post-signature, expirations incohérentes, SignatureModal sous la modale check-in)

**Critère de sortie : plus aucune moto ne rentre à l'atelier sans état des lieux signé et horodaté, opposable et consultable par les deux parties.**

---

## Lot C — Cockpit SRC (~6-8 jours, à lancer quand le pilote tourne)

**Principe : le SRC est un rôle TRANSVERSE aux ateliers.** `ROLE_SERVICE_CLIENT`
existe déjà côté backend mais sans interface. Point dur : tout le scoping staff
est par atelier (`TenantFilterListener`) — le SRC doit voir À TRAVERS.

### C1. Socle multi-atelier du rôle SRC (le chantier risqué)
- [ ] Audit des voters/filtres tenant : autoriser la lecture cross-atelier pour `ROLE_SERVICE_CLIENT` uniquement, en lecture, journalisée (`AuditLog`)
- [ ] Revue de sécurité dédiée avant toute mise en service (rôle cross-tenant = surface sensible)

### C2. « Où en est ma moto ? » — la recherche universelle
- [ ] Barre de recherche unique : nom / téléphone / plaque → dossier 360° en lecture : statut temps réel, timeline, photos, OR, état des lieux, historique des notifications envoyées (`NotificationLog`)
- [ ] Objectif : réponse au client en < 10 secondes sans appeler l'atelier

### C3. Prise de RDV au téléphone (booking proxy)
- [ ] Réutiliser le wizard de booking public en mode staff : choix de l'atelier, mêmes règles de créneaux/chevauchement, origine du RDV tracée `src_telephone` (alimente le KPI % RDV en ligne)

### C4. File de travail SRC
- [ ] Demandes d'annulation à traiter (entité `AnnulationRdv` existante) avec ancienneté visible
- [ ] Relances : travaux supp et devis sans décision client à H+N → liste d'appels à passer
- [ ] Réclamations : qualification simple (nouveau, en cours, clos) + notes horodatées — pas de CRM complet, un cahier de bord

### C5. Recette
- [ ] E2E : SRC voit les dossiers de 2 ateliers, ne peut RIEN modifier hors périmètre, booking proxy complet, file d'annulations

**Critère de sortie : un agent SRC répond à « où en est ma moto ? » en 10 s, prend un RDV au téléphone, et traite annulations/relances depuis une seule file — sans accès en écriture aux données atelier.**

---

## Instrumentation des KPI (transversal, léger) ✅ FAIT (2026-07-05)

- [x] Origine du RDV (`rendez_vous.origine` : web / comptoir / telephone / devis, sélecteur au formulaire staff) → tuile « % RDV en ligne » au dashboard (section `pilote` de /api/analytics/dashboard, fact tables synchronisées + rebuild)
- [x] Canal de décision travaux supp (`decision_canal` : client_token / client_portail) + délai moyen de décision. ✅ TRANCHÉ (cmoreau, 2026-07-12) : décision par TÉLÉPHONE = **« lien de confirmation »** — le staff enregistre l'accord téléphonique (canal `staff_telephone`, qui/quand), les travaux démarrent aussitôt, et un email part immédiatement avec un lien pour signer l'OR complémentaire en ligne. Si toujours non signé à la restitution, signature au comptoir avec le reste. → chantier « Canal téléphone » ci-dessous.
- [x] Litiges restitution : signalement à la signature de restitution → compteur au dashboard (0 en vert)

---

## Chantier — Canal téléphone (décision travaux supp par téléphone) 🔨 EN COURS (2026-07-12)

**Décision métier (cmoreau, 2026-07-12) : « lien de confirmation ».** Le staff enregistre
l'accord téléphonique, les travaux démarrent aussitôt, un email/SMS part immédiatement avec
un lien pour signer l'OR complémentaire en ligne. Repli : signature au comptoir à la
restitution (le staff rouvre le lien public sur la tablette).

### T1. Backend ✅ FAIT (2026-07-12)
- [x] Champ `decision_enregistree_par` (qui a pris l'appel) + migration
- [x] `decideParTelephone()` dans le service de décision : canal `staff_telephone`, statut→accepte/refuse, OR complémentaire créé **figé mais non signé** (statut `en_attente_signature`, signé à la confirmation), envoi immédiat du lien (template `demande_confirmation_telephone` email+SMS, sans interrupteur d'étape), notif staff « signature en attente »
- [x] Confirmation de signature côté client (page publique tokenisée + portail) : signe l'OR complémentaire a posteriori ; refus impossible après accord tél (409, contacter l'atelier)
- [x] Endpoint staff `POST /demandes-travaux-supp/{id}/decision-telephone` (décision enregistrée même si le client n'a pas d'email/téléphone — l'erreur d'envoi est remontée au staff)
- [x] KPI : `staff_telephone` dans le par-canal, délai moyen restreint aux canaux en ligne, compteur « accords tél en attente de signature »

### T2. Front staff ✅ FAIT (2026-07-12)
- [x] Modale « Décision téléphonique » (accepté/refusé, commentaire, canal d'envoi du lien — liste ET modal détail)
- [x] Badge « Signature en attente » + « Faire signer au comptoir » (ouvre le lien public sur tablette) + trace « enregistré par X le … »

### T3. Front client ✅ FAIT (2026-07-12)
- [x] Page publique : mode « Confirmez votre accord » (signature seule, pas de refus), état « Accord confirmé le … » après signature
- [x] Portail client : même bascule sur le bloc travaux supp du détail RDV (SignatureModal réutilisé, badge « signature à confirmer »)

### T4. Recette ✅ FAIT (2026-07-13)
- [x] `canal-telephone.spec.mjs` : accord tél → email + OR non signé, confirmation signature, refus tél, gardes 409, portail client, KPI (revalidation suite complète en cours après le fix sécurité T5)

### T5. Sécurité — isolation atelier (revue croisée + revue adversariale) ✅ FAIT (2026-07-15)
- [x] **CRITICAL corrigé** : `DemandeTravauxSupp` n'avait pas de colonne `atelier_id` → échappait au `TenantFilter` global (un staff pouvait lire/agir sur les demandes d'un autre atelier via les routes staff). Fix idiomatique : colonne `atelier_id` (migration `Version20260715100000` + backfill depuis le RDV), renseignée à la création, la demande rejoint le filtre global comme les autres entités tenant. Vérifié : cross-atelier → 404 + absente des listes ; mono-atelier intact ; `resolvePreferredAtelierId` empêche un user de repointer le filtre sur un atelier non autorisé.
- [x] **2e chemin de création durci** : `MecanicienController::createDemandeComplementaire` (`POST /api/me/demande-complementaire`) pose désormais explicitement `atelierId` depuis le RDV (avant : protégé seulement par le `TenantSetterListener` prePersist implicite — fragile). Revue adversariale : verdict PASS, aucun CRITICAL/HIGH résiduel.
- [x] **Durcissement anti-double-soumission (MEDIUM)** ✅ FAIT (2026-07-15, commit `c7f960f`) : index unique partiel « un OR complémentaire par demande » (`Version20260715110000`) — barrière DB contre le double-clic/retry ; le 2e flush viole la contrainte, traduit en 409 propre dans `decide` + `decideParTelephone` (pas de doublon d'OR ni de second lien envoyé). Couvre aussi le `decide()` en ligne préexistant.
- [ ] **Idempotence de la confirmation de signature (LOW, trouvé à la passe de debug 2026-07-19)** : `OrdreReparationPolicy::sign()` n'a pas de garde d'idempotence ; deux confirmations SIMULTANÉES de la même demande (double-tap client) passent toutes deux la garde `isEnAttenteConfirmationTelephone()` et re-signent l'OR (dernier écrivain gagne). Impact FAIBLE : même client, contenu gelé identique (hash/snapshot inchangés), seule l'image de signature + l'horodatage sont écrasés ; le double-clic SÉQUENTIEL est déjà bloqué (garde de statut → 409). Fix propre = verrou pessimiste sur ce chemin (ne PAS ajouter de garde globale dans `sign()` : casserait la re-signature de rectification). Non bloquant pilote.
- [ ] **Test de non-régression isolation atelier (LOW, à faire avec fixtures)** : ajouter un test fonctionnel backend (staff atelier B → 404 sur une demande de l'atelier A) sur `GET/PATCH/POST /api/demandes-travaux-supp/{id}`. Non trivial en E2E car le compte admin E2E est SUPER_ADMIN (filtre globalement off, Bearer sans cookie d'atelier) — nécessite un user staff non-super-admin rattaché à un 2e atelier. Isolation déjà vérifiée manuellement (session filtrée + flip SQL → 404).

> Extension possible (non retenue en v1) : relance automatique de la signature de
> confirmation — le repli comptoir suffit pour le pilote.

---

## Risques

| Risque | Niveau | Parade |
|---|---|---|
| Transparence max → appels inverses (« pourquoi en pause ? ») | MOYEN | Interrupteurs de notification par étape dans la config atelier (A3) |
| Rôle SRC cross-tenant = brèche d'isolation potentielle | **HAUT** | Lecture seule, audit systématique, revue sécurité dédiée (C1) avant service |
| Photos au fil de l'eau : volume disque + RGPD | FAIBLE | Purge 30 j déjà en place, surveiller le volume au pilote |
| KPI non mesurables tant que pas en prod | MOYEN | Déployer le pilote tôt, même périmètre MVP nu |
| Lot C démarré avant que le réseau soit réel | MOYEN | Lot C conditionné au pilote en fonctionnement |

---

## Récapitulatif

| Lot | Contenu | Durée estimée | Dépendance |
|---|---|---|---|
| Pilote | Déploiement serveur + domaine | 0,5-1 j | Choix hébergement (cmoreau) |
| A | Timeline + photos + notifs par étape + travaux supp en ligne | 5-7 j | Aucune |
| B | Check-in état des lieux signé + comparatif restitution | 4-5 j | Aucune (réutilise pattern OR) |
| C | Cockpit SRC multi-ateliers | 6-8 j | Pilote en fonctionnement |
| **Total** | | **~16-21 j** | |

> Chantier design system (migration des 2 200 styles inline, cf. MVP_PLAN.md)
> à intercaler entre les lots, page par page, quand on touche déjà une page.
