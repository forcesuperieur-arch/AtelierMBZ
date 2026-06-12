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
- [ ] Choix hébergement (comparatif VPS à fournir quand cmoreau est prêt)
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

## Lot B — Zéro litige : check-in / état des lieux au dépôt (~4-5 jours)

**Principe : réutiliser la mécanique éprouvée du document unique OR**
(snapshot PDF gelé + hash + signature) pour l'état des lieux d'entrée.

### B1. Le flux de check-in (mode tablette, comme le PDA mécano)
- [ ] Vue « Réception du matin » : liste des RDV du jour, bouton Check-in par moto
- [ ] Formulaire : km compteur, niveau carburant, observations, photos périphériques (réutilise `PhotoIntervention` avec un type `checkin`)
- [ ] Signature client sur tablette (composant `SignaturePad` existant)
- [ ] Génération du PDF état des lieux gelé + hash (pattern `OrdreReparationFreezeListener` / templates-documents existants)

### B2. Boucle du litige fermée
- [ ] État des lieux consultable dans l'espace client dès la signature
- [ ] À la restitution : rappel de l'état d'entrée côte à côte (avant/après) sur la page de signature de restitution
- [ ] Mention dans l'email « travaux terminés » : lien vers l'état des lieux

### B3. Recette
- [ ] E2E : check-in complet → PDF gelé → visible client → restitution avec comparatif

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

## Instrumentation des KPI (transversal, léger)

- [ ] Origine du RDV (web / téléphone / comptoir / SRC) sur `RendezVous` → % RDV en ligne
- [ ] Compteur décisions travaux supp en ligne vs téléphone
- [ ] Litiges restitution : champ de signalement à la restitution (compteur simple, le « zéro » se constate)

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
