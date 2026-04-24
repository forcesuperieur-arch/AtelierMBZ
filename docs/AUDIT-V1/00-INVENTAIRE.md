# AUDIT-V1 — Inventaire exhaustif AtelierMBZ

> Généré : 2026-04-23 — Source : exploration automatisée (sub-agent Explore).
> Modules en réécriture **exclus** des analyses détaillées : `stock/`, `facturation/`, `StockController`, `FacturationController`.

## 1. Backend — Entités (≈62)

| Entité | Table | atelierId | snap_* | Notes |
|---|---|---|---|---|
| Atelier | ateliers | n/a | — | Hub multi-tenant |
| User | users | ✓ | — | Identité + rôles |
| Client | clients | ✓ | — | |
| Vehicule | vehicules | ✓ | — | |
| RendezVous | rendez_vous | ✓ | — | Workflow principal |
| OrdreReparation | ordres_reparation | (à vérifier) | snap_client*, snap_vehicule* | Snapshot RGPD |
| Facture | factures | ✓ | snap_client*, snap_vehicule*, snap_atelier* | 10 ans |
| RapportIntervention | rapport_intervention | ✓ | signed_snapshot | |
| **RapportTechnicien** | rapports_technicien | ✗ | — | **DETTE PROBABLE — duplication** |
| EssaiRoutier | essai_routier | ✓ | — | |
| PhotoIntervention | photos_intervention | ✓ | — | |
| DemandeTravauxSupp | demandes_travaux_supp | ✓ | — | |
| Devis / LigneDevis | devis / lignes_devis | ✓ | — | |
| LigneFacture | lignes_factures | ✓ | — | |
| Prestation / GrilleTarifaire | prestations / grille_tarifaire | ✓ | — | |
| PieceDetachee / Fournisseur | pieces_detachees / fournisseurs | ✓ | — | (Stock — non analysé) |
| ConfigAtelier | config_atelier | ✓ | — | Tarifs, marges, délais, features |
| Mecanicien | mecaniciens | ✓ | — | `userId` ?int **sans FK Doctrine** |
| Pont / PontEquipement | ponts / pont_equipements | ✓ | — | |
| Absence | absences | ✓ | — | |
| AuditLog | audit_logs | ✓ | — | |
| NotificationTemplate / NotificationProviderConfig / Notification / NotificationEscalation / NotificationLog | notif_* | ✓ | — | Architecture en 5 entités |
| UserAtelierRole / RoleMetier / RolePermission / RolePermissionEntry | — | ✓ | — | |
| RevokedToken | revoked_tokens | ✓ | — | JWT revocation |
| VOPurchase | vo_purchases | ✓ | — | Workflow VO |
| VOFacture | vo_factures | ✓ | snap_* régime TVA | 10 ans |
| **VOLivrePolice** | vo_livre_police | ✓ | — | **IMMUABLE Art. 321-7 CP** |
| VODocument | vo_documents | ✓ | — | RETENTION_YEARS (0 pour pièce ID) |
| VORemiseEnEtat / VORemiseEnEtatLigne / VORemiseEnEtatPiece | vo_remise_en_etat* | ✓ | — | FRE |
| VODepotVente | vo_depot_vente | ✓ | — | Mandat + reverse |
| MotoTechnicalSpec / CategorieMoto / AtelierCategorieMoto / InterventionType | — | ✓ | — | Catalogue |
| HoraireAtelier | horaires_atelier | ✓ | — | |
| ClauseLegale | clauses_legales | — | — | Mentions PDF par rôle |
| **EmailTemplate** | email_templates | — | — | **ORPHELIN** (remplacé par NotificationTemplate) |
| RappelEmail | rappels_email | ✓ | — | |
| CerfaFieldConfig | cerfa_field_config | ✗ | — | Positions champs CERFA |
| VOCounter | vo_counters | ✓ | — | Numérotation séquences |
| Paiement | paiements | ✓ | — | |
| AnnulationRdv | annulations_rdv | ✓ | — | |

