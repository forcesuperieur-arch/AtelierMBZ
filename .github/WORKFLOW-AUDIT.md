# Audit workflow — Vision utilisateur réelle
Date : 2026-04-21

Ce document capture la vision métier réelle du flux principal (RDV → Restitution) telle que définie par le product owner.
À utiliser comme référence pour auditer l'existant avant toute implémentation.

---

## Étape 1 — Prise de RDV

**Statut vision** : ✅ Aligné avec l'existant

- Prestation propre au type de moto
- Pont et mécanicien attribués automatiquement
- Créneau affiché = disponibilité min 1 pont + calcul durée intervention format `hh:mm`
- Champ motif client / info complémentaire
- Statut créé : `en_attente`

---

## Étape 2 — Réception physique (via Companion tablette)

**Statut vision** : ⚠️ Partiellement aligné — mentions légales à vérifier

- Tout se passe via le Companion (tablette au comptoir, usage interne)
- Saisie km réel → `RendezVous.kilometrage`
- Checkup extérieur via Companion
- Photos de réception via Companion
- **Affichage de toutes les mentions légales et clauses avec cases à cocher obligatoires** avant signature
- Signature OR client

**OR figé :**
- Figé à l'instant T de la signature (prestations + prix)
- Option A retenue : les infos ajoutées par le mécanicien vont dans une **section séparée de l'OR**, non modifiable après saisie — elles ne modifient pas la partie signée initiale
- Important : ces notes mécanicien apparaissent dans le document final

**À auditer :**
- [ ] Les clauses légales et cases à cocher sont-elles présentes dans le Companion avant signature ?
- [ ] La section notes mécanicien sur l'OR est-elle distincte de la partie signée ?

---

## Étape 3 — Intervention mécanicien

**Statut vision** : ⚠️ Partiellement aligné — checkup + OR complémentaire à implémenter

### Timer
- Timer visible lors du démarrage
- Mise à jour statut temps réel via Mercure

### Checkup technicien — WIP à définir
- **Obligatoire en tout premier**, avant tout démarrage des travaux
- Objectif : vente de prestations et pièces additionnelles
- Contenu : formulaire avec points à cocher (batterie, pneus, freins, etc.) — **à définir précisément**
- Déclenche automatiquement des suggestions de prestations additionnelles — **logique à définir**
- **Statut** : 🚧 WIP — ne pas implémenter sans spec complète

### Notes mécanicien
- Vont dans `OrdreReparation.mechanic_notes` (jamais dans `rdv.commentaire`)
- Réutilisées dans le rapport d'intervention et l'OR (périmètre exact à définir)

### Demandes de travaux complémentaires — flux complet
1. Mécanicien sélectionne les travaux depuis le **référentiel tarifaire filtré sur le type de moto**
2. Peut associer une ou plusieurs photos à la demande
3. Réceptionniste reçoit la demande, vérifie, peut modifier les travaux
4. Réceptionniste appelle le client
5. **Client reçoit une demande avec :** photo(s) + travaux + prix supplémentaire + mentions légales obligatoires + **signature et case de consentement**
6. Génère un **OR complémentaire rattaché à l'OR initial** → un seul document PDF final regroupant tout
7. **Cumul des travaux et du tarif** pour la facturation finale

**Escalade notifications :** système d'escalade en cascade en cas de non-réaction (T+5/10/30min) — `NotificationEscalation` codé, à vérifier s'il est branché sur les demandes complémentaires.

**À auditer :**
- [ ] `DemandeTravauxSupp` est-il branché sur `NotificationEscalation` ?
- [ ] L'OR complémentaire rattaché à l'OR initial existe-t-il ? (PDF consolidé)
- [ ] La signature client avec consentements sur la demande complémentaire est-elle implémentée ?
- [ ] Le filtrage tarif par type de moto est-il opérationnel pour le mécanicien ?

### Essai routier
- Rempli par le mécanicien
- Km début, km fin, points de contrôle, anomalies, signature mécanicien

---

## Étape 4 — Clôture intervention

**Statut vision** : ⚠️ Ordre à corriger dans le code

**Ordre impératif :**
1. Essai routier en premier (obligatoire, bloquant)
2. Rapport d'intervention ensuite (travaux réalisés, alertes, km restitution, prochaine révision)
3. Signature mécanicien sur le rapport
4. Transition `terminer` débloquée uniquement si essai routier signé + rapport complet

