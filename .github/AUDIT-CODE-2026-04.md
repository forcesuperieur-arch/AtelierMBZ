# Audit code — AtelierMBZ
Date : 2026-04-21
Périmètre : Workflow atelier + VO + Companion + Messenger + Frontend critique

---

## RÉSUMÉ EXÉCUTIF

Audit réalisé sur ~30 fichiers (entités, services, controllers, listeners, messages, commandes, pages frontend, composants Companion). Couverture : workflow RDV complet, entités OR/EssaiRoutier/RapportIntervention, module VO, Companion (atelier + VO), Messenger/worker, commandes cron.

**8 problèmes critiques identifiés** (blocages légaux ou workflow), **9 problèmes importants** (fonctionnalités métier incomplètes).

---

## 🔴 CRITIQUES — À traiter en priorité absolue

### [C1] Guard manquant sur transition `terminer`
**Fichiers** : `backend/config/packages/workflow.yaml`, `backend/src/EventSubscriber/RdvWorkflowListener.php`
**Symptôme** : Aucun guard ne vérifie que `EssaiRoutier.signatureMecanicien` est non-null avant d'autoriser la transition `terminer`. Un mécanicien peut clôturer une intervention sans avoir signé l'essai routier.
**Conséquence** : Violation du processus légal atelier (essai routier obligatoire avant restitution).
**Action** : Créer `RdvTerminationGuardListener` qui écoute `workflow.rendez_vous.guard.terminer`, vérifie que l'essai routier est signé (ou que le RDV n'a pas d'essai associé si le type ne le nécessite pas).

---

### [C2] TVA VO régime non configurable dans `ConfigAtelier`
**Fichier** : `backend/src/Entity/ConfigAtelier.php`
**Symptôme** : Aucun champ `regimeTvaVoDefault` ou équivalent. Le régime TVA VO (marge Art.297A ou normale Art.256) est géré au niveau du dossier VOPurchase individuel, mais le défaut par atelier n'est pas paramétrable.
**Conséquence** : Risque de saisie incohérente par atelier, impossibilité d'appliquer un régime par défaut configurable.
**Action** : Ajouter champ `regimeTvaVoDefault` (enum : `marge` | `normale`, défaut `marge`) dans ConfigAtelier + migration.

---

### [C3] `VOLivrePolice` : mode de paiement et numéro chèque absents
**Fichier** : `backend/src/Entity/VOLivrePolice.php`
**Symptôme** : Aucun champ pour le mode de paiement (espèces, CB, chèque, virement) ni pour le numéro de chèque conditionnel.
**Conséquence** : Livre de Police non conforme — la réglementation (Art. 321-7 CP) impose la traçabilité du mode de paiement.
**Action** : Ajouter champs `modePaiement` (enum : `especes`|`cb`|`cheque`|`virement`), `numeroCheque` (nullable string), `nomBanque` (nullable string) + migration + affichage dans le template PDF LP.

---

### [C4] `NotificationEscalation` non liée à `DemandeTravauxSupp`
**Fichiers** : `backend/src/Entity/NotificationEscalation.php`, `backend/src/Message/` (vide pour escalade demandes)
**Symptôme** : `NotificationEscalation` est liée à `Notification` uniquement. Pas de lien vers `DemandeTravauxSupp`. La commande `ProcessNotificationEscalationsCommand` existe et tourne, mais aucune escalade n'est créée pour les demandes de travaux complémentaires.
**Conséquence** : Le workflow T+5/10/30min décrit dans les specs (et dans `DemandeTravauxSuppController.php` ligne 161) n'est pas fonctionnel end-to-end.
**Action** :
1. Créer `EscaladeDemandeTravauxSuppMessage` + handler
2. Dispatcher depuis `DemandeTravauxSuppController` après création
3. Ou utiliser `targetInfo` générique dans `NotificationEscalation` (vérifier si ça suffit)

---

### [C5] Clauses légales absentes avant signature Companion atelier
**Fichier** : `frontend/pages/public/companion/[token].vue`
**Symptôme** : Le Companion atelier affiche les étapes Photos → Checkup → Signature mais aucune clause légale ni case à cocher n'est présentée avant la signature. L'endpoint `/signature` accepte la signature sans vérification de consentements préalables.
**Conséquence** : Le client signe l'OR sans avoir explicitement accepté les CGV, la garantie légale, le traitement de ses données. RGPD et Code de la consommation non respectés.
**Action** :
1. Ajouter étape "Conditions" dans le stepper du Companion, avant la signature
2. Charger les clauses depuis l'entité `ClauseLegale` (codes à définir : `CGV_ATELIER`, `GARANTIE_LEGALE`, `RGPD_CONSENTEMENT`)
3. Backend : endpoint `/api/companion/{token}/signature` vérifie que `clausesAcceptees: ['CGV_ATELIER', ...]` est dans le payload

---

