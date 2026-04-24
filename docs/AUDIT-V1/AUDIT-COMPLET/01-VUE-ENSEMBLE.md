# Chapitre 1 — Vue d'ensemble

## 1.1 Ce que fait AtelierMBZ

AtelierMBZ est une **application de gestion d'atelier moto** destinée à un **réseau franchisé** (pas un SaaS commercial ouvert : pas de plans tarifaires actifs, pas d'inscription libre). Chaque franchisé est une entité juridique distincte (SIRET propre, TVA intra propre, responsable de traitement RGPD indépendant) liée par un contrat de franchise. Le multi-tenant row-level isole les données par établissement sur une base commune.

L'application couvre **deux grandes activités**, branchées sur la même base :

1. **Atelier mécanique moto** — prise de rendez-vous, réception physique, intervention mécanicien, essai routier, restitution, facturation. C'est le cœur de l'usage quotidien.
2. **Véhicules d'occasion (VO)** — rachat à un particulier, remise en état, mise en vente, dépôt-vente, livre de police, déclaration d'achat SIV, facture VO (régime marge ou normal). Module activable par atelier.

Autour de ces deux activités gravitent : clients, véhicules, catalogue moto et prestations, devis, ordres de réparation signés, photos d'intervention, demandes de travaux complémentaires, gardiennage, absences mécaniciens, comptabilité, notifications multi-canaux (email + SMS + Mercure temps réel), audit, paramétrage par atelier, gestion des rôles métier configurables.

### Stade actuel

**Beta prod soft** : en usage réel avec de vrais clients sur un atelier franchisé. Chaque bug impacte de vraies données. Priorité absolue : consolidation du flux principal RDV → réception → mécanicien → restitution.

### Modules en réécriture (non couverts par cet audit en profondeur)

- **Stock** — gestion des pièces détachées : en réécriture
- **Facturation** — refonte en cours

Ces modules existent dans le code, sont documentés dans les chapitres techniques, mais ne doivent **pas être modifiés** sans demande explicite.

## 1.2 Les métiers utilisateurs

Sept profils principaux interagissent avec l'application. Chacun a un écran d'entrée, des contraintes propres et des pièges à éviter.

### Le mécanicien (atelier — tablette/PDA)

- 6 à 10 interventions par jour, mains sales, peu de temps clavier
- Support principal : tablette ou smartphone (mobile-first)
- Écran : `/mecanicien`
- Voit ses RDV du jour (filtrés sur `Mecanicien.userId`)
- Démarre/arrête le chrono d'intervention
- Saisit notes techniques dans `OrdreReparation.mechanic_notes`
- Prend des photos via `PhotoController` (types : `en_cours`, `apres_travaux`, `restitution`, `probleme`)
- Crée des demandes de travaux complémentaires
- Remplit et signe l'essai routier puis le rapport d'intervention
- **Ne saisit jamais** : prix, durée estimée, kilométrage de réception
- **Ne touche jamais** `rdv.commentaire` (réservé à la réception)

### Le réceptionnaire (comptoir + téléphone)

- Accueil physique, prise de RDV, accueil client, restitution
- Écran principal : `/planning`
- Prend RDV (`/rdv/new`) : recherche client par nom/plaque, crée si nouveau, affecte pont + mécanicien
- **Réceptionne** la moto (transition `reception`) : saisit le km réel, checkup extérieur, photos, signature OR client via Companion
- Valide les demandes de travaux complémentaires (audité via `AuditService`)
- Restitue (signature client sur rapport)
- Gère no-show, reports, gardiennage

### Le responsable d'atelier (chef méca)

- Pilote la production, équilibre la charge, gère urgences
- Écrans : `/workshop`, `/admin/absences`
- Valide les OR rectificatifs (`OrdreReparationPolicy`)
- Supervise gardiennage, retours sous garantie
- `garantieTravauxJours` configurable (défaut 30j)

### Le responsable magasin / directeur

- Suit CA, marge, rentabilité par activité (`StatistiquesController`)
- Arbitre remises et gestes commerciaux
- Gère les litiges (escalade T+30min SMS via `NotificationEscalation`)

### Le gestionnaire VO (`ROLE_VO_MANAGER`)