> ⚠️ L'ordre dans `copilot-instructions.md` était inversé (rapport avant essai) — à corriger.

---

## Étape 5 — Restitution

**Statut vision** : ⚠️ Écarts sur notifications et consultation rapport

- Client reçoit des **notifications à chaque évolution depuis la réception** (pas seulement à la fin)
  - À auditer : quels events déclenchants sont câblés dans `NotificationDispatcher` ?
- Companion = **tablette interne au comptoir uniquement** (pas d'envoi de lien externe pour la restitution)
- Client doit **consulter le rapport avant de signer** (affiché sur la tablette)
- Restitution et facturation conditionnelles à l'**activation des modules concernés**

**À auditer :**
- [ ] Les notifications d'avancement (réception, en cours, terminé) sont-elles toutes câblées ?
- [ ] Le rapport est-il consultable sur la tablette avant signature ?

---

## Étape 6 — Facturation

**Statut vision** : ✅ Aligné — conditionnel à l'activation du module

- Module facturation à activer via `ConfigAtelier.featureModules`
- Cumul de l'OR initial + OR(s) complémentaires pour la facturation finale
- Encaissement → `paye`

---

## Cas particuliers

### `no_show`
- Transition OK
- **UI : libellé à revoir** (le texte affiché côté front est mal formulé)

### `reporter`
- Transition OK
- **À ajouter : proposition automatique de nouveau créneau disponible**
- À auditer : la page de report propose-t-elle des créneaux ou juste une date libre ?

### `en_attente_pieces`
- OK, rien à changer

### `en_gardiennage`
- OK, rien à changer

### `restitue_partiel` — DÉCISION PRODUIT
- **Interdit dans le workflow standard** — on ne restitue pas une moto sans paiement
- **Exception autorisée uniquement pour :** VO et Dépôt-vente
- À implémenter : bloquer la transition `restituer_partiel` sauf si type dossier = VO ou dépôt-vente

---

---

## Workflow VO — Vision utilisateur réelle

### Statuts : `brouillon → en_stock → en_vente → reserve → vendu`

---

### Étape 1 — Rachat (`brouillon`)

**Statut vision** : ⚠️ Partiellement aligné — LP mode paiement manquant, DA SIV + Cerfa auto à vérifier

- Gestionnaire VO crée le dossier
- OCR carte grise (`useCarteGriseOcr`) pré-remplit les infos véhicule — **à vérifier que ça fonctionne correctement**
- Saisie identité vendeur par **transcription uniquement** (RGPD rétention 0 jour, pas d'upload) — **à vérifier**
- PV rachat PDF → signature via **Companion VO interne** (tablette, pas de lien externe)
- **Livre de Police entrée** — manque : **mode de paiement obligatoire + n° chèque si règlement par chèque**
- **DA SIV (Cerfa 13751) + Cerfa cession générés automatiquement à la signature** du PV rachat, pré-remplis avec les données OCR + dossier — objectif : zéro ressaisie
- Statut → `en_stock` — visible pour la compta pour la clôture fiscale annuelle

**À auditer :**
- [ ] OCR carte grise : taux de reconnaissance, champs pré-remplis vs saisis manuellement
- [ ] Transcription identité vendeur : aucun stockage fichier, vérification API
- [ ] LP : champ mode de paiement présent ? n° chèque conditionnel si chèque ?
- [ ] DA SIV + Cerfa cession : générés automatiquement à la signature ou manuellement ?
- [ ] Statut `en_stock` visible dans la vue compta / suivi stock ?

---

### Étape 2 — Remise en état (FRE)

**Statut vision** : ⚠️ Partiellement aligné — intégration workflow atelier à préciser

- Tarif selon **type de moto** → utilise la **grille tarifaire atelier existante**
- La FRE est soumise à l'atelier sous forme de **demande de RDV** (pas un module séparé)
- Notifications internes : commande de pièce, passage rapide, etc.
- **Priorité client VO** — doit être visible dans le planning sans créer de complexité supplémentaire (pas de module VO distinct dans le planning)
- Passe par le **workflow réception complet SAUF la signature** (c'est interne, pas de Companion)
- `VOMarginService` recalcule la marge **à la fin des travaux** (pas en temps réel pendant l'intervention)

**À auditer :**
- [ ] La FRE peut-elle générer une demande de RDV atelier liée ?
- [ ] Le planning distingue-t-il visuellement les interventions VO des RDV clients ?
- [ ] La partie signature du workflow réception est-elle bien sautée pour les FRE ?
- [ ] `VOMarginService` est-il appelé au moment de la clôture FRE ?

---

### Étape 3 — Mise en vente

**Statut vision** : ✅ Aligné

- Statut → `en_vente`
- Photos véhicule uploadées — usage interne (identification moto), pas de diffusion externe
- Verdict vendabilité `VODocumentService` : `vendable` / `non_vendable`
- **Bloquant si DA SIV pas `enregistree`**

---

### Étape 4 — Réservation

**Statut vision** : 🚧 WIP — à cadrer

- Statut → `reserve`
- **Acompte à saisir** (montant, mode de règlement)
- **Document à faire signer** (bon de commande VO ?) — vision : Companion interne, tablette comptoir
- Rien n'est spécifié ni codé à ce stade

**À cadrer avant implémentation :**
- Quel document exactement ? Bon de commande VO avec montant acompte ?
- Signé via Companion interne (tablette) ?
- L'acompte est-il enregistré comme `Paiement` partiel lié au dossier ?

---

### Étape 5 — Vente

**Statut vision** : ⚠️ TVA à gérer côté UI sans complexifier

- **TVA** : mention obligatoire sur la facture (légal — déductibilité acheteur pro). Décision UI : le gestionnaire choisit le régime **une fois** à la création du dossier (marge Art. 297A ou normale Art. 256), imprimé automatiquement sur la facture. Le client final ne voit pas le choix technique.
- Génère **Cerfa cession vente** automatiquement
- **Mandat immatriculation** si atelier habilité SIV
- Inscription **LP sortie**
- Statut → `vendu`

**Décision TVA actée :**
- Régime **TVA sur la marge** (Art. 297A CGI) — valeur par défaut pour cet atelier
- Configurable dans l'administration (`ConfigAtelier`) — ne pas hardcoder
- Figé à l'émission de la facture, imprimé automatiquement, invisible pour le client

**Décision réservation actée :**
- Document = **bon de commande VO** avec montant acompte + mode de règlement
- Signé via **Companion interne** (tablette comptoir)
- Acompte enregistré comme `Paiement` partiel lié au dossier VO

**À auditer :**
- [ ] `ConfigAtelier` : champ régime TVA VO présent ? (`tvaVoRegime` ou équivalent)
- [ ] Cerfa cession vente généré automatiquement à la vente ?
- [ ] LP sortie générée automatiquement ou manuellement ?
- [ ] `snap_*` de `VOFacture` bien figés à l'émission ?
- [ ] Bon de commande VO + Companion pour la réservation : existe-t-il ? (WIP probable)

---

---

## Workflow DA SIV — Vision utilisateur réelle

### Statuts : `a_preparer → enregistree` / `rejetee` / `expiree`
> `en_cours` : état transitoire optionnel, ne doit pas être forcé

**Statut vision** : ⚠️ Partiellement aligné — `en_cours` superflu, flux à simplifier

---

### Étape 1 — Création (`a_preparer`)

- La DA est **créée avec le dossier VO** dès l'ouverture du rachat (pas à la signature)
- Pré-remplie avec données OCR + dossier : VIN, immat, identité vendeur, date achat
- Délai légal : **15 jours max** après l'achat (Art. R322-4 Code route)
- **Alerte J+10** si DA pas encore `enregistree` : notification in-app + email

---

### Étape 2 — Soumission et enregistrement

- L'atelier dispose d'un **accès assermenté SIV PRO** → validation **instantanée**
- Workflow réel : gestionnaire va sur SIV PRO (interface externe), saisit la DA, obtient la confirmation immédiate
- Revient dans l'app → passe directement à `enregistree` + upload récépissé
- **`en_cours` ne doit pas être une étape obligatoire** — transition directe `a_preparer → enregistree` doit être possible

---

### Étape 3 — États terminaux

- `enregistree` : récépissé uploadé → véhicule **vendable**
- `rejetee` : refus SIV PRO → motif affiché, relance possible
- `expiree` : J+15 dépassé sans `enregistree` → **alerte bloquante**

**Règle bloquante** : sans DA `enregistree`, le véhicule est invendable — `VODocumentService` bloque la transition vers `en_vente`.

---

**À auditer :**
- [ ] La DA est-elle bien créée à l'ouverture du dossier (pas à la signature) ?
- [ ] La transition directe `a_preparer → enregistree` est-elle possible (sans passer par `en_cours`) ?
- [ ] L'alerte J+10 est-elle câblée dans `NotificationDispatcher` (in-app + email) ?
- [ ] Le statut `expiree` est-il calculé automatiquement (job/scheduler) ou manuellement ?
- [ ] `VODocumentService` bloque bien `en_vente` si DA pas `enregistree` ?

---

## Workflow dépôt-vente — Vision utilisateur réelle

**Statut vision** : ⚠️ Partiellement aligné — renouvellement à distance + bon de commande non implémentés

---

### Étape 1 — Création du mandat

- **Config Atelier** : deux paramètres à ajouter — `dureeDefautMandatJours` (ex : 90j) + structure commission : soit **Forfait** (en sus du prix vendeur) soit **Pourcentage** (déduit du prix de vente client)
- **Livre de Police entrée obligatoire** — le véhicule entre physiquement dans le garage, il doit être tracé même s'il n'est pas acheté
- Signature mandat via **Companion interne** (tablette comptoir) — déposant lit le contrat complet + clauses légales + cases à cocher obligatoires avant signature
- **Pas de DA SIV** — le déposant reste propriétaire, pas d'achat

---

### Étape 2 — Suivi et renouvellement

- **Alerte J-7** avant expiration du mandat (notification in-app + email)
- **Renouvellement à distance** : gestionnaire clique "Renouveler" dans l'interface VO → saisit nouvelle durée dans une modale → email envoyé au déposant → déposant valide ou refuse via lien web

---

### Étape 3 — Vente

- **TVA uniquement sur la commission** perçue par l'atelier (Art. 1915 Code Civil)
- Cerfa de cession généré automatiquement (vendeur initial → acheteur final)
- **Option mandat immatriculation SIV** : case à cocher lors de la signature acheteur (via Companion) — sur demande de l'acheteur
- **Deux signatures via Companion** : vendeur (déposant) d'abord, puis acheteur — chacun voit les mentions légales et coche les clauses
- LP enregistre **2 lignes distinctes** : entrée dépôt + sortie vente
- Reversement net déposant **sous 15 jours max**

---

### Étape 4 — Non-vente / expiration

- Mandat expiré → véhicule restitué au déposant
- LP sortie sans vente (ligne de sortie distincte)

---

**À auditer :**
- [ ] `ConfigAtelier` : `dureeDefautMandatJours` + structure commission présents ?
- [ ] LP entrée automatique à la création du mandat ?
- [ ] Companion dépôt-vente : clauses + cases à cocher avant signature déposant ?
- [ ] Alerte J-7 câblée dans `NotificationDispatcher` ?
- [ ] Renouvellement à distance via lien web : implémenté ou WIP ?
- [ ] Deux passages Companion distincts (déposant puis acheteur) pour la vente ?
- [ ] LP : 2 lignes distinctes entrée/sortie correctement générées ?
- [ ] Reversement déposant : suivi du délai 15j dans l'app ?

---

## Workflow Companion — Vision utilisateur réelle

**Statut vision** : ⚠️ À auditer — usage interne uniquement, mentions légales à vérifier

### Companion atelier (`/public/companion/{token}`)

**Déclenché à la réception** — tablette au comptoir, jamais envoyé par lien externe

**Ce que le client voit et fait dans l'ordre :**
1. Résumé du RDV (prestation, moto, date, km relevé)
2. Checkup extérieur (photos de réception visibles)
3. **Mentions légales complètes** (CGV atelier, garantie légale, droit de rétractation)
4. **Cases à cocher obligatoires** (accord intervention, accord tarif, accord conditions)
5. Zone de signature tactile
6. Confirmation → OR figé

**À la restitution** — tablette au comptoir

1. Rapport d'intervention affiché en lecture (travaux réalisés, km restitution, alertes)
2. **Signature client** sur le rapport

**Companion demande complémentaire** (`/public/demande/{token}`)

- Envoyé par SMS au client pendant l'intervention
- Client voit : photo(s) + description travaux + prix supplémentaire
- **Mentions légales** + **case de consentement** obligatoire
- Signature client → validation → OR complémentaire généré

**À auditer :**
- [ ] Token en segment de chemin URL (jamais query string) ?
- [ ] Token non-devinable (entropie suffisante) ?
- [ ] Mentions légales affichées avant signature dans les 3 cas (réception / restitution / demande complémentaire) ?
- [ ] Cases à cocher bloquantes (on ne peut pas signer sans avoir coché) ?
- [ ] Companion restitution : rapport lisible avant signature ?
- [ ] OR figé immédiatement après signature (pas de modification possible côté back) ?

---

### Companion VO (`/public/vo-companion/{token}`)

**Ce que le vendeur voit à l'achat :**
1. Infos véhicule (VIN, immat, km, MEC) pré-remplies OCR
2. PV de rachat complet
3. Mentions légales + cases à cocher
4. Signature vendeur

**Ce que l'acheteur voit à la vente :**
1. Facture VO + Cerfa cession
2. Option case "Mandat immatriculation SIV" (si souhaité)
3. Mentions légales + cases à cocher
4. Signature acheteur

**Dépôt-vente — déposant :**
1. Contrat mandat complet (durée, commission, prix)
2. Mentions légales + cases à cocher
3. Signature déposant

**À auditer :**
- [ ] Même sécurité token que Companion atelier ?
- [ ] Les 3 parcours VO Companion sont-ils distincts ou un seul composant générique ?
- [ ] Cerfa + PV auto-remplis depuis les données dossier (pas de ressaisie) ?

---

## Workflow gardiennage — Vision utilisateur réelle

**Statut vision** : ✅ Logique codée — UX et relances automatiques à vérifier

### Déclenchement

- Moto non récupérée après intervention terminée
- Transition `passer_gardiennage` ou `mettre_en_gardiennage` depuis `planning.vue` ou `workshop.vue`
- Tarif journalier : `ConfigAtelier.tarifGardiennageJournalier` (défaut 5€/j)

### Relances automatiques (jours ouvrés)

| Délai | Action |
|---|---|
| J+15 (`delaiRelance1JoursOuvres`) | Notification 1 au client (email + SMS) |
| J+30 (`delaiRelance2JoursOuvres`) | Notification 2 — mise en demeure |
| J+45 (`delaiProposeGardiennageJoursOuvres`) | Proposition formelle de gardiennage avec tarif |
| J+180 (`delaiProcedureAbandonJoursOuvres`) | Déclenchement procédure d'abandon (LRAR) |

### Sortie

- Transition `sortir_gardiennage` → `en_cours` (reprise de l'intervention)
- Ou facturation gardiennage + restitution

**À auditer :**
- [ ] Les relances J+15/30/45/180 sont-elles automatiques (job/scheduler) ou manuelles ?
- [ ] `tarifGardiennageJournalier` est-il calculé et ajouté à la facture finale ?
- [ ] Le responsable atelier reçoit-il une alerte à chaque seuil de délai ?
- [ ] La procédure d'abandon J+180 génère-t-elle un document (LRAR) ou juste une alerte ?

---

## Workflow prise de RDV publique — Vision utilisateur réelle

**Statut vision** : ⚠️ Implémenté — validation côté réceptionniste à vérifier

### Côté client (`/public/booking`)

1. Saisit : nom, prénom, téléphone, type de prestation, plaque, date souhaitée
2. `BookingAtelierAccessService` vérifie que l'atelier accepte les réservations en ligne
3. RDV créé en statut `en_attente`
4. Client reçoit confirmation par email/SMS

### Côté réceptionniste (`planning.vue`)

1. RDV `en_attente` apparaît dans le planning (visuellement distinct)
2. Réceptionniste valide, affecte pont + mécanicien, confirme le créneau
3. Transition `confirmer` → statut `confirme`
4. Client reçoit confirmation de créneau

**À auditer :**
- [ ] Les RDV publics sont-ils visuellement distincts dans le planning ?
- [ ] `BookingAtelierAccessService` vérifie-t-il bien que le module `rdv_siege` ou `rdv` est actif ?
- [ ] Le client reçoit-il bien une confirmation après soumission ET après validation réceptionniste ?
- [ ] Les créneaux proposés au client tiennent-ils compte des disponibilités réelles (ponts + mécaniciens) ?

---

## Décisions produit actées (récap global)

| Décision | Détail |
|---|---|
| OR figé = option A | Notes méca dans section séparée, partie signée immuable |
| Checkup technicien | 🚧 WIP — ne pas implémenter sans spec complète |
| Companion = toujours interne | Tablette comptoir, aucun lien externe sauf demande complémentaire (SMS client) |
| `restitue_partiel` | Interdit sauf VO et dépôt-vente |
| Notifications | Dès la réception, à chaque transition, pas seulement à la fin |
| TVA VO | Marge (Art. 297A) par défaut, configurable dans `ConfigAtelier`, invisible client |
| Réservation VO | Bon de commande + acompte, signé Companion interne |
| DA SIV | Créée à l'ouverture du dossier, transition directe `a_preparer → enregistree` |
| Dépôt-vente commission | Forfait ou % — configurable dans `ConfigAtelier` |

---

## Checklist audit globale

### Workflow atelier
- [ ] **Étape 2** — Clauses et cases à cocher dans Companion avant signature OR
- [ ] **Étape 2** — Section notes mécanicien distincte de la partie signée sur l'OR
- [ ] **Étape 3** — `DemandeTravauxSupp` branché sur `NotificationEscalation`
- [ ] **Étape 3** — OR complémentaire rattaché + PDF consolidé
- [ ] **Étape 3** — Signature client + consentements sur demande complémentaire
- [ ] **Étape 3** — Filtrage tarif par type de moto côté mécanicien
- [ ] **Étape 4** — Ordre essai routier → rapport dans le code (corriger si inversé)
- [ ] **Étape 5** — Notifications à chaque transition câblées
- [ ] **Étape 5** — Rapport consultable sur tablette avant signature restitution
- [ ] **Cas particuliers** — UI `no_show` libellé à revoir
- [ ] **Cas particuliers** — `reporter` propose créneaux disponibles automatiquement
- [ ] **Cas particuliers** — Transition `restitue_partiel` bloquée sauf VO/dépôt-vente

### Workflow VO
- [ ] OCR carte grise fonctionnel et complet
- [ ] Transcription identité vendeur : aucun stockage fichier côté API
- [ ] LP : mode de paiement + n° chèque conditionnel présents
- [ ] DA SIV + Cerfa auto-générés à la signature du PV
- [ ] Transition directe `a_preparer → enregistree` possible
- [ ] Alerte DA SIV J+10 câblée (in-app + email)
- [ ] `ConfigAtelier` : champ régime TVA VO présent
- [ ] Bon de commande VO réservation : WIP à implémenter
- [ ] FRE : génère demande de RDV atelier liée
- [ ] FRE : planning distingue visuellement interventions VO

### Workflow dépôt-vente
- [ ] `ConfigAtelier` : `dureeDefautMandatJours` + structure commission
- [ ] LP entrée auto à la création du mandat
- [ ] Companion dépôt-vente : clauses + cases avant signature déposant
- [ ] Alerte J-7 câblée
- [ ] Renouvellement à distance via lien web : WIP
- [ ] Deux passages Companion distincts (déposant puis acheteur)
- [ ] LP 2 lignes distinctes entrée/sortie
- [ ] Suivi délai reversement 15j

### Companion
- [ ] Tokens en segment de chemin (jamais query string)
- [ ] Entropie token suffisante
- [ ] Mentions légales + cases bloquantes dans les 3 parcours atelier
- [ ] Rapport lisible avant signature restitution
- [ ] OR figé immédiatement après signature
- [ ] Cerfa + PV VO auto-remplis sans ressaisie

### Gardiennage
- [ ] Relances J+15/30/45/180 automatiques (scheduler)
- [ ] `tarifGardiennageJournalier` calculé et ajouté à la facture
- [ ] Alerte responsable atelier à chaque seuil
- [ ] Procédure abandon J+180 : document ou alerte ?

### RDV public
- [ ] RDV publics visuellement distincts dans le planning
- [ ] `BookingAtelierAccessService` vérifie module actif
- [ ] Double confirmation client (soumission + validation réceptionniste)
- [ ] Créneaux proposés = disponibilités réelles
