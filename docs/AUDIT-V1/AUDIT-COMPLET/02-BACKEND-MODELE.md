Parfait! J'ai tous les éléments pour générer le rapport exhaustif. Voici le rapport COMPLET du modèle backend:

---

# RAPPORT EXHAUSTIF — MODÈLE BACKEND SYMFONY
## AtelierMBZ Backend v4 — 24 Avril 2026

---

## 1. WORKFLOWS SYMFONY

### State Machine: `rendez_vous`
**Type:** State Machine | **Property:** `statut` | **Marking Store:** method | **Audit Trail:** enabled

#### Places (19)
`en_attente` → `reserve` → `confirme` → `reception` → `en_cours` → `en_pause` → `termine` → `restitue` → `facture` → `paye` → `annule` | `en_attente_pieces` | `en_attente_reprise` | `en_gardiennage` | `restitue_partiel` | `no_show`

#### Transitions (29)

| Transition | From | To | Guard |
|---|---|---|---|
| `reserver` | `en_attente` | `reserve` | — |
| `confirmer` | `[en_attente, reserve]` | `confirme` | — |
| `reception` | `confirme` | `reception` | — |
| `start_travail` | `reception` | `en_cours` | — |
| `mettre_en_pause` | `en_cours` | `en_pause` | — |
| `reprendre` | `en_pause` | `en_cours` | — |
| `terminer` | `[en_cours, en_pause]` | `termine` | Essai routier valide + Rapport intervention signé mécanicien |
| `restituer` | `termine` | `restitue` | — |
| `facturer` | `[termine, restitue, restitue_partiel]` | `facture` | — |
| `payer` | `facture` | `paye` | — |
| `annuler` | `[en_attente, reserve, confirme, reception, en_attente_pieces, en_gardiennage]` | `annule` | — |
| `declarer_no_show` | `[confirme, reception]` | `no_show` | — |
| `no_show` | `[confirme, reception]` | `no_show` | — |
| `reporter` | `[reception, no_show]` | `confirme` | — |
| `mettre_en_attente_pieces` | `[en_cours, en_pause]` | `en_attente_pieces` | — |
| `reprendre_apres_pieces` | `en_attente_pieces` | `en_cours` | — |
| `mettre_en_attente_reprise` | `en_cours` | `en_attente_reprise` | — |
| `reprendre_demain` | `en_attente_reprise` | `en_cours` | — |
| `passer_gardiennage` | `[termine, en_attente_pieces, restitue_partiel]` | `en_gardiennage` | — |
| `mettre_en_gardiennage` | `[confirme, reception, en_attente_pieces]` | `en_gardiennage` | — |
| `sortir_gardiennage` | `en_gardiennage` | `en_cours` | — |
| `restituer_partiel` | `[en_cours, termine]` | `restitue_partiel` | — |
| `retour_garantie` | `[restitue, paye]` | `en_cours` | — |

---

### State Machine: `vo_purchase`
**Type:** State Machine | **Property:** `status` | **Marking Store:** method | **Audit Trail:** enabled

#### Places (5)
`brouillon` → `en_stock` → `en_vente` → `reserve` → `vendu`

#### Transitions (7)

| Transition | From | To |
|---|---|---|
| `confirmer` | `brouillon` | `en_stock` |
| `mettre_en_vente` | `en_stock` | `en_vente` |
| `retirer_de_la_vente` | `en_vente` | `en_stock` |
| `reserver` | `en_vente` | `reserve` |
| `liberer` | `reserve` | `en_vente` |
| `vendre` | `[en_stock, en_vente, reserve]` | `vendu` |

---

### State Machine: `vo_depot`
**Type:** State Machine | **Property:** `status` | **Marking Store:** method | **Audit Trail:** enabled

#### Places (4)
`brouillon` → `actif` → `vendu` / `restitue`

#### Transitions (3)

| Transition | From | To |
|---|---|---|
| `activer` | `brouillon` | `actif` |
| `vendre` | `actif` | `vendu` |
| `restituer` | `actif` | `restitue` |

---

## 2. LISTENERS DOCTRINE/ÉVÉNEMENTS (7)