- Rachat, remise en état, vente, dépôt-vente
- Écrans : `/vo/rachats/`, `/vo/depots/`, `/vo/remises-en-etat/`, `/vo/livre-police`, `/vo/factures`
- OCR carte grise (`useCarteGriseOcr`), génération PV rachat PDF, inscription LP, DA SIV
- Companion VO (`/public/vo-companion`) pour signature électronique vendeur/déposant
- Verdict vendabilité automatique (`VODocumentService`) avec motifs hiérarchisés (légal, RGPD, workflow, atelier)

### Le comptable (`ROLE_COMPTABLE`)

- Consulte factures atelier et VO
- Enregistre encaissements (`Paiement` : espèces, CB, virement, chèque)
- Vérifie cohérence TVA, exporte FEC
- Suppression facture émise **interdite** : annulation par avoir uniquement

### Le super-admin (`ROLE_SUPER_ADMIN`)

- Crée/configure ateliers, groupes, utilisateurs
- Paramètre prestations, grilles tarifaires
- Gère rôles métier paramétrables et permissions granulaires
- Suit l'audit log global
- **Bypass total du `TenantFilter`** : toute action est auditée systématiquement via `AuditService::log()`

### Le client final (sans compte)

- Reçoit SMS/email de confirmation RDV
- Signe l'OR sur PDA du réceptionniste via Companion (`/public/companion/{token}`)
- Valide/refuse demande de travaux complémentaires via lien SMS (`/public/demande/{token}`)
- Suit son dossier via page publique (`/public/suivi/{token}`)
- Peut prendre RDV lui-même via `/public/booking`
- Pour le VO : signe via `/public/vo-companion/{token}`
- Tokens jamais en query string, toujours en segment de chemin

## 1.3 Architecture en bref

### Backend

PHP 8.3, Symfony 7.2, API Platform 4.1, Doctrine ORM, PostgreSQL 15, Symfony Workflow (state machines), Lexik JWT, DomPDF + Twig, Symfony Messenger.

### Frontend

Nuxt 3 (mode SPA, `ssr: false`), Vue 3 Composition API (`<script setup lang="ts">`), Pinia, Nuxt UI v3, composables maison (`useApi`, `useAuth`, `useFormat`, etc.).

### Infrastructure

Docker Compose : services `php`, `nuxt`, `worker`, `caddy`, `postgres`, `mercure`, `mailhog`. Caddy comme reverse proxy. `docker-compose.preprod.yml` pour la préprod dédiée.

### Multi-tenant row-level

Toutes les entités opérationnelles ont `atelierId`. `TenantFilter` Doctrine applique `WHERE atelier_id = X` automatiquement, activé par `TenantFilterListener` depuis le JWT (`atelier_id` dans le payload). `TenantSetterListener` pose `atelierId` sur les nouvelles entités. `ROLE_SUPER_ADMIN` bypass total.

### Modules par atelier

`ConfigAtelier.featureModules` (JSON) active/désactive des modules par atelier. Modules : `dashboard`, `rdv`, `rdv_siege`, `planning`, `workshop`, `suivi`, `clients`, `or`, `motos`, `devis`, `facturation`, `stock`, `mecanicien`, `absences`, `admin`, `tarifs`, `vo` (désactivé par défaut).

## 1.4 Séquence workflow atelier — l'ordre est impératif

```
Prise de RDV (planning)
  → Réception physique (km relevé, état, carburant, photos, signature OR client)
    → Intervention mécanicien (checkup, notes méca, photos, demandes complémentaires)
      → Essai routier (obligatoire, km début/fin, points de contrôle)
        → Rapport d'intervention signé mécanicien
          → Restitution (signature client sur rapport)
            → Facturation
```

### Règles de saisie kilométrage — immuables

- **Prise de RDV** : JAMAIS de km
- **Réception** (`/planning`, transition `reception`) : km réel relevé au comptoir → `RendezVous.kilometrage`
- **Rapport d'intervention** (`RapportIntervention.kilometrageRestitution`) : km saisi par le mécanicien à la fin de l'essai routier

### OR vs Rapport d'intervention — deux documents distincts

| | `OrdreReparation` | `RapportIntervention` |
|---|---|---|
| Qui le crée | Système à la réception | Mécanicien à la clôture |
| Qui le signe | Client (avant intervention) | Mécanicien puis client (à la restitution) |
| Contenu | Prestations à réaliser, devis, accord client | Travaux réalisés, alertes, km restitution, prochaine révision, essai routier, photos |
| Figé après signature | OUI (`OrdreReparationFreezeListener`) | OUI |
| Table | `ordres_reparation` | `rapport_intervention` |
| Template PDF | `ordre_reparation.html.twig` | `rapport_intervention.html.twig` |

