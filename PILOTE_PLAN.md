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
- [x] **Idempotence de la confirmation de signature (LOW)** ✅ FAIT (2026-07-19, commit `bb92dd8`) : `confirmerSignatureTelephone()` prend un **verrou pessimiste** (`LockMode::PESSIMISTIC_WRITE` + `refresh` + re-vérif de `signatureClient` dans une transaction) ; deux confirmations simultanées sont sérialisées, la 2e tombe en 409 « Accord déjà confirmé » sans re-signer l'OR. `sign()` partagé non touché. Prouvé : 2 POST /decision parallèles → un 200 + un 409, OR signé une seule fois.
- [x] **Test de non-régression isolation atelier (LOW)** ✅ FAIT (2026-07-19, commit `2046f49`) : test E2E dans `canal-telephone.spec.mjs` — une demande basculée sur un autre atelier (flip SQL) → 404 sur GET + 404 sur decision-telephone + absente de la liste, puis re-visible une fois restaurée. NB : contrairement à une note antérieure, le `TenantFilter` EST bien actif pour la session Bearer du compte E2E `admin@atelier.local` (vérifié : atelier 1 → 200, autre atelier → 404), donc le test au niveau E2E suffit.

> Extension possible (non retenue en v1) : relance automatique de la signature de
> confirmation — le repli comptoir suffit pour le pilote.

---

## Chantier — Suivi des motos en atelier + alerte 72h ouvré ✅ FAIT (2026-07-27)

Demande cmoreau : repérer les motos qui dorment à l'atelier, avant que le client rappelle —
puis, sur sa demande complémentaire, **un onglet dédié pour suivre TOUTES les motos présentes**
(pas seulement les dépassements) avec de vraies actions dessus.

**Décisions métier (QCM cmoreau 2026-07-27)** : départ du compteur = arrivée physique de la
moto ; « 72h ouvré » = chrono 24h/24 **gelé les jours de fermeture** (week-end, fériés,
fermetures exceptionnelles) — donc une moto reçue vendredi n'alerte pas le lundi matin ;
statuts surveillés = toute moto physiquement immobilisée (réception, en cours, en pause,
attente pièces, attente reprise, gardiennage) ; trois canaux : badge planning + dashboard,
notification cloche, e-mail à l'atelier.

- [x] **Cœur du calcul** : `SejourAtelierService` — départ = réception tracée dans
  `RdvStatutHistorique` si elle existe ; sinon la date **la plus ancienne** entre le premier
  événement d'atelier connu et la date du RDV. Cette borne est indispensable : sans elle, sur un
  dossier sans réception tracée (legacy, seed, moto entrée directement en gardiennage), une simple
  mise en pause du jour devenait « l'arrivée » et **remettait le compteur à zéro** — défaut trouvé
  et corrigé en recette navigateur (une moto à 56 j retombait à 0 h après un changement de statut).
  Le calendrier de fermeture est délégué à `JoursOuvresService` (source unique, déjà utilisée par
  le gardiennage) plutôt que dupliqué. 11 tests unitaires (calcul et règle de date sont purs).
- [x] **Bug latent corrigé au passage** : `JoursOuvresService::estJourFerie` appelait `easter_days()`,
  fonction de l'extension PHP `calendar` **absente de l'image Docker** → erreur fatale à chaque
  appel du calendrier des jours ouvrés (donc `GardiennageService` et `app:relance-client-stockage`
  étaient cassés). Remplacé par un calcul pur (Meeus/Jones/Butcher), vérifié sur 10 années de
  référence — aucun rebuild d'image nécessaire.
- [x] **Cron + notifications** : `app:alerte-sejour-atelier` (options `--seuil`, `--atelier`,
  `--dry-run`), planifié à 8h30 dans `Schedule.php`. Crée une notification cloche `warning` par
  moto (cible ADMIN + RECEPTIONNAIRE, push Mercure non bloquant) avec **anti-doublon 24h** — pas
  de spam quotidien tant que l'alerte n'est pas traitée — puis un e-mail récapitulatif par atelier
  à `ADMIN_EMAIL`. Le récap est volontairement envoyé à chaque passage : c'est la photo du jour.
