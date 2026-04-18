# 📋 Spécifications techniques — Atelier MBZ v2

> Document de spécifications pour l'IDE (Cursor / Copilot / Claude Code).
> Priorité : **limiter les litiges clients** et **fluidifier le workflow métier**.
> À implémenter lot par lot dans l'ordre de priorité.

---

## 🎯 Contexte

**Stack :** Symfony 7.2 + PHP 8.3 + Nuxt 3 + PostgreSQL 15 + Caddy + Docker
**Modules hors scope pour l'instant :** Stock, Facturation (réimplantés plus tard)
**Objectif principal :** Sécuriser la chaîne de preuve et le workflow opérationnel

---

## 🚨 LOT 0 — Corrections critiques sécurité (AVANT PROD)

### 0.1 Retirer les secrets hardcodés

**Fichier :** `backend/.env`

**Actions :**
- Supprimer les valeurs par défaut de `APP_SECRET`, `JWT_SECRET_KEY`, `ADMIN_PASSWORD`
- Les définir uniquement dans `.env.local` (non commité) ou via variables d'environnement système
- Régénérer de nouvelles valeurs :
  - `APP_SECRET` : `openssl rand -hex 32`
  - `JWT_SECRET_KEY` : `openssl rand -hex 64`
  - `ADMIN_PASSWORD` : minimum 16 caractères, caractères spéciaux