### Snapshots RGPD figés
- `OrdreReparation.snap_client*/snap_vehicule*` (figé à signature)
- `Facture.snap_*` (10 ans, comptable)
- `RapportIntervention.signed_snapshot` (JSON, figé après signature mécanicien)
- `VOFacture.snap_*` (régime TVA + atelier, 10 ans)

## 2. Backend — Controllers (≈39)

Routes principales et permissions. Détails par controller :

| Controller | Permission |
|---|---|
| RendezVousController | ROLE_USER |
| OrdreReparationController | ROLE_ADMIN (rectify) |
| StockController | (en réécriture) |
| FacturationController | (en réécriture) |
| PhotoController | ROLE_USER |
| CompanionController | Public token |
| PublicVoCompanionController | Public token |
| PublicBookingController | Public + module guard |
| MecanicienController | ROLE_USER |
| RapportInterventionController | ROLE_USER |
| AdminAtelierController | ROLE_ADMIN |
| AdminUserProvisioningController | ROLE_SUPER_ADMIN |
| AdminTemplatePreviewController | ROLE_ADMIN |
| ClientStatsController | ROLE_USER |
| SlotController | ROLE_USER |
| VehiculeLookupController | ROLE_USER |
| MotosLookupController | ⚠ permission à vérifier |
| ConfigController | ROLE_USER |
| StatistiquesController | ROLE_USER |
| NotificationController / NotificationProviderController | ROLE_USER / ROLE_ADMIN |
| GardiennageController | ROLE_USER |
| DemandeTravauxSuppController | ROLE_USER |
| VOController / VORemiseEnEtatController | ROLE_VO_MANAGER |
| OrdreReparationPdfController | ROLE_USER |
| HistoriqueEntretienController | ROLE_USER |
| ClauseLegaleController | Public |
| SuiviController | Public token |
| RdvPrestationCatalogController | ROLE_USER |
| PontStatusController | ROLE_USER |
| **HealthController** | **PUBLIC** (intentionnel — load balancer) |
| AuthController | Public (login) / ROLE_USER (refresh) |
| DevisController | ROLE_USER |
| RendezVousFacturationCompatController | ROLE_USER |

## 3. Backend — Services (≈38)

PricingService, RapportInterventionService, RendezVousWorkflowService, VOMarginService, VODocumentService, AuditService, NotificationDispatcher, GardiennageService, OrdreReparationPolicy, PhotoService, SlotService, BookingAtelierAccessService, ClauseLegaleVisibilityService, HistoriqueEntretienService, UserMecanicienSyncService, UserArchiveService, ConfigEncryptionService, JoursOuvresService, MotoCatalogImporter, NotificationTemplateCatalog, NotificationProviderConfigSanitizer, PrestationCatalogService, MercureNotifier, VORemiseEnEtatService, VOCompanionWorkflowService, VOLivrePoliceService, VONumberingService, VOGeneratedDocumentService, CerfaOverlayService, CurrentAtelierResolver, UserRoleMapper, AdminConfigValidator, AtelierCatalogBootstrapService, PdfService, NotificationMessage, NotificationResult, VORemiseEnEtatDocumentService.

## 4. Backend — Listeners / Subscribers (13)

| Listener | Événement | Rôle |
|---|---|---|
| TenantFilterListener | preLoadClassMetadata | Active filtre WHERE atelier_id |
| TenantSetterListener | prePersist | Pose atelierId sur nouvelles entités |
| OrdreReparationFreezeListener | postPersist/postUpdate | Snapshot après signature |
| RdvWorkflowListener | workflow.* | Cascades création OR / notifs / logs |
| UserSecurityListener | onAuth* | JWT refresh, audit login |
| ApiDebugExceptionListener | kernel.exception | Format erreur API |
| SecurityHeadersListener | kernel.response | HSTS, CSP |
| UserMecanicienSyncSubscriber | postPersist (User) | Sync User ↔ Mecanicien |
| UserRoleMetierSyncSubscriber | postUpdate (User) | Sync rôles |
| DevisDateValiditeSubscriber | prePersist/preUpdate | Valide durée devis |
| AbsenceValidationSubscriber | prePersist/preUpdate | Conflit créneau |
| RdvTerminationGuardSubscriber | workflow.terminer | Bloque clôture si essai/rapport KO |
| UserPasswordHashSubscriber | prePersist/preUpdate | Hash password |

