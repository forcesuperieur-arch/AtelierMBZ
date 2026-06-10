# Plan d'implémentation — Système de Notifications

**Date :** 2026-05-22  
**Contexte :** MVP mono-atelier  
**Objectif :** Rendre le système de notifications pleinement fonctionnel pour la mise en production

---

## État actuel (synthèse audit)

Le système est **à 70% fonctionnel**. L'architecture est bonne mais trois déconnections critiques bloquent tout :

| Composant | État | Problème |
|-----------|------|---------|
| `NotificationDispatcher` | ✅ Complet | Multi-provider (Twilio/OVH/Email), fallback, logging |
| `MercureNotifier` | ✅ Complet | Push temps réel par atelier |
| `NotificationController` | ✅ Complet | API list/acknowledge/markRead |
| `NotificationProviderController` | ✅ Complet | CRUD providers/templates + webhooks |
| `AppNotificationBell` + `NotificationPopIn` | ✅ Complet | UI frontend opérationnelle |
| `ProcessNotificationEscalationsCommand` | ✅ Complet | Exécuté chaque minute via Schedule |
| `RdvWorkflowListener` | ❌ Déconnecté | Utilise legacy `SendRappelHandler` — pas de SMS, pas de Notification entity UI |
| `RappelProchaineRevisionCommand` | ❌ Vide | TODO ligne 46, jamais schedulé, n'envoie rien |
| Escalations SMS | ❌ Incomplet | `targetInfo` = "ROLE_X" sans numéro de téléphone |
| `EmailTemplate` vs `NotificationTemplate` | ⚠️ Doublon | Deux systèmes parallèles, `SendRappelHandler` utilise l'ancien |

---

## Notifications critiques pour le MVP

| Priorité | Événement déclencheur | Canal | Template |
|----------|----------------------|-------|---------|
| 🔴 P0 | RDV confirmé | Email + SMS | `rdv_confirmation` |
| 🔴 P0 | Rappel J-1 avant RDV | Email + SMS | `rappel_j1` |
| 🔴 P0 | Moto prête à récupérer | SMS | `travaux_termines` |
| 🟠 P1 | Demande travaux supplémentaires | Push UI + SMS | `demande_complementaire` |
| 🟠 P1 | Rappel J-3 avant RDV | Email | `rappel_j3` |
| 🟡 P2 | Rappel révision J-30 | Email | `rappel_revision` |
| 🟡 P2 | No-show client | Push UI | (interne atelier) |

---

## PHASE 1 — Corrections bloquantes (2-3 jours)

### Tâche 1.1 — Ajouter `phoneNumber` sur `User` et `Client`

**Pourquoi :** Les escalations SMS construisent `targetInfo = "ROLE_X"` sans numéro.  
Sans numéro, `NotificationDispatcher` ne peut pas envoyer le SMS.

**Fichiers à modifier :**
- `src/Entity/User.php` — ajouter champ `phoneNumber`
- `src/Entity/Client.php` — vérifier que `telephone` est bien mappé
- Créer migration Doctrine

**Migration :**
```php
$this->addSql('ALTER TABLE user ADD phone_number VARCHAR(20) DEFAULT NULL');
```

**Dans `User.php` :**
```php
#[ORM\Column(length: 20, nullable: true)]
private ?string $phoneNumber = null;
```

---

### Tâche 1.2 — Brancher `RdvWorkflowListener` sur `NotificationDispatcher`

**Pourquoi :** Actuellement le listener dispatche `SendRappelMessage` (legacy EmailTemplate).  
Résultat : email envoyé via l'ancien système, **aucune Notification entity créée**, **pas de SMS**, **pas d'escalation**.

**Fichier à modifier :** `src/EventListener/RdvWorkflowListener.php`

**Avant :**
```php
public function __construct(
    private MessageBusInterface $bus,
) {}

public function __invoke(CompletedEvent $event): void
{
    match ($transition) {
        'confirmer' => $this->bus->dispatch(new SendRappelMessage($rdv->getId(), 'confirmation')),
        'terminer'  => $this->bus->dispatch(new SendRappelMessage($rdv->getId(), 'travaux_termines')),
        // ...
    };
}
```