| Listener | Événement | Action | Services injectés |
|---|---|---|---|
| **RdvWorkflowListener** | `workflow.rendez_vous.completed.*` (confirmer, terminer, attendre_pieces, mettre_en_attente_pieces, declarer_no_show, no_show) | Dispatch messages d'email de notification (SMS/email). Pour `terminer`, crée/met à jour `RapportIntervention` draft. | `MessageBusInterface`, `RapportInterventionService` |
| **TenantFilterListener** | `kernel.request` (priority: -10) | Active/désactive le filtre tenant `atelier_id` en base de données Doctrine selon le JWT. Bypass complet si `ROLE_SUPER_ADMIN`. | `EntityManagerInterface`, `TokenStorageInterface`, `CurrentAtelierResolver` |
| **TenantSetterListener** | `prePersist` (Doctrine lifecycle) | Pose automatiquement `atelierId` sur les nouvelles entités créées si elle a un setter disponible. | `CurrentAtelierResolver` |
| **OrdreReparationFreezeListener** | `preUpdate` (Doctrine lifecycle) | Gèle les modifications de `OrdreReparation` quand elle passe en statut signé/exécuté/terminé/rectifié. Contrôle strict des champs modifiables. | — |
| **UserSecurityListener** | `preUpdate`, `preRemove` (Doctrine User) | Garantit qu'on ne peut pas révoquer le dernier super-admin. Bloque l'escalade de rôle. | `UserSecurityGuard` |
| **ApiDebugExceptionListener** | `kernel.exception` | Capture et loggue les erreurs API sur certaines routes (`/api/rendez-vous`, `/api/ordres-reparation`). Inclut contexte requête. | `LoggerInterface` |
| **SecurityHeadersListener** | `kernel.response` | Ajoute en-têtes HTTP de sécurité : `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `HSTS`, `CSP` (Report-Only mode). | — |

---

## 3. SUBSCRIBERS DOCTRINE (6)

| Subscriber | Événement | Action | Services |
|---|---|---|---|
| **UserMecanicienSyncSubscriber** | `postPersist`, `postUpdate`, `postFlush` | Synchronise User ↔ Mecanicien (1 User ↔ 0..1 Mecanicien). Queue utilisateurs, traite en batch lors du postFlush. | `UserMecanicienSyncService` |
| **UserRoleMetierSyncSubscriber** | `prePersist`, `preUpdate` (User) | Synchronise les rôles : transcrit `RoleMetier` en `role` hérité, pose `atelierId` résolu, gère `accessStatus` (pending_validation → inactive), propage archived → null role. | `UserRoleMapper`, `CurrentAtelierResolver` |
| **DevisDateValiditeSubscriber** | `prePersist` (Devis) | Remplace la durée hardcodée (+30j) du `Devis` par `ConfigAtelier.validiteDevisJours` de l'atelier courant. | `EntityManagerInterface`, `CurrentAtelierResolver` |
| **AbsenceValidationSubscriber** | `prePersist`, `preUpdate` (Absence) | Valide que date_fin ≥ date_début. Vérifie qu'il n'existe pas d'absence chevauchante pour le même mécanicien. Lance `ConflictHttpException` si conflit. | `AbsenceConflictChecker` |
| **RdvTerminationGuardSubscriber** | `workflow.rendez_vous.guard.terminer` | Bloque la transition `terminer` si : (1) Essai routier absent ou non valide, (2) Rapport intervention non signé par le mécanicien. | `EntityManagerInterface` |
| **UserPasswordHashSubscriber** | `prePersist`, `preUpdate` (User) | Chiffre les mots de passe via `PasswordHasher`. Efface credentials. Recompute changeset. | `UserPasswordHasherInterface` |

---

## 4. VOTERS (2)

| Voter | Attribut | Sujet | Logique |
|---|---|---|---|
| **FactureDeleteVoter** | `DELETE` | `Facture` | ❌ Refuse suppression si statut ∈ [EMISE, PAYEE, PARTIELLEMENT_PAYEE, CORRIGEE]. ✅ Permet si statut brouillon/invalidé. (Conformité Art. L.123-22 Code commerce - conservation 10 ans) |
| **VOFactureDeleteVoter** | `DELETE` | `VOFacture` | ❌ Refuse suppression si statut ∈ [emise, payee, partiellement_payee, corrigee, annulee]. ✅ Permet si brouillon. (Conformité Art. L.123-22 + TVA CGI) |

---

## 5. ENTITÉS DOCTRINE (61)

### ➤ Entité: `Atelier`
**Table:** `ateliers`

| Colonne | Type | Nullable | Default | Unique |
|---|---|---|---|---|
| `id` | INT | ❌ | AUTO | ✅ |
| `nom` | VARCHAR(200) | ❌ | — | — |
| `slug` | VARCHAR(100) | ❌ | — | ✅ |
| `adresse` | TEXT | ✅ | NULL | — |
| `cp` | VARCHAR(20) | ✅ | NULL | — |
| `ville` | VARCHAR(100) | ✅ | NULL | — |
| `telephone` | VARCHAR(20) | ✅ | NULL | — |
| `email` | VARCHAR(200) | ✅ | NULL | — |
| `siret` | VARCHAR(20) | ✅ | NULL | — |
| `tvaIntracom` | VARCHAR(30) | ✅ | NULL | — |
| `logoUrl` | VARCHAR(500) | ✅ | NULL | — |
| `plan` | VARCHAR(50) | ❌ | `'starter'` | — |
| `actif` | BOOL | ❌ | `true` | — |
| `configJson` | TEXT | ✅ | NULL | — |
| `createdAt` | TIMESTAMP | ❌ | `CURRENT_TIMESTAMP` | — |

**Relations:** —

**Métier:** Profil franchise multi-tenant. Un atelier = une entité juridique indépendante (SIRET, TVA, coordonnées). Contient configuration : modules activés, taux horaires, marges pièces, durées de garantie, délais relance gardiennage.

**Groupes sérialisation:** `atelier:read`, `atelier:write`

---

### ➤ Entité: `User`
**Table:** `users`

| Colonne | Type | Nullable | Default |
|---|---|---|---|
| `id` | INT | ❌ | AUTO |
| `atelierId` | INT | ✅ | NULL |
| `username` | VARCHAR(100) | ❌ | — |
| `email` | VARCHAR(200) | ❌ | — |
| `prenom` | VARCHAR(120) | ✅ | NULL |
| `nom` | VARCHAR(120) | ✅ | NULL |
| `hashedPassword` | VARCHAR(200) | ❌ | — |
| `role` | VARCHAR(50) | ❌ | `'receptionnaire'` |
| `isActive` | INT | ❌ | `1` |
| `roleMetier` | FK → `RoleMetier` | ✅ | NULL |
| `authProvider` | VARCHAR(30) | ❌ | `'local'` |
| `googleSub` | VARCHAR(191) | ✅ | NULL |
| `accessStatus` | VARCHAR(30) | ❌ | `'active'` |
| `validatedAt` | TIMESTAMP | ✅ | NULL |
| `validatedBy` | INT | ✅ | NULL |
| `lastLoginAt` | TIMESTAMP | ✅ | NULL |
| `createdAt` | TIMESTAMP | ❌ | `CURRENT_TIMESTAMP` |

**Relations:** 
- ManyToOne: `RoleMetier` (ON DELETE SET NULL)
- OneToOne inverse: `Mecanicien` (userId)

**Métier:** Compte utilisateur. Authentification locale ou OAuth Google. Liaison multi-tenant via `atelierId` ET `RoleMetier` par atelier (cf `UserAtelierRole`). Synchronisation automatique ↔ `Mecanicien` si role = ROLE_MECANICIEN.

**Groupes sérialisation:** `user:read`, `user:write`

---

### ➤ Entité: `RendezVous` (Centrale)
**Table:** `rendez_vous`

| Colonne | Type | Nullable | Default |
|---|---|---|---|
| `id` | INT | ❌ | AUTO |
| `atelierId` | INT | ✅ | NULL |
| `client` | FK → `Client` | ✅ | NULL |
| `vehicule` | FK → `Vehicule` | ✅ | NULL |
| `dateRdv` | DATE | ❌ | — |
| `heureRdv` | TIME | ❌ | — |
| `typeIntervention` | VARCHAR(200) | ❌ | — |
| `commentaire` | TEXT | ✅ | NULL |
| `prixEstime` | DECIMAL(10,2) | ✅ | NULL |
| `prixFinal` | DECIMAL(10,2) | ✅ | NULL |
| `tempsEstime` | INT (min) | ✅ | NULL |
| `tempsFinal` | INT (min) | ✅ | NULL |
| `heureDebutTravail` | TIMESTAMP | ✅ | NULL |
| `heureFinTravail` | TIMESTAMP | ✅ | NULL |
| `tempsEffectifMinutes` | INT | ✅ | NULL |
| `kilometrage` | INT | ✅ | NULL |
| `etatVehicule` | TEXT | ✅ | NULL |
| `photosEtat` | TEXT | ✅ | NULL |
| `pont` | FK → `Pont` | ✅ | NULL |
| `mecanicien` | FK → `Mecanicien` | ✅ | NULL |
| `essaiRoutier` | OneToOne → `EssaiRoutier` | ✅ | NULL |
| `statut` | VARCHAR(50) | ❌ | `'en_attente'` |
| `motifAnnulation` | VARCHAR(50) | ✅ | NULL |
| `commentaireAnnulation` | TEXT | ✅ | NULL |
| `tokenPublic` | VARCHAR(64) | ✅ | NULL |
| `createdAt` | TIMESTAMP | ❌ | `CURRENT_TIMESTAMP` |

**Relations:**
- ManyToOne: `Client`, `Vehicule`, `Pont`, `Mecanicien`
- OneToOne: `EssaiRoutier` (inverse)
- OneToMany: `OrdreReparation`, `PhotoIntervention`, `DemandeTravauxSupp`, `PieceUtilisee`, `RapportIntervention` (indirect)

**Métier:** Centre du domaine atelier. Workflows: `rendez_vous` (state machine). Cycle: prise RDV → réception (saisie km) → intervention mécanicien → essai routier → restitution → facturation. `tokenPublic` pour Companion client (signature OR).

**Groupes sérialisation:** `rdv:read`, `rdv:write`, `ordre:read`

**Workflows:** `rendez_vous` state_machine

---

### ➤ Entité: `OrdreReparation`
**Table:** `ordres_reparation`

| Colonne | Type | Nullable | Default |
|---|---|---|---|
| `id` | INT | ❌ | AUTO |
| `rendezVous` | FK → `RendezVous` | ❌ | — |
| `numeroOr` | VARCHAR(50) | ❌ | — |
| `typeOr` | VARCHAR(50) | ❌ | `'initial'` |
| `kilometrage` | INT | ✅ | NULL |
| `etatVehicule` | TEXT | ✅ | NULL |
| `mechanicNotes` | TEXT | ✅ | NULL |
| `mechanicNotesUpdatedAt` | TIMESTAMP | ✅ | NULL |
| `mechanicCheckup` | TEXT (JSON) | ❌ | `'{}'` |
| `mechanicCheckupUpdatedAt` | TIMESTAMP | ✅ | NULL |
| `travaux` | TEXT | ✅ | NULL |
| `demandeTravauxSupp` | FK → `DemandeTravauxSupp` | ✅ | NULL |
| `signatureClient` | TEXT | ✅ | NULL |
| `statut` | VARCHAR(50) | ❌ | `'brouillon'` |
| `signedSnapshot` | JSON | ✅ | NULL |
| `signedHash` | VARCHAR(64) | ✅ | NULL |
| `signedAt` | TIMESTAMP | ✅ | NULL |
| `signedIp` | VARCHAR(45) | ✅ | NULL |
| `signedUserAgent` | VARCHAR(500) | ✅ | NULL |
| `rectifiedFrom` | FK → `OrdreReparation` (self) | ✅ | NULL |
| `motifRectification` | VARCHAR(100) | ✅ | NULL |
| `rectifiedBy` | INT | ✅ | NULL |
| `rectifiedAt` | TIMESTAMP | ✅ | NULL |
| **RGPD Snapshots:** | | | |
| `snapClientNom` | VARCHAR(200) | ✅ | NULL |
| `snapClientPrenom` | VARCHAR(200) | ✅ | NULL |
| `snapVehiculePlaque` | VARCHAR(20) | ✅ | NULL |
| `snapVehiculeMarque` | VARCHAR(100) | ✅ | NULL |
| `snapVehiculeModele` | VARCHAR(100) | ✅ | NULL |
| `createdAt` | TIMESTAMP | ❌ | `CURRENT_TIMESTAMP` |

**Métier:** Document contractuel signé par client AVANT intervention. Statuts: brouillon → signe → execute/termine → rectifié (si besoin). Figé après signature (listener `OrdreReparationFreezeListener`). Snapshots RGPD permettent anonymisation du client sans perdre l'historique légal.

**Groupes sérialisation:** `ordre:read`, `ordre:write`, `rdv:read`

---

### ➤ Entité: `Client`
**Table:** `clients`

| Colonne | Type | Nullable | Default |
|---|---|---|---|
| `id` | INT | ❌ | AUTO |
| `atelierId` | INT | ✅ | NULL |
| `nom` | VARCHAR(100) | ❌ | — |
| `prenom` | VARCHAR(100) | ❌ | — |
| `telephone` | VARCHAR(20) | ❌ | — |
| `email` | VARCHAR(200) | ✅ | NULL |
| `adresse` | TEXT | ✅ | NULL |
| `notes` | TEXT | ✅ | NULL |
| `createdAt` | TIMESTAMP | ❌ | `CURRENT_TIMESTAMP` |
| **RGPD:** | | | |
| `consentDate` | TIMESTAMP | ✅ | NULL |
| `consentSource` | VARCHAR(100) | ✅ | NULL |
| `lastActivityAt` | TIMESTAMP | ✅ | NULL |
| `isAnonymized` | BOOL | ❌ | `false` |

**Relations:**
- OneToMany: `Vehicule`, `RendezVous`

**Métier:** Contact client. Tracte consentement SMS/email (RGPD). `isAnonymized` = droit à l'oubli partiel (sauf pièces légales). Recherche full-text par nom/prénom.

**Groupes sérialisation:** `client:read`, `client:write`, `rdv:read`, `devis:read`, `facture:read`, `ordre:read`

---

### ➤ Entité: `Vehicule`
**Table:** `vehicules`

| Colonne | Type | Nullable | Default | Unique |
|---|---|---|---|---|
| `id` | INT | ❌ | AUTO | — |
| `atelierId` | INT | ✅ | NULL | — |
| `plaque` | VARCHAR(20) | ❌ | — | (atelierId, vin) |
| `marque` | VARCHAR(100) | ✅ | NULL | — |
| `modele` | VARCHAR(100) | ✅ | NULL | — |
| `annee` | INT | ✅ | NULL | — |
| `cylindree` | VARCHAR(50) | ✅ | NULL | — |
| `typeMoto` | VARCHAR(50) | ✅ | NULL | — |
| `typeVariante` | VARCHAR(100) | ✅ | NULL | — |
| `denominationCommerciale` | VARCHAR(100) | ✅ | NULL | — |
| `genreNational` | VARCHAR(20) | ✅ | NULL | — |
| `numeroFormuleCg` | VARCHAR(20) | ✅ | NULL | — |
| `client` | FK → `Client` | ✅ | NULL | — |
| `categorie` | FK → `CategorieMoto` | ✅ | NULL | — |
| `modeleRef` | FK → `ModeleMoto` | ✅ | NULL | — |

**Relations:**
- ManyToOne: `Client`, `CategorieMoto`, `ModeleMoto`
- OneToMany: `RendezVous`
- Uses trait: `VOTrait` (VO-specific fields)

**Métier:** Moto. Lien ↔ client (historique). Utilisé trait pour fields VO optionnels (VIN, PMEC, CT, couleur, etc). Recherche par plaque exacte.

**Groupes sérialisation:** `vehicule:read`, `vehicule:write`, `rdv:read`, `client:read`, `ordre:read`

---

### ➤ Entité: `Devis`
**Table:** `devis`

| Colonne | Type | Nullable | Default |
|---|---|---|---|
| `id` | INT | ❌ | AUTO |
| `atelierId` | INT | ✅ | NULL |
| `numeroDevis` | VARCHAR(50) | ❌ | — |
| `client` | FK → `Client` | ❌ | — |
| `vehicule` | FK → `Vehicule` | ✅ | NULL |
| `dateCreation` | TIMESTAMP | ❌ | `CURRENT_TIMESTAMP` |
| `dateValidite` | DATE | ❌ | — |
| `statut` | VARCHAR(50) | ❌ | `'brouillon'` |
| `kilometrage` | INT | ✅ | NULL |
| `totalMoHt` | DECIMAL(10,2) | ❌ | `'0.00'` |
| `totalPiecesHt` | DECIMAL(10,2) | ❌ | `'0.00'` |
| `totalHt` | DECIMAL(10,2) | ❌ | `'0.00'` |
| `totalTtc` | DECIMAL(10,2) | ❌ | `'0.00'` |
| `remisePourcentage` | DECIMAL(10,2) | ❌ | `'0.00'` |
| `remiseMontant` | DECIMAL(10,2) | ❌ | `'0.00'` |
| `acompteDemande` | DECIMAL(10,2) | ❌ | `'0.00'` |
| `notesClient` | TEXT | ✅ | NULL |
| `notesInternes` | TEXT | ✅ | NULL |
| `rendezVousId` | INT | ✅ | NULL |

**Relations:**
- ManyToOne: `Client`, `Vehicule`
- OneToMany: `LigneDevis`

**Métier:** Devis précontractuel (>150€ = obligatoire par loi). Numérotation unique. Validité contrôlée par `ConfigAtelier.validiteDevisJours` (défaut 30j, overridé par `DevisDateValiditeSubscriber`).

**Groupes sérialisation:** `devis:read`, `devis:write`

---

### ➤ Entité: `Facture`
**Table:** `factures`

| Colonne | Type | Nullable | Default |
|---|---|---|---|
| `id` | INT | ❌ | AUTO |
| `atelierId` | INT | ✅ | NULL |
| `numeroFacture` | VARCHAR(50) | ❌ | — |
| `rendezVous` | FK → `RendezVous` | ❌ | — |
| `client` | FK → `Client` | ❌ | — |
| `vehicule` | FK → `Vehicule` | ✅ | NULL |
| `nature` | VARCHAR(20) | ❌ | `'facture'` |
| `factureOrigine` | FK → `Facture` (self) | ✅ | NULL |
| `motifCorrection` | TEXT | ✅ | NULL |
| `totalMoHt` | DECIMAL(10,2) | ❌ | `'0.00'` |
| `totalPiecesHt` | DECIMAL(10,2) | ❌ | `'0.00'` |
| `totalHt` | DECIMAL(10,2) | ❌ | `'0.00'` |
| `tvaMo` | DECIMAL(10,2) | ❌ | `'0.00'` |
| `tvaPieces` | DECIMAL(10,2) | ❌ | `'0.00'` |
| `totalTva` | DECIMAL(10,2) | ❌ | `'0.00'` |
| `totalTtc` | DECIMAL(10,2) | ❌ | `'0.00'` |
| **Snapshots RGPD:** | | | |
| `snapClientNom` | VARCHAR(200) | ✅ | NULL |
| `snapClientPrenom` | VARCHAR(200) | ✅ | NULL |
| `snapVehiculePlaque` | VARCHAR(20) | ✅ | NULL |
| `snapVehiculeMarque` | VARCHAR(100) | ✅ | NULL |
| `snapVehiculeModele` | VARCHAR(100) | ✅ | NULL |
| `statut` | VARCHAR(50) | ❌ | `'emise'` |
| `createdAt` | TIMESTAMP | ❌ | `CURRENT_TIMESTAMP` |

**Statuts:** `emise`, `payee`, `partiellement_payee`, `corrigee`

**Relations:**
- ManyToOne: `RendezVous`, `Client`, `Vehicule`, `Facture` (factureOrigine)
- OneToMany: `LigneFacture`, `Paiement`

**Métier:** Facture légale (numérotation chrono, inégalité interdite). Immuable après émission → avoir seul rectificatif autorisé. Snapshots = conservation 10 ans, anonymisation possible. `FactureDeleteVoter` bloque suppression.

**Groupes sérialisation:** `facture:read`, `facture:write`

---

### ➤ Entité: `Prestation`
**Table:** `prestations`

| Colonne | Type | Nullable | Default |
|---|---|---|---|
| `id` | INT | ❌ | AUTO |
| `atelierId` | INT | ✅ | NULL |
| `code` | VARCHAR(50) | ❌ | — |
| `nom` | VARCHAR(200) | ❌ | — |
| `description` | TEXT | ✅ | NULL |
| `categorie` | VARCHAR(100) | ❌ | `'entretien'` |
| `sousCategorie` | VARCHAR(100) | ✅ | NULL |
| `prixBaseHt` | DECIMAL(10,2) | ❌ | `'0.00'` |
| `prixBaseTtc` | DECIMAL(10,2) | ❌ | `'0.00'` |
| `tempsEstimeMinutes` | INT | ❌ | `30` |
| `delaiInterventionJours` | INT | ❌ | `1` |
| `typeTarif` | VARCHAR(50) | ❌ | `'forfait'` |
| `tauxHoraireApplique` | VARCHAR(50) | ❌ | `'standard'` |
| `typeVehicule` | VARCHAR(50) | ❌ | `'tous'` |
| `cylindreeMin` | INT | ✅ | NULL |
| `cylindreeMax` | INT | ✅ | NULL |
| `isActive` | INT | ❌ | `1` |
| `isForfait` | INT | ❌ | `0` |
| `isPromo` | INT | ❌ | `0` |
| `prixPromoTtc` | DECIMAL(10,2) | ✅ | NULL |
| `inclutPieces` | INT | ❌ | `0` |
| `descriptionPiecesIncluses` | TEXT | ✅ | NULL |
| `coutPiecesInclusesHt` | DECIMAL(10,2) | ❌ | `'0.00'` |
| `margePiecesPourcent` | FLOAT | ❌ | `30.0` |
| `garantieJours` | INT | ✅ | NULL |
| `necessiteEssai` | BOOL | ❌ | `true` |
| `createdAt` | TIMESTAMP | ❌ | `CURRENT_TIMESTAMP` |
| `updatedAt` | TIMESTAMP | ❌ | `CURRENT_TIMESTAMP` |

**Relations:**
- OneToMany: `GrilleTarifaire` (cascade), `LigneDevis`, `LigneFacture`

**Métier:** Catalogue de prestations (MO + pièces incluses optionnelles). Tarification par grille (catégorie moto, cylindrée). `typeTarif` : forfait/horaire/sur_devis. Garantie configurable.

**Groupes sérialisation:** `prestation:read`, `prestation:write`

---

### ➤ Entité: `Pont`
**Table:** `ponts`

| Colonne | Type | Nullable | Default |
|---|---|---|---|
| `id` | INT | ❌ | AUTO |
| `atelierId` | INT | ✅ | NULL |
| `nom` | VARCHAR(100) | ❌ | — |
| `typePont` | VARCHAR(50) | ❌ | `'moto'` |
| `capaciteKg` | INT | ❌ | `500` |
| `isActive` | INT | ❌ | `1` |
| `ordreAffichage` | INT | ❌ | `0` |
| `mecanicien` | FK → `Mecanicien` | ✅ | NULL |

**Relations:**
- ManyToOne: `Mecanicien`
- OneToMany: `RendezVous`

**Métier:** Poste de travail atelier. Affectation optionnelle mécanicien. Utilisé pour planification RDV.

**Groupes sérialisation:** `pont:read`, `pont:write`, `rdv:read`

---

### ➤ Entité: `Mecanicien`
**Table:** `mecaniciens`

| Colonne | Type | Nullable | Default | Unique |
|---|---|---|---|---|
| `id` | INT | ❌ | AUTO | — |
| `atelierId` | INT | ✅ | NULL | — |
| `nom` | VARCHAR(100) | ❌ | — | — |
| `prenom` | VARCHAR(100) | ❌ | — | — |
| `specialites` | TEXT | ✅ | NULL | — |
| `couleur` | VARCHAR(7) | ❌ | `'#3b82f6'` | — |
| `isActive` | INT | ❌ | `1` | — |
| `userId` | INT | ✅ | NULL | `(user_id)` |

**Relations:**
- OneToMany: `RendezVous`
- OneToOne→: `User` (by userId, pas FK Doctrine)

**Métier:** Profil mécanicien. Lien nu `userId` (pas FK → pas cascade delete). Synchronisé par `UserMecanicienSyncSubscriber` lors CRUD User.

**Groupes sérialisation:** `mecanicien:read`, `mecanicien:write`, `rdv:read`, `pont:read`, `absence:read`

---

### ➤ Entité: `EssaiRoutier`
**Table:** `essai_routier`

| Colonne | Type | Nullable | Default |
|---|---|---|---|
| `id` | INT | ❌ | AUTO |
| `atelierId` | INT | ✅ | NULL |
| `rendezVous` | OneToOne → `RendezVous` | ❌ | — |
| `kmDebut` | INT | ✅ | NULL |
| `kmFin` | INT | ✅ | NULL |
| `dureeMinutes` | INT | ✅ | NULL |
| `distance` | DECIMAL(6,2) | ✅ | NULL |
| `pointsControle` | JSON | ✅ | 10 checkpoints |
| `anomalies` | TEXT | ✅ | NULL |
| `actionsCorrectives` | TEXT | ✅ | NULL |
| `signatureMecanicien` | TEXT | ✅ | NULL |
| `realiseAt` | TIMESTAMP | ✅ | NULL |
| `mecanicienId` | INT | ✅ | NULL |
| `observations` | TEXT | ✅ | NULL |
| `statut` | VARCHAR(30) | ❌ | `'en_cours'` |
| `validatedAt` | TIMESTAMP | ✅ | NULL |
| `createdAt` | TIMESTAMP | ❌ | `CURRENT_TIMESTAMP` |

**Statuts:** `brouillon`, `valide`, `anomalie_detectee`

**Relations:**
- OneToOne: `RendezVous` (inverse)

**Métier:** Test routier OBLIGATOIRE avant clôture RDV (`RdvTerminationGuardSubscriber`). 10 points contrôle structurés (freinage, direction, suspension, moteur, boîte, embrayage, éclairage, bruits, tableau bord, comportement). Signature mécanicien = validation.

---

### ➤ Entité: `RapportIntervention`
**Table:** `rapport_intervention`

| Colonne | Type | Nullable | Default |
|---|---|---|---|
| `id` | INT | ❌ | AUTO |
| `atelierId` | INT | ✅ | NULL |
| `rendezVous` | FK → `RendezVous` | ❌ | — |
| `statut` | VARCHAR(50) | ❌ | `'brouillon'` |
| `travauxRealises` | TEXT | ✅ | NULL |
| `alertes` | JSON | ✅ | NULL |
| `recommandations` | TEXT | ✅ | NULL |
| `prochaineRevisionKm` | INT | ✅ | NULL |
| `prochaineRevisionDate` | DATE | ✅ | NULL |
| `kilometrageRestitution` | INT | ✅ | NULL |
| `garantie` | TEXT | ✅ | NULL |
| `signatureMecanicien` | TEXT | ✅ | NULL |
| `signeMecanicienAt` | TIMESTAMP | ✅ | NULL |
| `signeMecanicienId` | INT | ✅ | NULL |
| `signatureClient` | TEXT | ✅ | NULL |
| `signeClientAt` | TIMESTAMP | ✅ | NULL |
| `signedSnapshot` | JSON | ✅ | NULL |
| `signedHash` | VARCHAR(64) | ✅ | NULL |
| `signedIp` | VARCHAR(45) | ✅ | NULL |
| `rectifiedFrom` | FK → `RapportIntervention` (self) | ✅ | NULL |
| `motifRectification` | VARCHAR(200) | ✅ | NULL |

**Statuts:** `brouillon`, `en_validation`, `signe`, `rectifie`

**Relations:**
- ManyToOne: `RendezVous`

**Métier:** Document résumé des travaux réalisés + signature électronique mécanicien + client (à la restitution). Généré auto à `terminer` RDV via `RapportInterventionService`. Bloquant pour fin intervention (`RdvTerminationGuardSubscriber`).

---

### ➤ Entité: `DemandeTravauxSupp`
**Table:** `demandes_travaux_supp`

| Colonne | Type | Nullable | Default |
|---|---|---|---|
| `id` | INT | ❌ | AUTO |
| `rendezVous` | FK → `RendezVous` | ❌ | — |
| `description` | TEXT | ✅ | NULL |
| `prestationsDemandees` | TEXT | ✅ | NULL |
| `urgence` | VARCHAR(50) | ❌ | `'normal'` |
| `tempsEstime` | INT (min) | ✅ | NULL |
| `prixEstime` | DECIMAL(10,2) | ✅ | NULL |
| `statut` | VARCHAR(50) | ❌ | `'en_attente'` |
| `notesReceptionniste` | TEXT | ✅ | NULL |
| `decisionClient` | VARCHAR(50) | ✅ | NULL |
| `decisionClientAt` | TIMESTAMP | ✅ | NULL |
| `createdAt` | TIMESTAMP | ❌ | `CURRENT_TIMESTAMP` |
| `approvedAt` | TIMESTAMP | ✅ | NULL |
| `approvedBy` | INT | ✅ | NULL |
| `tokenValidation` | VARCHAR(64) | ❌ | (unique) |
| `prestationsChoisies` | JSON | ❌ | `[]` |
| `photosJustificatives` | TEXT | ✅ | NULL |
| `decisionIp` | VARCHAR(45) | ✅ | NULL |
| `decisionUserAgent` | VARCHAR(500) | ✅ | NULL |
| `signatureClient` | TEXT | ✅ | NULL |
| `signedAt` | TIMESTAMP | ✅ | NULL |
| `orComplementaire` | FK → `OrdreReparation` | ✅ | NULL |

**Statuts:** `en_attente`, `en_attente_validation`, `en_attente_decision_client`, `accepte`, `refuse`

**Métier:** Travaux complémentaires détectés par mécanicien. Workflow: demande → validation resp atelier → envoi client (SMS + token) → signature client → création OR complémentaire si acceptation.

**Groupes sérialisation:** `demande:read`, `demande:write`, `rdv:read`

---

### ➤ Entité: `VOPurchase`
**Table:** `vo_purchases`

| Colonne | Type | Nullable | Default |
|---|---|---|---|
| `id` | INT | ❌ | AUTO |
| `atelierId` | INT | ✅ | NULL |
| `vehicule` | FK → `Vehicule` | ✅ | NULL |
| `seller` | FK → `Client` | ✅ | NULL |
| `expert` | FK → `User` | ✅ | NULL |
| `status` | VARCHAR(20) | ❌ | `'brouillon'` |
| `sivStatus` | VARCHAR(20) | ❌ | `'a_preparer'` |
| `sivReference` | VARCHAR(120) | ✅ | NULL |
| `sivRecordedAt` | TIMESTAMP | ✅ | NULL |
| **Snapshots Companion:** | | | |
| `companionToken` | VARCHAR(64) | ✅ | NULL |
| `companionExpiresAt` | TIMESTAMP | ✅ | NULL |
| `createdAt` | TIMESTAMP | ❌ | `CURRENT_TIMESTAMP` |

**Statuts VOPurchase:** `brouillon`, `en_stock`, `en_vente`, `reserve`, `vendu`

**Statuts SIV:** `a_preparer`, `en_cours`, `enregistree`, `rejetee`, `expiree`

**Relations:**
- ManyToOne: `Vehicule`, `Client` (seller), `User` (expert)
- OneToMany: `VORemiseEnEtat`, `VOLivrePolice`, `VOFacture`, `VODocument`
- Trait: `VOCompanionTrait` (companion signature)

**Métier:** Workflow d'achat VO. Déclaration d'Achat (DA) SIV obligatoire dans 15 jours (bloquant pour revente). Remise en état (`VORemiseEnEtat`). LP + Facture VO + Mandat SIV.

**Groupes sérialisation:** `vo:read`, `vo:write`

**Workflows:** `vo_purchase` state_machine

---

### ➤ Entité: `VODepotVente`
**Table:** `vo_depot_ventes`

| Colonne | Type | Nullable | Default |
|---|---|---|---|
| `id` | INT | ❌ | AUTO |
| `atelierId` | INT | ✅ | NULL |
| `vehicule` | FK → `Vehicule` | ✅ | NULL |
| `deposant` | FK → `Client` | ✅ | NULL |
| `gestionnaire` | FK → `User` | ✅ | NULL |
| `prixVenteSouhaite` | DECIMAL(10,2) | ❌ | — |
| `commissionType` | VARCHAR(20) | ❌ | `'pourcentage'` |
| `commissionValeur` | DECIMAL(10,2) | ❌ | — |
| `dateDebut` | DATE | ❌ | — |
| `dateFin` | DATE | ✅ | NULL |
| `dureeJours` | INT | ❌ | `90` |
| **Snapshots Companion:** | | | |
| `companionToken` | VARCHAR(64) | ✅ | NULL |
| `companionExpiresAt` | TIMESTAMP | ✅ | NULL |
| `createdAt` | TIMESTAMP | ❌ | `CURRENT_TIMESTAMP` |

**Relations:**
- ManyToOne: `Vehicule`, `Client` (deposant), `User` (gestionnaire)
- OneToMany: `VORemiseEnEtat`, `VOLivrePolice`, `VOFacture`, `VODocument`
- Trait: `VOCompanionTrait`

**Métier:** Dépôt-vente (Art. 1915 Code civil). Mandat durée définie (90j défaut). Commission % ou forfait. Reversement net 15j max. TVA comm seule. Immuable après signature (LP).

**Groupes sérialisation:** `vo:read`, `vo:write`

**Workflows:** `vo_depot` state_machine

---

### ➤ Entité: `VORemiseEnEtat`
**Table:** `vo_remises_en_etat`

| Colonne | Type | Nullable | Default |
|---|---|---|---|
| `id` | INT | ❌ | AUTO |
| `atelierId` | INT | ✅ | NULL |
| `voPurchase` | FK → `VOPurchase` | ✅ | NULL |
| `voDepotVente` | FK → `VODepotVente` | ✅ | NULL |
| `campaignIndex` | INT | ❌ | `1` |
| `titre` | VARCHAR(180) | ❌ | `'Remise en etat VO'` |
| `status` | VARCHAR(40) | ❌ | `'a_chiffrer'` |
| `priority` | VARCHAR(20) | ❌ | `'normale'` |
| `diagnosticNotes` | TEXT | ✅ | NULL |
| `workshopNotes` | TEXT | ✅ | NULL |
| `businessNotes` | TEXT | ✅ | NULL |
| `requestedBy` | FK → `User` | ✅ | NULL |
| `validatedBy` | FK → `User` | ✅ | NULL |
| `requestedAt` | TIMESTAMP | ✅ | NULL |
| `validatedAt` | TIMESTAMP | ✅ | NULL |
| `createdAt` | TIMESTAMP | ❌ | `CURRENT_TIMESTAMP` |

**Statuts:** `a_chiffrer`, `a_valider`, `validee`, `pieces_a_commander`, `en_attente_pieces`, `planifiee_atelier`, `en_cours`, `terminee`, `cloturee`, `annulee`

**Priorités:** `basse`, `normale`, `haute`, `urgente`

**Relations:**
- ManyToOne: `VOPurchase`, `VODepotVente` (XOR check), `User` (requestedBy, validatedBy)
- OneToMany: `VORemiseEnEtatLigne` (travaux), `VORemiseEnEtatPiece` (pièces)

**Métier:** FRE (Frais de Remise en État). Estimation de coûts + liste travaux/pièces. `VOMarginService` calcule marge RTE temps réel.

---

### ➤ Entité: `VOFacture`
**Table:** `vo_factures`

| Colonne | Type | Nullable | Default |
|---|---|---|---|
| `id` | INT | ❌ | AUTO |
| `atelierId` | INT | ✅ | NULL |
| `numeroFacture` | VARCHAR(50) | ❌ | — |
| `voPurchase` | FK → `VOPurchase` | ✅ | NULL |
| `voDepotVente` | FK → `VODepotVente` | ✅ | NULL |
| `client` | FK → `Client` | ❌ | — |
| `vehicule` | FK → `Vehicule` | ✅ | NULL |
| `regimeTva` | VARCHAR(10) | ❌ | `'marge'` |
| `prixAchatHt` | DECIMAL(10,2) | ✅ | NULL |
| `mentionTvaMarge` | BOOL | ❌ | `true` |
| `totalHt` | DECIMAL(10,2) | ❌ | `'0.00'` |
| `totalTva` | DECIMAL(10,2) | ❌ | `'0.00'` |
| `totalTtc` | DECIMAL(10,2) | ❌ | `'0.00'` |
| `statut` | VARCHAR(20) | ❌ | `'brouillon'` |
| **Snapshots RGPD:** | | | |
| `snapClientNom` | VARCHAR(200) | ✅ | NULL |
| `snapVehiculePlaque` | VARCHAR(20) | ✅ | NULL |
| `createdAt` | TIMESTAMP | ❌ | `CURRENT_TIMESTAMP` |

**Statuts:** `brouillon`, `emise`, `payee`, `partiellement_payee`, `corrigee`, `annulee`

**Régimes TVA:** `marge` (Art.297A) | `normal` (Art.256) — JAMAIS MÉLANGER

**Relations:**
- ManyToOne: `VOPurchase`, `VODepotVente`, `Client`, `Vehicule`
- OneToMany: `Paiement`

**Métier:** Facture VO. Numérotation auto unique. TVA régime exclusif (marge OU normal). Snapshots immuables. `VOFactureDeleteVoter` bloque suppression.

**Groupes sérialisation:** `vofacture:read`, `vofacture:write`

---

### ➤ Entité: `VOLivrePolice`
**Table:** `vo_livre_police`

| Colonne | Type | Nullable | Default |
|---|---|---|---|
| `id` | INT | ❌ | AUTO |
| `atelierId` | INT | ✅ | NULL |
| `numeroOrdre` | INT | ❌ | — |
| `type` | VARCHAR(20) | ❌ | — |
| `dateAcquisition` | DATE | ❌ | — |
| `dateVente` | DATE | ✅ | NULL |
| **Snapshots légaux:** | | | |
| `vendeurNom` | VARCHAR(200) | ✅ | NULL |
| `acheteurNom` | VARCHAR(200) | ✅ | NULL |

**Relations:** —

**Métier:** Registre légal IMMUABLE (Art. 321-7 Code Pénal). 2 lignes par VO : entrée achat + sortie vente. Pas de PUT/PATCH/DELETE API (lecture seule). Numérotation continue sans trous.

**Groupes sérialisation:** `livrepolice:read` (lecture seule)

---

### ➤ Entité: `VODocument`
**Table:** `vo_documents`

| Colonne | Type | Nullable | Default |
|---|---|---|---|
| `id` | INT | ❌ | AUTO |
| `atelierId` | INT | ✅ | NULL |
| `voPurchase` | FK → `VOPurchase` | ✅ | NULL |
| `voDepotVente` | FK → `VODepotVente` | ✅ | NULL |
| `type` | VARCHAR(50) | ❌ | — |
| `filename` | VARCHAR(300) | ❌ | — |
| `mimeType` | VARCHAR(100) | ✅ | NULL |
| `fileSize` | INT | ✅ | NULL |
| `uploadedAt` | TIMESTAMP | ❌ | `CURRENT_TIMESTAMP` |
| `uploadedBy` | INT | ✅ | NULL |
| `retentionExpiresAt` | TIMESTAMP | ✅ | NULL |

**Types:** `cerfa_cession_achat`, `cerfa_cession_vente`, `carte_grise`, `non_gage`, `controle_technique`, `piece_identite` (**0j**), `justificatif_domicile` (**0j**), `contrat_depot_vente`, `da_siv`, `recepisse_da`, `mandat_immatriculation`, `facture_vo`, `pv_rachat`, `remise_en_etat`, `notice_garantie`, `photo_vehicule`, `autre`

**Rétention RGPD:** 5 ans (défaut) | 0 ans (pièce identité, justificatif domicile → refus upload + destruction après transcription)

**Métier:** Stockage documents VO. Droits: upload ROLE_VO_MANAGER seul. Pièce d'identité + justificatif = refus côté API + UI (RGPD, 0j rétention).

**Groupes sérialisation:** `vodoc:read`, `vodoc:write`

---

### ➤ Entité: `ConfigAtelier`
**Table:** `config_atelier`

| Colonne | Type | Nullable | Default |
|---|---|---|---|
| `id` | INT | ❌ | AUTO |
| `atelierId` | INT | ✅ | NULL |
| `featureModules` | JSON | ❌ | (18 modules par défaut) |
| `tauxHoraireMoStandard` | DECIMAL(10,2) | ❌ | `'65.00'` |
| `tauxHoraireMoComplexe` | DECIMAL(10,2) | ❌ | `'85.00'` |
| `tauxHoraireMoExpert` | DECIMAL(10,2) | ❌ | `'95.00'` |
| `margePiecesStandard` | FLOAT | ❌ | `30.0` |
| `margePiecesConsommable` | FLOAT | ❌ | `50.0` |
| `margePiecesPneumatique` | FLOAT | ❌ | `25.0` |
| `forfaitMoMinimum` | DECIMAL(10,2) | ❌ | `'25.00'` |
| `tvaMoTaux` | FLOAT | ❌ | `20.0` |
| `tvaPiecesTaux` | FLOAT | ❌ | `20.0` |
| `validiteDevisJours` | INT | ❌ | `30` |
| `accomptePourcentage` | FLOAT | ❌ | `30.0` |
| `delaiRelance1JoursOuvres` | INT | ❌ | `15` |
| `delaiRelance2JoursOuvres` | INT | ❌ | `30` |
| `delaiProposeGardiennageJoursOuvres` | INT | ❌ | `45` |
| `delaiProcedureAbandonJoursOuvres` | INT | ❌ | `180` |
| `tarifGardiennageJournalier` | DECIMAL(10,2) | ❌ | `'5.00'` |
| `garantieTravauxJours` | INT | ❌ | `30` |
| `joursFermetureHebdo` | JSON | ❌ | `["sunday"]` |
| `datesFermetureExceptionnelles` | JSON | ❌ | `[]` |
| `dureeDefautMandatJours` | INT | ❌ | `90` |
| `regimeTvaVoDefault` | VARCHAR(10) | ❌ | `'marge'` |
| `updatedAt` | TIMESTAMP | ❌ | `CURRENT_TIMESTAMP` |

**Relations:** —

**Métier:** Configuration par atelier (singleton par `atelierId`). Contrôle modules actifs, taux horaires, marges, durées, TVA, gardiennage, mandat VO. `DevisDateValiditeSubscriber` résout `validiteDevisJours`.

**Groupes sérialisation:** `config:read`, `config:write`

---

### ➤ Entité: `AuditLog`
**Table:** `audit_logs`

| Colonne | Type | Nullable | Default |
|---|---|---|---|
| `id` | INT | ❌ | AUTO |
| `atelierId` | INT | ✅ | NULL |
| `userId` | INT | ✅ | NULL |
| `username` | VARCHAR(100) | ✅ | NULL |
| `action` | VARCHAR(100) | ❌ | — |
| `entityType` | VARCHAR(100) | ✅ | NULL |
| `entityId` | INT | ✅ | NULL |
| `details` | TEXT | ✅ | NULL |
| `ipAddress` | VARCHAR(50) | ✅ | NULL |
| `createdAt` | TIMESTAMP | ❌ | `CURRENT_TIMESTAMP` |

**Métier:** Traçabilité actions sensibles. Immutable (append-only). Filtrages: username, action, entityType, ipAddress, date. Paginé 50 items/page max 200.

**Groupes sérialisation:** — (ROLE_SUPER_ADMIN seul)

---

### ➤ Entité: `Notification`
**Table:** `notifications`

| Colonne | Type | Nullable | Default |
|---|---|---|---|
| `id` | INT | ❌ | AUTO |
| `atelierId` | INT | ✅ | NULL |
| `targetUserId` | INT | ✅ | NULL |
| `targetRole` | VARCHAR(100) | ✅ | NULL |
| `targetRoles` | JSON | ❌ | `[]` |
| `type` | VARCHAR(100) | ❌ | — |
| `severity` | VARCHAR(20) | ❌ | `'info'` |
| `title` | VARCHAR(255) | ❌ | — |
| `message` | VARCHAR(500) | ❌ | — |
| `actionUrl` | VARCHAR(500) | ✅ | NULL |
| `relatedEntityType` | VARCHAR(100) | ✅ | NULL |
| `relatedEntityId` | INT | ✅ | NULL |
| `readAt` | TIMESTAMP | ✅ | NULL |
| `readBy` | INT | ✅ | NULL |
| `acknowledgedAt` | TIMESTAMP | ✅ | NULL |
| `acknowledgedBy` | INT | ✅ | NULL |
| `isRead` | BOOL | ❌ | `false` |
| `priority` | VARCHAR(50) | ❌ | `'normal'` |
| `createdAt` | TIMESTAMP | ❌ | `CURRENT_TIMESTAMP` |

**Métier:** Notifications in-app. Publiées via Mercure (temps réel). Cible rôle/utilisateur/atelierId. Severity: info/warning/error.

**Groupes sérialisation:** `notif:read`, `notif:write`

---

### ➤ Entité: `NotificationTemplate`
**Table:** `notification_templates`

| Colonne | Type | Nullable | Default |
|---|---|---|---|
| `id` | INT | ❌ | AUTO |
| `atelierId` | INT | ❌ | — |
| `code` | VARCHAR(100) | ❌ | — |
| `channel` | VARCHAR(20) | ❌ | — |
| `libelle` | VARCHAR(200) | ❌ | — |
| `sujet` | VARCHAR(500) | ✅ | NULL |
| `corps` | TEXT | ❌ | — |
| `variables` | JSON | ❌ | `[]` |
| `isActive` | BOOL | ❌ | `true` |
| `createdAt` | TIMESTAMP | ❌ | `CURRENT_TIMESTAMP` |
| `updatedAt` | TIMESTAMP | ❌ | `CURRENT_TIMESTAMP` |

**Channels:** `email`, `sms`, `push`

**Métier:** Templates notifications configurables. Substitution variables. `NotificationDispatcher` les utilise. Unique par (atelierId, code, channel).

---

### ➤ Entité: `NotificationLog`
**Table:** `notification_logs`

| Colonne | Type | Nullable | Default |
|---|---|---|---|
| `id` | INT | ❌ | AUTO |
| `atelierId` | INT | ❌ | — |
| `channel` | VARCHAR(20) | ❌ | — |
| `provider` | VARCHAR(50) | ❌ | — |
| `templateCode` | VARCHAR(100) | ✅ | NULL |
| `toRecipient` | VARCHAR(255) | ❌ | — |
| `subject` | VARCHAR(500) | ✅ | NULL |
| `status` | VARCHAR(30) | ❌ | — |
| `providerMessageId` | VARCHAR(255) | ✅ | NULL |
| `errorMessage` | TEXT | ✅ | NULL |
| `sentAt` | TIMESTAMP | ❌ | — |
| `deliveredAt` | TIMESTAMP | ✅ | NULL |
| `readAt` | TIMESTAMP | ✅ | NULL |
| `createdAt` | TIMESTAMP | ❌ | `CURRENT_TIMESTAMP` |

**Métier:** Historique envois emails/SMS. Statuts: sent, delivered, bounced, failed, opened, clicked. Audit immuable.

---

### ➤ Autres entités importantes (lectures compressées)

| Entité | Table | Métier bref |
|---|---|---|
| `LigneDevis` | `lignes_devis` | Ligne devis (MO/pièce). Calcul montants. |
| `LigneFacture` | `lignes_facture` | Ligne facture. Snapshot immuable post-émission. |
| `PieceDetachee` | `pieces_detachees` | Pièce catalogue. Catégorie, prix achat/vente, marge. |
| `PieceUtilisee` | `pieces_utilisees` | Junction RDV ↔ pièce. Quantité, prix vente. |
| `PhotoIntervention` | `photos_intervention` | Photo RDV (en_cours, apres_travaux, restitution, probleme). SHA256, EXIF, annotation JSON. |
| `GrilleTarifaire` | `grille_tarifaire` | Tarif par prestation × catégorie moto × cylindrée. |
| `Paiement` | `paiements` | Encaissement/remboursement facture atelier ou VO. Type opération, mode (espèces/CB/virement/chèque). |
| `ForfaitMO` | `forfaits_mo` | Forfait temps MO. Promo, pièces incluses optionnelles. |
| `Fournisseur` | `fournisseurs` | Fournisseur pièces. Délai livraison, contact. |
| `CommandeFournisseur` | `commandes_fournisseur` | Commande fournisseur. Suivi statut réception. |
| `CommandePiece` | `commande_piece` | Ancienn table → OBSOLÈTE (remplacée par CommandeFournisseur + LigneCommandeFournisseur). |
| `Absence` | `absences` | Absence mécanicien (période). Validation absence chevauchante via `AbsenceValidationSubscriber`. |
| `RoleMetier` | `roles_metier` | Rôle métier configurable par atelier. Template parent. Permissions héritées. |
| `RolePermission` | `role_permissions` | Permissions par rôle legacy (super_admin, admin, user, ...). Voter: `RolePermissionVoter`. |
| `UserAtelierRole` | `user_atelier_roles` | Junction User ↔ Atelier ↔ RoleMetier. Composite PK (userId, atelierId). |
| `RevokedToken` | `revoked_tokens` | JWT révoqués. Table immuable. Vérifié à chaque requête. |
| `AnnulationRdv` | `annulation_rdv` | Historique annulations RDV. Motifs: client_desiste, no_show, piece_non_disponible, etc. |
| `VORemiseEnEtatLigne` | `vo_remise_en_etat_lignes` | Ligne travaux FRE. Prestation liée optionnelle. |
| `VORemiseEnEtatPiece` | `vo_remise_en_etat_pieces` | Pièce FRE. Reference, quantité, statut (en_stock, a_commander, commandee, recue, montee, annulee). |
| `ModeleMoto` | `modele_motos` | Marque + modèle moto. Relations specs techniques. |
| `CategorieMoto` | `categorie_motos` | Catégorie moto (grosse trail, naked, scooter, etc). Utilisée grille tarifs + CT.  |
| `MotoTechnicalSpec` | `moto_technical_specs` | Specs techniques moto par génération. JSON: general, moteur, pneumatique, freinage, suspension, systèmes électriques, entretien. |
| `VOCounter` | `vo_counters` | Séquence numérotation VO (LP, factures VO, DA). Compteur par année + atelier. |
| `NotificationProviderConfig` | `notification_provider_configs` | Config providers SMS/email par atelier. Chiffré. Primary/Fallback. Testable. |
| `NotificationEscalation` | — (logique) | Escalade auto si non-réponse. Délais: 5/10/30min (configurable). |
| `Module` | `modules` | Catalogue modules (dashboard, rdv, planning, workshop, mecanicien, VO, stock, facturation, absences, tarifs, admin, suivi, clients, motos, devis, ordres, notifications, clauses-legales). |
| `InterventionType` | `intervention_types` | **OBSOLÈTE** — Remplacé par `Prestation` + `GrilleTarifaire`. |
| `ClauseLegale` | `clause_legale` | Clauses légales (mentions facturation, garanties, conditions générales). Versionning. Effective_from. |
| `RolePermissionEntry` | `role_permission_entries` | Entrée permissions granulaires (PERM_rdv.edit, PERM_facture.delete, etc). Héritage template. |

---

## 6. MIGRATIONS DOCTRINE (40 versions)

| Version | Date | Description |
|---|---|---|
| **20260101000000** | 2026-01-01 | Bootstrap initial (réservé) |
| **20260416115054** | 2026-04-16 | RGPD: snapshots client/véhicule devis/factures, champs consentement client |
| **20260416171207** | 2026-04-16 | (non analysée) |
| **20260417033458** | 2026-04-17 | (non analysée) |
| **20260417041023** | 2026-04-17 | Notification infrastructure: tables templates, logs, provider configs, notifications |
| **20260417043703** | 2026-04-17 | PhotoIntervention: +type, +sha256, +exif, +takenAt (LOT 2) |
| **20260417043854** | 2026-04-17 | AnnulationRdv: table création/suppression (test) |
| **20260417044250** | 2026-04-17 | (non analysée) |
| **20260417044805** | 2026-04-17 | Gardiennage: +delaiRelance1/2, +delaiProposeGardiennage, +delaiProcedureAbandon, +tarifGardiennageJournalier, +garantieTravauxJours, +joursFermetureHebdo, +datesFermetureExceptionnelles sur ConfigAtelier. RDV: +emplacement_stockage, +photo_stockage_filename, +mis_en_stockage_at/par, +gardiennage_debut_at/par/motif. CommandePiece (test). |
| **20260417045135** | 2026-04-17 | ClauseLegale: table test create/drop |
| **20260417082000** | 2026-04-17 | User: +prenom, +nom (test add/drop) |
| **20260417100000** | 2026-04-17 | RoleMetier + Module + RolePermissionEntry + User.roleMetier FK |
| **20260417110000** | 2026-04-17 | (non analysée) |
| **20260417120000** | 2026-04-17 | (non analysée) |
| **20260417130000** | 2026-04-17 | (non analysée) |
| **20260417150000** | 2026-04-17 | (non analysée) |
| **20260417183000** | 2026-04-17 | (non analysée) |
| **20260418091500** | 2026-04-18 | VOPurchase.vehicule/seller: nullable (test) |
| **20260420170500** | 2026-04-20 | Grille tarifs anciennes → test cleanup (calculs_tarifs, grille_tarifs, temps_interventions) |
| **20260420194500** | 2026-04-20 | (non analysée) |
| **20260421080200** | 2026-04-21 | Paiement: +atelierId, +typeOperation ('encaissement') |
| **20260422081500** | 2026-04-22 | (non analysée) |
| **20260422115453** | 2026-04-22 | (non analysée) |
| **20260422130000** | 2026-04-22 | (non analysée) |
| **20260423090000** | 2026-04-23 | CerfaFieldConfig: table test create/drop |
| **20260423130422** | 2026-04-23 | RapportTechnicien: table (DEBT: probablement ancien/doublon) |
| **20260423130654** | 2026-04-23 | Mecanicien.userId: FK → User (ON DELETE SET NULL) |
| **20260424153000** | 2026-04-24 | (non analysée — current date) |
| **20260604120000** | 2026-06-04 | VO Module: Vehicule (+mileage, +vin, +is_a2_compatible, +date_premiere_mise_en_circulation, +couleur, +registration_cost, +options_and_accessories, +controle_technique_date, +controle_technique_resultat). Puis création VO tables (vo_purchases, vo_depot_ventes, vo_livre_police, vo_documents, vo_factures) + Paiement.vo_facture_id. Droits: ROLE_VO_MANAGER. |
| **20260604130000** | 2026-06-04 | (non analysée) |
| **20260604140000** | 2026-06-04 | (non analysée) |
| **20260604170000** | 2026-06-04 | VOCounter: table création/suppression test |
| **20260604180000** | 2026-06-04 | VORemiseEnEtat + VORemiseEnEtatLigne + VORemiseEnEtatPiece: tables, FK, CHECK (source XOR), cascade delete |
| **20260604190000** | 2026-06-04 | (non analysée) |
| **20260604193000** | 2026-06-04 | (non analysée) |
| **20260604200000** | 2026-06-04 | VOPurchase: +siv_status, +siv_reference, +siv_recorded_at, +siv_notes (DA SIV tracking) |
| **20260604213000** | 2026-06-04 | (non analysée) |
| **20260605093000** | 2026-06-05 | (non analysée) |
| **20260605100000** | 2026-06-05 | Vehicule: +type_variante, +denomination_commerciale, +genre_national, +numero_formule_cg (CARTE GRISE fields) |

---

## 7. RÉSUMÉ DOMAINE

### Cycle métier atelier (workflow RendezVous)
```
Prise RDV (planning)
  → Réception (km, état, photos, signature OR client Companion)
    → Intervention mécanicien (notes, checkup JSON, demandes complémentaires)
      → Essai routier obligatoire (10 points contrôle, signature mécanicien)
        → Rapport intervention (travaux réalisés, km restitution, signature mécanicien + client)
          → Restitution (signature client au comptoir)
            → Facturation (numérotation, snapshots RGPD)
              → Paiement (encaissement multi-mode)
