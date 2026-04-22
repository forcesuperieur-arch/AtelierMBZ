# 🚀 PLAN DIRECTEUR DE REFONTE ET D'AMÉLIORATION GLOBALE (AtelierMBZ)

Ce document établit la feuille de route pour propulser l'application **AtelierMBZ** au maximum de son potentiel, en consolidant l'architecture existante, en supprimant la dette technique, et en poussant l'expérience utilisateur (UX) et les interfaces (UI) à un niveau premium, tout en respectant scrupuleusement la stack actuelle (Symfony 7.2 / API Platform / Nuxt 3).

---

## 🛠️ 1. BACKEND : CONSOLIDATION & NETTOYAGE (Symfony 7.2 + API Platform 4.1)

### 🧹 Éradication de la Dette Technique et Doublons
- **Suppression de `RapportTechnicien`** : Confirmer l'obsolescence et purger l'entité, ses repositories, controllers et UI associées (remplacé par `RapportIntervention`).
- **Mutualisation des Traits** : Regrouper les logiques répétitives (upload de documents, tracking de dates, signature Companion) dans des traits PHP (`CompanionSignableTrait`, `AuditTrackableTrait`).
- **Nettoyage des Endpoints API** : Réduire le nombre de endpoints custom (`Controller/`) au strict minimum en déportant la logique métier vers les `StateProcessors` et `StateProviders` natifs d'API Platform.

### ⚙️ Refonte et Optimisation des Workflows (Symfony Workflow)
- **Ordre Impératif de Restitution (Atelier)** : Brancher un `EventSubscriber` sur le workflow `rendez_vous` pour bloquer rigoureusement la clotûre si l'Essai Routier (km_debut, km_fin, anomalies) n'est pas dûment signé.
- **Workflow Dépôt-Vente & VO** :
  - Lier la logique TVA conditionnelle pour isoler systématiquement l'Art. 297A CGI (Marge) et Art. 256 CGI (Normale).
  - Injecter la TVA **uniquement sur la commission** pour le dépôt-vente.
- **Workflow DA SIV** : Supprimer l'étape obligatoire `en_cours`. Rendre la transition `a_preparer` ➔ `enregistree` directe pour fluidifier.

### 🛡️ Sécurité et Traçabilité Max (RGPD + Audit)
- **TenantFilter Infaillible** : Vérifier que `TenantFilterListener` ne peut être court-circuité par aucune sous-requête (GraphQL / Subresource) sauf si `$user->hasRole('ROLE_SUPER_ADMIN')`.
- **Rétention Zéro Jour** : Script automatique asynchrone (via Symfony Messenger) qui purge automatiquement de la base les `TYPE_PIECE_IDENTITE` et `TYPE_JUSTIFICATIF_DOMICILE` dès leur transcription dans le Livre de Police.

---

## 💻 2. FRONTEND : ARCHITECTURE & PERFORMANCES (Nuxt 3 + Pinia + Nuxt UI v3)

### 🧩 Composants Nuxt UI & Suppression de CSS Custom
- Refonte des tableaux lourds (`PlanningGrid`, gestion VO) en utilisant extensivement `<UTable>` avec pagination server-side systématique.
- Normalisation de toutes les modales, alertes et formulaires pour utiliser `<UModal>`, `<UCard>`, `<UFormField>`, `<UToast>`.
- Remplacement du CSS custom (générateur de dette) par les utilitaires Tailwind CSS sous-jacents de Nuxt UI.

### 📡 Optimisation API et State Management
- Stopper le fetch asynchrone aveugle (`itemsPerPage=2000`) : généraliser le lazy-loading par semaine via `ApiFilter(DateFilter)` sur les pages critiques (comme `planning.vue`).
- Standardisation sticte de la `Composition API` : `<script setup lang="ts">` systématique, suppression des vestiges format Options API s'il en reste.

---

## 🎨 3. UI/UX "AU MAX" : EXPERIENCES MÉTIER PREMIUM