**Après :**
```php
public function __construct(
    private NotificationDispatcher $dispatcher,
    private EntityManagerInterface $em,
    private MercureNotifier $mercure,
) {}

public function __invoke(CompletedEvent $event): void
{
    $rdv    = $event->getSubject();
    $client = $rdv->getClient();
    $atId   = $rdv->getAtelierId();

    match ($transition) {
        'confirmer' => $this->notifyClient($rdv, 'rdv_confirmation', 'Confirmation RDV'),
        'terminer'  => $this->notifyClient($rdv, 'travaux_termines', 'Moto prête'),
        'attendre_pieces' => $this->notifyClient($rdv, 'attente_pieces', 'En attente de pièces'),
        default     => null,
    };
}

private function notifyClient(RendezVous $rdv, string $templateCode, string $uiTitle): void
{
    $client = $rdv->getClient();
    $atId   = $rdv->getAtelierId();
    $vars   = [
        'client_nom'   => $client->getNom(),
        'client_prenom'=> $client->getPrenom(),
        'date_rdv'     => $rdv->getDateHeureDebut()->format('d/m/Y à H:i'),
        'type_travaux' => $rdv->getTypeIntervention(),
    ];

    // Email
    if ($client->getEmail()) {
        $this->dispatcher->sendFromTemplate($templateCode, 'email', $atId, $client->getEmail(), $vars, 'RendezVous', $rdv->getId());
    }

    // SMS
    if ($client->getTelephone()) {
        $this->dispatcher->sendFromTemplate($templateCode, 'sms', $atId, $client->getTelephone(), $vars, 'RendezVous', $rdv->getId());
    }

    // Notification UI interne
    $notif = new Notification();
    $notif->setAtelierId($atId);
    $notif->setType($templateCode);
    $notif->setSeverity('info');
    $notif->setTitle($uiTitle);
    $notif->setMessage($client->getPrenom() . ' ' . $client->getNom() . ' — ' . $rdv->getDateHeureDebut()->format('d/m/Y H:i'));
    $notif->setRelatedEntityType('RendezVous');
    $notif->setRelatedEntityId($rdv->getId());
    $notif->setTargetRoles(['ROLE_RECEPTIONNAIRE', 'ROLE_ADMIN']);
    $this->em->persist($notif);
    $this->em->flush();

    $this->mercure->publishToAtelier($atId, $notif);
}
```

---

### Tâche 1.3 — Implémenter `RappelProchaineRevisionCommand`

**Pourquoi :** Commande codée mais TODO ligne 46. Non schedulée. N'envoie rien.

**Fichier à modifier :** `src/Command/RappelProchaineRevisionCommand.php`

**Remplacer le TODO par :**
```php
public function __construct(
    private EntityManagerInterface $em,
    private NotificationDispatcher $dispatcher,
) {
    parent::__construct();
}

// Dans execute() — remplacer le TODO :
if ($client && $client->getEmail()) {
    $vars = [
        'client_nom'    => $client->getNom(),
        'client_prenom' => $client->getPrenom(),
        'date_revision' => $ordre->getProchaineRevisionDate()->format('d/m/Y'),
        'vehicule'      => $rdv->getVehicule()?->getMarque() . ' ' . $rdv->getVehicule()?->getModele(),
    ];

    $this->dispatcher->sendFromTemplate(
        'rappel_revision', 'email',
        $ordre->getAtelierId(),
        $client->getEmail(),
        $vars,
        'OrdreReparation',
        $ordre->getId()
    );

    if ($client->getTelephone()) {
        $this->dispatcher->sendFromTemplate(
            'rappel_revision', 'sms',
            $ordre->getAtelierId(),
            $client->getTelephone(),
            $vars,
            'OrdreReparation',
            $ordre->getId()
        );
    }

    $count++;
}
```

**Ajouter au Schedule :** `src/Schedule.php`
```php
->add(RecurringMessage::cron('0 9 * * *', new RunCommandMessage('app:rappel-prochaine-revision')))
```

---

### Tâche 1.4 — Corriger les escalations SMS (numéros de téléphone)