### [C6] Clauses légales absentes avant signature Companion VO (rachat et dépôt-vente)
**Fichier** : `frontend/pages/public/vo-companion/[token].vue`
**Symptôme** : Même problème que [C5] côté VO. Le parcours Seller → Vehicle → Documents → Signature ne contient pas d'étape clauses légales. De plus, les clauses ne sont pas distinguées entre contexte "rachat" (Art. 321-7 CP, LP) et "dépôt-vente" (Art. 1915 CC, durée mandat, commission).
**Conséquence** : Signature PV rachat et contrat dépôt-vente sans consentement explicite.
**Action** :
1. Ajouter étape "Clauses légales" dans le stepper VO, avant la signature
2. Distinguer les clauses selon `payload.partyRole` : `vendeur_rachat` vs `deposant` vs `acheteur`
3. Cases à cocher bloquantes (signature refusée si cases non cochées)

---

### [C7] Rapport d'intervention non consultable avant signature restitution
**Fichiers** : `frontend/pages/public/companion/[token].vue`, `backend/src/Controller/CompanionController.php`
**Symptôme** : Le Companion atelier ne gère que la réception (signature OR avant intervention). Il n'existe pas d'étape "restitution" dans ce Companion. Le client ne consulte pas le rapport d'intervention avant de signer à la restitution.
**Conséquence** : Signature "à l'aveugle" sans que le client ait lu les travaux réalisés — contestation possible.
**Action** : Créer parcours "restitution" dans le Companion atelier (ou nouveau token dédié restitution) :
  - Afficher rapport d'intervention (travaux réalisés, km, alertes, prochaine révision)
  - Puis zone de signature client

---

### [C8] `RelanceClientStockageCommand` est vide — aucun envoi de relances gardiennage
**Fichier** : `backend/src/Command/RelanceClientStockageCommand.php`
**Symptôme** : La commande affiche en console "Relance 1", "Relance 2", "Proposition gardiennage" mais ne dispatche aucun `Message` Messenger, n'appelle pas `NotificationDispatcher`, ne crée pas de log.
**Conséquence** : Les clients laissant leur moto en gardiennage ne sont jamais relancés aux seuils J+15/30/45/180. Le module gardiennage est entièrement inactif côté notifications.
**Action** :
1. Créer `SendGardiennageRappelMessage` (templateCode: `relance_gardiennage_j15|j30|j45|j180`)
2. Créer handler correspondant
3. Dans `RelanceClientStockageCommand`, dispatcher le bon message selon le seuil atteint
4. Ajouter `AuditService::log()` pour chaque relance envoyée

---

## 🟠 IMPORTANTS — À traiter dans les prochains sprints

### [I1] `CheckDaSivExpiryCommand` inexistante
**Symptôme** : Aucune commande ne passe automatiquement les `VOPurchase.sivStatus` à `expiree` passé J+15. Statut figé manuellement.
**Action** : Créer `CheckDaSivExpiryCommand` (cron daily) — pour chaque VOPurchase avec `sivStatus = en_cours` et `createdAt < NOW() - 15 days` → passer à `expiree` + `AuditService::log()` + notification in-app.

---

### [I2] `RappelProchaineRevisionCommand` non branchée sur `NotificationDispatcher`
**Fichier** : `backend/src/Command/RappelProchaineRevisionCommand.php`
**Symptôme** : Commande identifie les véhicules J+30 révision mais ne dispatche pas de notification (TODO non résolu).
**Action** : Intégrer `NotificationDispatcher::send()` avec template `RAPPEL_REVISION`.

---

### [I3] Store `rdv.ts` sans pagination
**Fichier** : `frontend/stores/rdv.ts`
**Symptôme** : `fetchRdvs()` ne passe aucun `page` ni `itemsPerPage` en paramètre. Retourne potentiellement toute la collection sans limite.
**Conséquence** : Dégradation performance en production avec volume de données réel.
**Action** : Ajouter `?page=1&itemsPerPage=50` (ou selon fenêtre de date) dans les params de requête.

---

### [I4] RDV publics non différenciés visuellement dans `planning.vue`
**Fichier** : `frontend/pages/planning.vue`
**Symptôme** : Les RDV créés via `/public/booking` (statut initial `en_attente`, source `web`) n'ont pas de marqueur visuel distinct (badge, couleur) dans la vue planning.
**Action** : Ajouter badge ou indicateur couleur "PUBLIC" si `rdv.source === 'web'` ou attribut équivalent.

---

### [I5] Verdict `VODocumentService` : usage bloquant non confirmé dans `VOController`
**Fichier** : `backend/src/Controller/VOController.php`
**Symptôme** : `buildPurchaseSaleVerdict()` est implémenté et retourne `vendable`/`non_vendable`, mais il n'est pas certain que ce verdict bloque explicitement la transition `mettre_en_vente` dans le controller.
**Action** : Vérifier que `transitionPurchase('mettre_en_vente')` appelle le verdict et refuse si `status !== 'vendable'`.

---