## 5. Backend — Workflow

### State machine `rendez_vous`
**Places** : en_attente, reserve, confirme, reception, en_cours, en_pause, termine, restitue, facture, paye, annule, en_attente_pieces, en_attente_reprise, en_gardiennage, restitue_partiel, no_show
**Transitions clés** : reserver, confirmer, reception, start_travail, mettre_en_pause, reprendre, mettre_en_attente_pieces, reprendre_apres_pieces, terminer, restituer, restituer_partiel, facturer, payer, annuler, declarer_no_show, reporter, mettre_en_gardiennage, sortir_gardiennage, retour_garantie.

### State machine `vo_purchase`
**Places** : brouillon, en_stock, en_vente, reserve, vendu
**Transitions** : confirmer, mettre_en_vente, retirer_de_la_vente, reserver, liberer, vendre

## 6. Migrations
36 fichiers (avril → juin 2026 dans le projet, dates futures cohérentes avec contexte). Analyse détaillée hors scope inventaire.

## 7. Tests existants
- **Backend** : 25 Unit + 18 Functional ≈ 187 tests
- **Frontend Vitest** : 6 fichiers, 19 tests
- **Playwright E2E** : 9 fichiers (auth, business-flows, navigation, non-regression, notifications, notification-providers, roles-workflow, vo-companion-flow, vo-pricing-diff)

## 8. Pages Frontend (≈58 .vue)

Voir détail dans le rapport sub-agent. Modules en réécriture exclus :
- `facturation/index.vue` — NON ANALYSÉ
- `stock/index.vue` — NON ANALYSÉ

## 9–11. Composants / Composables / Stores

- **18 composants** (AppPageHeader, AppErrorState, AppEmptyState, AppLoadingState, AppBanner, AppNotificationBell, NotificationPopIn, AppModal, AppPdfEmbed, UTable, StatusBadge, StatsCard, PlanningGrid, SidebarLink, vo/VONav, vo/VODossierMotoCard, vo/VOCompanionCard, vo/VORemiseEnEtatCard)
- **14 composables** (useApi, useAuth, useFormat, useValidation, useNotifications, usePdfDownload, useQrCode, useDebounceFn, useMotoAutocomplete, useCarteGriseOcr, useVoHelpers, voVehicleForm, voCompanionDraftSync, voRefurbishmentCard)
- **7 stores Pinia** (app, auth, atelier, rdv, stock, billing, vo)

## 12. Templates PDF Twig (13)

facture, devis, ordre_reparation, rapport_intervention, vo_facture, vo_pv_rachat, vo_contrat_depot_vente, vo_mandat_immatriculation, vo_da_siv, vo_livre_police, vo_remise_en_etat, historique_entretien, _paddock_footer.

**Aucun "PRO MOTO" hardcodé détecté** — utilisation systématique de `{{ atelier.* }}`.

## 13. Configuration

`config/packages/` : api_platform, cache, doctrine, doctrine_migrations, framework, framework_test, lexik_jwt_authentication, lock, mailer, mercure, messenger, nelmio_cors, notifier, rate_limiter, routing, security, twig, validator, **workflow**.

## 15. Anomalies repérées (pour audit critique)

### 🔴 Critique
1. **RapportTechnicien vs RapportIntervention** — duplication probable, table `rapports_technicien` orpheline ?
2. **VOLivrePolice immuabilité** — déclarée mais à confirmer côté API Platform (méthodes HTTP exposées)
3. **Mecanicien.userId** — `?int` nu sans FK Doctrine, risque orphelins

### 🟠 Important
4. **EmailTemplate** — table orpheline (legacy avant NotificationTemplate)
5. **HealthController PUBLIC** — intentionnel, à vérifier qu'aucune info sensible ne fuit
6. **Facture suppression** — pas de FacturePolicy équivalent à OrdreReparationPolicy
7. **TenantFilter** — bypass silencieux si entité sans `atelierId`
8. **MotosLookupController** — permission non claire

### 🔵 Confort
9. **PdfService** — vérifier logging des erreurs
10. **36 migrations en 2 mois** — vérifier réversibilité