**Pourquoi :** `DemandeTravauxSuppController::createEscalationSchedule()` met `targetInfo = "ROLE_X"`.  
`ProcessNotificationEscalationsCommand` reçoit ça comme destinataire SMS — invalide.

**Fichier à modifier :** `src/Controller/DemandeTravauxSuppController.php`

**Créer un service de résolution :** `src/Service/RolePhoneResolver.php`
```php
class RolePhoneResolver
{
    public function __construct(private EntityManagerInterface $em) {}

    /** Retourne les numéros de téléphone des users actifs ayant ce rôle dans cet atelier */
    public function resolvePhones(string $role, int $atelierId): array
    {
        $users = $this->em->getRepository(User::class)
            ->createQueryBuilder('u')
            ->where('u.atelierId = :atId')
            ->andWhere('u.roles LIKE :role')
            ->andWhere('u.phoneNumber IS NOT NULL')
            ->setParameter('atId', $atelierId)
            ->setParameter('role', '%' . $role . '%')
            ->getQuery()
            ->getResult();

        return array_filter(array_map(fn($u) => $u->getPhoneNumber(), $users));
    }
}
```

**Dans `createEscalationSchedule()` :**
```php
// Niveau 3 — SMS responsable atelier
$phones = $this->phoneResolver->resolvePhones('ROLE_ADMIN', $notif->getAtelierId());
foreach ($phones as $phone) {
    $escalation = new NotificationEscalation();
    $escalation->setLevel(3);
    $escalation->setChannel('sms');
    $escalation->setTargetInfo($phone); // numéro réel
    $escalation->setScheduledAt(new \DateTimeImmutable('+10 minutes'));
    // ...
}
```

---

## PHASE 2 — Unification des templates (1-2 jours)

### Tâche 2.1 — Migrer `SendRappelHandler` vers `NotificationDispatcher`

**Pourquoi :** Deux systèmes parallèles (`EmailTemplate` legacy + `NotificationTemplate` nouveau).  
`SendRappelHandler` utilise encore l'ancien.

**Plan de migration :**
1. Vérifier que tous les templates `rdv_confirmation`, `rappel_j1`, `rappel_j3`, `travaux_termines` existent dans `NotificationTemplate` (via `NotificationTemplateCatalog::getDefaults()`)
2. Dans `SendRappelHandler`, remplacer l'appel `EmailTemplate` par `NotificationDispatcher::sendFromTemplate()`
3. Garder `EmailTemplate` en lecture seule pendant 1 sprint puis supprimer

**Note :** Ne pas supprimer `EmailTemplate` avant que tous les anciens templates soient migrés et testés.

---

### Tâche 2.2 — Ajouter template `rappel_revision` au catalogue

**Fichier :** `src/Service/NotificationTemplateCatalog.php`

**Ajouter dans `getDefaults()` :**
```php
[
    'code'      => 'rappel_revision',
    'channel'   => 'email',
    'libelle'   => 'Rappel prochaine révision',
    'sujet'     => 'Rappel : révision de votre moto le {{ date_revision }}',
    'corps'     => "Bonjour {{ client_prenom }},\n\nVotre {{ vehicule }} est due pour révision le {{ date_revision }}.\n\nPrenez rendez-vous en ligne ou appelez-nous.",
    'variables' => ['client_nom', 'client_prenom', 'date_revision', 'vehicule'],
],
[
    'code'      => 'rappel_revision',
    'channel'   => 'sms',
    'libelle'   => 'Rappel prochaine révision SMS',
    'sujet'     => null,
    'corps'     => "Bonjour {{ client_prenom }}, votre {{ vehicule }} est due pour révision le {{ date_revision }}. Prenez RDV sur notre site.",
    'variables' => ['client_prenom', 'date_revision', 'vehicule'],
],
```

---

## PHASE 3 — Finalisation et robustesse (1 jour)

### Tâche 3.1 — Configurer un provider SMS pour le développement

**Pourquoi :** Sans provider SMS actif, tous les SMS tombent silencieusement.  
En dev, utiliser `log_sms` (déjà implémenté dans `NotificationDispatcher::sendSmsLogged()`).