- Ajouter `.env.local` et `.env.prod.local` à `.gitignore` (vérifier qu'ils y sont déjà)

### 0.2 Sécuriser les cookies JWT selon l'environnement

**Fichier :** `backend/src/Controller/AuthController.php`

**Actions :**
- Remplacer tous les `->withSecure(false)` par :
  ```php
  ->withSecure($this->getParameter('kernel.environment') === 'prod')
  ```
- 3 occurrences : login access_token, login refresh_token, refresh access_token

### 0.3 Activer HTTPS auto dans Caddy

**Fichier :** `Caddyfile`

**Actions :**
- Retirer `auto_https off`
- Remplacer `:80` par la configuration domaine (placeholder `{$APP_DOMAIN}`)
- Ajouter variable d'environnement `APP_DOMAIN` dans docker-compose

### 0.4 Activer l'audit de sécurité Composer

**Fichier :** `backend/composer.json`

**Actions :**
- Passer `"block-insecure": true` dans la section `audit`
- Lancer `composer audit` en CI

### 0.5 Ajouter rate limiting sur endpoints publics

**Actions :**
- Installer : `composer require symfony/rate-limiter`
- Configurer dans `config/packages/rate_limiter.yaml` :
  - `public_booking` : 5/minute par IP
  - `public_suivi` : 60/minute par IP
  - `companion_upload` : 20/minute par token
- Ajouter attributs `#[RateLimiter(...)]` sur les contrôleurs :
  - `PublicBookingController::createBooking`
  - `PublicBookingController::slots`
  - `SuiviController::suivi`
  - `CompanionController::uploadPhoto`

### 0.6 Retirer l'accès photo public

**Fichier :** `backend/config/packages/security.yaml`

**Actions :**
- Supprimer `/api/photos/file/` de la liste des routes publiques
- Créer un endpoint alternatif `/api/public/photos/{token}/{filename}` qui :
  - Vérifie que le filename appartient bien au RDV du token
  - Vérifie que le token n'est pas expiré

---

## 🔴 LOT 1 — Traçabilité OR & Signatures (ANTI-LITIGE PRIORITAIRE)

### 1.1 Figer l'OR à la signature

**Fichier :** `backend/src/Entity/OrdreReparation.php`

**Ajouter les champs :**
```php
#[ORM\Column(length: 50, options: ['default' => 'brouillon'])]
private string $statut = 'brouillon';
// Statuts : brouillon | en_attente_signature | signe | execute | termine | rectifie

#[ORM\Column(type: 'json', nullable: true)]
private ?array $signedSnapshot = null;

#[ORM\Column(length: 64, nullable: true)]
private ?string $signedHash = null;

#[ORM\Column(type: 'datetime', nullable: true)]
private ?\DateTimeInterface $signedAt = null;

#[ORM\Column(length: 45, nullable: true)]
private ?string $signedIp = null;

#[ORM\Column(length: 500, nullable: true)]
private ?string $signedUserAgent = null;

// Rectification
#[ORM\ManyToOne(targetEntity: OrdreReparation::class)]
#[ORM\JoinColumn(name: 'rectified_from_id', nullable: true)]
private ?OrdreReparation $rectifiedFrom = null;

#[ORM\Column(length: 100, nullable: true)]
private ?string $motifRectification = null;

#[ORM\Column(nullable: true)]
private ?int $rectifiedBy = null;

#[ORM\Column(type: 'datetime', nullable: true)]
private ?\DateTimeInterface $rectifiedAt = null;
```

### 1.2 Créer le service OrdreReparationPolicy

**Fichier :** `backend/src/Service/OrdreReparationPolicy.php` (NOUVEAU)

**Rôle :** Centraliser les règles "qui peut modifier quoi et quand".

**Méthodes à implémenter :**
- `canEdit(OrdreReparation $or, User $user): bool`
- `canSign(OrdreReparation $or): bool`
- `canRectify(OrdreReparation $or, User $user): bool`
- `canAddComplementaire(OrdreReparation $or, User $user): bool`
- `buildSnapshot(OrdreReparation $or): array` — sérialise tout le contenu de l'OR
- `computeHash(array $snapshot): string` — SHA-256 du JSON canonique
- `sign(OrdreReparation $or, string $signatureData, Request $request): void`
- `verifyIntegrity(OrdreReparation $or): bool`

**Règles métier :**
- OR en `brouillon` → édition libre par réceptionniste/responsable
- OR en `en_attente_signature` → édition bloquée sauf annulation
- OR en `signe` → **aucune édition possible**, uniquement rectification par Responsable Atelier/Magasin
- OR en `execute`/`termine` → gel total
- Mécanicien ne peut **jamais** modifier l'OR, uniquement ajouter demande complémentaire

### 1.3 Créer le Listener Doctrine pour bloquer les modifs

**Fichier :** `backend/src/EventListener/OrdreReparationFreezeListener.php` (NOUVEAU)

**Rôle :** Lever une exception `\DomainException` en `preUpdate` sur un OR signé.

**Contenu :**
```php
#[AsDoctrineListener(event: Events::preUpdate)]
class OrdreReparationFreezeListener {
    public function preUpdate(PreUpdateEventArgs $args): void {
        $entity = $args->getObject();
        if (!$entity instanceof OrdreReparation) return;
        
        if (in_array($entity->getStatut(), ['signe', 'execute', 'termine'])) {
            // Vérifier si c'est un changement autorisé (rectification)
            $changeSet = $args->getEntityChangeSet();
            $allowedFields = ['statut']; // uniquement transition d'état autorisée
            
            foreach (array_keys($changeSet) as $field) {
                if (!in_array($field, $allowedFields)) {
                    throw new \DomainException(
                        "OR signé — modification interdite. Utilisez la rectification."
                    );
                }
            }
        }
    }
}
```

### 1.4 Endpoint de signature client via Companion

**Fichier :** `backend/src/Controller/CompanionController.php`

**Modifier :** `saveSignature()`

**Actions :**
- Appeler `OrdreReparationPolicy::sign()` au lieu de setter direct
- Capturer IP et User-Agent depuis la Request
- Générer le snapshot + hash avant signature
- Passer l'OR en statut `signe`
- Retourner le hash dans la réponse pour affichage côté client (vérification possible plus tard)

### 1.5 Endpoint de rectification

**Fichier :** `backend/src/Controller/RendezVousController.php` OU nouveau `OrdreReparationController.php`

**Routes à créer :**
```php
#[Route('/api/or/{id}/rectifier', methods: ['POST'])]
#[IsGranted('ROLE_RESPONSABLE_ATELIER')]
public function rectifier(int $id, Request $request): JsonResponse
```

**Logique :**
- Vérifier OR existant signé
- Créer nouveau OR avec `rectifiedFrom` pointant vers l'original
- Pré-remplir toutes les données depuis l'original
- Statut initial : `en_attente_signature`
- Envoyer notification + email au client avec lien de re-signature
- Log audit détaillé

### 1.6 Vérification d'intégrité

**Route :** `GET /api/or/{id}/verify-integrity`

**Actions :**
- Recalculer le hash du `signedSnapshot` actuel
- Comparer avec `signedHash` stocké
- Retourner OK ou ALERTE avec détails

---

## 🔴 LOT 2 — Photos & Preuves visuelles

### 2.1 Typer les photos

**Fichier :** `backend/src/Entity/PhotoIntervention.php`

**Ajouter :**
```php
#[ORM\Column(length: 50)]
private string $type; 
// Types : reception | avant_travaux | en_cours | apres_travaux | restitution | probleme

#[ORM\Column(length: 64)]
private string $sha256;  // hash du fichier

#[ORM\Column(type: 'json', nullable: true)]
private ?array $exif = null;

#[ORM\Column(type: 'datetime')]
private \DateTimeInterface $takenAt;  // horodatage serveur à l'upload
```

### 2.2 Service PhotoService

**Fichier :** `backend/src/Service/PhotoService.php` (NOUVEAU)

**Méthodes :**
- `upload(UploadedFile $file, string $type, RendezVous $rdv): PhotoIntervention`
- `computeHash(string $path): string` — SHA-256
- `extractExif(string $path): array`
- `requirePhotosForTransition(string $transition, RendezVous $rdv): array` — retourne les types requis manquants

**Règles métier :**
- Transition `reception` : minimum 4 photos type `reception` (avant droit, avant gauche, arrière, compteur)
- Transition `termine` : minimum 2 photos type `apres_travaux`
- Transition `restitue` : minimum 3 photos type `restitution`

### 2.3 Blocage des transitions sans photos

**Fichier :** `backend/src/Controller/RendezVousController.php`

**Modifier :** `transition()`

**Actions :**
- Appeler `PhotoService::requirePhotosForTransition()` avant d'appliquer la transition
- Retourner HTTP 400 avec la liste des types manquants si incomplet

---

## 🔴 LOT 3 — Workflow RDV enrichi

### 3.1 Nouveaux statuts et transitions

**Fichier :** `backend/config/packages/workflow.yaml`

**Ajouter les places :**
```yaml
places:
    - en_attente
    - reserve
    - confirme
    - reception
    - en_cours
    - en_attente_pieces      # NOUVEAU
    - en_attente_reprise     # NOUVEAU
    - en_gardiennage         # NOUVEAU
    - termine
    - restitue
    - restitue_partiel       # NOUVEAU
    - facture
    - paye
    - annule
    - no_show                # NOUVEAU
```

**Ajouter les transitions :**
```yaml
transitions:
    # Gestion no-show
    declarer_no_show:
        from: [confirme, reception]
        to: no_show
    
    # Retour arrière réception
    reporter:
        from: reception
        to: confirme
    
    # Attente pièces
    attendre_pieces:
        from: [en_cours, reception]
        to: en_attente_pieces
    reprendre_apres_pieces:
        from: en_attente_pieces
        to: en_cours
    
    # Replanification
    mettre_en_attente_reprise:
        from: en_cours
        to: en_attente_reprise
    reprendre_demain:
        from: en_attente_reprise
        to: en_cours
    
    # Gardiennage
    passer_gardiennage:
        from: [termine, en_attente_pieces]
        to: en_gardiennage
    sortir_gardiennage:
        from: en_gardiennage
        to: en_cours
    
    # Restitution partielle
    restituer_partiel:
        from: en_cours
        to: restitue_partiel
    
    # Retour SAV / garantie
    retour_garantie:
        from: restitue
        to: en_cours
```

### 3.2 Motif obligatoire sur annulation

**Fichier :** `backend/src/Controller/RendezVousController.php`

**Modifier :** `transition()` pour transition `annuler`

**Créer nouvelle entité :** `AnnulationRdv`
```php
class AnnulationRdv {
    int $id;
    RendezVous $rendezVous;
    string $motif; 
    // 'client_desiste' | 'client_no_show' | 'atelier_indisponible' 
    // | 'force_majeure' | 'erreur_saisie' | 'autre'
    ?string $commentaire;
    string $source; // 'atelier' | 'client'
    int $annulePar; // user_id ou null si auto
    \DateTime $annuleAt;
}
```

### 3.3 No-show automatique

**Nouvelle commande :** `backend/src/Command/CheckNoShowCommand.php`

**Logique :**
- CRON toutes les 15 min
- Identifie les RDV en statut `confirme` dont `heure_rdv + 30min < now()` et même date qu'aujourd'hui
- Bascule automatiquement en `no_show` avec source `automatique`
- Notification au client + réceptionniste

---

## 🔴 LOT 4 — Demandes travaux complémentaires

### 4.1 Compléter l'entité DemandeTravauxSupp

**Fichier :** `backend/src/Entity/DemandeTravauxSupp.php`

**Ajouter :**
```php
#[ORM\Column(length: 64, unique: true)]
private string $tokenValidation;  // bin2hex(random_bytes(32))

#[ORM\Column(type: 'json')]
private array $prestationsChoisies = [];  
// [{prestation_id, designation, prix, temps_minutes, from_catalog: true}]

#[ORM\Column(type: 'text', nullable: true)]
private ?string $photosJustificatives = null;  // IDs de PhotoIntervention

#[ORM\Column(length: 45, nullable: true)]
private ?string $decisionIp = null;

#[ORM\Column(length: 500, nullable: true)]
private ?string $decisionUserAgent = null;

#[ORM\Column(type: 'text', nullable: true)]
private ?string $signatureClient = null;

#[ORM\Column(type: 'datetime', nullable: true)]
private ?\DateTimeInterface $signedAt = null;

#[ORM\ManyToOne(targetEntity: OrdreReparation::class)]
#[ORM\JoinColumn(name: 'or_complementaire_id', nullable: true)]
private ?OrdreReparation $orComplementaire = null;
```

### 4.2 Controller DemandeTravauxSuppController

**Fichier :** `backend/src/Controller/DemandeTravauxSuppController.php` (NOUVEAU)

**Routes à implémenter :**

```php
// Depuis le Companion (mécanicien) — ajout d'une demande
#[Route('/api/companion/{token}/demande-complementaire', methods: ['POST'])]
public function create(...)
// Body : { prestations_ids: [1,2], commentaire: "...", photos_ids: [42] }
// Prix/temps calculés depuis catalogue, JAMAIS fournis par le mécanicien

// Côté admin — liste des demandes en attente
#[Route('/api/demandes-travaux-supp', methods: ['GET'])]
public function list(...)

// Envoi au client (déclenché par réceptionniste ou auto)
#[Route('/api/demandes-travaux-supp/{id}/envoyer', methods: ['POST'])]
public function envoyer(...)

// Endpoint public pour décision client
#[Route('/api/public/demandes-travaux-supp/{token}/decision', methods: ['POST'])]
public function decisionPublique(...)
// Body : { decision: 'accepte'|'refuse', signature: 'data:image/...' }

// Endpoint public pour consultation
#[Route('/api/public/demandes-travaux-supp/{token}', methods: ['GET'])]
public function viewPublique(...)
```

### 4.3 Blocage reprise travail si demande en attente

**Fichier :** `backend/src/Controller/RendezVousController.php`

**Dans transition `terminer` :**
- Vérifier qu'aucune `DemandeTravauxSupp` liée n'est en statut `en_attente_decision_client`
- Si oui → HTTP 409 "Demande complémentaire en attente de décision client"

### 4.4 Création auto de l'OR complémentaire à l'acceptation

Dans `decisionPublique()` si `decision = 'accepte'` :
- Créer un nouveau `OrdreReparation` de type `complementaire`
- Lier via `parentOr` à l'OR initial
- Pré-rempli avec les prestations acceptées
- Statut `signe` (la signature vient de se faire)
- Figer le snapshot + hash

### 4.5 Notification temps réel à la création

Dans `create()` :
- Publier sur Mercure topic `atelier/{id}/notifications`
- Créer entry `Notification` en base avec escalade configurée
- Cibler `ROLE_RECEPTIONNAIRE` + `ROLE_ADMIN`

---

## 🔴 LOT 5 — Notifications temps réel (Mercure)

### 5.1 Installation Mercure Hub

**Fichier :** `docker-compose.yml` (ou `compose.yaml`)

**Ajouter service :**
```yaml
mercure:
  image: dunglas/mercure
  restart: unless-stopped
  environment:
    SERVER_NAME: ':3000'
    MERCURE_PUBLISHER_JWT_KEY: '${MERCURE_JWT_SECRET}'
    MERCURE_SUBSCRIBER_JWT_KEY: '${MERCURE_JWT_SECRET}'
    MERCURE_EXTRA_DIRECTIVES: |
      cors_origins https://${APP_DOMAIN}
      anonymous
      subscriptions
  volumes:
    - mercure_data:/data
    - mercure_config:/config
  ports:
    - "3000:3000"
```

**Fichier :** `backend/composer.json`

**Ajouter :** `symfony/mercure-bundle`

### 5.2 Entités Notification

**Fichier :** `backend/src/Entity/Notification.php` (NOUVEAU)

```php
#[ORM\Entity]
#[ORM\Table(name: 'notifications')]
class Notification {
    int $id;
    int $atelierId;
    ?int $targetUserId;
    ?string $targetRole;
    string $type;
    string $severity; // info | warning | critical
    string $title;
    string $message;
    ?string $actionUrl;
    ?string $relatedEntityType;
    ?int $relatedEntityId;
    ?\DateTime $readAt;
    ?int $readBy;
    ?\DateTime $acknowledgedAt;
    ?int $acknowledgedBy;
    \DateTime $createdAt;
    ?\DateTime $expiresAt;
}
```

**Fichier :** `backend/src/Entity/NotificationEscalation.php` (NOUVEAU)

```php
class NotificationEscalation {
    int $id;
    Notification $notification;
    int $level; // 1, 2, 3...
    string $channel; // push | sms | email
    \DateTime $scheduledAt;
    ?\DateTime $executedAt;
    ?string $result; // success | failed | skipped
    ?string $skipReason; // acknowledged_before | ...
    ?string $targetInfo;
}
```

### 5.3 Service MercureNotifier

**Fichier :** `backend/src/Service/MercureNotifier.php` (NOUVEAU)

```php
class MercureNotifier {
    public function __construct(private HubInterface $hub) {}
    
    public function publishToAtelier(int $atelierId, Notification $notif): void {
        $update = new Update(
            "atelier/{$atelierId}/notifications",
            json_encode([
                'id' => $notif->getId(),
                'type' => $notif->getType(),
                'severity' => $notif->getSeverity(),
                'title' => $notif->getTitle(),
                'message' => $notif->getMessage(),
                'actionUrl' => $notif->getActionUrl(),
                'createdAt' => $notif->getCreatedAt()->format(DATE_ATOM),
            ])
        );
        $this->hub->publish($update);
    }
    
    public function publishAcknowledged(int $atelierId, int $notifId, int $userId): void {
        // topic : atelier/{id}/notifications/acknowledged
    }
}
```

### 5.4 NotificationController

**Fichier :** `backend/src/Controller/NotificationController.php` (NOUVEAU)

```php
#[Route('/api/notifications')]
class NotificationController {
    // GET /api/notifications?status=unread
    public function list() {}
    
    // GET /api/notifications/unread-count
    public function unreadCount() {}
    
    // POST /api/notifications/{id}/acknowledge
    // Logique atomique : UPDATE WHERE acknowledged_at IS NULL
    // Si rowsAffected = 0 → 409 Conflict
    public function acknowledge(int $id) {}
    
    // POST /api/notifications/{id}/mark-read
    public function markRead(int $id) {}
}
```

### 5.5 Service d'escalade

**Fichier :** `backend/src/Command/ProcessNotificationEscalationsCommand.php` (NOUVEAU)

**Logique :**
- CRON chaque minute (via Symfony Scheduler)
- Charge les `NotificationEscalation` avec `scheduledAt <= now()` et `executedAt IS NULL`
- Pour chacune :
  - Si la notification parente est `acknowledged` → marquer l'escalade `skipped`
  - Sinon : dispatcher le message via le canal (`push`, `sms`, `email`)
  - Marquer `executedAt`

**Règles d'escalade pour `demande_complementaire` :**
- T+0 : push web tous les `ROLE_RECEPTIONNAIRE` + `ROLE_ADMIN` de l'atelier
- T+5min : push web + notification sonore renforcée
- T+10min : SMS au `ROLE_RESPONSABLE_ATELIER`
- T+30min : SMS au `ROLE_RESPONSABLE_MAGASIN`

### 5.6 Composable Nuxt pour abonnement

**Fichier :** `frontend/composables/useNotifications.ts` (NOUVEAU)

```typescript
export const useNotifications = () => {
  const notifications = ref<Notification[]>([])
  const unreadCount = ref(0)
  const eventSource = ref<EventSource | null>(null)
  
  const connect = (atelierId: number) => {
    const mercureUrl = useRuntimeConfig().public.mercureUrl
    const topic = `${mercureUrl}?topic=atelier/${atelierId}/notifications`
    eventSource.value = new EventSource(topic, { withCredentials: true })
    
    eventSource.value.onmessage = (event) => {
      const notif = JSON.parse(event.data)
      handleIncoming(notif)
    }
  }
  
  const acknowledge = async (id: number) => {
    await $api(`/api/notifications/${id}/acknowledge`, { method: 'POST' })
  }
  
  // ...
  
  return { notifications, unreadCount, connect, acknowledge }
}
```

### 5.7 Composant pop-in planning

**Fichier :** `frontend/components/NotificationPopIn.vue` (NOUVEAU)

**Rôle :** Affichage bloquant sur `/planning` quand notification `demande_complementaire`.

### 5.8 Badge sidebar

**Fichier :** `frontend/components/SidebarLink.vue`

**Actions :**
- Ajouter prop `badgeCount`
- Afficher pastille rouge avec compteur si > 0

**Fichier :** `frontend/layouts/default.vue`

**Actions :**
- Utiliser `useNotifications().unreadCount` pour afficher le badge sur le lien Planning

---

## 🔴 LOT 6 — Rôles et permissions paramétrables

### 6.1 Nouvelle architecture des rôles

**Ajouter au `role_hierarchy` dans `security.yaml` :**
```yaml
role_hierarchy:
    ROLE_SUPER_ADMIN: [ROLE_ADMIN]
    ROLE_ADMIN: [ROLE_USER]
    # Les rôles métier sont gérés dynamiquement via RoleMetier
```

### 6.2 Entités

**Fichier :** `backend/src/Entity/RoleMetier.php` (NOUVEAU)

```php
#[ORM\Entity]
#[ORM\Table(name: 'roles_metier')]
class RoleMetier {
    int $id;
    ?int $atelierId; // null = template global
    string $code;
    string $libelle;
    string $description;
    string $baseRole; // ROLE_USER | ROLE_ADMIN
    ?RoleMetier $heritedFrom;
    bool $isSystemTemplate = false;
    bool $isActive = true;
    Collection $permissions; // RolePermissionEntry
    \DateTime $createdAt;
    ?int $createdBy;
}
```

**Fichier :** `backend/src/Entity/RolePermissionEntry.php` (NOUVEAU)

```php
class RolePermissionEntry {
    int $id;
    RoleMetier $role;
    string $module;     // rdv, clients, factures, ...
    string $action;     // view, create, edit, delete, export
    string $scope;      // own, team, atelier, all
    ?array $conditions;
    bool $granted = true;
}
```

**Fichier :** `backend/src/Entity/Module.php` (NOUVEAU — référentiel statique)

### 6.3 PermissionVoter

**Fichier :** `backend/src/Security/Voter/PermissionVoter.php` (NOUVEAU)

```php
class PermissionVoter extends Voter {
    protected function supports(string $attribute, mixed $subject): bool {
        return str_starts_with($attribute, 'perm.');
        // Ex: perm.FACTURE.edit, perm.CLIENT.view
    }
    
    protected function voteOnAttribute(
        string $attribute, 
        mixed $subject, 
        TokenInterface $token
    ): bool {
        $user = $token->getUser();
        if (!$user instanceof User) return false;
        
        // Parse perm.MODULE.ACTION
        [$_, $module, $action] = explode('.', $attribute);
        
        $roleMetier = $user->getRoleMetier();
        if (!$roleMetier) return false;
        
        foreach ($roleMetier->getPermissions() as $perm) {
            if ($perm->getModule() === $module 
                && $perm->getAction() === $action
                && $perm->isGranted()) {
                // Vérifier scope + conditions si applicable
                return $this->checkScope($perm, $subject, $user);
            }
        }
        
        return false;
    }
}
```

### 6.4 Migration des 6 rôles templates

**Fichier :** `backend/migrations/Version_RolesMetierBootstrap.php` (NOUVEAU)

**Créer les templates avec leurs permissions :**
- Responsable Atelier (baseRole: ROLE_ADMIN)
- Responsable Magasin (baseRole: ROLE_ADMIN)
- Réceptionniste (baseRole: ROLE_USER)
- Mécanicien (baseRole: ROLE_USER)
- Comptable (baseRole: ROLE_USER)
- VO Manager (baseRole: ROLE_USER)

Voir matrices détaillées dans la doc métier.

**Cas particulier mécanicien :**
- AUCUNE permission sur les champs prix/temps
- AUCUNE permission `edit` sur OR, Devis, Facture
- Uniquement `view` sur catalogue prestations (pour sélection)
- `create` sur `DemandeTravauxSupp` uniquement

### 6.5 Interface SuperAdmin

**Fichier :** `frontend/pages/superadmin/roles-metier/index.vue` (NOUVEAU)
**Fichier :** `frontend/pages/superadmin/roles-metier/[id].vue` (NOUVEAU)

### 6.6 Sécurité SuperAdmin

**Protection "dernier super-admin" :**
- Vérifier lors de delete/downgrade qu'il reste au moins 1 `ROLE_SUPER_ADMIN` actif
- Sinon HTTP 403 "Au moins un SuperAdmin doit rester actif"

**Protection élévation de privilège :**
- Un user ne peut pas s'auto-attribuer un rôle supérieur au sien
- Vérification dans le contrôleur

### 6.7 TenantFilter pour SuperAdmin

**Fichier :** `backend/src/EventListener/TenantFilterListener.php`

**Modifier la logique :**
- Si `ROLE_SUPER_ADMIN` : lire `$session->get('active_atelier_id')`
  - Si `null` ou `'all'` → pas de filtre (accès global)
  - Sinon → filtre sur l'atelier choisi
- Sinon (user normal) : filtre automatique sur `user.atelierId`

---

## 🔴 LOT 7 — Catalogue Prestations structuré

### 7.1 Enum ModeTarification

**Fichier :** `backend/src/Enum/ModeTarification.php` (NOUVEAU)

```php
enum ModeTarification: string {
    case FORFAIT = 'forfait';
    case HORAIRE = 'horaire';
    case SUR_DEVIS = 'sur_devis';
}
```

### 7.2 Entité Prestation

**Fichier :** `backend/src/Entity/Prestation.php` (NOUVEAU)

```php
class Prestation {
    int $id;
    int $atelierId;
    string $code;
    string $libelle;
    ?string $description;
    ModeTarification $modeTarification;
    
    // Pour FORFAIT
    ?string $prixForfait;  // decimal
    
    // Pour HORAIRE
    ?int $tempsEstimeMinutes;
    
    // Communes
    ?int $garantieJours;
    bool $necessiteEssai = true;
    bool $isActive = true;
    
    /** @var Collection<int, CategorieMoto> */
    Collection $categoriesMotos;  // ManyToMany
    
    \DateTime $createdAt;
    \DateTime $updatedAt;
}
```

### 7.3 Table de jointure Prestation × CategorieMoto avec tarifs spécifiques

**Fichier :** `backend/src/Entity/PrestationTarifCategorie.php` (NOUVEAU)

```php
class PrestationTarifCategorie {
    int $id;
    Prestation $prestation;
    CategorieMoto $categorieMoto;
    ?string $prixSpecifique;  // override du prix forfait si différent selon catégorie
    ?int $tempsSpecifiqueMinutes;
}
```

### 7.4 Service PrestationCatalogService

**Fichier :** `backend/src/Service/PrestationCatalogService.php` (NOUVEAU)

**Méthodes :**
- `getApplicablePrestations(Vehicule $vehicule): array` — prestations dispo pour cette moto
- `calculatePrice(Prestation $p, Vehicule $v, int $tauxHoraire): array` — retourne [prix, temps]
- `validateAddition(Prestation $p, OrdreReparation $or): void` — lance exception si incompatible

### 7.5 Endpoint pour l'espace mécanicien

```php
#[Route('/api/companion/{token}/prestations-disponibles', methods: ['GET'])]
public function prestationsDisponibles(string $token): JsonResponse
```

Retourne uniquement les prestations applicables au véhicule du RDV, avec prix déjà calculé. **Le mécanicien n'a qu'à cocher**, jamais à saisir un prix ou un temps.

### 7.6 Migration des données existantes

**Fichier :** `backend/migrations/Version_MigrateCalculTarifToPrestation.php` (NOUVEAU)

Convertir les entrées `CalculTarif` existantes en `Prestation` avec `ModeTarification::FORFAIT` par défaut.

---

## 🔴 LOT 8 — Rapport d'intervention à la restitution

### 8.1 Entités

**Fichier :** `backend/src/Entity/RapportIntervention.php` (NOUVEAU)

```php
class RapportIntervention {
    int $id;
    int $atelierId;
    RendezVous $rendezVous;
    OrdreReparation $orInitial;
    
    // Statut
    string $statut = 'brouillon'; 
    // brouillon | en_validation | signe | rectifie
    
    // Contenu
    string $travauxRealises;
    ?string $alertesImportantes;
    ?string $recommandationsProchaineVisite;
    ?int $prochaineRevisionKm;
    ?\DateTime $prochaineRevisionDate;
    
    // État véhicule restitution
    int $kilometrageRestitution;
    ?string $etatVehiculeRestitution; // JSON
    
    // Garantie (figée au moment signature)
    int $garantieJours;
    ?\DateTime $garantieJusquAu;
    
    // Essai routier (OneToOne obligatoire)
    ?EssaiRoutier $essaiRoutier;
    
    // Signatures
    ?string $signatureMecanicien;
    ?\DateTime $signedByMecanicienAt;
    ?string $signatureClient;
    ?\DateTime $signedByClientAt;
    
    // Intégrité
    ?array $signedSnapshot;
    ?string $signedHash;
    ?string $signedIp;
    ?string $signedUserAgent;
    
    // Rectification
    ?RapportIntervention $rectifiedFrom;
    ?string $motifRectification;
    ?int $rectifiedBy;
    ?\DateTime $rectifiedAt;
    
    // Envoi
    ?\DateTime $emailSentAt;
    ?string $emailSentTo;
    
    \DateTime $createdAt;
}
```

**Fichier :** `backend/src/Entity/EssaiRoutier.php` (NOUVEAU)

```php
class EssaiRoutier {
    int $id;
    RapportIntervention $rapport;
    Mecanicien $mecanicien;
    \DateTime $debutAt;
    \DateTime $finAt;
    int $kmDebut;
    int $kmFin;
    int $dureeMinutes;   // calculé
    int $distanceKm;     // calculé
    
    // Points de contrôle structurés
    array $pointsControles;
    // Structure imposée :
    // 'freinage_avant' => 'ok'|'nok'|'na',
    // 'freinage_arriere' => ...,
    // 'direction' => ...,
    // 'suspension' => ...,
    // 'embrayage' => ...,
    // 'boite_vitesses' => ...,
    // 'eclairage' => ...,
    // 'avertisseur' => ...,
    // 'compteur' => ...,
    // 'bruits_anormaux' => ...,
    
    ?string $commentaireGeneral;
    bool $anomaliesConstatees = false;
    ?string $descriptionAnomalies;
    ?string $actionCorrective;
    string $signatureMecanicien;
}
```

### 8.2 Service RapportInterventionService

**Fichier :** `backend/src/Service/RapportInterventionService.php` (NOUVEAU)

**Méthodes :**
- `createDraft(RendezVous $rdv): RapportIntervention` — auto à la transition `termine`
- `prefillFromOR(RapportIntervention $rapport): void` — agrège prestations + pièces des OR signés
- `validateCompleteness(RapportIntervention $rapport): array` — liste des manques
- `calculateNextRevision(RapportIntervention $rapport): void` — algorithme selon type intervention
- `signByMecanicien(RapportIntervention $rapport, string $sig): void`
- `signByClient(RapportIntervention $rapport, string $sig, Request $req): void`
- `sendByEmail(RapportIntervention $rapport): void`

### 8.3 Validations bloquantes avant signature

- Essai routier **complet** (tous points contrôlés, distance > 0)
- `travauxRealises` non vide (minimum 20 caractères)
- `recommandationsProchaineVisite` non vide
- Photos de restitution minimum 3 présentes
- Si `anomaliesConstatees = true` dans essai → `actionCorrective` renseignée

### 8.4 Création auto à la transition `termine`

**Fichier :** `backend/src/EventListener/WorkflowListener.php` (NOUVEAU)

**Logique :**
- Écouter `workflow.rendez_vous.completed.terminer`
- Créer un `RapportIntervention` en brouillon automatiquement
- Notifier le mécanicien ("Rapport à compléter avant restitution")

### 8.5 Endpoints

```php
#[Route('/api/rapports-intervention/{id}', methods: ['GET'])]
public function show(int $id)

#[Route('/api/rapports-intervention/{id}', methods: ['PUT'])]
public function update(int $id) // édition mécanicien

#[Route('/api/rapports-intervention/{id}/essai', methods: ['POST'])]
public function saveEssai(int $id)

#[Route('/api/rapports-intervention/{id}/sign-mecanicien', methods: ['POST'])]
public function signMecanicien(int $id)

#[Route('/api/rapports-intervention/{id}/sign-client', methods: ['POST'])]
public function signClient(int $id) // à la restitution tablette

#[Route('/api/rapports-intervention/{id}/pdf', methods: ['GET'])]
public function pdf(int $id)

#[Route('/api/rapports-intervention/{id}/rectifier', methods: ['POST'])]
#[IsGranted('ROLE_RESPONSABLE_ATELIER')]
public function rectifier(int $id)
```

### 8.6 Template PDF

**Fichier :** `backend/templates/pdf/rapport_intervention.html.twig` (NOUVEAU)

Sections obligatoires : en-tête atelier, client + véhicule, n° rapport, travaux réalisés, pièces changées, contrôles effectués, essai routier (km + commentaire), alertes (rouge si critical), recommandations, mention garantie + date limite, clauses légales, signatures, hash document.

### 8.7 Blocage transition `restitue`

**Fichier :** `backend/src/Controller/RendezVousController.php`

Dans `transition()` pour `restituer` :
- Vérifier qu'un `RapportIntervention` en statut `signe` (par mécanicien ET client) existe pour ce RDV
- Sinon HTTP 400 "Rapport d'intervention non signé"

### 8.8 CRON rappel prochaine révision

**Fichier :** `backend/src/Command/RappelProchaineRevisionCommand.php` (NOUVEAU)

Identifie les rapports dont `prochaineRevisionDate - 30 jours = today` et envoie email/SMS au client.

---

## 🔴 LOT 9 — Gardiennage et attente pièces

### 9.1 Entité CommandePiece

**Fichier :** `backend/src/Entity/CommandePiece.php` (NOUVEAU)

```php
class CommandePiece {
    int $id;
    int $atelierId;
    RendezVous $rendezVous;
    OrdreReparation $or;
    string $reference;
    string $designation;
    int $quantite;
    ?string $fournisseur;
    ?string $numeroCommandeFournisseur;
    ?string $prixAchat;
    ?string $prixVente;
    \DateTime $dateCommande;
    ?\DateTime $dateLivraisonEstimee;
    ?\DateTime $dateLivraisonReelle;
    string $statut;
    // a_commander | commandee | expediee | recue | installee | annulee | retour_fournisseur
    ?string $notes;
    ?\DateTime $clientNotifieAt;
    ?int $notifiedBy;
}
```

### 9.2 Champs RDV stockage

**Fichier :** `backend/src/Entity/RendezVous.php`

**Ajouter :**
```php
?string $emplacementStockage;
?string $photoStockageFilename;
?\DateTime $misEnStockageAt;
?int $misEnStockagePar;

// Gardiennage
?\DateTime $gardiennageDebutAt;
?int $gardiennageDebutPar;
?string $gardiennageMotif;
```

### 9.3 Config atelier

**Fichier :** `backend/src/Entity/ConfigAtelier.php`

**Ajouter :**
```php
int $delaiRelance1JoursOuvres = 15;
int $delaiRelance2JoursOuvres = 30;
int $delaiProposeGardiennageJoursOuvres = 45;
int $delaiProcedureAbandonJoursOuvres = 180;
string $tarifGardiennageJournalier = '5.00';
int $garantieTravauxJours = 30;
array $joursFermetureHebdo = ['sunday'];
array $datesFermetureExceptionnelles = [];
```

### 9.4 Service JoursOuvresService

**Fichier :** `backend/src/Service/JoursOuvresService.php` (NOUVEAU)

**Méthodes :**
- `compterJoursOuvres(\DateTime $debut, \DateTime $fin, int $atelierId): int`
- `estJourFerie(\DateTime $date): bool` — inclut dates fixes + mobiles (Pâques, Pentecôte...)
- `estJourFerme(\DateTime $date, int $atelierId): bool` — jours ouvrés + config atelier
- `ajouterJoursOuvres(\DateTime $debut, int $jours, int $atelierId): \DateTime`

### 9.5 Entité JourFerie

**Fichier :** `backend/src/Entity/JourFerie.php` (NOUVEAU)

Alimentée par migration pour les 5 prochaines années.

### 9.6 Commande de relance auto

**Fichier :** `backend/src/Command/RelanceClientStockageCommand.php` (NOUVEAU)

**Logique :**
- CRON quotidien
- Identifie RDV en `termine` ou `en_attente_pieces` depuis X jours ouvrés selon config
- Envoie SMS/email de relance
- À J+45 ouvrés : notifie responsable atelier "Proposition de gardiennage"

### 9.7 Service GardiennageService

**Fichier :** `backend/src/Service/GardiennageService.php` (NOUVEAU)

**Méthodes :**
- `peutDeclencher(RendezVous $rdv, User $user): bool` — vérifie relances préalables effectuées
- `declencher(RendezVous $rdv, User $user, string $motif): void`
- `calculerMontant(RendezVous $rdv, \DateTime $dateRestitution): string`

### 9.8 Endpoint déclenchement

```php
#[Route('/api/rdv/{id}/declencher-gardiennage', methods: ['POST'])]
#[IsGranted('perm.GARDIENNAGE.trigger')]
public function declencherGardiennage(int $id)
```

### 9.9 Workflow pièces

**Endpoints :**
```php
POST /api/rdv/{id}/commandes-pieces
PUT  /api/commandes-pieces/{id}
POST /api/commandes-pieces/{id}/recue  // notifie mécanicien
```

À la création : bascule auto RDV en `en_attente_pieces` + notification client.
À la réception : notif mécanicien + propose transition `reprendre_apres_pieces`.

---

## 🔴 LOT 10 — Historique entretien véhicule

### 10.1 Endpoints

```php
#[Route('/api/vehicules/{id}/historique-entretien', methods: ['GET'])]
#[IsGranted('perm.HISTORIQUE.view')]
public function historique(int $id)

#[Route('/api/vehicules/{id}/historique-entretien/pdf', methods: ['GET'])]
#[IsGranted('perm.HISTORIQUE.export')]
public function historiquePdf(int $id)
```

### 10.2 Service HistoriqueEntretienService

**Fichier :** `backend/src/Service/HistoriqueEntretienService.php` (NOUVEAU)

**Méthodes :**
- `buildHistorique(Vehicule $v): array` — données complètes chronologiques
- `generatePdf(Vehicule $v): string` — chemin du PDF

### 10.3 Contenu du rapport

Pour chaque intervention :
- Date + kilométrage
- Numéro d'OR
- Mécanicien intervenant
- Liste prestations
- Pièces changées + références
- Observations pertinentes (alertes du rapport)

### 10.4 Template PDF

**Fichier :** `backend/templates/pdf/historique_entretien.html.twig` (NOUVEAU)

En-tête avec infos atelier + véhicule. Corps chronologique. Pied avec mention de certification + hash pour vérification.

### 10.5 Audit des exports

Chaque téléchargement du PDF génère un entry `audit_log` avec :
- `action` : `export_historique_entretien`
- `target_type` : `Vehicule`
- `target_id` : id véhicule
- Données : utilisateur demandeur, IP

---

## 🔴 LOT 11 — Multi-provider SMS/Email

### 11.1 Installation

```bash
composer require symfony/notifier
composer require symfony/twilio-notifier
composer require symfony/ovh-cloud-notifier
composer require symfony/mailgun-mailer
```

### 11.2 Entités

**Fichier :** `backend/src/Entity/NotificationProviderConfig.php` (NOUVEAU)

```php
class NotificationProviderConfig {
    int $id;
    int $atelierId;
    string $channel; // sms | email
    string $provider; // ovh | twilio | mailgun | smtp_custom
    bool $isPrimary = false;
    bool $isFallback = false;
    int $priority = 1;
    string $configEncrypted; // JSON chiffré des clés API
    bool $isActive = true;
    ?\DateTime $lastTestAt;
    ?bool $lastTestSuccess;
}
```

**Fichier :** `backend/src/Entity/NotificationLog.php` (NOUVEAU)

```php
class NotificationLog {
    int $id;
    int $atelierId;
    string $channel;
    string $provider;
    ?string $templateCode;
    string $toRecipient;
    ?string $subject;
    string $status; // sent | delivered | failed | bounced
    ?string $providerMessageId;
    ?string $errorMessage;
    \DateTime $sentAt;
    ?\DateTime $deliveredAt;
    ?\DateTime $readAt;
    ?string $relatedEntityType;
    ?int $relatedEntityId;
}
```

### 11.3 Service de chiffrement des clés

**Fichier :** `backend/src/Service/ConfigEncryptionService.php` (NOUVEAU)

Chiffre/déchiffre les configs sensibles avec une clé dérivée de `APP_SECRET`.

### 11.4 Service NotificationDispatcher

**Fichier :** `backend/src/Service/NotificationDispatcher.php` (NOUVEAU)

```php
class NotificationDispatcher {
    public function send(NotificationMessage $msg): NotificationResult {
        $providers = $this->getActiveProvidersForChannel(
            $msg->getChannel(), 
            $msg->getAtelierId()
        );
        
        foreach ($providers as $provider) {
            try {
                $result = $provider->send($msg);
                $this->logResult($msg, $provider, $result);
                if ($result->isSuccess()) return $result;
            } catch (\Exception $e) {
                $this->logFailure($msg, $provider, $e);
            }
        }
        
        return NotificationResult::allFailed();
    }
}
```

### 11.5 Interface admin config

**Fichier :** `frontend/pages/admin/notifications/providers.vue` (NOUVEAU)

UI avec sélection provider + formulaire de clés + bouton "Tester la connexion".

### 11.6 Templates multi-canal

**Fichier :** `backend/src/Entity/NotificationTemplate.php` (NOUVEAU — étendre EmailTemplate)

```php
class NotificationTemplate {
    int $id;
    int $atelierId;
    string $code; // rdv_confirme | demande_complementaire | ...
    string $channel; // email | sms | push
    string $libelle;
    ?string $sujet;
    string $corps; // Twig pour email, string interpolation pour SMS
    array $variables;
    bool $isActive;
}
```

### 11.7 Webhooks de retour

```php
#[Route('/api/webhooks/notifications/{provider}', methods: ['POST'])]
public function webhookRetour(string $provider, Request $request)
```

Sécurisé par signature HMAC du provider. Met à jour `NotificationLog` avec le statut réel.

---

## 🔴 LOT 12 — Clauses légales & mentions obligatoires

### 12.1 Texte des clauses

**Fichier :** `backend/src/Entity/ClauseLegale.php` (NOUVEAU)

```php
class ClauseLegale {
    int $id;
    int $atelierId;
    string $code; // accessoires | garantie | essai | gardiennage | cgv
    string $libelle;
    string $texte;
    int $version; // incrémenté à chaque modif
    \DateTime $effectiveFrom;
    bool $isActive;
}
```

### 12.2 Clauses obligatoires par défaut

**Migration** : insérer les textes suivants au bootstrap d'un atelier :

**Clause accessoires :**
> "L'atelier [NOM_ATELIER] décline toute responsabilité concernant les effets personnels et accessoires laissés dans, sur ou avec le véhicule (casque, gants, blouson, antivol, GPS, bagages, etc.). Il est expressément recommandé au client de récupérer tous ses effets avant de laisser son véhicule. Le client reconnaît avoir été informé de cette clause et décharge l'atelier de toute responsabilité en cas de perte, vol ou dégradation desdits accessoires. La présente clause ne s'applique pas en cas de faute lourde ou intentionnelle de l'atelier ou de ses préposés."

**Clause essai routier :**
> "Un essai routier systématique est effectué par le mécanicien intervenant avant restitution du véhicule, conformément aux règles de l'art et à l'obligation de résultat de l'atelier. Le client reconnaît avoir été informé de cette pratique et y consent."

**Clause garantie :**
> "Les travaux réalisés par l'atelier sont garantis pendant [X] jours à compter de la date de restitution, hors pièces d'usure (plaquettes, pneus, huiles, filtres) et dégâts d'usage. La garantie couvre la reprise gratuite des travaux en cas de malfaçon avérée. Elle est annulée en cas d'intervention extérieure sur les mêmes organes."

**Clause gardiennage :**
> "À défaut de reprise du véhicule dans un délai de [X] jours ouvrés après notification de disponibilité, des frais de gardiennage pourront être appliqués à raison de [Y]€ par jour ouvré, après relance préalable. À défaut de reprise dans un délai de 180 jours ouvrés, une procédure de véhicule abandonné pourra être engagée conformément au Code de commerce."

### 12.3 Affichage systématique

**Sur chaque document signé** (OR, rapport d'intervention, devis) :
- Section dédiée aux clauses applicables
- Hash des clauses stocké dans le `signedSnapshot` (preuve d'acceptation à la version X)

### 12.4 Historique des versions

Si une clause est modifiée, création d'une nouvelle version. Les documents signés pointent vers leur version au moment de la signature.

---

## 📊 Ordre d'implémentation recommandé

1. **LOT 0** — Corrections sécurité critiques (bloquant prod)
2. **LOT 6** — Rôles/permissions (socle pour le reste)
3. **LOT 1** — Traçabilité OR/signatures
4. **LOT 7** — Catalogue Prestations (prérequis LOT 4)
5. **LOT 4** — Demandes travaux complémentaires
6. **LOT 5** — Notifications temps réel
7. **LOT 11** — Multi-provider SMS/Email
8. **LOT 2** — Photos typées + hash
9. **LOT 3** — Workflow RDV enrichi
10. **LOT 8** — Rapport intervention + essai routier
11. **LOT 9** — Gardiennage + pièces
12. **LOT 10** — Historique entretien
13. **LOT 12** — Clauses légales

---

## ⚙️ Commandes utiles pour chaque lot

```bash
# Créer une migration
docker compose exec php php bin/console make:migration

# Exécuter les migrations
docker compose exec php php bin/console doctrine:migrations:migrate

# Vider le cache
docker compose exec php php bin/console cache:clear

# Vérifier les routes
docker compose exec php php bin/console debug:router

# Vérifier le workflow
docker compose exec php php bin/console workflow:dump rendez_vous | dot -Tpng -o workflow.png

# Lancer les tests
docker compose exec php php bin/phpunit
cd frontend && npm run test:e2e
```

---

## ✅ Checklist de validation par lot

Pour chaque lot, vérifier :
- [ ] Migration Doctrine créée et testée en dry-run
- [ ] Entités avec annotations complètes
- [ ] Controllers avec attributs `#[IsGranted]` corrects
- [ ] Validation des entrées (DTOs + contraintes Symfony Validator)
- [ ] Audit log sur les actions sensibles
- [ ] Tests PHPUnit au minimum sur les services critiques
- [ ] Documentation OpenAPI générée (via API Platform)
- [ ] Pages frontend correspondantes avec permissions
- [ ] Tests E2E Playwright sur les parcours clés
- [ ] Code review avec focus sur la cohérence métier

---

## 🔗 Fichiers de référence existants (contexte)

- Workflow RDV actuel : `backend/config/packages/workflow.yaml`
- Sécurité actuelle : `backend/config/packages/security.yaml`
- Entités existantes : `backend/src/Entity/*.php`
- Controllers existants : `backend/src/Controller/*.php`
- Services existants : `backend/src/Service/*.php`
- Frontend pages : `frontend/pages/**/*.vue`
- Stores Pinia : `frontend/stores/*.ts`

---

## ⚠️ Points d'attention transversaux

1. **Toujours** valider les rôles sur les endpoints backend (ne jamais se fier au frontend)
2. **Toujours** utiliser des transactions pour les opérations multi-entités
3. **Toujours** hasher/chiffrer les données sensibles stockées
4. **Toujours** logger les actions critiques dans `audit_log`
5. **Toujours** valider les entrées utilisateur avec Symfony Validator
6. **Toujours** utiliser bcmath ou strings pour les calculs financiers (jamais de floats)
7. **Jamais** de modification directe d'un document signé (OR, rapport, devis accepté)
8. **Jamais** de saisie de prix/temps par un mécanicien
9. **Toujours** exposer le contenu public au strict minimum (pas de PII dans /api/public)
10. **Toujours** respecter le principe du moindre privilège (rôles paramétrables mais restrictifs par défaut)

---

**Document généré le : Avril 2026**
**Version : 1.0 — Cadrage métier validé**
**Usage : Guide d'implémentation IDE (Cursor, Copilot, Claude Code)**