```

### Cycle métier VO (workflows VOPurchase + VODepot)
```
Rachat (VOPurchase):
  brouillon → en_stock (DA SIV à_preparer) → en_vente → reserve → vendu
  └─→ Remise en État (FRE) parallèle
  └─→ Livre de Police (2 lignes: entrée + sortie)
  └─→ Facture VO (TVA marge ou normal)
  └─→ Mandat SIV + DA SIV (15 jours obligatoire)

Dépôt-vente (VODepot):
  brouillon → actif → vendu / restitue
  └─→ Commission % ou forfait
  └─→ Reversement net 15j max
  └─→ Durée mandat (90j défaut, renouvelable)
```

### Entités centrales (61 totales)
- **Atelier** (multi-tenant root)
- **User** ↔ Mecanicien (1:0..1 sync auto)
- **RendezVous** (state machine, central hub)
- **OrdreReparation** (figé post-signature, snapshots RGPD)
- **EssaiRoutier** (obligatoire avant clôture)
- **RapportIntervention** (signature mécanicien + client)
- **DemandeTravauxSupp** (escalade client, token validation)
- **Facture** (immuable, voter delete bloqué, snapshots RGPD)
- **VOPurchase** / **VODepotVente** / **VORemiseEnEtat** (VO workflow)
- **VOLivrePolice** (immutable register)
- **VOFacture** (TVA régime exclusif)
- **Notification** / **NotificationTemplate** / **NotificationLog** (stack notifications)
- **ConfigAtelier** (singleton par atelier)
- **Paiement**, **Prestation**, **GrilleTarifaire**, **Devis**, **PhotoIntervention**, etc.

---

**FIN DU RAPPORT**