## 1.5 Subtilités à connaître

### Subtilités métier

- **Mécanicien jamais d'estimation** : ni prix ni temps. Ces champs sont saisis par le réceptionnaire.
- **Notes mécanicien** : vont dans `OrdreReparation.mechanic_notes`. Le champ `RendezVous.commentaire` est réservé à la réception et ne doit jamais être écrasé par le mécanicien.
- **Essai routier obligatoire avant `terminer`** : zéro exception. Bloqué par `RdvTerminationGuardSubscriber`.
- **OR signé = figé** : seuls Resp. Atelier ou Resp. Magasin peuvent faire un OR rectificatif via `OrdreReparationPolicy::rectify()`.
- **3 modes tarification** : FORFAIT / HORAIRE / SUR_DEVIS (cf. `ModeTarificationTest`).
- **Garantie travaux atelier** : 30j par défaut, configurable dans `ConfigAtelier.garantieTravauxJours`.
- **Garantie légale VO** : 12 mois minimum (Art. L.217-3 CC).

### Subtilités VO

- **Sans DA SIV `enregistree`, le véhicule est invendable** — bloquant vérifié par `VODocumentService::getPurchaseSaleBlockers()`.
- **Livre de Police immuable** : pas de PUT/PATCH/DELETE (Art. 321-7 CP). Lecture seule côté API.
- **Numérotation LP et factures VO** via `VONumberingService` qui utilise des séquences PostgreSQL (jamais MAX+1, sinon race condition).
- **TVA marge vs TVA normale** sur facture VO : régimes exclusifs, jamais mélangés (Art. 297 A vs Art. 256 CGI).
- **Pièce d'identité et justificatif de domicile** : `RETENTION_YEARS = 0`. Refus d'upload côté API ET côté UI. Transcription dans LP puis destruction.
- **Companion VO** : signatures électroniques par token unique (segment de chemin, pas query string), durée d'expiration configurable.
- **Verdict vendabilité** : exposé par `VODocumentService` avec motifs hiérarchisés (légal, RGPD, workflow, atelier).

### Subtilités sécurité

- **Tokens Companion** : non-devinables, longs, en segment de chemin (jamais query string). QR code généré localement (`useQrCode`), jamais via service externe.
- **Photos d'intervention** : appartiennent à l'étape mécanicien, pas à la réception. Endpoint `/api/photos` n'est appelé que depuis `/mecanicien`. Photos de réception viennent du Companion.
- **Rôles/permissions** : ne sont jamais stockés dans le `localStorage`. Le contexte est rechargé depuis le serveur à chaque bootstrap (`useAuth().fetchMe()`).
- **`/api/docs`** : non public en préprod. Seul `/api/health` est public.
- **`setStatut()` direct interdit** : toujours passer par une transition workflow. Sinon les listeners `RdvWorkflowListener` et le guard d'essai routier sont contournés.
- **Bypass `ROLE_SUPER_ADMIN`** : court-circuite le `TenantFilter`. Toute action super-admin doit être auditée via `AuditService::log()`.

### Subtilités RGPD

- Durée de conservation codée dans `VODocument::RETENTION_YEARS` :
  - Factures : 10 ans
  - CERFA cession, carte grise, non-gage, contrat dépôt-vente, PV rachat, mandat immat, récépissé DA, certificat situation admin, copie permis, CT : 5 ans
  - **Pièce d'identité : 0 ans** (à détruire après transcription)
  - **Justificatif de domicile : 0 ans**
- **Droit à l'effacement** : exclut les données liées à facture ou OR signé. D'où les colonnes `snap_*` figées au moment de l'émission/signature qui permettent l'anonymisation du client/véhicule sans perdre la traçabilité légale.
- **Anonymisation** = nullification des FK + conservation des snapshots. Jamais de suppression de facture, OR, LP.
- **Cron `app:rgpd-purge`** (1er du mois 3:00) purge clients 3 ans inactifs, devis > 6 mois, audit > 3 ans.
- **Cron `app:purge-identity-documents`** (chaque jour 4:00) détruit les pièces d'identité résiduelles.