**Via l'admin UI** (`/admin/notifications/providers`) ou directement en BDD :
```sql
INSERT INTO notification_provider_config 
  (atelier_id, channel, provider, is_primary, is_active, priority, config_encrypted)
VALUES 
  (1, 'sms', 'log_sms', true, true, 1, '{}');
```

**Pour la prod :** Configurer Twilio ou OVH via l'interface admin.

---

### Tâche 3.2 — Configurer le mailer pour le développement

**`backend/.env` :**
```
MAILER_DSN=smtp://mailhog:1025
```
Mailhog est déjà dans `docker-compose.yml` — les emails de dev sont visibles sur `http://localhost:8025`.

---

### Tâche 3.3 — Ajouter gestion d'erreur dans `MercureNotifier`

**Pourquoi :** Si Mercure est down, `publishToAtelier()` lève une exception non catchée.

```php
public function publishToAtelier(int $atelierId, Notification $notif): void
{
    try {
        $this->hub->publish(/* ... */);
    } catch (\Throwable $e) {
        $this->logger->warning('Mercure publish failed (non-blocking): {error}', [
            'error' => $e->getMessage(),
        ]);
    }
}
```

---

### Tâche 3.4 — Ajouter le rappel J-1 à `ProcessScheduledRappelsHandler`

**Vérifier que `rappel_j1` est bien dispatché :**  
`ProcessScheduledRappelsHandler` dispatche J-3 et J-1 via `SendRappelMessage`.  
Après la tâche 2.1 (migration), s'assurer que J-1 passe bien par `NotificationDispatcher` avec SMS.

---

## Récapitulatif des fichiers à modifier

| Fichier | Type de changement | Phase |
|---------|-------------------|-------|
| `src/Entity/User.php` | Ajout champ `phoneNumber` | 1.1 |
| `migrations/VersionXXX.php` | ALTER TABLE user ADD phone_number | 1.1 |
| `src/EventListener/RdvWorkflowListener.php` | Brancher NotificationDispatcher | 1.2 |
| `src/Command/RappelProchaineRevisionCommand.php` | Implémenter le TODO | 1.3 |
| `src/Schedule.php` | Ajouter cron révisions (9h/jour) | 1.3 |
| `src/Service/RolePhoneResolver.php` | Nouveau service (créer) | 1.4 |
| `src/Controller/DemandeTravauxSuppController.php` | Escalations avec vrais numéros | 1.4 |
| `src/MessageHandler/SendRappelHandler.php` | Migrer vers NotificationDispatcher | 2.1 |
| `src/Service/NotificationTemplateCatalog.php` | Ajouter template rappel_revision | 2.2 |
| `src/Service/MercureNotifier.php` | Try/catch sur publish | 3.3 |
| `src/MessageHandler/ProcessScheduledRappelsHandler.php` | Vérifier SMS J-1 | 3.4 |

---

## Checklist de validation MVP

Avant la mise en production, tester manuellement ces scénarios :

- [ ] Créer un RDV → email de confirmation reçu par le client
- [ ] Passer un RDV en `confirme` → notification UI visible dans la cloche
- [ ] Attendre J-1 (ou simuler) → rappel email + SMS envoyé
- [ ] Passer un RDV en `termine` → SMS "moto prête" envoyé au client
- [ ] Créer une demande de travaux supplémentaires depuis le companion → notification UI + SMS atelier après 10 min
- [ ] OR avec `prochaineRevisionDate` dans 30 jours → exécuter la commande → email envoyé
- [ ] Configurer un provider Twilio en admin → tester → succès

---

## Ce qui est hors scope MVP

- Push notifications (Firebase/WebPush)
- Déduplication avancée
- Rate limiting par notification
- Multi-langue templates
- Archivage automatique des notifications expirées
- Notification par WhatsApp

---

## Estimation

| Phase | Effort estimé |
|-------|--------------|
| Phase 1 — Corrections bloquantes | 2-3 jours |
| Phase 2 — Unification templates | 1-2 jours |
| Phase 3 — Robustesse | 1 jour |
| **Total** | **4-6 jours** |