### [I6] `dureeDefautMandatJours` hardcodé 90 dans `VODepotVente`
**Fichier** : `backend/src/Entity/VODepotVente.php`
**Symptôme** : Valeur 90 jours en dur dans l'entité au lieu d'être lue depuis `ConfigAtelier.dureeDefautMandatJours`.
**Action** : Ajouter `dureeDefautMandatJours` dans `ConfigAtelier` (défaut 90) + service qui charge cette valeur à la création du mandat.

---

### [I7] Module `rdv_public` non vérifié dans `PublicBookingController`
**Fichier** : `backend/src/Controller/PublicBookingController.php`
**Symptôme** : La prise de RDV publique ne vérifie pas que `ConfigAtelier.featureModules` contient `rdv` ou `rdv_public`.
**Note** : `BookingAtelierAccessService` existe — vérifier s'il couvre ce cas ou s'il ne vérifie que les créneaux.

---

### [I8] Alerte J-7 mandat dépôt-vente non câblée
**Symptôme** : `VODepotVente.isMandatExpire()` et `getJoursRestantsMandat()` existent, mais aucune commande ni listener ne déclenche la notification J-7 avant expiration.
**Action** : Ajouter dans une commande cron (daily) : pour chaque VODepotVente `actif` avec `dateFinMandat = TODAY + 7j` → notification in-app + email gestionnaire VO.

---

### [I9] DA SIV alerte J+10 non câblée
**Symptôme** : L'alerte J+10 "DA SIV non encore enregistrée" est décrite dans la spec mais aucune logique ne la déclenche (ni commande, ni listener, ni scheduler).
**Action** : Ajouter dans `CheckDaSivExpiryCommand` (ou commande séparée) : pour chaque `VOPurchase` avec `sivStatus != 'enregistree'` et `createdAt < NOW() - 10 days` → notification in-app + email.

---

## ✅ CE QUI FONCTIONNE CORRECTEMENT

| Composant | Détail |
|-----------|--------|
| **Workflow RDV** | 15 places + 20 transitions bien définies dans workflow.yaml |
| **OrdreReparation** | Snapshots RGPD + signature + gel + rectificatif sécurisé |
| **EssaiRoutier** | Tous les champs (km, points contrôle, anomalies, signature mécanicien) |
| **RapportIntervention** | Lié à l'essai routier, 2 signatures distinctes, snapshot |
| **OrdreReparationPolicy** | Rectification sécurisée, hash intégrité SHA256, rôles limités |
| **VOPurchase.vehicule** | ManyToOne correct (pas OneToOne — conforme spec) |
| **VODepotVente** | Commission correcte (forfait/pourcentage), mandat expiré tracé |
| **ConfigAtelier** | Tarifs MO, marges pièces, TVA, gardiennage complets |
| **VODocumentService** | Verdict vendable/non_vendable avec DA SIV = critical |
| **VOLivrePolice** | Immuabilité API — GET only (pas de PUT/PATCH/DELETE) |
| **GardiennageService** | Calcul `bcmul(joursOuvres, tarifJournalier, 2)` depuis ConfigAtelier |
| **Messenger routing** | SendRappelMessage, GeneratePdfMessage, ProcessScheduledRappels câblés |
| **ProcessNotificationEscalationsCommand** | Escalade T+5/10/30min implémentée (mais pas branchée demandes travaux) |
| **PurgeIdentityDocumentsCommand** | RGPD 0-day rétention pièces identité opérationnel |
| **RgpdPurgeCommand** | Anonymisation clients 3+ ans, effacement IPs audit |
| **CheckNoShowCommand** | Auto-transition RDV > 30min → no_show |
| **Token companion** | Segment de chemin URL (jamais query string) ✓ |
| **Rate limiting** | Sur tous les endpoints publics (Companion, booking, VO) |
| **Upload pièce d'identité** | Bloqué côté API + côté VO Companion (RETENTION_YEARS=0) |
| **Zones tap ≥ 44px** | Implémentées dans mecanicien.vue et Companion |
| **Photo capture mobile** | `capture="environment"` dans Companion et mecanicien.vue |
| **calcul tarif mécanicien** | Calculé côté serveur — le mécanicien ne saisit jamais de prix |

---

## LISTE ACTIONS PRIORISÉES

### Sprint immédiat (bloquants légaux)
1. **[C5]** Clauses légales + cases bloquantes dans Companion atelier avant signature
2. **[C6]** Clauses légales + cases bloquantes dans Companion VO (rachat vs dépôt)
3. **[C3]** Champs mode_paiement + numero_cheque dans VOLivrePolice + migration

### Sprint court (conformité workflow)
4. **[C1]** Guard transition `terminer` — essai routier signé obligatoire
5. **[C2]** ConfigAtelier : ajouter `regimeTvaVoDefault`
6. **[C7]** Companion restitution — rapport visible avant signature client
7. **[C8]** RelanceClientStockageCommand — dispatcher des messages réels