### Subtilités UX

- Pour chaque fonctionnalité, 5 questions :
  1. Quelle donnée peut être remplie automatiquement ? (OCR CG, recherche par plaque, historique, grille tarifaire)
  2. Quelle décision peut être proposée par défaut ? (durée estimée, pont affecté, régime TVA)
  3. Quelle action peut être déclenchée en cascade ? (confirmation rachat → LP + PV + DA SIV)
  4. Quelle info peut être visible sans clic ? (marge, jours stock, docs manquants, verdict vendabilité)
  5. Quel rappel anticipé ? (DA SIV J+10, mandat J-7, non-gage expiré, CT expiré)
- **Si une info est en base, elle ne doit jamais être redemandée. Si une action est déductible, elle doit être proposée.**

## 1.6 Cadre réglementaire

### RGPD

- Minimisation, finalité, durée de conservation par type de document
- Sécurité : chiffrement au repos, accès par rôle, audit trail (`AuditLog`)
- Consentement : opt-in séparé pour marketing, pas besoin pour notifications transactionnelles
- DPA obligatoire avec sous-traitants (SMS, email)

### Documents obligatoires côté ACHAT VO

| Document | Format | Obligation | Conservation |
|---|---|---|---|
| Certificat de cession (Cerfa 15776*02) | Papier/PDF signé | Art. R322-4 Code route | 5 ans |
| Carte grise barrée + signée | Original | Art. R322-4 | 5 ans (copie) |
| Certificat situation administrative (< 15j) | PDF ANTS/HistoVec | Art. R322-4 | 5 ans |
| Pièce d'identité vendeur | Vérif visuelle | LP | **0 jour — détruire** |
| Justificatif de domicile (< 3 mois) | Si demandé | LP | **0 jour — détruire** |
| Contrôle technique (moto > 5 ans) | PV ou vignette | Art. R323-22 | 5 ans |
| PV de rachat | Signé 2 parties | Preuve transaction | 5 ans |

Côté SIV : DA (Cerfa 13751) dans les 15 jours (Art. R322-4). Sans DA enregistrée, véhicule invendable.

### Documents obligatoires côté VENTE VO

| Document | Conservation |
|---|---|
| Facture VO (PDF) | 10 ans |
| Certificat de cession vente (Cerfa 15776*02) | 5 ans |
| Récépissé DA + carte grise ancienne barrée | remis à l'acheteur |
| Certificat situation admin < 15j | 5 ans |
| CT (> 5 ans, < 6 mois à la vente) | remis à l'acheteur |
| Notice garantie légale (L.217-3 + 1641 CC) | 10 ans |
| Mandat immatriculation (Cerfa 13757*03) si habilité SIV | 5 ans |

Mentions facture VO : régime TVA exclusif — soit marge (Art. 297 A CGI), soit normale (Art. 256 CGI). Jamais les deux.

### Dépôt-vente VO (Art. 1915 Code civil)

- LP enregistre 2 lignes : entrée dépôt + vente
- TVA uniquement sur la commission
- Reversement sous 15 jours max
- Mandat durée définie (souvent 90j), prolongeable
- Pas de DA : le déposant reste propriétaire jusqu'à la vente

### Réglementation atelier moto

- Devis obligatoire > 150 € (Art. R.112-1 Code consommation)
- OR signé avant intervention
- Travaux complémentaires : accord client explicite avant exécution (workflow `DemandeTravauxSupp`)
- Facturation (Art. 289 CGI) : numérotation continue, mentions obligatoires
- Garantie légale sur pièces et MO (Art. L.217-3) : min 2 ans pièces neuves
- CT moto obligatoire depuis 15/04/2024 (Décret 2023-974) : 5 ans après 1ère immat, puis tous les 3 ans

### Archivage comptable

- 10 ans pour pièces comptables (Art. L.123-22 Code commerce)
- 3 ans pour documents fiscaux hors factures
- FEC : format officiel d'export pour contrôle fiscal (Art. L.47 A-I LPF)

## 1.7 Règles métier non négociables