### 📱 L'Espace Mécanicien ("Mobile & Hands-Dirty First")
- **Refonte UI de `mecanicien.vue`** : 
  - Interface tactile ultra-large (Zones de tap ≥ 48px).
  - Suppression totale du scroll vertical sur les modales.
  - Déclenchement One-Tap de la caméra (`<input capture="environment">`) pour les photos d'intervention sans navigation complexe.
- **Visibilité en 1 coup d'œil** : Le mécanicien doit voir son prochain véhicule, l'OR client annoté, ses pièces commandées, l'accès chrono, sans charger toute la vue du garage.

### 👔 La Réception (Le HUB Ultra-Rapide)
- **Création de RDV Intelligente** : Auto-complétion via API (HistoVec, SIV) par saisie de plaque d'immatriculation ➔ Préremplit Modèle, Cylindrée, MEC, Type.
- **OR "A tiroirs" pour Companion** : Sur la tablette comptoir, l'OR s'affiche en vue "Client" simple, épurée, avec grand encart pour lire les Conditions Générales et cocher (obligatoire).

### 💰 L'Espace VO & Dépôt-Vente (Tableau de Bord Centralisé)
- **Verdict Vendabilité Dynamique** : Une `<UBadge>` ou "Progress Bar" qui montre ce qui manque pour vendre le VO ("Manque DA SIV", "CT expiré").
- **Dépôt Vente Auto** : 
  - Modal "Modélisation Commission" simulant dynamiquement "Combien le client touche" vs "Combien l'atelier gagne" avant la signature du mandat.
  - Bouton "Renouveler" envoyant le mail interactif en 1 clic.

---

## 🤖 4. ADD-ONS ET AUTOMATISATIONS PENSÉES "AGENT / SMART"

### 🔔 Moteur de Relance Intelligent (NotificationEscalation)
- **T+5, T+10, T+30** : Si le client ne valide pas un "Travail Supplémentaire" via SMS, escalade automatique d'un SMS discret vers un Appel sortant (Alerte rouge chez le réceptionniste) puis suspension des travaux.
- **Gardiennage Automatisé** : CRON job passant les motos abandonnées en `en_gardiennage` au jour J+3, émettant un mail formaté de mise en garde.
- **SIV Alerte J-7** : Notification Mercure pushée au gestionnaire VO `"DA SIV expirant dans 7 jours sur Z750"`.

### 📄 Motorisation Documentaire (Twig -> PDF)
- **OR Rattachés Consolidés** : Un OR initial (150€) + une Demande de travail supplémentaire via SMS validée (50€) génèrent UN SEUL rapport PDF consolidé sous forme de facturation, mais un OR PDF en deux volets légaux avec double "signatures temporelles".
- **Cerfa Auto-filling** : Interdiction totale de ressaisie. Tout signataire (Vendeur, Acheteur) Companion envoie sa payload à `PdfService` qui dresse les 13751, 15776, 13757 au millimètre près.

---

## 🗓️ 5. PLAN D'ATTAQUE (ORDRE D'EXÉCUTION RECOMMANDÉ)

1. **Phase 1 : Éradication de la Dette Base de Données (Backend)**
   - Suppression du `RapportTechnicien` + Nettoyage schema `doctrine`.
   - Setup des `ConfigAtelier` pour Dépôt-Vente (duree, typeCommission).
2. **Phase 2 : Solidification des Workflows & Automatisations (Backend)**
   - Réparation du Workflow `Ordre essai routier` avant `Rapport d'intervention`.
   - Câblage de l'escalade SMS sur `DemandeTravauxSupp`.
3. **Phase 3 : Migration UI Composants Nuxt V3 (Frontend)**
   - Uniformiser le design-system via `<UCard>`, `<UModal>`.
   - Modernisation de la Vue Mécanicien (Mobile First).
4. **Phase 4 : Les Super-Powers SIV & VO (Full Stack)**
   - Intégration du OCR Carte Grise systématique et du Verdict Vendabilité (VODocumentService).
   - Flux web "Renouvellement mandat Dépôt-Vente" via lien magique.