### Sprint moyen (complétude fonctionnelle)
8. **[C4]** Escalade demandes travaux supp — Message + handler + dispatcher
9. **[I1]** CheckDaSivExpiryCommand + alerte J+10 [I9]
10. **[I3]** Pagination store rdv.ts
11. **[I4]** Badge "PUBLIC" pour RDV prise en ligne
12. **[I5]** Confirmer/corriger guard VOController → `mettre_en_vente`
13. **[I8]** Alerte J-7 mandat dépôt-vente

### Backlog (améliorations)
- **[I2]** RappelProchaineRevisionCommand → NotificationDispatcher
- **[I6]** dureeDefautMandatJours configurable dans ConfigAtelier
- **[I7]** Check module rdv_public dans PublicBookingController

---

---

# AUDIT VAGUE 2 — Entités restantes / Services / Controllers / Frontend VO+Admin / Composables / PDF
Date : 2026-04-22

---

## 🔴 NOUVEAUX CRITIQUES

### [C9] VIN sans contrainte UNIQUE sur `Vehicule`
**Fichier** : `backend/src/Entity/Vehicule.php`
**Symptôme** : Aucun `#[ORM\UniqueConstraint]` ni `unique=true` sur le champ VIN. Doublons possibles.
**Conséquence** : Plusieurs dossiers VO/RDV sur le même VIN → incohérence Livre de Police, DA SIV dupliquée.
**Action** : Migration + `unique=true` sur colonne VIN + contrainte composite `(vin, atelier_id)` si multi-tenant.

---

### [C10] Facture et VOFacture : pas de protection DELETE côté API
**Fichiers** : `backend/src/Entity/Facture.php`, `backend/src/Entity/VOFacture.php`
**Symptôme** : Pas de voter ni de state processor API Platform bloquant le DELETE. Si un endpoint DELETE est exposé (même implicitement par API Platform), une facture émise peut être supprimée.
**Conséquence** : Violation Art. L.123-22 Code commerce (conservation 10 ans).
**Action** : Ajouter `security: "object.isEmise() ? false : true"` ou un voter `FACTUREDeleteVoter` qui bloque systématiquement.

---

### [C11] `Devis.dateValidite` hardcodé +30j, non lié à `ConfigAtelier`
**Fichier** : `backend/src/Entity/Devis.php`
**Symptôme** : Constructeur pose `dateValidite = now + 30j` en dur. `ConfigAtelier.devisValiditeJours` (ou équivalent) n'est pas injecté.
**Conséquence** : Si l'atelier configure une validité différente (15j, 60j), le devis ignore ce paramètre.
**Action** : Sortir la valeur par défaut du constructeur, la poser au niveau du controller/service depuis `ConfigAtelier`.

---

### [C12] `VODocument` : upload types 0-day pas bloqué côté API Platform
**Fichier** : `backend/src/Entity/VODocument.php` + processor API Platform
**Symptôme** : `RETENTION_YEARS` à 0 est défini, mais aucun state processor ni validation Symfony n'empêche physiquement d'uploader un `TYPE_PIECE_IDENTITE` ou `TYPE_JUSTIFICATIF_DOMICILE`.
**Note** : Le Companion VO bloque côté front, mais le controller ne semble pas vérifier avec une exception systématique côté back.
**Action** : Ajouter dans `VODocumentStateProcessor` (ou controller) : `if (in_array($type, [VODocument::TYPE_PIECE_IDENTITE, VODocument::TYPE_JUSTIFICATIF_DOMICILE])) throw new UnprocessableEntityHttpException(...)`.

---

### [C13] `VOLivrePoliceService::recordSale()` : pas de garde "déjà vendu"
**Fichier** : `backend/src/Service/VOLivrePoliceService.php`
**Symptôme** : `recordSale()` peut être appelé deux fois sur la même entrée LP sans vérification préalable.
**Conséquence** : LP potentiellement corrompue (deux dates de vente, deux acheteurs).
**Action** : Ajouter `if ($entry->getDateVente() !== null) throw new \LogicException('LP entry already sold')` en début de méthode.

---

### [C14] `UserMecanicienSyncService` non appelé dans `AdminUserProvisioningController`
**Fichier** : `backend/src/Controller/AdminUserProvisioningController.php`
**Symptôme** : L'approbation d'un utilisateur avec `ROLE_MECANICIEN` pose le `roleMetier` mais n'appelle pas `UserMecanicienSyncService`. Résultat : `Mecanicien.userId` non créé → le mécanicien n'apparaît pas dans la liste mécaniciens.
**Action** : Après `approve()`, appeler `UserMecanicienSyncService::syncFromUser($user)`.

---

### [C15] `ordres/[id].vue` : section Essai Routier absente
**Fichier** : `frontend/pages/ordres/[id].vue`
**Symptôme** : La page OR ne contient pas d'affichage du km_debut, km_fin, points de contrôle, anomalies, signature mécanicien de l'essai routier.
**Conséquence** : Le réceptionniste ne peut pas vérifier que l'essai a été fait avant de restituer.
**Action** : Ajouter une section "Essai routier" dans la timeline OR — lecture seule, statut (signé / non signé).

---