1. Le mécanicien n'estime jamais (ni prix ni temps)
2. Le mécanicien ne touche jamais `rdv.commentaire`
3. Essai routier obligatoire avant `terminer` — zéro exception
4. OR signé = figé — seuls Resp. Atelier ou Resp. Magasin peuvent faire un rectificatif
5. Le kilométrage n'est jamais saisi à la prise de RDV — uniquement à la réception
6. 3 modes tarification : FORFAIT / HORAIRE / SUR_DEVIS
7. Demandes complémentaires : workflow avec escalade T+5/10/30min
8. Livre de Police immuable : pas de PUT/PATCH/DELETE
9. DA SIV dans les 15 jours : obligatoire, bloquant pour la revente
10. Garantie travaux atelier : 30j par défaut, configurable dans `ConfigAtelier`
11. Garantie légale VO : 12 mois minimum
12. Toute action sensible est auditée via `AuditService::log()`

## 1.8 Anti-patterns à éviter

- Hardcoder le nom de l'atelier ("PRO MOTO") dans un template → utiliser `atelier.nom`
- `MAX(numero) + 1` pour numéroter LP/factures → race condition. Utiliser séquences PostgreSQL via `VONumberingService`.
- QR Companion via service externe → fuite token. Générer en local via `useQrCode`.
- `VOPurchase.vehicule` OneToOne → doit être ManyToOne
- PDF silencieux en `try/catch` vide → toujours logger
- Saisie d'ID numérique dans un formulaire utilisateur → utiliser recherche/select
- Token Companion en query string → toujours segment de chemin URL
- Uploader une pièce d'identité → refus côté API (`RETENTION_YEARS = 0`) et côté UI
- Stocker rôles/permissions dans le `localStorage` → recharger depuis le serveur
- `setStatut()` direct au lieu d'une transition workflow → contourne les listeners

## 1.9 Glossaire

### Métier atelier

- **OR** — Ordre de Réparation : document signé par le client avant intervention, figé après signature
- **RDV** — Rendez-vous
- **PDA** — Portable Digital Assistant, tablette utilisée en atelier
- **MO** — Main d'Œuvre
- **FRE** — Frais de Remise en État (sur un VO avant revente)
- **CT** — Contrôle Technique
- **Gardiennage** — Facturation du stockage d'un véhicule non récupéré

### Métier VO

- **VO** — Véhicule d'Occasion
- **LP** — Livre de Police, registre légal immuable des achats/ventes (Art. 321-7 CP)
- **DA** — Déclaration d'Achat (Cerfa 13751), obligatoire dans les 15 jours pour un pro
- **SIV** — Système d'Immatriculation des Véhicules (base nationale, gérée par l'ANTS)
- **ANTS** — Agence Nationale des Titres Sécurisés
- **CG** — Carte Grise = Certificat d'Immatriculation
- **CPI** — Certificat Provisoire d'Immatriculation (1 mois, France uniquement)
- **CERFA 13751** — Formulaire de Déclaration d'Achat pro
- **CERFA 13757*03** — Mandat pour démarches d'immatriculation
- **CERFA 15776*02** — Certificat de cession d'un véhicule d'occasion
- **VIN** — Vehicle Identification Number (17 caractères ISO 3779, sans I/O/Q)
- **MEC** — Mise En Circulation (date de 1ère immatriculation)
- **HistoVec** — Service public d'historique d'un véhicule

### Régalien

- **RGPD** — Règlement Général sur la Protection des Données
- **DPA** — Data Processing Agreement (sous-traitance RGPD)
- **CNIL** — Commission Nationale de l'Informatique et des Libertés
- **CP** — Code Pénal
- **CGI** — Code Général des Impôts
- **LPF** — Livre des Procédures Fiscales
- **CC** — Code Civil

### Compta

- **FEC** — Fichier des Écritures Comptables (format officiel pour contrôle fiscal)
- **TVA** — Taxe sur la Valeur Ajoutée
- **HT** — Hors Taxes
- **TTC** — Toutes Taxes Comprises
- **CA** — Chiffre d'Affaires
- **LRAR** — Lettre Recommandée avec Accusé de Réception

### Technique

- **CRUD** — Create, Read, Update, Delete
- **DTO** — Data Transfer Object
- **FK** — Foreign Key
- **JWT** — JSON Web Token
- **API** — Application Programming Interface
- **SIRET** — Identifiant unique d'établissement (14 chiffres)
- **DR** — Demande Rectificative (OR rectificatif)
- **SSE** — Server-Sent Events (utilisé par Mercure)
- **CSP** — Content Security Policy
- **HSTS** — HTTP Strict Transport Security
