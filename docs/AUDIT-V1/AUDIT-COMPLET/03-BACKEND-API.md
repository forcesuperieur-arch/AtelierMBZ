Parfait ! J'ai maintenant suffisamment de contexte pour générer le rapport exhaustif. Laissez-moi compiler tout cela :

# RAPPORT EXHAUSTIF — BACKEND SYMFONY ATELIERMBZ

## 📋 RÉSUMÉ EXÉCUTIF

**Projet** : AtelierMBZ v4 (Gestion d'atelier moto franchisé)  
**Framework** : Symfony 7.2 + API Platform 4.1  
**BD** : PostgreSQL 15, Doctrine ORM  
**Async** : Symfony Messenger  
**Notifications** : Email + SMS + Mercure (temps réel)

---

## 🎛️ CONTROLLERS (43 au total)

### HealthController
**But** : Endpoint de santé publique (health check).
| Méthode | Chemin | Permission | But | Codes |
|---------|--------|-----------|-----|-------|
| GET | `/api/health` | Public | Retour `{status: "ok", time: ISO8601}` | 200 |

---

### AuthController
**But** : Authentification, gestion des tokens JWT, login/logout, refresh, switch atelier, intégration Google OAuth.

| Méthode | Chemin | Permission | But | Codes |
|---------|--------|-----------|-----|-------|
| GET | `/api/auth/google/dev-simulate` | Public (DEV) | Simule une authentification Google en mode dev | 200, 400 |
| GET | `/api/auth/google/url` | Public | Retourne l'URL d'authentification Google | 200 |
| POST | `/api/auth/google/exchange` | Public | Échange le code OAuth contre un JWT | 200, 401 |
| POST | `/api/auth/login` | Public | Login classique (username/password) | 200, 401 |
| GET | `/api/auth/me` | `ROLE_USER` | Retourne l'utilisateur courant + permissions | 200 |
| GET | `/api/auth/rdv-ateliers` | `ROLE_USER` | Liste des ateliers accessibles pour booking | 200 |
| POST | `/api/auth/switch-atelier` | `ROLE_USER` | Change l'atelier courant (génère nouveau JWT) | 200, 400 |
| POST | `/api/auth/refresh` | Public | Refresh le JWT depuis le refresh token | 200, 401 |
| POST | `/api/auth/logout` | `ROLE_USER` | Révoque le JWT courant | 200 |

**Services injectés** : `EntityManager`, `JWTTokenManager`, `UserPasswordHasher`, `HttpClient`, `Mailer`, `UserRoleMapper`, `BookingAtelierAccessService`

---

### RendezVousController
**But** : Gestion complète du workflow des RDV (CRUD, transitions, validations métier).

| Méthode | Chemin | Permission | But | Codes |
|---------|--------|-----------|-----|-------|
| POST | `/api/rendez-vous` | `ROLE_USER` | Crée un RDV + client/véhicule auto-créés | 201, 400 |
| POST | `/api/rendez-vous/{id}/transition/{transition}` | `ROLE_USER` | Applique une transition workflow (confirmer, reception, terminer, etc.) | 200, 409, 400 |

**Validations métier** :
- Essai routier obligatoire avant `terminer`
- Demandes de travaux complémentaires en attente bloquent la clôture
- Notes mécanicien requises si anomalies à l'essai

---

### OrdreReparationController
**But** : Gestion des ordres de réparation (OR) signés, rectification, vérification intégrité.

| Méthode | Chemin | Permission | But | Codes |
|---------|--------|-----------|-----|-------|
| POST | `/api/or/{id}/rectifier` | `ROLE_ADMIN` | Crée un nouvel OR rectificatif (copie l'original signée) | 201, 403 |
| GET | `/api/or/{id}/verify-integrity` | `ROLE_USER` | Vérifie que l'OR n'a pas été altéré après signature | 200 |

---

### MecanicienController
**But** : Espace de travail du mécanicien (tableau, rapports, essais, notes).

| Méthode | Chemin | Permission | But | Codes |
|---------|--------|-----------|-----|-------|
| GET | `/api/mecanicien/me` | `ROLE_USER` | Infos du mécanicien courant + statistiques | 200 |
| GET | `/api/mecanicien/me/rdvs` | `ROLE_USER` | Liste RDV du jour du mécanicien (filtrés `userId`) | 200 |
| PATCH | `/api/mecanicien/me/rapport/{orId}` | `ROLE_USER` | Sauvegarde brouillon du rapport d'intervention | 200, 400 |
| POST | `/api/mecanicien/me/essai-routier` | `ROLE_USER` | Crée un essai routier (km début/fin, points contrôle, anomalies) | 201, 400 |

---

### RapportInterventionController
**But** : Gestion des rapports d'intervention (création, validation, signature mécanicien/client, signatures, PDF).

| Méthode | Chemin | Permission | But | Codes |
|---------|--------|-----------|-----|-------|
| GET | `/api/rdv/{rdvId}/rapport` | `ROLE_USER` | Récupère ou crée le brouillon du rapport | 200 |
| PUT | `/api/rapport/{id}` | `ROLE_USER` | Update rapport (avant signature) | 200, 400 |
| POST | `/api/rapport/{id}/essai` | `ROLE_USER` | Crée/update essai routier attaché au rapport | 201, 200 |
| POST | `/api/rapport/{id}/sign-mecanicien` | `ROLE_USER` | Signe électroniquement par le mécanicien | 200, 400 |
| POST | `/api/rapport/{id}/sign-client` | Public (token) | Signe électroniquement par le client | 200, 403 |
| GET | `/api/rapport/{id}/pdf` | `ROLE_USER` | Génère et retourne le PDF | 200 |
| POST | `/api/rapport/{id}/send-email` | `ROLE_USER` | Envoie le rapport par email au client | 200 |
| POST | `/api/rapport/{id}/rectifier` | `ROLE_ADMIN` | Crée un rapport rectificatif (immuabilité post-sig) | 201 |

---

### PhotoController
**But** : Upload, stockage, listage des photos d'intervention.

| Méthode | Chemin | Permission | But | Codes |
|---------|--------|-----------|-----|-------|
| POST | `/api/photos/upload` | `ROLE_USER` | Upload d'une photo (type: en_cours, apres_travaux, restitution, probleme) | 201, 413, 400 |
| GET | `/api/photos/file/{filename}` | `ROLE_USER` | Sert une photo stockée | 200, 404 |
| GET | `/api/photos/rdv/{rdvId}` | `ROLE_USER` | Liste photos d'un RDV | 200 |

---

### PublicPhotoController
**But** : Accès public aux photos via token Companion (sans auth JWT).

| Méthode | Chemin | Permission | But | Codes |
|---------|--------|-----------|-----|-------|
| GET | `/api/public/photos/{token}/{filename}` | Public (token) | Sert une photo publique attachée au RDV | 200, 404 |

---

### RdvPrestationCatalogController
**But** : Retourne le catalogue des prestations applicables à un véhicule.

| Méthode | Chemin | Permission | But | Codes |
|---------|--------|-----------|-----|-------|
| GET | `/api/rdv/prestations-catalogue` | `ROLE_USER` | Liste prestations filtrées par catégorie véhicule et tarifs | 200 |

---

### SlotController
**But** : Calcule les créneaux disponibles (ponts × horaires × mécaniciens).

| Méthode | Chemin | Permission | But | Codes |
|---------|--------|-----------|-----|-------|
| GET | `/api/slots` | `ROLE_USER` | Creneaux dispos (query: date, duree_estimee, atelierId) | 200 |
| GET | `/api/creneaux/disponibles` | `ROLE_USER` | Alias legacy pour compatibilité frontend | 200 |

---

### DevisController
**But** : Gestion des devis (création, envoi, acceptation, refus, conversion en OR, PDF).

| Méthode | Chemin | Permission | But | Codes |
|---------|--------|-----------|-----|-------|
| POST | `/api/devis/{id}/envoyer` | `ROLE_USER` | Envoie le devis au client (génère token de signature) | 200 |
| POST | `/api/devis/{id}/email` | `ROLE_USER` | Envoie le PDF par email | 200 |
| POST | `/api/devis/{id}/accepter` | `ROLE_USER` | Client accepte → crée OR | 201, 400 |
| POST | `/api/devis/{id}/refuser` | `ROLE_USER` | Client refuse → statut refusé | 200 |
| POST | `/api/devis/{id}/convertir` | `ROLE_USER` | Force conversion en OR (internal) | 201 |
| GET | `/api/devis/{id}/pdf` | `ROLE_USER` | Télécharge le PDF | 200 |

---

### PontStatusController
**But** : État en temps réel des ponts (occupé, libre, maintenance).

| Méthode | Chemin | Permission | But | Codes |
|---------|--------|-----------|-----|-------|
| GET | `/api/ponts/status` | `ROLE_USER` | JSON statuts ponts + équipements | 200 |

---

### GardiennageController
**But** : Gestion du gardiennage (RDV non récupérés, relances, tarifs, commandes pièces).

| Méthode | Chemin | Permission | But | Codes |
|---------|--------|-----------|-----|-------|
| POST | `/api/rdv/{id}/declencher-gardiennage` | `ROLE_USER` | Déclenche le gardiennage (crée log, notifications) | 200, 400 |
| GET | `/api/rdv/{id}/gardiennage-montant` | `ROLE_USER` | Calcule le montant gardiennage (jours × tarif config) | 200 |
| GET | `/api/rdv/{id}/commandes-pieces` | `ROLE_USER` | Liste pièces commandées en gardiennage | 200 |
| POST | `/api/rdv/{id}/commandes-pieces` | `ROLE_USER` | Crée une commande de pièces (gardiennage) | 201 |
| PUT | `/api/commandes-pieces/{id}` | `ROLE_USER` | Update commande | 200 |
| POST | `/api/commandes-pieces/{id}/recue` | `ROLE_USER` | Marque la pièce reçue | 200 |

---

### MotosLookupController
**But** : Recherche et autocomplete motos (marque, modèle, specs techniques).

| Méthode | Chemin | Permission | But | Codes |
|---------|--------|-----------|-----|-------|
| GET | `/api/motos/marques` | `ROLE_USER` | Liste des marques de moto | 200 |
| GET | `/api/motos/autocomplete` | `ROLE_USER` | Autocomplete (query: "marque modele") | 200 |

---

### VehiculeLookupController
**But** : Recherche véhicules par plaque ou VIN.

| Méthode | Chemin | Permission | But | Codes |
|---------|--------|-----------|-----|-------|
| GET | `/api/vehicule/{query}` | `ROLE_USER` | Cherche véhicule par plaque/VIN | 200 |

---

### ClientController
**But** : Gestion des clients (liste, anonymisation RGPD, export données).

| Méthode | Chemin | Permission | But | Codes |
|---------|--------|-----------|-----|-------|
| GET | `/api/clients` | `ROLE_USER` | Liste clients de l'atelier (paginé) | 200 |
| POST | `/api/clients/{id}/anonymize` | `ROLE_ADMIN` | RGPD anonymisation client | 200 |
| GET | `/api/clients/{id}/export` | `ROLE_ADMIN` | Export JSON données client (RGPD droit à portabilité) | 200 |

---

### ClientStatsController
**But** : Statistiques clients (count, activité).

| Méthode | Chemin | Permission | But | Codes |
|---------|--------|-----------|-----|-------|
| GET | `/api/clients/stats` | `ROLE_USER` | Compte clients actifs, no-show, taux satisfaction | 200 |

---

### StatistiquesController
**But** : Dashboard KPIs (CA, marge, mécaniciens, rentabilité).

| Méthode | Chemin | Permission | But | Codes |
|---------|--------|-----------|-----|-------|
| GET | `/api/statistiques/dashboard` | `ROLE_USER` | KPIs dashboard (CA 30/90j, anomalies, alerts) | 200 |
| GET | `/api/statistiques/ca` | `ROLE_USER` | Chiffre affaires par période | 200 |
| GET | `/api/statistiques/mecaniciens` | `ROLE_USER` | Stats par mécanicien (heures facturées, taux) | 200 |

---

### SuiviController
**But** : Suivi public RDV (client via URL avec token, sans auth).

| Méthode | Chemin | Permission | But | Codes |
|---------|--------|-----------|-----|-------|
| GET | `/api/public/suivi/{token}` | Public (token) | Statut RDV + photos + estimations | 200, 404 |

---

### ClauseLegaleController
**But** : Gestion clauses légales (conditions générales, mentions obligatoires facture).

| Méthode | Chemin | Permission | But | Codes |
|---------|--------|-----------|-----|-------|
| GET | `/api/clauses-legales` | Public | Liste clauses visibles | 200 |
| GET | `/api/clauses-legales/{code}/active` | Public | Retourne version active pour un code | 200 |
| POST | `/api/clauses-legales` | `ROLE_ADMIN` | Crée clause (versions multiples) | 201, 400 |
| PUT | `/api/clauses-legales/{id}` | `ROLE_ADMIN` | Update clause | 200 |
| POST | `/api/clauses-legales/hash` | `ROLE_USER` | Calcule hash des clauses actives (validation signature) | 200 |

---

### AdminAtelierController
**But** : Création, configuration ateliers (SIRET, TVA intra, horaires, config tarifaires).

| Méthode | Chemin | Permission | But | Codes |
|---------|--------|-----------|-----|-------|
| GET | `/api/admin/ateliers` | `ROLE_SUPER_ADMIN` | Liste tous les ateliers | 200 |
| POST | `/api/admin/ateliers` | `ROLE_SUPER_ADMIN` | Crée atelier + paramètres | 201, 400 |
| PUT | `/api/admin/ateliers/{id}` | `ROLE_SUPER_ADMIN` | Update config atelier | 200 |

---

### AdminUserProvisioningController
**But** : Invitation utilisateurs, approbation, rejet (workflow provisioning).

| Méthode | Chemin | Permission | But | Codes |
|---------|--------|-----------|-----|-------|
| GET | `/api/admin/users/pending` | `ROLE_SUPER_ADMIN` | Liste invitations en attente | 200 |
| POST | `/api/admin/users/{id}/approve` | `ROLE_SUPER_ADMIN` | Approuve l'accès utilisateur | 200 |
| POST | `/api/admin/users/{id}/reject` | `ROLE_SUPER_ADMIN` | Rejette l'accès | 200 |

---

### NotificationController
**But** : Notifications Mercure (in-app temps réel), emails, SMS (côté utilisateur).

| Méthode | Chemin | Permission | But | Codes |
|---------|--------|-----------|-----|-------|
| GET | `/api/notifications` | `ROLE_USER` | Liste notifications utilisateur (paginé) | 200 |
| GET | `/api/notifications/unread-count` | `ROLE_USER` | Compte notifications non lues | 200 |
| POST | `/api/notifications/{id}/acknowledge` | `ROLE_USER` | Marque notif comme vue (Mercure) | 200 |
| POST | `/api/notifications/{id}/mark-read` | `ROLE_USER` | Marque comme lue en DB | 200 |

---

### NotificationProviderController
**But** : Configuration providers (email, SMS, Mercure), tests, webhooks de retour.

| Méthode | Chemin | Permission | But | Codes |
|---------|--------|-----------|-----|-------|
| GET | `/api/admin/notification-providers` | `ROLE_ADMIN` | Liste providers configs | 200 |
| POST | `/api/admin/notification-providers` | `ROLE_ADMIN` | Crée config (SMTP, Twilio, Mercure) | 201, 400 |
| PUT | `/api/admin/notification-providers/{id}` | `ROLE_ADMIN` | Update config | 200 |
| DELETE | `/api/admin/notification-providers/{id}` | `ROLE_ADMIN` | Supprime config | 204 |
| POST | `/api/admin/notification-providers/{id}/test` | `ROLE_ADMIN` | Envoie test (SMS, email) | 200 |
| GET | `/api/admin/notification-logs` | `ROLE_ADMIN` | Historique envois | 200 |
| GET | `/api/admin/notification-templates` | `ROLE_ADMIN` | Templates (RDV confirmation, escalades, etc.) | 200 |
| POST | `/api/webhooks/notifications/{provider}` | Public | Webhook retour Twilio/Mailgun (statuts bounce, ouverture, etc.) | 200 |

---

### CerfaFieldConfigController
**But** : Personnalisation champs CERFAs PDF (DA, mandat immat, etc.) par atelier.

| Méthode | Chemin | Permission | But | Codes |
|---------|--------|-----------|-----|-------|
| GET | `/api/admin/cerfa-config` | `ROLE_ADMIN` | Configuration des champs CERFA pour l'atelier | 200 |
| PATCH | `/api/admin/cerfa-config/{id}` | `ROLE_ADMIN` | Update position/visibility champs | 200 |
| POST | `/api/admin/cerfa-config/{id}/reset` | `ROLE_ADMIN` | Reset aux defaults | 200 |
| GET | `/api/admin/cerfa-config/preview/{cerfaRef}` | `ROLE_ADMIN` | Prévisualise le CERFA rendu | 200 |

---

### PublicBookingController
**But** : Prise de RDV publique (client sans compte).

| Méthode | Chemin | Permission | But | Codes |
|---------|--------|-----------|-----|-------|
| GET | `/api/public/ateliers` | Public | Liste ateliers acceptant booking public | 200 |
| GET | `/api/public/prestations-catalogue` | Public | Prestations disponibles (query: atelierId) | 200 |
| GET | `/api/public/slots` | Public | Creneaux dispos pour atelierId | 200 |
| POST | `/api/public/booking` | Public | Crée RDV + client auto-créé, retourne token suivi | 201, 400 |

---

### VOPurchaseController
**But** : Gestion achat véhicule occasion (rachat, stock, revente, marges, facture VO).

| Méthode | Chemin | Permission | But | Codes |
|---------|--------|--------|-----|-------|
| GET | `/api/vo/purchases` | `ROLE_VO_MANAGER` | Liste rachats | 200 |
| GET | `/api/vo/purchases/{id}` | `ROLE_VO_MANAGER` | Détail rachat | 200 |
| GET | `/api/vo/purchases/{id}/full` | `ROLE_VO_MANAGER` | Détail enrichi + verdicts vendabilité | 200 |
| POST | `/api/vo/purchases` | `ROLE_VO_MANAGER` | Crée dossier rachat (OCR CG, PV, LP entry) | 201, 400 |
| PATCH | `/api/vo/purchases/{id}` | `ROLE_VO_MANAGER` | Update rachat | 200 |
| POST | `/api/vo/purchases/{id}/confirm` | `ROLE_VO_MANAGER` | Confirme rachat → crée LP + DA SIV | 200 |
| POST | `/api/vo/purchases/{id}/transition` | `ROLE_VO_MANAGER` | Transition workflow (draft → stock → vente) | 200 |
| POST | `/api/vo/purchases/{id}/sell` | `ROLE_VO_MANAGER` | Vend rachat → facture VO + rotation LP | 201, 400 |
| GET | `/api/vo/factures` | `ROLE_VO_MANAGER` | Liste factures VO | 200 |
| GET | `/api/vo/factures/{id}/pdf` | `ROLE_VO_MANAGER` | Télécharge facture VO PDF | 200 |
| GET | `/api/vo/purchases/{id}/pv-rachat/pdf` | `ROLE_VO_MANAGER` | PV rachat 2 signatures | 200 |
| GET | `/api/vo/purchases/{id}/cerfa-cession-achat/pdf` | `ROLE_VO_MANAGER` | CERFA cession achat | 200 |
| GET | `/api/vo/purchases/{id}/da-siv/pdf` | `ROLE_VO_MANAGER` | DA SIV préparée ou finale | 200 |
| GET | `/api/vo/purchases/{id}/mandat-immat/pdf` | `ROLE_VO_MANAGER` | Mandat immatriculation (CERFA 13757) | 200 |
| POST | `/api/vo/purchases/{id}/siv/prepare` | `ROLE_VO_MANAGER` | Prépare DA SIV (validation docs + warnings) | 200 |
| POST | `/api/vo/margin/calculate` | `ROLE_VO_MANAGER` | Calcule marge TVA/normal | 200 |
| POST | `/api/vo/margin/simulate` | `ROLE_VO_MANAGER` | Simule marge (scénarios achat/vente) | 200 |

---

### VODepotController
**But** : Gestion dépôt-vente (mandat, commission, reversal).

| Méthode | Chemin | Permission | But | Codes |
|---------|--------|-----------|-----|-------|
| GET | `/api/vo/depots` | `ROLE_VO_MANAGER` | Liste dépôts-vente | 200 |
| GET | `/api/vo/depots/{id}` | `ROLE_VO_MANAGER` | Détail dépôt | 200 |
| GET | `/api/vo/depots/{id}/full` | `ROLE_VO_MANAGER` | Détail enrichi + verdicts | 200 |
| POST | `/api/vo/depots` | `ROLE_VO_MANAGER` | Crée mandat dépôt-vente (90j, LP entry) | 201 |
| PATCH | `/api/vo/depots/{id}` | `ROLE_VO_MANAGER` | Update dépôt | 200 |
| POST | `/api/vo/depots/{id}/sell` | `ROLE_VO_MANAGER` | Vend dépôt → calcule commission, LP rotation | 201 |
| GET | `/api/vo/depots/{id}/contrat/pdf` | `ROLE_VO_MANAGER` | Contrat dépôt-vente | 200 |
| GET | `/api/vo/depots/{id}/mandat-immat/pdf` | `ROLE_VO_MANAGER` | Mandat immat pour vente dépôt | 200 |
| POST | `/api/vo/depots/{id}/restituer` | `ROLE_VO_MANAGER` | Rend le véhicule au déposant | 200 |

---

### VORemiseEnEtatController
**But** : Gestion remise en état (FRE : travaux + pièces + coûts).

| Méthode | Chemin | Permission | But | Codes |
|---------|--------|-----------|-----|-------|
| GET | `/api/vo/remises-en-etat` | `ROLE_VO_MANAGER` | Liste campagnes FRE | 200 |
| GET | `/api/vo/remises-en-etat/{id}` | `ROLE_VO_MANAGER` | Détail campagne FRE | 200 |
| POST | `/api/vo/remises-en-etat` | `ROLE_VO_MANAGER` | Crée campagne FRE | 201 |
| PATCH | `/api/vo/remises-en-etat/{id}` | `ROLE_VO_MANAGER` | Update campagne | 200 |
| GET | `/api/vo/remises-en-etat/{id}/pdf` | `ROLE_VO_MANAGER` | PDF état des travaux | 200 |

---

### SecurityReportController
**But** : Collecte rapports CSP (Content Security Policy violations).

| Méthode | Chemin | Permission | But | Codes |
|---------|--------|-----------|-----|-------|
| POST | `/api/security/csp-report` | Public | Endpoint CSP report-only (collecte violations) | 204 |

---

### StockController
**But** : Alertes stock pièces (rupture, min atteint).

| Méthode | Chemin | Permission | But | Codes |
|---------|--------|-----------|-----|-------|
| GET | `/api/stock/alertes` | `ROLE_USER` | Alertes stock rupture/min pièces | 200 |

---

## 🔧 SERVICES MÉTIER (39 au total)

### AuditService
**But** : Centralise les logs d'audit pour conformité RGPD et traçabilité.

**Méthodes publiques** :
- `log(string $action, string $entityType, int $entityId, string $context, ?int $userId = null): void` — Persiste log audit

**Dépendances** : EntityManager, CurrentUser

---

### RapportInterventionService
**But** : Gestion du cycle de vie des rapports d'intervention (brouillon, validation, signature, rectification).

**Méthodes publiques** :
- `findLatestForRdv(RendezVous $rdv): ?RapportIntervention`
- `getOrCreateDraft(RendezVous $rdv): RapportIntervention`
- `createDraft(RendezVous $rdv): RapportIntervention`
- `prefillFromOR(RapportIntervention $rapport, RendezVous $rdv): void`
- `calculateNextRevision(?int $currentKm): array` — Déduit prochaine révision (kilométrique)
- `validateCompleteness(RapportIntervention $rapport): array` — Tableau erreurs si incomplet
- `validateForMecanicienSignature(RapportIntervention $rapport): array`
- `signByMecanicien(RapportIntervention $rapport, string $signature, int $mecanicienId): void`
- `signByClient(RapportIntervention $rapport, string $signature, string $ip): void`
- `rectify(RapportIntervention $original, string $motif, int $userId): RapportIntervention` — Crée copie rectificative

---

### OrdreReparationPolicy
**But** : Logique métier pour OR (permissions, signature, intégrité, rectification).

**Méthodes publiques** :
- `canEdit(OrdreReparation $or, User $user): bool`
- `canSign(OrdreReparation $or): bool`
- `canRectify(OrdreReparation $or, User $user): bool`
- `canAddComplementaire(OrdreReparation $or, User $user): bool`
- `buildSnapshot(OrdreReparation $or): array` — Capture l'état pour signature
- `computeHash(array $snapshot): string` — SHA256 immuabilité
- `sign(OrdreReparation $or, string $signatureData, Request $request): string` — Retourne hash signé
- `verifyIntegrity(OrdreReparation $or): bool` — Valide intégrité post-signature
- `rectify(OrdreReparation $original, User $user, string $motif): OrdreReparation` — Crée rectificatif

---

### PricingService
**But** : Calcul tarifaire prestations (MO, pièces, TVA).

**Méthodes publiques** :
- `getConfig(int $atelierId): ?ConfigAtelier`
- `calculatePrestationPrice(Prestation $prestation, Vehicule $vehicule, ?int $atelierId = null, string $tauxType = 'standard'): array`
- `calculateMoPrice(int $minutes, string $tauxType = 'standard', ?int $atelierId = null): array` — Retourne HT + TVA
- `applyPieceMargin(string $prixAchatHt, string $categorie = 'standard', ?int $atelierId = null): array` — Applique marge config

---

### VOMarginService
**But** : Calcul marge VO (TVA régime normal vs régime particulier marge).

**Méthodes publiques** :
- `calculateMarginVat(string $purchasePrice, string $salePrice, string $vatRate = '20.0'): array` — Régime particulier marge (Art. 297 A CGI)
- `calculateNormalVat(string $salePriceHt, string $vatRate = '20.0'): array` — TVA normale
- `calculateDepotVenteCommission(string $salePrice, string $commissionRate): array` — Commission reversement

---

### VODocumentService
**But** : Gestion documents VO (upload, archivage, RGPD purge, verdicts vendabilité, contrôles légaux).

**Méthodes publiques** :
- `upload(UploadedFile $file, VOPurchase|VODepotVente $record, string $type, User $user): VODocument`
- `storeRawContent(string $rawPath, VOPurchase|VODepotVente $record, string $type, User $user): VODocument`
- `archiveGeneratedPdf(VOPurchase|VODepotVente $record, string $type, string $pdfContent, ?User $user = null): VODocument`
- `getMissingDocuments(VOPurchase $purchase, bool $includeSale = false): array`
- `getMissingDocumentsDepot(VODepotVente $depot): array`
- `getPurchaseSivSummary(VOPurchase $purchase): array`
- `buildPurchaseLegalChecklist(VOPurchase $purchase): array`
- `buildDepotLegalChecklist(VODepotVente $depot): array`
- `getPurchaseSaleBlockers(VOPurchase $purchase): array` — Liste blocages vente
- `buildPurchaseSaleVerdict(VOPurchase $purchase, array $extraBlockers = []): array` — Verdict complet vendabilité
- `getDepotSaleBlockers(VODepotVente $depot): array`
- `buildDepotSaleVerdict(VODepotVente $depot, array $extraBlockers = [], bool $companionComplete = true): array`
- `getPurchaseDossierStatus(VOPurchase $purchase): string` — Enum statut dossier
- `getDepotDossierStatus(VODepotVente $depot): string`
- `getExpiredDocuments(?int $atelierId = null): array` — Docs expirés (RGPD)
- `getAlerts(?int $atelierId = null): array` — Alertes docum manquantes
- `batchDocumentTypes(array $purchaseIds, array $depotIds): array` — Batch lookup types
- `purgeExpiredIdentityDocuments(): int` — RGPD purge pièces ID (0 ans rétention)
- `countStoredSensitiveIdentityDocuments(?int $atelierId = null): int`

---

### VOLivrePoliceService
**But** : Gestion Livre de Police (enregistrement achats/ventes immutables).

**Méthodes publiques** :
- `createEntryForPurchase(VOPurchase $purchase, User $user): VOLivrePolice`
- `createEntryForDepotVente(VODepotVente $depot, User $user): VOLivrePolice`
- `recordSale(VOLivrePolice $entry, VOFacture|null $facture, User $user): void` — Enregistre sortie

---

### VONumberingService
**But** : Numérotation factures VO et n° ordre LP (via séquences PostgreSQL).

**Méthodes publiques** :
- `nextFactureNumber(?int $atelierId, ?\DateTimeInterface $date = null): string`
- `nextLivrePoliceOrder(?int $atelierId): int`

---

### VOCompanionWorkflowService
**But** : Workflow signature électronique Companion VO (achat + dépôt-vente).

**Méthodes publiques** :
- `ensureToken(VOPurchase|VODepotVente $record): bool`
- `getMode(VOPurchase|VODepotVente $record): string` — 'purchase' | 'depot'
- `getPartyRoleLabel(VOPurchase|VODepotVente $record): string` — "Vendeur" | "Déposant"
- `getParty(VOPurchase|VODepotVente $record): ?Client`
- `getRequiredDocuments(VOPurchase|VODepotVente $record): array`
- `getAdditionalDocumentOptions(VOPurchase|VODepotVente $record): array`
- `getGeneratedDocuments(VOPurchase|VODepotVente $record): array`
- `buildSteps(VOPurchase|VODepotVente $record, array $documents): array`

---

### VORemiseEnEtatService
**But** : Campagnes remise en état VO (travaux + pièces + marge live).

**Méthodes publiques** :
- `getCampaignsForRecord(VOPurchase|VODepotVente $record): array`
- `getActiveCampaignForRecord(VOPurchase|VODepotVente $record): ?VORemiseEnEtat`
- `hasBlockingActiveCampaign(VOPurchase|VODepotVente $record): bool`
- `createCampaignForRecord(VOPurchase|VODepotVente $record, ?User $requestedBy, array $payload = []): VORemiseEnEtat`
- `normalizeCampaign(VORemiseEnEtat $campaign): array`
- `normalizeQueueItem(VORemiseEnEtat $campaign): array`

---

### GardiennageService
**But** : Gardiennage RDV oubliés (relances, tarifs, facturations).

**Méthodes publiques** :
- `peutDeclencher(RendezVous $rdv): bool`
- `declencher(RendezVous $rdv, int $userId, string $motif): void`
- `notifierEntreeGardiennage(RendezVous $rdv): void` — Envoie notif client
- `calculerMontant(RendezVous $rdv, \DateTime $dateRestitution): string` — bcmath

---

### NotificationDispatcher
**But** : Dispatche notifications par canal (email, SMS, Mercure).

**Méthodes publiques** :
- `send(NotificationMessage $msg): NotificationResult`
- `sendFromTemplate(int $atelierId, string $templateCode, string $channel, string $recipient, int $relatedEntityId = 0, string $relatedEntityType = '', array $variables = []): NotificationResult`
- `testProvider(NotificationProviderConfig $config, string $testRecipient): NotificationResult`

---

### MercureNotifier
**But** : Notifications temps réel Mercure (SSE).

**Méthodes publiques** :
- `publishToAtelier(int $atelierId, Notification $notif): void`
- `publishAcknowledged(int $atelierId, int $notifId, int $userId): void`

---

### NotificationTemplateCatalog
**But** : Catalogue templates notifications (textes par défaut, variables).

**Méthodes publiques** :
- `getDefaults(): array` — Templates defaults
- `ensureDefaultsForAtelier(int $atelierId): int` — Crée defaults atelier

---

### NotificationMessage & NotificationResult
**But** : DTOs notification (input + output).

**NotificationMessage** : `channel`, `atelierId`, `recipient`, `body`, `subject`, `templateCode`, `relatedEntityType`, `relatedEntityId`
**NotificationResult** : `success`, `providerMessageId`, `provider`, `errorMessage`

---

### PdfService
**But** : Génération PDFs (Twig + DomPDF).

**Méthodes publiques** :
- `generateOrPdf(OrdreReparation $or): string` — Retourne chemin fichier
- `generateRapportPdf(RapportIntervention $rapport, ?object $essai = null): string`
- `generateFacturePdf(Facture $facture): string`
- `generateDevisPdf(Devis $devis): string`
- `generateVoFacturePdf(VOFacture $facture): string`
- `generateLivrePolicePdf(array $entries, ?int $atelierId = null): string`
- `generateContratDepotVentePdf(VODepotVente $depot): string`
- `generatePvRachatPdf(VOPurchase $purchase): string`
- `generateDaSivPreparationPdf(VOPurchase $purchase, array $blockers = []): string`
- `generateMandatImmatriculationPdf(VOPurchase|VODepotVente $record, ?Client $buyer = null): string`
- `generateCerfaCessionAchatPdf(VOPurchase $purchase): string`
- `generateVoRemiseEnEtatPdf(array $document, ?int $atelierId = null): string`
- `buildRdvPhotoContext(?RendezVous $rdv): array`
- `getCachedPdfPath(string $filename): ?string`

---

### CerfaOverlayService
**But** : Overlay champs CERFA sur templates PDF (personnalisation par atelier).

**Méthodes publiques** :
- `generateDaSivPreparationPdf(VOPurchase $purchase, Atelier $atelier): string`
- `generateMandatImmatriculationPdf(VOPurchase|VODepotVente $record, Atelier $atelier, ?Client $buyer = null): string`
- `generateCerfaCessionAchatPdf(VOPurchase $purchase, Atelier $atelier): string`

---

### PhotoService
**But** : Gestion photos intervention (upload, EXIF, hash, validation).

**Méthodes publiques** :
- `allowedTypes(): array`
- `upload(UploadedFile $file, string $type, RendezVous $rdv, ?string $description = null, ?string $annotationJson = null): PhotoIntervention`
- `computeHash(string $path): string` — SHA256
- `extractExif(string $path): ?array`
- `requirePhotosForTransition(string $transition, RendezVous $rdv): array` — Quelles photos requises pour transition

---

### SlotService
**But** : Calcul créneaux disponibles (ponts × horaires × mécaniciens × absences).

**Méthodes publiques** :
- `getAvailableSlots(int $atelierId, \DateTime $date, int $estimatedMinutes, ?int $mecanicienId = null): array`
- `getSlotsForDay(int $atelierId, \DateTime $date, int $estimatedMinutes, ?int $mecanicienId = null): array`

---

### PrestationCatalogService
**But** : Catalogue prestations applicables par type véhicule + tarifs.

**Méthodes publiques** :
- `getApplicablePrestations(Vehicule $vehicule): array`
- `calculatePrice(Prestation $prestation, Vehicule $vehicule, ?int $atelierId = null): array`
- `validateAddition(Prestation $prestation, OrdreReparation $or): void`

---

### RendezVousWorkflowService
**But** : Gestion side-effects workflow (notifications, calculs, audit).

**Méthodes publiques** :
- `recordCancellation(RendezVous $rdv, User $user, string $reason): void`
- `startWorkSession(RendezVous $rdv, ?\DateTimeInterface $startedAt = null): void`
- `finalizeWorkSession(RendezVous $rdv, ?\DateTimeInterface $endedAt = null): void`
- `handleTransitionSideEffects(string $transition, RendezVous $rdv, User $user, array $context = []): void` — Dispatcher principal

---

### MotoCatalogImporter
**But** : Import/gestion catalogue motos (marque, modèle, specs, OCR CG).

**Méthodes publiques** :
- `importFromDefaultFile(): array`
- `importFromXlsx(string $filePath): array`
- `prepareCatalogRows(array $rows): array`
- `importPreparedRows(array $rows): array`

---

### HistoriqueEntretienService
**But** : Agrégation historique entretien véhicule (toutes interventions + suggestions prochaines révisions).

**Méthodes publiques** :
- `buildHistorique(Vehicule $vehicule): array`
- `generatePdf(Vehicule $vehicule): string`

---

### JoursOuvresService
**But** : Calcul jours ouvrés (calendrier, jours fériés, fermetures atelier).

**Méthodes publiques** :
- `compterJoursOuvres(\DateTime $debut, \DateTime $fin, int $atelierId): int`
- `estJourFerie(\DateTime $date): bool`
- `estJourFerme(\DateTime $date, int $atelierId): bool`
- `ajouterJoursOuvres(\DateTime $debut, int $jours, int $atelierId): \DateTime`

---

### ClauseLegaleVisibilityService
**But** : Logique visibilité clauses légales (active, version, code).

**Méthodes publiques** :
- `pickVisibleClauses(array $clauses, bool $activeOnly): array`
- `pickPreferredClause(array $clauses): ?ClauseLegale`

---

### AtelierCatalogBootstrapService
**But** : Initialisation catalogue prestations par atelier.

**Méthodes publiques** :
- `ensurePrestationsForAtelier(?int $atelierId): int` — Nombre prestations créées

---

### UserMecanicienSyncService
**But** : Synchronisation User ↔ Mecanicien (rôles métier).

**Méthodes publiques** :
- `syncForUser(User $user, bool $allowCreate = true): ?Mecanicien`

---

### UserRoleMapper
**But** : Mappage rôles legacy vs RoleMetier.

**Méthodes publiques** :
- `mapRoleMetierToLegacyRole(RoleMetier $roleMetier): string`
- `mapLegacyRoleToRoleMetierCode(?string $legacyRole): ?string`

---

### BookingAtelierAccessService
**But** : Permissions accès atelier pour chaque utilisateur.

**Méthodes publiques** :
- `getAllowedAteliers(User $user): array`
- `canAccessAtelier(User $user, ?int $atelierId): bool`
- `resolvePreferredAtelierId(User $user, ?int $requestedAtelierId = null): ?int`
- `isServiceClient(User $user): bool`
- `isSuperAdmin(User $user): bool`

---

### CurrentAtelierResolver
**But** : Résout l'atelier courant depuis JWT.

**Méthodes publiques** :
- `resolveAtelierId(): ?int`
- `isGlobalScopeRequested(): bool`

---

### ConfigEncryptionService
**But** : Chiffrement config sensible (clés API, tokens) stockée en base.

**Méthodes publiques** :
- `encrypt(array $config): string`
- `decrypt(string $encrypted): array`

---

### UserArchiveService
**But** : Archivage utilisateur RGPD.

**Méthodes publiques** :
- `archive(User $user, string $reason = 'Archive RGPD'): void`

---

### AdminConfigValidator
**But** : Validation config atelier avant persist.

**Méthodes publiques** :
- `validateConfigPayload(array $configData, array $horaires = []): array` — Tableau erreurs

---

### NotificationProviderConfigSanitizer
**But** : Merge configs providers (mask secrets, validation).

**Méthodes publiques** :
- `merge(array $existing, array $submitted): array`

---

### AbsenceConflictChecker
**But** : Détecte chevauchement absences mécaniciens.

**Méthodes publiques** :
- `isDateRangeValid(\DateTimeInterface $start, \DateTimeInterface $end): bool`
- `hasConflict(\DateTimeInterface $start, \DateTimeInterface $end, array $existingRanges): bool`

---

### VOGeneratedDocumentService
**But** : Archivage documents générés Companion VO.

**Méthodes publiques** :
- `archiveCompanionDocumentIfReady(VOPurchase|VODepotVente $record, ?User $user = null, bool $prepareSiv = false): bool`
- `archivePurchaseSivPreparation(VOPurchase $purchase, ?User $user = null, bool $markAsInProgress = false): bool`

---

### VORemiseEnEtatDocumentService
**But** : Documents générés campagne remise en état.

**Méthodes publiques** :
- `normalizeDocumentState(VORemiseEnEtat $campaign): array`
- `generateLivePdf(VORemiseEnEtat $campaign): string`
- `signCampaignDocument(VORemiseEnEtat $campaign, string $signatureData, ?User $user = null): void`
- `archiveFallbackDocumentIfMissing(VORemiseEnEtat $campaign, ?User $user = null): ?VODocument`
- `getArchivedDocument(VORemiseEnEtat $campaign): ?VODocument`
- `buildCurrentSnapshot(VORemiseEnEtat $campaign): array`
- `computeHash(array $snapshot): string`

---

## 📮 MESSAGES & HANDLERS (4 messages + 4 handlers)

| Message | Handler | Dispatché par | But |
|---------|---------|---------------|-----|
| `GeneratePdfMessage` | `GeneratePdfHandler` | Controllers (async/queue) | Génère PDF asynchrone (OR, facture, devis, VO docs) |
| `SendRappelMessage` | `SendRappelHandler` | Controllers + Scheduler | Envoie email rappel RDV (confirmation, J-1, J-3) |
| `SendGardiennageRappelMessage` | `SendGardiennageRappelHandler` | Schedule + Relance cron | Relances gardiennage (J+15, J+30, J+45, J+180) |
| `ProcessScheduledRappels` | `ProcessScheduledRappelsHandler` | Scheduler 8:00 | Lance batch traitement rappels du jour |

---

## ⏰ SCHEDULER & CRONS

**Fichier** : [src/Schedule.php](src/Schedule.php)

| Cron | Commande | But | Fréquence |
|------|----------|-----|-----------|
| `0 8 * * *` | `ProcessScheduledRappels` | Process batch rappels | **Chaque jour 8:00** |
| `0 3 1 * *` | `app:rgpd-purge --execute` | RGPD purge donnees (clients 3a inactifs, devis > 6m) | **1er du mois 3:00** |
| `* * * * *` | `app:process-notification-escalations` | Escalade notif non-lues | **Chaque minute** |
| `0 4 * * *` | `app:purge-identity-documents` | RGPD purge pièces ID (0 jours rétention) | **Chaque jour 4:00** |
| `0 7 * * *` | `app:relance-client-stockage` | Gardiennage relances (J+15/30/45/180) | **Chaque jour 7:00** |
| `0 6 * * *` | `app:check-da-siv-expiry` | Alerte DA SIV J+10 expiration / J+15 expiré | **Chaque jour 6:00** |
| `30 6 * * *` | `app:check-depot-vente-mandat` | Alerte mandat dépôt-vente J-7 avant expiration | **Chaque jour 6:30** |
| `0 9 * * *` | `app:rappel-prochaine-revision` | Rappel révision véhicule (J-30 prochaine KM) | **Chaque jour 9:00** |

---

## 🎯 COMMANDES CLI (14 au total)

| Commande | Signature | But | Fréquence |
|----------|-----------|-----|-----------|
| `app:create-admin` | `create-admin` | Crée super-admin + atelier par défaut (setup initial) | **Manuel — une fois** |
| `app:seed` | `seed [--demo]` | Seed données ref (rôles, catégories, horaires, config) | **Manuel — setup** |
| `app:seed-reset` | `seed-reset` | Purge + réinitialise données demo | **Manuel — dev** |
| `app:rgpd-purge` | `rgpd-purge [--execute]` | Purge données > durée rétention (clients 3a, devis 6m, audit 3a, IDs 0j) | **Cron 1er/mois 3:00 --execute** |
| `app:process-notification-escalations` | `process-notification-escalations` | Escalade notifs non-lues (T+5/10/30 min) | **Cron **chaque minute** |
| `app:purge-identity-documents` | `purge-identity-documents` | RGPD purge pièces ID stockées (rétention = 0j) | **Cron chaque jour 4:00** |
| `app:check-no-show` | `check-no-show` | Auto-transition RDV no-show si heure+30min < now | **Cron? Manuel?** |
| `app:relance-client-stockage` | `relance-client-stockage` | Envoie relances gardiennage (J+15/30/45/180 jours ouvrés) | **Cron chaque jour 7:00** |
| `app:check-da-siv-expiry` | `check-da-siv-expiry` | Alerte DA SIV J+10 / expiration J+15 | **Cron chaque jour 6:00** |
| `app:check-depot-vente-mandat` | `check-depot-vente-mandat` | Alerte mandat dépôt-vente J-7 avant expiration | **Cron chaque jour 6:30** |
| `app:import-moto-catalog` | `import-moto-catalog [file.xlsx]` | Import/update catalogue motos (marque, modèle, specs) | **Manuel + cron?** |
| `app:rappel-prochaine-revision` | `rappel-prochaine-revision` | Rappel client révision J-30 avant prochaine date estimée | **Cron chaque jour 9:00** |
| `app:send-rappels` | `send-rappels [type]` | Envoie rappels RDV en batch (confirmation, J-1, J-3) | **Manuel / Cron via Messenger?** |
| `app:backfill-snapshots` | `backfill-snapshots` | Remplit colonnes `snap_*` entités (migration data) | **Manuel — une fois** |

---

## 📊 RÉSUMÉ DENSITÉ & COMPLEXITÉ

| Couche | Fichiers | Lignes approx | Complexité |
|--------|----------|---------------|-----------|
| Controllers | 43 | ~8000 | Haute (multi-endpoints, validations, workflows) |
| Services | 39 | ~12000 | Très haute (logique métier, RGPD, VO, notifications) |
| Messages + Handlers | 8 | ~800 | Moyenne (async simple) |
| Commands | 14 | ~1500 | Moyenne (crons, batch) |
| **TOTAL** | **104** | **~22000** | **Très haute** |

---

## 🔐 SÉCURITÉ & PERMISSIONS

**Hiérarchie des rôles** :
1. `ROLE_SUPER_ADMIN` — Bypass total TenantFilter, accès cross-ateliers, audit log obligatoire
2. `ROLE_ADMIN` — Admin atelier (config, users, archives)
3. `ROLE_VO_MANAGER` — Gestion VO (rachats, dépôts, marges)
4. `ROLE_USER` — Accès général
5. **RoleMetier** — Rôles paramétrables (permissions granulaires par module/action)

**Authentification** : JWT + Refresh Token (révocation via `RevokedToken`)

**Multi-tenant** : `TenantFilter` auto-appliqué sur toutes requêtes (colonne `atelierId` implicite)

---

## ⚠️ POINTS CRITIQUES

1. **OR signé = immuable** — Modification seulement via rectificatif (audit + bloc workflow)
2. **Essai routier obligatoire** — Bloquant avant `terminer` RDV
3. **DA SIV bloquant** — Sans DA enregistrée, VO invendable
4. **RGPD purge** — Pièces ID, audit IPs, clients 3a inactifs (automatisé cron)
5. **TVA marge vs normale** — Régimes exclusifs facture VO (Art. 297 A vs 256 CGI)
6. **Livre Police immuable** — Pas PUT/DELETE après création
7. **Notification escalade** — Chaque minute, relances T+5/10/30 min auto

---

FIN DU RAPPORT.