- [x] **Badge staff** : composant `AlerteSejourAtelier.vue` monté sur le dashboard et le planning
  (bandeau ambre repliable, 10 lignes max + « … et N autres », lien « Suivi complet → » vers
  l'onglet). Lit `GET /api/sejour-atelier/alertes` en direct (TenantFilter → atelier courant),
  rafraîchi toutes les 5 min, échec de chargement signalé avec « Réessayer » (jamais avalé).
- [x] **Onglet « En atelier »** (`pages/en-atelier.vue`, sidebar ⏳ section `planning`) : la liste de
  TOUTES les motos présentes, triée du séjour le plus long au plus court, dépassements surlignés.
  4 KPI (présentes / au-delà du seuil / ancienneté moyenne / la plus ancienne), filtres par statut,
  recherche plaque-client-moto-mécano, bascule « seulement les dépassements », compteur affichées /
  masquées, date de dernière relance par ligne. Alimenté par `GET /api/sejour-atelier/motos`
  (même service, drapeau `en_depassement`, récapitulatif par statut), rafraîchi toutes les 2 min.

### Fiche actionnable (décision cmoreau : « je ne peux rien faire dans la pop-in »)

La `RdvDetailModal` partagée est en lecture seule (pied de page = « Fermer ») : inutile ici. D'où une
modale dédiée `MotoEnAtelierModal.vue` — la modale partagée n'est pas touchée (zéro risque pour les
4 écrans qui l'utilisent). Actions retenues au QCM, toutes vérifiées au navigateur :

- [x] **Appeler le client** : numéro cliquable (`tel:`) + copie presse-papiers, avec rappel de la
  date de dernière relance.
- [x] **Relancer le client** : `POST /api/sejour-atelier/{id}/relancer` (e-mail ou SMS, message libre
  du staff de 500 caractères max). Nouveau template `sejour_prolonge` (e-mail + SMS) ajouté au
  catalogue — créé automatiquement par atelier via `ensureDefaultsForAtelier`, donc rien à faire à
  l'install. Refus explicites : moto plus en atelier (409), aucun canal disponible (409), envoi
  échoué (502) — jamais de faux « envoyé ». Traçé dans `notification_logs`, ce qui alimente la
  date de dernière relance affichée dans l'onglet et la fiche.
- [x] **Affecter / changer le mécanicien** : `PATCH /api/rendez-vous/{id}` (existant), liste chargée
  en pagination complète et ordre stable.
- [x] **Faire avancer le dossier** : boutons construits depuis `GET /api/rendez-vous/{id}/transitions`
  — ce sont les transitions réellement autorisées par le serveur, donc **les mêmes gardes métier que
  le planning** (aucun statut deviné côté front) ; les alias du workflow (`pause_travail` vs
  `mettre_en_pause`…) sont dédoublonnés pour ne pas afficher deux fois la même action.
- [x] **Recette** : commande vérifiée bout-en-bout (3 notifications, 2e passage bloqué par
  l'anti-doublon, e-mail reçu dans MailHog) ; endpoints 200 ; relance réelle reçue avec message et
  lien de suivi ; aller-retour complet au navigateur (affectation → mise en pause → reprise →
  désaffectation, retour à l'état initial, 0 erreur console) ; E2E navigation / non-régression /
  mvp-complete / lot-b / notifications **verts**.

### Réglages en administration ✅ FAIT (2026-07-27, demande cmoreau)

- [x] **Seuil d'alerte réglable** par atelier (`ConfigAtelier.seuilSejourAtelierHeures`, défaut 72,
  migration `Version20260727120000`) + **interrupteur de l'alerte automatique**
  (`alerteSejourAtelierActive`). Réglages exposés dans l'assistant de configuration, **étape 3
  « Horaires »** — à côté des jours fermés et des fermetures exceptionnelles, dont ils dépendent —
  et donc accessibles à tout admin d'atelier (pas réservés au super admin comme les modules).
  Champ + raccourcis 1/2/3/5/7 jours + libellé calculé (« ≈ 3 jours, week-end et fériés non comptés »).
- [x] **Portée du réglage** : l'API (`/alertes`, `/motos`) et le cron appliquent le seuil de chaque
  atelier ; un paramètre `?seuil=` explicite le surcharge (diagnostic). Alerte coupée → le cron
  n'envoie plus rien (« N moto(s) ignorée(s) : alerte désactivée en administration ») mais l'onglet
  de suivi reste consultable.
- [x] **Validation serveur** : seuil borné 1–8760 h dans `AdminConfigValidator` (un 0 alerterait
  toutes les motos dès l'arrivée) → 400 avec message métier.
- [x] **Recette** : cycle complet vérifié au navigateur (affichage 72, preset 5 jours, sauvegarde,
  seuil appliqué immédiatement par l'API, remise à 72) ; refus du 0 vérifié ; cron avec alerte
  coupée vérifié ; E2E navigation / non-régression / mvp-complete / en-atelier / lot-b **verts**.

### Nettoyage — onglet « Dossiers atelier » supprimé (2026-07-27, demande cmoreau)

L'entrée de menu `/ordres` pointait vers une **page inexistante** (aucun `pages/ordres*`). Retirée,
ainsi que le titre de section associé et **deux boutons staff** qui menaient à la même route morte
(`demandes-travaux-supp.vue`, `DemandeTravauxSuppDetailModal.vue`) — remplacés par la référence de
l'OR en texte. Trois tests E2E « validaient » cette route grâce à des regex trop permissives (la
regex `/ordre|réparation|or|dossier/i` matchait le menu lui-même) : supprimés ou recentrés sur l'API
`ordres-reparation`, qui elle existe bien.

> Non retenu pour l'instant : durée estimée dans la fiche (`RendezVous` n'expose pas de durée directe).

---

## Chantier — Refonte de l'administration + règles métier configurables ✅ FAIT (2026-07-27)

Demande cmoreau : « plutôt que d'avoir des sous-menus, rentrer dans la page et avoir des onglets
comme Google Chrome », puis « refondre toute la page admin, simplifier », et « regarder toutes les
règles codées en dur et les rendre configurables dans la page admin ».

### Navigation par onglets

- [x] **`pages/admin.vue`** = cadre à onglets persistant + `<NuxtPage />`. Choix technique : les
  **routes imbriquées de Nuxt**, donc les 11 pages `pages/admin/*.vue` restent inchangées et gardent
  leur URL (rechargeable, partageable, historique navigateur intact) — contrairement à un `v-if`
  local qui aurait exigé de fusionner 4 800 lignes dans un seul fichier.
- [x] `/admin` n'est plus une grille de cartes : il ouvre directement le premier onglet. Les 11
  flèches « ◀ retour » des pages enfants sont retirées (la barre d'onglets joue ce rôle).
- [x] Onglet actif déduit de l'URL **par préfixe** : une sous-page (designer de documents) garde son
  onglet parent actif. Onglets `Ateliers` / `Profils d'accès` réservés au super admin, comme avant.
- [x] **Bug de scroll corrigé (remonté par cmoreau)** : un élément `sticky` se colle sur le *padding
  box* du conteneur de défilement. La zone de contenu ayant `padding: 24px`, la barre se fixait 24 px
  trop bas et le contenu défilait dans la bande restée visible au-dessus. Corrigé par `top: -24px` +
  marges négatives (la barre déborde sur le padding et couvre toute la largeur). Vérifié par mesure :
  haut de barre = haut de zone, plus rien ne passe au-dessus.

### Onglet Configuration simplifié

- [x] **L'assistant en 6 étapes est supprimé** : toutes les sections sont désormais sur une seule
  page déroulante, avec un sommaire cliquable et une barre d'enregistrement collée en bas. Plus de
  navigation Précédent/Suivant, plus de réglages cachés derrière une étape (le check-in et les
  notifications étaient enfermés dans l'étape « Modules », donc invisibles pour un admin non super).
- [x] **Doublon supprimé (remonté par cmoreau)** : la section « Tarifs par prestation » reprenait —
  moins bien — ce que fait l'onglet **Prestations** (même modale de configuration). Remplacée par un
  renvoi vers cet onglet ; la modale de tarifs et son code sont retirés de `config.vue`
  (**1 293 → 1 143 lignes**), l'action d'amorçage « Pré-remplir les premiers tarifs » est conservée.

### Règles métier sorties du code

Nouveau service **`ReglesAtelier`** = source unique de lecture, avec repli sur les valeurs
historiques (donc **aucun changement de comportement** sur une installation existante). Colonnes
ajoutées à `ConfigAtelier` (migration `Version20260727140000`), pilotées dans la section
« Règles métier » de l'onglet Configuration :

| Règle | Était codée en dur dans | Défaut |
|---|---|---|
| Photos d'entrée exigées | `EtatDesLieuxDocumentService::MIN_PHOTOS_ENTREE` | 4 |
| Rappels avant RDV (2 délais) | `SendRappelsCommand` (`+3 days` / `+1 day`) | 3 et 1 j |
| Délai de relance travaux supp. | `RelanceDemandesTravauxCommand::DELAI_HEURES` | 4 h |
| Fenêtre horaire des envois | `RelanceDemandesTravauxCommand::HEURE_MIN/MAX` | 8 h–19 h |
| Validité des liens clients | `PublicTokenPolicy::GRACE_PERIOD` | 30 j |
| Points de contrôle essai routier | `MecanicienController` (`< 5`) | 5 |
| Rappel d'alerte « moto en atelier » | `AlerteSejourAtelierCommand::RENOTIFY_AFTER_HOURS` | 24 h |
| Seuil d'alerte séjour | `SejourAtelierService::SEUIL_HEURES_DEFAUT` | 72 h ouvrées |

- [x] **Validation serveur** sur chaque borne + deux contrôles croisés (heure de fin > heure de
  début ; 1 ou 2 rappels entre 1 et 60 jours) → 400 avec message métier.
- [x] **Volontairement NON exposé**, et affiché comme tel dans l'écran : durées de conservation RGPD
  (3 ans / 10 ans), tailles maximales d'upload, durées de session et de jetons d'authentification,
  throttle des e-mails d'erreur. Ce sont des obligations légales ou des garde-fous de sécurité, pas
  des choix d'exploitation — les laisser réglables serait un risque, pas un service.
- [x] `SejourAtelierService` délègue désormais ses réglages à `ReglesAtelier` (une seule lecture de
  configuration au lieu de deux implémentations).
- [x] **Recette** : 181 tests unitaires verts ; cycle complet au navigateur (affichage, modification,
  enregistrement, relecture, remise des défauts) ; validations refusées vérifiées (fenêtre
  incohérente, 3 rappels, seuil hors bornes) ; **effet métier prouvé** en bootant le noyau — le même
  dossier clôturé passe de « lien expiré » à « lien valide » puis de nouveau « expiré » selon le
  réglage de validité des liens.

> Piège retenu : lancer la suite E2E pendant un `docker compose restart nuxt` produit une centaine de
> faux échecs (front indisponible). Toujours attendre « Listening on » avant de lancer les tests.

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