### [C16] `ordres/[id].vue` : section Rapport d'intervention absente
**Fichier** : `frontend/pages/ordres/[id].vue`
**Symptôme** : Pas d'affichage du rapport (travaux réalisés, km restitution, alertes, prochaine révision) dans la page OR.
**Action** : Ajouter section "Rapport d'intervention" avec lien PDF + statut (signé mécanicien / signé client).

---

### [C17] `workshop.vue` : drag & drop absent
**Fichier** : `frontend/pages/workshop.vue`
**Symptôme** : L'affectation des mécaniciens aux ponts se fait uniquement via `<select>` + bouton Enregistrer. Pas de drag & drop pour réaffecter rapidement les RDV.
**Note** : Cette fonctionnalité est critique pour le responsable d'atelier en production. Un `<select>` peut suffire pour une V1 mais il manque la confirmation visuelle de sauvegarde.
**Action** : Court terme — ajouter toast de confirmation après sauvegarde affectation. Long terme — drag & drop.

---

### [C18] `clients/index.vue` : droit d'effacement RGPD absent
**Fichier** : `frontend/pages/clients/index.vue`
**Symptôme** : L'endpoint `POST /api/clients/{id}/anonymize` existe côté back, mais la page liste des clients n'expose aucun bouton "Anonymiser" / workflow RGPD Art.17.
**Conséquence** : L'exercice du droit à l'effacement est techniquement possible mais inaccessible depuis l'UI.
**Action** : Ajouter bouton "Anonymiser" (conditionné : pas d'OR/facture active liée) dans la fiche client ou la liste.

---

### [C19] `voCompanionDraftSync.ts` : expiration token non vérifiée
**Fichier** : `frontend/composables/voCompanionDraftSync.ts`
**Symptôme** : Le composable synce les champs du formulaire Companion VO sans vérifier si le token a expiré. Les données sont postées vers un token mort → silencieusement rejetées côté back.
**Action** : Ajouter `if (token.expiresAt && new Date() > new Date(token.expiresAt)) { /* afficher erreur expiration */ return }` avant tout POST.

---

### [C20] `vo_facture.html.twig` : pas de garde exclusivité TVA marge / TVA normale
**Fichier** : `backend/templates/pdf/vo_facture.html.twig`
**Symptôme** : Le template affiche `mentionTvaMarge` mais ne bloque pas si les deux régimes coexistent (data incorrecte en base).
**Conséquence** : Facture VO fiscalement invalide si mixte Art.297A + Art.256 CGI.
**Action** : Le back-end (`VOFactureService` ou processor) doit valider avant génération. Le template peut ajouter `{% if facture.regimeTva == 'marge' %} ... {% elseif facture.regimeTva == 'normale' %} ... {% else %}{{ throw }} {% endif %}`.

---

### [C21] `PhotoController::serve()` : path traversal potentiel
**Fichier** : `backend/src/Controller/PhotoController.php`
**Symptôme** : `basename($filename)` protège partiellement, mais si les fichiers sont servis depuis un répertoire accessible web, un nom de fichier malformé pourrait traverser.
**Action** : Vérifier que `/var/photos/` est bien hors du webroot `public/`. Si servi via controller, ajouter `realpath()` + `str_starts_with($realPath, $uploadDir)` avant envoi.

---

### [C22] `PhotoController::upload()` : contenu fichier non vérifié
**Fichier** : `backend/src/Controller/PhotoController.php`
**Symptôme** : Le type de fichier est validé sur la string du type MIME, mais pas sur le contenu réel du fichier (magic bytes). Un attaquant peut uploader un script PHP renommé en `.jpg`.
**Action** : Utiliser `finfo_file()` ou `getimagesize()` pour valider le contenu réel + désactiver l'exécution PHP dans le répertoire d'upload.

---

### [C23] `AuthController` : OAuth redirect_uri non validé
**Fichier** : `backend/src/Controller/AuthController.php`
**Symptôme** : `getGoogleConfig()` lit depuis l'env mais `redirect_uri` dans le flow OAuth n'est pas comparé à une whitelist. Risque d'open redirect.
**Action** : Valider que `redirect_uri` correspond exactement à la valeur en `APP_URL` env — jamais valeur dynamique.

---

## 🟠 NOUVEAUX IMPORTANTS

### [I10] `SlotService` : jours de fermeture non vérifiés
**Fichier** : `backend/src/Service/SlotService.php`
**Symptôme** : Le service filtre les absences mécaniciens mais ne consulte pas `ConfigAtelier.joursNonOuvres` ni `ConfigAtelier.datesExceptionnelles`. Des créneaux sont proposés les jours de fermeture.
**Action** : Intégrer la vérification des jours fermés dans `generateSlots()`.

---

### [I11] `RendezVous.kilometrage` writable à la création
**Fichier** : `backend/src/Entity/RendezVous.php`
**Symptôme** : Champ nullable et writable — rien n'empêche de saisir le km à la prise de RDV. Règle métier : km saisi uniquement à la réception physique.
**Action** : Ajouter voter ou assertion : `setKilometrage()` n'est autorisé que si `statut IN ['reception', 'en_cours', ...]`.

---

### [I12] `FacturationController::list()` sans pagination
**Fichier** : `backend/src/Controller/FacturationController.php`
**Symptôme** : `getQuery()->getResult()` charge toutes les factures d'un atelier en mémoire. Risque OOM sur volume élevé.
**Action** : Ajouter `setMaxResults(50)` + paramètre page, ou déléguer à API Platform avec `@ApiResource` paginated.

---

### [I13] `DevisController::convertir()` : taux horaire hardcodé
**Fichier** : `backend/src/Controller/DevisController.php`
**Symptôme** : Calcul du temps estimé avec `/65` (taux horaire 65€ en dur) au lieu de `ConfigAtelier.tauxHoraireMoStandard`.
**Action** : Remplacer par `$configAtelier->getTauxHoraireMoStandard()`.

---

### [I14] PDF générés de façon synchrone (Devis, Rapport, FacturationController)
**Fichiers** : `DevisController.php`, `RapportInterventionController.php`, `FacturationController.php`
**Symptôme** : `PdfService::generate*()` appelé en synchrone dans la requête HTTP. Si DomPDF est lent (PDF complexe), timeout possible.
**Action** : Dispatcher `GeneratePdfMessage` async pour les gros documents. Réponse immédiate `202 Accepted` + polling ou notification Mercure quand prêt.

---

### [I15] `StatistiquesController` : stock alerts sur module désactivé
**Fichier** : `backend/src/Controller/StatistiquesController.php`
**Symptôme** : Le dashboard calcule `stockAlerts` même si le module `stock` est désactivé dans `ConfigAtelier.featureModules`.
**Action** : Encapsuler le calcul dans `if ($this->isModuleEnabled('stock', $atelier))`.

---

### [I16] `SuiviController` : `tokenSuivi` stocké en clair, potentiellement prévisible
**Fichier** : `backend/src/Controller/SuiviController.php`
**Symptôme** : Token stocké en plaintext, expiry hardcodée 30j. Pas de mention de générateur cryptographique vérifié.
**Action** : Vérifier que `tokenSuivi` est généré via `bin2hex(random_bytes(32))`. Envisager de stocker un hash bcrypt.

---

### [I17] `MecanicienController` : transition essai routier via `setStatut()` direct
**Fichier** : `backend/src/Controller/MecanicienController.php`
**Symptôme** : L'essai routier change de statut via `$essai->setStatut()` directement, pas via une state machine.
**Action** : Soit créer un micro-workflow pour `EssaiRoutier`, soit documenter explicitement pourquoi c'est acceptable ici (entité simple sans workflow Symfony).

---

### [I18] `AdminAtelierController` : atelier activable sans SIRET/TVA intra
**Fichier** : `backend/src/Controller/AdminAtelierController.php`
**Symptôme** : `setActif(true)` possible sans que `siret` et `tvaIntracom` soient renseignés. Or ces champs sont obligatoires sur tout PDF généré (factures, OR, LP…).
**Action** : Ajouter validation : si `actif = true` et `siret` null ou `tvaIntracom` null → erreur 422 avec message explicite.

---

### [I19] `useAuth.ts` : permissions rechargées une seule fois au login
**Fichier** : `frontend/composables/useAuth.ts`
**Symptôme** : Les rôles/permissions sont chargés depuis le serveur au login et gardés en store session. Un admin qui modifie les permissions d'un utilisateur en ligne ne voit pas le changement avant son prochain logout/login.
**Action** : Ajouter appel `/auth/me` dans un middleware Nuxt (ex: toutes les 5 min ou à chaque navigation vers une route protégée).

---

### [I20] `admin/config.vue` : `regimeTvaVoDefault` absent de l'UI
**Fichier** : `frontend/pages/admin/config.vue`
**Symptôme** : La configuration du régime TVA VO par défaut (marge Art.297A vs normale Art.256) n'est pas exposée dans l'interface admin. Le gestionnaire VO ne peut pas voir/modifier le défaut de son atelier.
**Action** : Ajouter champ `<USelect>` dans la section VO de config.vue (en attente que ConfigAtelier.regimeTvaVoDefault soit créé côté back — voir C2).

---

### [I21] VIN non validé (Luhn + caractères I/O/Q interdits)
**Fichiers** : `frontend/composables/useCarteGriseOcr.ts`, `frontend/composables/voVehicleForm.ts`
**Symptôme** : `normalizeVin()` tronque à 17 chars mais n'exclut pas les caractères I, O, Q (interdits par la norme ISO 3779 en positions 1-10) et ne calcule pas le checksum.
**Action** : Ajouter `function isValidVin(vin: string): boolean` avec exclusion I/O/Q + validation longueur 17 exactement. Message d'erreur visible si VIN invalide.

---

### [I22] Mentions légales incomplètes dans `facture.html.twig` et `devis.html.twig`
**Fichiers** : `backend/templates/pdf/facture.html.twig`, `backend/templates/pdf/devis.html.twig`
**Symptôme** :
- Facture : pas de mention explicite "Facture conforme Art. 289 CGI", numéro TVA intracom atelier non affiché.
- Devis : pas de mention "Art. R.112-1 Code consommation" (devis obligatoire > 150€ HT).
**Action** :
- Facture : ajouter `{{ atelier.tvaIntracom }}` + note pied de page réglementaire.
- Devis : ajouter mention conditionnelle `{% if devis.totalHt > 150 %}` avec texte Art. R.112-1.

---

### [I23] `ordre_reparation.html.twig` : garantie travaux absente
**Fichier** : `backend/templates/pdf/ordre_reparation.html.twig`
**Symptôme** : La durée de garantie travaux (`ConfigAtelier.garantieTravauxJours`, défaut 30j) n'est pas affichée dans l'OR. Le client ne sait pas quelle garantie s'applique.
**Action** : Ajouter section pied de page "Garantie travaux : {{ configAtelier.garantieTravauxJours }} jours à compter de la restitution".

---

### [I24] `depots/new.vue` : champ durée mandat absent
**Fichier** : `frontend/pages/vo/depots/new.vue`
**Symptôme** : Le wizard de création d'un mandat dépôt-vente ne propose pas de saisir la durée (90j, 180j, custom). La durée par défaut est hardcodée à 90j.
**Action** : Ajouter champ durée mandat (select ou input number) dans l'étape "Mandat" du wizard.

---

### [I25] `RendezVousController::mecanicienRdvs()` pas restreint au mécanicien connecté
**Fichier** : `backend/src/Controller/RendezVousController.php`
**Symptôme** : L'endpoint `GET /api/rendez-vous/mecanicien` filtre par date mais pas explicitement par `mecanicienId = utilisateur connecté`. Un mécanicien pourrait lire le planning d'un autre en passant un paramètre différent.
**Action** : Forcer `mecanicienId` depuis le JWT/session, jamais depuis un paramètre de requête non validé.

---

### [I26] `devis/[id].vue` : conversion en OR non automatique
**Fichier** : `frontend/pages/devis/[id].vue`
**Symptôme** : L'action "Accepter" passe le devis en statut `accepte` mais ne crée pas automatiquement un OR. L'utilisateur doit aller créer l'OR manuellement.
**Action** : Soit appeler `DevisController::convertir()` automatiquement après acceptation, soit afficher un bouton "Convertir en OR" visible immédiatement après.

---

### [I27] `FEC export absent` dans `FacturationController`
**Fichier** : `backend/src/Controller/FacturationController.php`
**Symptôme** : Pas de route `GET /api/facturation/fec` pour l'export FEC (format obligatoire Art. L.47 A-I LPF pour contrôle fiscal).
**Action** : Créer `exportFec()` qui génère le fichier CSV au format FEC standard.

---

### [I28] `rapport_intervention.html.twig` : signature client non matérialisée
**Fichier** : `backend/templates/pdf/rapport_intervention.html.twig`
**Symptôme** : La signature mécanicien est affichée, mais la signature client (à la restitution) n'est pas visible dans le template. Or le rapport est signé par les deux parties.
**Action** : Clarifier le flux restitution (Companion restitution à créer — voir C7) puis ajouter `{{ rapport.signatureClient }}` dans le template PDF.

---

## ✅ NOUVEAU — CE QUI FONCTIONNE CORRECTEMENT (vague 2)

| Composant | Détail |
|-----------|--------|
| **Snapshots RGPD** | Facture, VOFacture, Devis, OrdreReparation — tous figés à émission |
| **VODocument RETENTION_YEARS** | Constantes définies : 0j pièce ID/justif, 5 ans CERFA, 10 ans factures |
| **VONumberingService** | Séquences PostgreSQL UPSERT (pas MAX+1), LP + factures VO |
| **VOMarginService** | bcmath partout, TVA marge vs normale distincts |
| **CerfaOverlayService** | Cerfa 13751 + 13757 + 15776 remplis automatiquement |
| **AuditService** | log(action, entityType, entityId, details, user, atelier, IP) |
| **PricingService** | 3 taux MO (standard/complexe/expert) + forfait min + bcmath |
| **useApi.ts** | JWT cookie, refresh 401 + retry, logging tronqué (sécurisé) |
| **useNotifications.ts** | Mercure + fallback polling 15s + reconnexion automatique |
| **useQrCode.ts** | Génération locale (pas de service externe) |
| **auth store** | `persist: false` — pas de localStorage, permissions depuis serveur |
| **atelier store** | featureModules normalisés, branding dynamique |
| **billing store** | `createAvoir()` uniquement, pas de deleteFacture |
| **vo_pv_rachat.html.twig** | Identité vendeur, VIN, immat, km, non-gage, 2 signatures |
| **vo_contrat_depot_vente.html.twig** | Durée mandat, commission, clause reversement 15j, Art.1915 CC |
| **vo_livre_police.html.twig** | Art.321-7 CP, numérotation séquentielle, mention immuabilité |
| **ClientController** | Anonymisation RGPD Art.17 + export portabilité Art.20 |
| **FacturationController** | Numérotation séquences, avoir crée au lieu de supprimer, encaissement Paiement |
| **RapportInterventionController** | Signatures séparées mécanicien/client, gel après signature |
| **SuiviController** | Rate limiting, données minimales exposées, token 30j |
| **VORemiseEnEtatController** | Path traversal protection (realpath + str_starts_with) |
| **RendezVousController** | Essai routier + rapport vérifié avant `terminer`, workflow pour toutes transitions |

---

## LISTE COMPLÈTE ACTIONS PRIORISÉES (vagues 1 + 2)

### 🔴 Sprint 0 — Bloquants sécurité / légaux / prod immédiats
1. **[C21]** PhotoController : path traversal → vérifier que /var/photos hors webroot + realpath check
2. **[C22]** PhotoController : contenu fichier non vérifié → `getimagesize()` ou `finfo_file()`
3. **[C23]** AuthController : OAuth redirect_uri → whitelist obligatoire
4. **[C10]** Facture/VOFacture : bloquer DELETE API Platform
5. **[C20]** vo_facture.html.twig : valider exclusivité TVA marge/normale avant génération PDF
6. **[C19]** voCompanionDraftSync.ts : vérifier expiration token avant POST

### 🔴 Sprint 1 — Conformité workflow + RGPD
7. **[C5]** Clauses légales Companion atelier avant signature
8. **[C6]** Clauses légales Companion VO (rachat vs dépôt)
9. **[C1]** Guard transition `terminer` — essai routier signé obligatoire
10. **[C3]** VOLivrePolice : champs mode_paiement + numero_cheque
11. **[C9]** VIN UNIQUE constraint sur Vehicule + migration
12. **[C12]** VODocument upload types 0-day bloqué côté API
13. **[C13]** VOLivrePoliceService::recordSale() → guard "déjà vendu"
14. **[C14]** UserMecanicienSyncService appelé dans AdminUserProvisioningController
15. **[C18]** clients/index.vue → bouton Anonymiser RGPD
16. **[C15]** ordres/[id].vue → section Essai routier
17. **[C16]** ordres/[id].vue → section Rapport d'intervention

### 🔴 Sprint 2 — Complétion workflow
18. **[C2]** ConfigAtelier : ajouter regimeTvaVoDefault
19. **[C7]** Companion restitution — rapport visible + signature client
20. **[C8]** RelanceClientStockageCommand — dispatcher messages gardiennage
21. **[C4]** Escalade demandes travaux supp — Message + handler
22. **[C11]** Devis.dateValidite lié à ConfigAtelier

### 🟠 Sprint 3 — Corrections importantes
23. **[I10]** SlotService : jours fermeture ConfigAtelier
24. **[I11]** RendezVous.km : writable seulement à la réception
25. **[I12]** FacturationController::list() : pagination
26. **[I13]** DevisController::convertir() : taux horaire depuis ConfigAtelier
27. **[I17]** MecanicienController : transition essai routier (setStatut → workflow ou doc)
28. **[I18]** AdminAtelierController : bloquer activation sans SIRET/TVA
29. **[I21]** VIN validation (I/O/Q + longueur 17)
30. **[I22]** Mentions légales facture (Art.289 CGI) + devis (Art.R.112-1)
31. **[I23]** OR PDF : garantie travaux configurable affichée
32. **[I24]** depots/new.vue : champ durée mandat
33. **[I25]** RendezVousController::mecanicienRdvs() : forcer mecanicienId JWT
34. **[I26]** devis/[id].vue : conversion OR automatique post-acceptation
35. **[I28]** rapport_intervention.html.twig : signature client
36. **[I1]** CheckDaSivExpiryCommand + alerte J+10
37. **[I9]** DA SIV alerte J+10

### 🟠 Sprint 4 — Expérience complète
38. **[I14]** PDF génération async (worker)
39. **[I15]** StatistiquesController : module stock désactivé
40. **[I19]** useAuth : refresh permissions (middleware /auth/me)
41. **[I20]** admin/config.vue : regimeTvaVoDefault UI
42. **[I27]** FEC export FacturationController
43. **[I3]** Pagination store rdv.ts + vo.ts
44. **[I4]** Badge "PUBLIC" RDV planning
45. **[I8]** Alerte J-7 mandat dépôt-vente
46. **[C17]** workshop.vue : toast confirmation affectation (drag & drop backlog)

### 🔵 Backlog confort
- **[I2]** RappelProchaineRevisionCommand → NotificationDispatcher
- **[I5]** Confirmer guard VOController → `mettre_en_vente`
- **[I6]** dureeDefautMandatJours configurable dans ConfigAtelier
- **[I7]** Check module rdv_public dans PublicBookingController
- **[I16]** tokenSuivi : vérifier générateur crypto + hash en DB
- Client.email : index UNIQUE
- AuditService : logger userRole pour traçabilité SUPER_ADMIN
- useNotifications.ts : timeout explicite EventSource
