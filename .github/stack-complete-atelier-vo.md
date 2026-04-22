# Stack Complète - Application Atelier Gestion Parc VO

## 📋 Table des matières
1. [Architecture globale](#architecture-globale)
2. [Symfony Bundles essentiels](#symfony-bundles-essentiels)
3. [Upload & Médias](#upload--médias)
4. [Sécurité renforcée](#sécurité-renforcée)
5. [Monitoring & Performance](#monitoring--performance)
6. [Testing & Qualité](#testing--qualité)
7. [Communications](#communications)
8. [Documents & Exports](#documents--exports)
9. [Base de données](#base-de-données)
10. [Frontend/Nuxt addons](#frontendnuxt-addons)
11. [Intégrations tierces](#intégrations-tierces)
12. [Admin/Backoffice](#adminbackoffice)
13. [PWA & Mobile](#pwa--mobile)
14. [DevOps & Docker](#devops--docker)
15. [Docker Compose complet](#docker-compose-complet)
16. [Plan d'implémentation par phases](#plan-dimplémentation-par-phases)

---

## Architecture globale

```
┌─────────────────────────────────────┐
│         FRONT PUBLIC (Docker)        │
│  Nuxt + Pinia + VeeValidate + PWA   │
│  Mercure client + Image optimization │
│         Port: 3000                   │
└──────────────┬──────────────────────┘
               │
┌──────────────▼──────────────────────┐
│         FRONT ADMIN (Docker)         │
│  Nuxt + EasyAdmin style + Upload     │
│  Mercure + Charts + Calendar         │
│         Port: 3001                   │
└──────────────┬──────────────────────┘
               │
┌──────────────▼──────────────────────┐
│         API SYMFONY (Docker)         │
│  API Platform + JWT + Workflow       │
│  VichUploader + LiipImagine          │
│  StofExtensions + RateLimiter        │
│  Mailer + Notifier + Scheduler       │
│         Port: 8000                   │
└──────────────┬──────────────────────┘
               │
    ┌──────────┼──────────┐
    │          │          │
┌───▼───┐  ┌──▼───┐  ┌──▼────┐
│Worker │  │Redis │  │Mercure│
│Symfony│  │Cache │  │  Hub  │
│Messengr│  │Session│ │ :3002 │
└───┬───┘  └──────┘  └───────┘
    │
┌───▼────────────────────────────────┐
│         PostgreSQL + Redis          │
│   (Données + Cache + Queue jobs)    │
└─────────────────────────────────────┘
```

**Flux type :**
1. Client accède au front public (Nuxt) → consultation VO
2. Front admin (Nuxt) → gestion atelier par équipe
3. Les 2 fronts appellent l'API Symfony via JWT
4. Mercure diffuse les updates temps réel aux 2 fronts
5. Worker traite les tâches async (PDFs, emails, sync annonces)

---

## Symfony Bundles essentiels

### 1. StofDoctrineExtensionsBundle
**Installation :**
```bash
composer require stof/doctrine-extensions-bundle
```

**Fonctionnalités :**
- **Timestampable** : `createdAt` / `updatedAt` automatiques
- **Sluggable** : URLs propres pour fiches VO (`/vehicule/renault-clio-2020`)
- **SoftDelete** : Suppression logique, jamais perdre de données
- **Blameable** : Tracer qui a modifié quoi
- **Sortable** : Ordre des photos véhicules
- **Loggable** : Historique complet des modifications (audit trail)

**Config (`config/packages/stof_doctrine_extensions.yaml`) :**
```yaml
stof_doctrine_extensions:
    default_locale: fr_FR
    orm:
        default:
            timestampable: true
            sluggable: true
            softdeleteable: true
            blameable: true
            sortable: true
            loggable: true
```

**Exemple usage :**
```php
use Gedmo\Mapping\Annotation as Gedmo;

#[ORM\Entity]
class Vehicle
{
    #[Gedmo\Timestampable(on: 'create')]
    private ?\DateTimeInterface $createdAt = null;
    
    #[Gedmo\Timestampable(on: 'update')]
    private ?\DateTimeInterface $updatedAt = null;
    
    #[Gedmo\Slug(fields: ['brand', 'model', 'year'])]
    private ?string $slug = null;
    
    #[Gedmo\SoftDeleteable(fieldName: 'deletedAt')]
    private ?\DateTimeInterface $deletedAt = null;
    
    #[Gedmo\Blameable(on: 'create')]
    private ?string $createdBy = null;
}
```

---

### 2. LexikJWTAuthenticationBundle
**Installation :**
```bash
composer require lexik/jwt-authentication-bundle
```

**Usage :** Authentification API sécurisée pour les 2 fronts

**Config JWT :**
```yaml
# config/packages/lexik_jwt_authentication.yaml
lexik_jwt_authentication:
    secret_key: '%env(resolve:JWT_SECRET_KEY)%'
    public_key: '%env(resolve:JWT_PUBLIC_KEY)%'
    pass_phrase: '%env(JWT_PASSPHRASE)%'
    token_ttl: 3600 # 1h pour admin, plus court pour public
```

**Générer les clés :**
```bash
php bin/console lexik:jwt:generate-keypair
```

**Routes différenciées :**
```yaml
# config/routes.yaml
api_login_public:
    path: /api/public/login
    
api_login_admin:
    path: /api/admin/login
```

---

### 3. NelmioCorsBundle
**Installation :**
```bash
composer require nelmio/cors-bundle
```

**Usage :** Gérer les CORS entre Docker/domaines différents

**Config :**
```yaml
# config/packages/nelmio_cors.yaml
nelmio_cors:
    defaults:
        origin_regex: true
        allow_origin: ['%env(CORS_ALLOW_ORIGIN)%']
        allow_methods: ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS']
        allow_headers: ['Content-Type', 'Authorization']
        expose_headers: ['Link']
        max_age: 3600
    paths:
        '^/api/public':
            allow_origin: ['https://public.votreapp.com']
        '^/api/admin':
            allow_origin: ['https://admin.votreapp.com']
```

---

### 4. API Platform
**Installation :**
```bash
composer require api-platform/core
```

**Usage :** API REST professionnelle avec filtres, pagination, validation auto

**Exemple entité Vehicle :**
```php
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Doctrine\Orm\Filter\RangeFilter;

#[ApiResource(
    operations: [
        new Get(),
        new GetCollection(),
        new Post(security: "is_granted('ROLE_ADMIN')"),
        new Put(security: "is_granted('ROLE_ADMIN')"),
        new Delete(security: "is_granted('ROLE_ADMIN')")
    ],
    normalizationContext: ['groups' => ['vehicle:read']],
    denormalizationContext: ['groups' => ['vehicle:write']]
)]
#[ApiFilter(SearchFilter::class, properties: ['brand' => 'partial', 'model' => 'partial'])]
#[ApiFilter(RangeFilter::class, properties: ['price', 'year', 'mileage'])]
class Vehicle
{
    // ...
}
```

**Avantages :**
- Documentation OpenAPI auto (`/api/docs`)
- Filtres et pagination configurables
- Validation Symfony intégrée
- GraphQL optionnel

---

### 5. FOSRestBundle (Alternative à API Platform)
**Installation :**
```bash
composer require friendsofsymfony/rest-bundle
```

**Usage :** Plus de contrôle manuel sur les endpoints API

---

### 6. SymfonyCastsResetPasswordBundle
**Installation :**
```bash
composer require symfonycasts/reset-password-bundle
```

**Usage :** Réinitialisation mot de passe clé en main

---

### 7. SymfonyCastsVerifyEmailBundle
**Installation :**
```bash
composer require symfonycasts/verify-email-bundle
```

**Usage :** Vérification email à l'inscription

---

### 8. KnpPaginatorBundle
**Installation :**
```bash
composer require knplabs/knp-paginator-bundle
```

**Usage :** Pagination propre dans l'admin

**Exemple :**
```php
public function list(PaginatorInterface $paginator, Request $request): Response
{
    $query = $this->vehicleRepository->createQueryBuilder('v')->getQuery();
    
    $pagination = $paginator->paginate(
        $query,
        $request->query->getInt('page', 1),
        20 // items par page
    );
    
    return $this->render('vehicle/list.html.twig', ['pagination' => $pagination]);
}
```

---

### 9. KnpMenuBundle
**Installation :**
```bash
composer require knplabs/knp-menu-bundle
```

**Usage :** Menus dynamiques selon rôles utilisateurs

---

## Upload & Médias

### 10. VichUploaderBundle
**Installation :**
```bash
composer require vich/uploader-bundle
```

**Usage :** Gestion upload fichiers/photos simplifiée

**Config :**
```yaml
# config/packages/vich_uploader.yaml
vich_uploader:
    db_driver: orm
    mappings:
        vehicle_images:
            uri_prefix: /uploads/vehicles
            upload_destination: '%kernel.project_dir%/public/uploads/vehicles'
            namer: Vich\UploaderBundle\Naming\SmartUniqueNamer
```

**Entité :**
```php
use Vich\UploaderBundle\Mapping\Annotation as Vich;

#[Vich\Uploadable]
class VehicleImage
{
    #[Vich\UploadableField(mapping: 'vehicle_images', fileNameProperty: 'imageName')]
    private ?File $imageFile = null;
    
    private ?string $imageName = null;
}
```

---

### 11. LiipImagineBundle
**Installation :**
```bash
composer require liip/imagine-bundle
```

**Usage :** Redimensionnement images à la volée, miniatures, watermark

**Config :**
```yaml
# config/packages/liip_imagine.yaml
liip_imagine:
    driver: 'gd'
    filter_sets:
        vehicle_thumb:
            quality: 85
            filters:
                thumbnail: { size: [300, 200], mode: outbound }
        vehicle_large:
            quality: 90
            filters:
                thumbnail: { size: [1200, 800], mode: outbound }
        watermarked:
            filters:
                watermark:
                    image: '%kernel.project_dir%/public/watermark.png'
                    size: 0.3
                    position: bottomright
```

**Usage dans Twig :**
```twig
<img src="{{ asset(vehicle.image|imagine_filter('vehicle_thumb')) }}" alt="{{ vehicle.model }}">
```

---

### 12. OneupFlysystemBundle
**Installation :**
```bash
composer require oneup/flysystem-bundle
composer require league/flysystem-aws-s3-v3 # Pour S3
```

**Usage :** Abstraction stockage (local/S3/FTP) → migration cloud facile

**Config :**
```yaml
# config/packages/oneup_flysystem.yaml
oneup_flysystem:
    adapters:
        local_adapter:
            local:
                location: '%kernel.project_dir%/var/storage'
        s3_adapter:
            awss3v3:
                client: aws_s3_client
                bucket: 'your-bucket'
                prefix: 'vehicles/'
    filesystems:
        vehicle_storage:
            adapter: s3_adapter # ou local_adapter en dev
```

---

## Sécurité renforcée

### 13. SchebTwoFactorBundle
**Installation :**
```bash
composer require scheb/2fa-bundle
composer require scheb/2fa-google-authenticator
```

**Usage :** 2FA pour l'admin (Google Authenticator)

**Config :**
```yaml
# config/packages/scheb_2fa.yaml
scheb_two_factor:
    google:
        enabled: true
        issuer: 'Atelier VO'
        server_name: 'admin.votreapp.com'
```

---

### 14. NelmioSecurityBundle
**Installation :**
```bash
composer require nelmio/security-bundle
```

**Usage :** Headers sécurité, CSP, XSS protection

**Config :**
```yaml
# config/packages/nelmio_security.yaml
nelmio_security:
    signed_cookie:
        names: ['*']
    clickjacking:
        paths:
            '^/admin': DENY
            '^/': SAMEORIGIN
    content_type:
        nosniff: true
    xss_protection:
        enabled: true
        mode_block: true
    csp:
        enabled: true
        report_uri: /csp-report
        hosts: []
        content_types: []
        enforce:
            default-src: ['self']
            script-src: ['self', 'unsafe-inline']
            img-src: ['self', 'data:', 'https:']
```

---

### 15. RateLimiterBundle (Symfony natif)
**Usage :** Limiter requêtes API publique

**Config :**
```yaml
# config/packages/rate_limiter.yaml
framework:
    rate_limiter:
        api_public:
            policy: 'sliding_window'
            limit: 100
            interval: '1 hour'
        api_admin:
            policy: 'sliding_window'
            limit: 1000
            interval: '1 hour'
```

**Controller :**
```php
use Symfony\Component\RateLimiter\RateLimiterFactory;

public function search(RateLimiterFactory $apiPublicLimiter, Request $request): Response
{
    $limiter = $apiPublicLimiter->create($request->getClientIp());
    
    if (!$limiter->consume(1)->isAccepted()) {
        throw new TooManyRequestsHttpException();
    }
    
    // Traitement...
}
```

---

### 16. SecurityAdvisoriesBundle
**Installation :**
```bash
composer require roave/security-advisories:dev-latest
```

**Usage :** Alerte si dépendances vulnérables (vérifie à chaque `composer update`)

---

## Monitoring & Performance

### 17. BlackfireBundle
**Installation :**
```bash
composer require blackfire/php-sdk
```

**Usage :** Profiling performance (trouver lenteurs SQL, CPU)

**Installation Blackfire Agent :**
```bash
# Dans docker-compose.yml (voir section Docker)
# Puis profiler via blackfire.io
```

---

### 18. SentryBundle
**Installation :**
```bash
composer require sentry/sentry-symfony
```

**Usage :** Tracking erreurs en production temps réel

**Config :**
```yaml
# config/packages/sentry.yaml
sentry:
    dsn: '%env(SENTRY_DSN)%'
    options:
        environment: '%kernel.environment%'
        release: '%env(APP_VERSION)%'
```

**Avantages :**
- Notification immédiate des bugs en prod
- Stack traces complètes
- Breadcrumbs (actions utilisateur avant erreur)

---

### 19. Prometheus/Grafana
**Usage :** Métriques applicatives (CPU, mémoire, requêtes/sec)

**Bundle :**
```bash
composer require artprima/prometheus-metrics-bundle
```

**Config :**
```yaml
# config/packages/artprima_prometheus_metrics.yaml
artprima_prometheus_metrics:
    namespace: app
    ignored_routes:
        - '_profiler'
        - '_wdt'
```

**Dashboards Grafana :**
- Nombre de véhicules en stock temps réel
- Temps réponse API
- Taux d'erreur
- Nombre de recherches/sec

---

### 20. Doctrine Query Log
**Usage :** Repérer requêtes SQL lentes (problèmes N+1)

**Config dev :**
```yaml
# config/packages/dev/doctrine.yaml
doctrine:
    dbal:
        logging: true
        profiling: true
```

**Puis dans Profiler Symfony :**
- Voir toutes les requêtes par page
- Identifier les requêtes en double
- Optimiser avec JOIN

---

### 21. VarDumper Server
**Installation :**
```bash
composer require --dev symfony/var-dumper
```

**Usage :** Debug propre sans polluer les logs

**Lancer le serveur :**
```bash
php bin/console server:dump
```

**Dans le code :**
```php
dump($vehicle); // S'affiche dans le terminal du serveur dump
```

---

## Testing & Qualité

### 22. PHPUnit + Doctrine Fixtures
**Installation :**
```bash
composer require --dev phpunit/phpunit
composer require --dev doctrine/doctrine-fixtures-bundle
```

**Usage :** Tests automatisés avec données de test

**Exemple test :**
```php
namespace App\Tests\Repository;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class VehicleRepositoryTest extends KernelTestCase
{
    public function testFindAvailableVehicles(): void
    {
        self::bootKernel();
        $repository = static::getContainer()->get(VehicleRepository::class);
        
        $vehicles = $repository->findAvailable();
        
        $this->assertGreaterThan(0, count($vehicles));
    }
}
```

---

### 23. Alice + Faker
**Installation :**
```bash
composer require --dev alice/alice-bundle
composer require --dev fakerphp/faker
```

**Usage :** Génération données réalistes (1000 VO de test)

**Fixture (`fixtures/vehicles.yaml`) :**
```yaml
App\Entity\Vehicle:
    vehicle_{1..1000}:
        brand: '<company()>'
        model: '<word()> <randomNumber(3)>'
        year: '<numberBetween(2015, 2024)>'
        mileage: '<numberBetween(5000, 150000)>'
        price: '<numberBetween(8000, 45000)>'
        fuelType: '<randomElement(["diesel", "essence", "hybrid", "electric"])>'
        transmission: '<randomElement(["manual", "automatic"])>'
        description: '<paragraph()>'
```

**Charger :**
```bash
php bin/console hautelook:fixtures:load
```

---

### 24. PHPStan Level Max
**Installation :**
```bash
composer require --dev phpstan/phpstan
composer require --dev phpstan/extension-installer
composer require --dev phpstan/phpstan-symfony
composer require --dev phpstan/phpstan-doctrine
```

**Usage :** Analyse statique code (éviter bugs)

**Config (`phpstan.neon`) :**
```neon
parameters:
    level: 9 # Max
    paths:
        - src
        - tests
    symfony:
        container_xml_path: var/cache/dev/App_KernelDevDebugContainer.xml
    doctrine:
        repositoryClass: App\Repository\BaseRepository
```

**Run :**
```bash
vendor/bin/phpstan analyse
```

---

### 25. PHP-CS-Fixer
**Installation :**
```bash
composer require --dev friendsofphp/php-cs-fixer
```

**Usage :** Formatage code automatique

**Config (`.php-cs-fixer.php`) :**
```php
<?php

return (new PhpCsFixer\Config())
    ->setRules([
        '@Symfony' => true,
        'array_syntax' => ['syntax' => 'short'],
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
    ])
    ->setFinder(
        PhpCsFixer\Finder::create()
            ->in(__DIR__ . '/src')
            ->in(__DIR__ . '/tests')
    );
```

**Run :**
```bash
vendor/bin/php-cs-fixer fix
```

---

## Communications

### 26. SymfonyMailer + Mailer Component
**Installation :**
```bash
composer require symfony/mailer
composer require symfony/mailgun-mailer # Ou sendgrid, postmark, etc.
```

**Usage :** Emails transactionnels (confirmations, relances)

**Config :**
```yaml
# config/packages/mailer.yaml
framework:
    mailer:
        dsn: '%env(MAILER_DSN)%' # mailgun+smtp://username:password@default
        envelope:
            sender: 'noreply@votreapp.com'
```

**Exemple :**
```php
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

public function sendVehicleReservation(MailerInterface $mailer, Vehicle $vehicle): void
{
    $email = (new Email())
        ->from('noreply@votreapp.com')
        ->to('client@example.com')
        ->subject('Réservation confirmée')
        ->html($this->renderView('emails/reservation.html.twig', ['vehicle' => $vehicle]));
    
    $mailer->send($email);
}
```

---

### 27. NotifierComponent + Mercure
**Installation :**
```bash
composer require symfony/notifier
composer require symfony/mercure-bundle
```

**Usage :** Notifications multi-canal (email, SMS, push, Slack)

**Config :**
```yaml
# config/packages/notifier.yaml
framework:
    notifier:
        chatter_transports:
            slack: '%env(SLACK_DSN)%'
        texter_transports:
            twilio: '%env(TWILIO_DSN)%'
        channel_policy:
            urgent: ['sms', 'email']
            high: ['slack', 'email']
            low: ['email']
```

**Exemple :**
```php
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Notifier\Notification\Notification;

public function notifyNewVehicle(NotifierInterface $notifier): void
{
    $notification = (new Notification('Nouveau véhicule en stock!', ['chat/slack']))
        ->content('Une Renault Clio 2023 vient d\'arriver.');
    
    $notifier->send($notification);
}
```

---

### 28. Twilio/Vonage Bundle
**Installation :**
```bash
composer require twilio/sdk
# Ou
composer require vonage/client
```

**Usage :** SMS automatiques clients

**Exemple avec Twilio :**
```php
use Twilio\Rest\Client;

public function sendSmsReady(Vehicle $vehicle): void
{
    $client = new Client($_ENV['TWILIO_SID'], $_ENV['TWILIO_TOKEN']);
    
    $client->messages->create(
        '+33612345678', // Téléphone client
        [
            'from' => '+33987654321',
            'body' => "Votre {$vehicle->brand} {$vehicle->model} est prête!"
        ]
    );
}
```

---

### 29. Symfony/Scheduler Component
**Installation :**
```bash
composer require symfony/scheduler
```

**Usage :** Tâches planifiées (relances auto, cotations hebdo)

**Config :**
```php
// config/packages/scheduler.yaml
namespace App\Scheduler;

use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

#[AsSchedule('default')]
class DefaultScheduleProvider implements ScheduleProviderInterface
{
    public function getSchedule(): Schedule
    {
        return (new Schedule())
            ->add(RecurringMessage::every('1 day', new UpdateVehicleValuationsMessage()))
            ->add(RecurringMessage::cron('0 9 * * 1', new SendWeeklyReportMessage())); // Lundi 9h
    }
}
```

---

## Documents & Exports

### 30. KnpSnappyBundle (wkhtmltopdf)
**Installation :**
```bash
composer require knplabs/knp-snappy-bundle
# Installer wkhtmltopdf : https://wkhtmltopdf.org/downloads.html
```

**Usage :** Génération PDF (factures, rapports expertise)

**Config :**
```yaml
# config/packages/knp_snappy.yaml
knp_snappy:
    pdf:
        enabled: true
        binary: /usr/local/bin/wkhtmltopdf
        options:
            enable-local-file-access: true
```

**Exemple :**
```php
use Knp\Bundle\SnappyBundle\Snappy\Response\PdfResponse;
use Knp\Snappy\Pdf;

public function generateInvoice(Pdf $pdf, Vehicle $vehicle): Response
{
    $html = $this->renderView('pdf/invoice.html.twig', ['vehicle' => $vehicle]);
    
    return new PdfResponse(
        $pdf->getOutputFromHtml($html),
        'facture-' . $vehicle->getId() . '.pdf'
    );
}
```

---

### 31. PhpSpreadsheet
**Installation :**
```bash
composer require phpoffice/phpspreadsheet
```

**Usage :** Export Excel (listings VO, stats)

**Exemple :**
```php
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

public function exportVehicles(array $vehicles): Response
{
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    
    $sheet->setCellValue('A1', 'Marque');
    $sheet->setCellValue('B1', 'Modèle');
    $sheet->setCellValue('C1', 'Prix');
    
    $row = 2;
    foreach ($vehicles as $vehicle) {
        $sheet->setCellValue('A' . $row, $vehicle->getBrand());
        $sheet->setCellValue('B' . $row, $vehicle->getModel());
        $sheet->setCellValue('C' . $row, $vehicle->getPrice());
        $row++;
    }
    
    $writer = new Xlsx($spreadsheet);
    $temp = tempnam(sys_get_temp_dir(), 'vehicles');
    $writer->save($temp);
    
    return $this->file($temp, 'vehicules.xlsx');
}
```

---

### 32. TwigExtraBundle
**Installation :**
```bash
composer require twig/extra-bundle
```

**Usage :** Filtres Twig avancés pour templating

---

## Base de données

### 33. DoctrineMigrationsBundle
**Installation :**
```bash
composer require doctrine/doctrine-migrations-bundle
```

**Usage :** Versioning BDD (déjà inclus dans Symfony)

**Commandes :**
```bash
php bin/console make:migration # Créer migration
php bin/console doctrine:migrations:migrate # Appliquer
php bin/console doctrine:migrations:status # Statut
```

---

### 34. DoctrineFixturesBundle
**Installation :**
```bash
composer require --dev doctrine/doctrine-fixtures-bundle
```

**Usage :** Données de dev/démo

---

### 35. StofDoctrineExtensionsBundle + Loggable
*(Déjà mentionné section 1)*

**Usage :** Historique complet modifications (audit trail)

**Exemple :**
```php
use Gedmo\Loggable\Entity\LogEntry;

// Récupérer l'historique d'un véhicule
$repo = $em->getRepository(LogEntry::class);
$logs = $repo->getLogEntries($vehicle);

foreach ($logs as $log) {
    echo "{$log->getUsername()} a modifié {$log->getObjectClass()} le {$log->getLoggedAt()->format('Y-m-d H:i')}";
}
```

---

## Frontend/Nuxt addons

### 36. @nuxtjs/axios
**Installation :**
```bash
npm install @nuxtjs/axios
```

**Usage :** HTTP client optimisé

**Config (`nuxt.config.ts`) :**
```typescript
export default defineNuxtConfig({
  modules: ['@nuxtjs/axios'],
  axios: {
    baseURL: process.env.API_URL || 'http://localhost:8000/api'
  }
})
```

---

### 37. @pinia/nuxt
**Installation :**
```bash
npm install pinia @pinia/nuxt
```

**Usage :** State management moderne (remplacement Vuex)

**Store exemple (`stores/vehicles.ts`) :**
```typescript
import { defineStore } from 'pinia'

export const useVehiclesStore = defineStore('vehicles', {
  state: () => ({
    vehicles: [],
    filters: {
      brand: null,
      minPrice: 0,
      maxPrice: 50000
    }
  }),
  actions: {
    async fetchVehicles() {
      const { data } = await $fetch('/api/public/vehicles', {
        params: this.filters
      })
      this.vehicles = data
    }
  }
})
```

---

### 38. @vueuse/nuxt
**Installation :**
```bash
npm install @vueuse/nuxt @vueuse/core
```

**Usage :** Utilitaires Vue composition API

**Exemples :**
```typescript
import { useLocalStorage, useDark, useToggle } from '@vueuse/core'

// LocalStorage réactif
const favorites = useLocalStorage('favorite-vehicles', [])

// Dark mode
const isDark = useDark()
const toggleDark = useToggle(isDark)
```

---

### 39. @nuxt/image
**Installation :**
```bash
npm install @nuxt/image
```

**Usage :** Optimisation images automatique

**Config :**
```typescript
export default defineNuxtConfig({
  modules: ['@nuxt/image'],
  image: {
    domains: ['votreapp.com'],
    alias: {
      vehicles: '/uploads/vehicles'
    }
  }
})
```

**Usage :**
```vue
<template>
  <NuxtImg
    src="/uploads/vehicles/clio.jpg"
    width="300"
    height="200"
    format="webp"
    :alt="vehicle.model"
  />
</template>
```

---

### 40. nuxt-security
**Installation :**
```bash
npm install nuxt-security
```

**Usage :** Headers sécurité frontend

**Config :**
```typescript
export default defineNuxtConfig({
  modules: ['nuxt-security'],
  security: {
    headers: {
      contentSecurityPolicy: {
        'img-src': ["'self'", 'data:', 'https:'],
        'script-src': ["'self'", "'unsafe-inline'"]
      }
    }
  }
})
```

---

### 41. @nuxtjs/google-fonts
**Installation :**
```bash
npm install @nuxtjs/google-fonts
```

**Usage :** Polices optimisées

---

### 42. @vee-validate/nuxt
**Installation :**
```bash
npm install vee-validate @vee-validate/nuxt
```

**Usage :** Validation formulaires côté client

---

### 43. @nuxtjs/device
**Installation :**
```bash
npm install @nuxtjs/device
```

**Usage :** Détection mobile/desktop

---

### 44. nuxt-swiper
**Installation :**
```bash
npm install nuxt-swiper
```

**Usage :** Carousels photos VO fluides

---

### 45. @formkit/nuxt
**Installation :**
```bash
npm install @formkit/nuxt
```

**Usage :** Formulaires complexes (filtres recherche VO)

---

## Intégrations tierces

### 46. Stripe/PayPlug Bundle
**Installation :**
```bash
composer require stripe/stripe-php
```

**Usage :** Paiement en ligne (acomptes VO)

---

### 47. Google Maps API
**Usage :** Localisation garage, calcul distance client

**Frontend :**
```bash
npm install @googlemaps/js-api-loader
```

---

### 48. Elasticsearch + FOSElasticaBundle
**Installation :**
```bash
composer require friendsofsymfony/elastica-bundle
```

**Usage :** Recherche full-text ultra-rapide dans parc VO

**Config :**
```yaml
# config/packages/fos_elastica.yaml
fos_elastica:
    clients:
        default: { host: elasticsearch, port: 9200 }
    indexes:
        vehicles:
            properties:
                brand: ~
                model: ~
                description: ~
                year: { type: integer }
                price: { type: float }
```

---

### 49. Redis/Memcached
**Installation :**
```bash
composer require symfony/cache
composer require predis/predis # Client Redis
```

**Usage :** Cache distribué (sessions, résultats recherche)

**Config :**
```yaml
# config/packages/cache.yaml
framework:
    cache:
        app: cache.adapter.redis
        default_redis_provider: 'redis://redis:6379'
        pools:
            vehicle_search_cache:
                adapter: cache.adapter.redis
                default_lifetime: 3600
```

---

### 50. RabbitMQ/Redis pour Messenger
**Installation :**
```bash
composer require symfony/amqp-messenger # RabbitMQ
# Ou
composer require symfony/redis-messenger # Redis
```

**Usage :** File d'attente messages robuste

**Config :**
```yaml
# config/packages/messenger.yaml
framework:
    messenger:
        transports:
            async:
                dsn: '%env(MESSENGER_TRANSPORT_DSN)%' # amqp://guest:guest@rabbitmq:5672/%2f/messages
                retry_strategy:
                    max_retries: 3
                    delay: 1000
                    multiplier: 2
```

---

### 51. Webhook Bundle
**Installation :**
```bash
composer require symfony/webhook
```

**Usage :** Recevoir événements externes (LeBonCoin, paiements)

---

## Admin/Backoffice

### 52. EasyAdmin 4
**Installation :**
```bash
composer require easycorp/easyadmin-bundle
```

**Usage :** Interface admin moderne sans coder

**Config Dashboard :**
```php
namespace App\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;

class DashboardController extends AbstractDashboardController
{
    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('Atelier VO - Admin');
    }
    
    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Dashboard', 'fa fa-home');
        yield MenuItem::linkToCrud('Véhicules', 'fa fa-car', Vehicle::class);
        yield MenuItem::linkToCrud('Clients', 'fa fa-users', Customer::class);
    }
}
```

---

### 53. SonataAdminBundle (Alternative)
**Installation :**
```bash
composer require sonata-project/admin-bundle
```

**Usage :** Plus customisable mais plus complexe

---

## PWA & Mobile

### 54. @kevinmarrec/nuxt-pwa
**Installation :**
```bash
npm install @kevinmarrec/nuxt-pwa
```

**Usage :** App installable sur mobile

**Config :**
```typescript
export default defineNuxtConfig({
  modules: ['@kevinmarrec/nuxt-pwa'],
  pwa: {
    manifest: {
      name: 'Atelier VO',
      short_name: 'AtelierVO',
      description: 'Gestion parc véhicules d\'occasion',
      theme_color: '#1a73e8',
      icons: [
        {
          src: '/icon-192.png',
          sizes: '192x192',
          type: 'image/png'
        }
      ]
    }
  }
})
```

---

### 55. @nuxtjs/web-vitals
**Installation :**
```bash
npm install @nuxtjs/web-vitals
```

**Usage :** Performance monitoring (Core Web Vitals)

---

## DevOps & Docker

### 56. Docker Compose avec services complets
*(Voir section suivante pour fichier complet)*

---

### 57. Symfony CLI
**Installation :**
```bash
# macOS/Linux
curl -sS https://get.symfony.com/cli/installer | bash

# Ou via Homebrew
brew install symfony-cli/tap/symfony-cli
```

**Usage :** Serveur dev local + certificats HTTPS auto

**Commandes :**
```bash
symfony server:start -d # Démarrer serveur
symfony server:ca:install # Installer CA local (HTTPS)
symfony check:security # Vérifier vulnérabilités
```

---

### 58. Deployer
**Installation :**
```bash
composer require --dev deployer/deployer
```

**Usage :** Déploiement automatisé

**Config (`deploy.php`) :**
```php
<?php
namespace Deployer;

require 'recipe/symfony.php';

set('application', 'atelier-vo');
set('repository', 'git@github.com:you/atelier-vo.git');

host('production')
    ->set('hostname', 'votreserveur.com')
    ->set('remote_user', 'deploy')
    ->set('deploy_path', '/var/www/atelier-vo');

after('deploy:failed', 'deploy:unlock');
```

**Déployer :**
```bash
vendor/bin/dep deploy production
```

---

## Docker Compose complet

```yaml
version: '3.8'

services:
  # ============================================
  # NGINX - Reverse proxy
  # ============================================
  nginx:
    image: nginx:alpine
    container_name: atelier-nginx
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./docker/nginx/nginx.conf:/etc/nginx/nginx.conf:ro
      - ./docker/nginx/sites:/etc/nginx/conf.d:ro
      - ./symfony/public:/var/www/symfony/public:ro
      - ./docker/nginx/certs:/etc/nginx/certs:ro
    depends_on:
      - php
      - front-public
      - front-admin
      - mercure
    networks:
      - atelier-network

  # ============================================
  # PHP-FPM - Backend Symfony
  # ============================================
  php:
    build:
      context: ./docker/php
      dockerfile: Dockerfile
    container_name: atelier-php
    working_dir: /var/www/symfony
    volumes:
      - ./symfony:/var/www/symfony
      - ./docker/php/php.ini:/usr/local/etc/php/php.ini:ro
    environment:
      APP_ENV: ${APP_ENV:-dev}
      APP_SECRET: ${APP_SECRET}
      DATABASE_URL: postgresql://${DB_USER}:${DB_PASSWORD}@postgres:5432/${DB_NAME}?serverVersion=15&charset=utf8
      REDIS_URL: redis://redis:6379
      MERCURE_URL: http://mercure:3002/.well-known/mercure
      MERCURE_PUBLIC_URL: https://mercure.votreapp.com/.well-known/mercure
      MERCURE_JWT_SECRET: ${MERCURE_JWT_SECRET}
      MESSENGER_TRANSPORT_DSN: redis://redis:6379/messages
      MAILER_DSN: ${MAILER_DSN}
      JWT_SECRET_KEY: '%kernel.project_dir%/config/jwt/private.pem'
      JWT_PUBLIC_KEY: '%kernel.project_dir%/config/jwt/public.pem'
      JWT_PASSPHRASE: ${JWT_PASSPHRASE}
      SENTRY_DSN: ${SENTRY_DSN}
      ELASTICSEARCH_URL: http://elasticsearch:9200
    depends_on:
      - postgres
      - redis
      - elasticsearch
    networks:
      - atelier-network

  # ============================================
  # Worker Symfony - Tâches asynchrones
  # ============================================
  worker:
    build:
      context: ./docker/php
      dockerfile: Dockerfile
    container_name: atelier-worker
    working_dir: /var/www/symfony
    command: php bin/console messenger:consume async -vv
    volumes:
      - ./symfony:/var/www/symfony
    environment:
      APP_ENV: ${APP_ENV:-dev}
      DATABASE_URL: postgresql://${DB_USER}:${DB_PASSWORD}@postgres:5432/${DB_NAME}?serverVersion=15&charset=utf8
      REDIS_URL: redis://redis:6379
      MESSENGER_TRANSPORT_DSN: redis://redis:6379/messages
      MAILER_DSN: ${MAILER_DSN}
      MERCURE_URL: http://mercure:3002/.well-known/mercure
      MERCURE_JWT_SECRET: ${MERCURE_JWT_SECRET}
    depends_on:
      - postgres
      - redis
    restart: unless-stopped
    networks:
      - atelier-network

  # ============================================
  # PostgreSQL - Base de données principale
  # ============================================
  postgres:
    image: postgres:15-alpine
    container_name: atelier-postgres
    ports:
      - "5432:5432"
    environment:
      POSTGRES_DB: ${DB_NAME}
      POSTGRES_USER: ${DB_USER}
      POSTGRES_PASSWORD: ${DB_PASSWORD}
    volumes:
      - postgres-data:/var/lib/postgresql/data
    networks:
      - atelier-network
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U ${DB_USER}"]
      interval: 10s
      timeout: 5s
      retries: 5

  # ============================================
  # Redis - Cache + Sessions + Queue
  # ============================================
  redis:
    image: redis:7-alpine
    container_name: atelier-redis
    ports:
      - "6379:6379"
    volumes:
      - redis-data:/data
    command: redis-server --appendonly yes
    networks:
      - atelier-network
    healthcheck:
      test: ["CMD", "redis-cli", "ping"]
      interval: 10s
      timeout: 5s
      retries: 5

  # ============================================
  # Mercure - Hub temps réel
  # ============================================
  mercure:
    image: dunglas/mercure:latest
    container_name: atelier-mercure
    restart: unless-stopped
    ports:
      - "3002:3002"
    environment:
      SERVER_NAME: ':3002'
      MERCURE_PUBLISHER_JWT_KEY: ${MERCURE_JWT_SECRET}
      MERCURE_SUBSCRIBER_JWT_KEY: ${MERCURE_JWT_SECRET}
      MERCURE_EXTRA_DIRECTIVES: |
        cors_origins https://public.votreapp.com https://admin.votreapp.com
        publish_origins https://api.votreapp.com
    volumes:
      - mercure-data:/data
      - mercure-config:/config
    networks:
      - atelier-network

  # ============================================
  # Elasticsearch - Recherche full-text
  # ============================================
  elasticsearch:
    image: docker.elastic.co/elasticsearch/elasticsearch:8.11.0
    container_name: atelier-elasticsearch
    environment:
      - discovery.type=single-node
      - xpack.security.enabled=false
      - "ES_JAVA_OPTS=-Xms512m -Xmx512m"
    ports:
      - "9200:9200"
    volumes:
      - elasticsearch-data:/usr/share/elasticsearch/data
    networks:
      - atelier-network
    healthcheck:
      test: ["CMD-SHELL", "curl -f http://localhost:9200/_cluster/health || exit 1"]
      interval: 30s
      timeout: 10s
      retries: 5

  # ============================================
  # Front Public - Interface clients
  # ============================================
  front-public:
    build:
      context: ./front-public
      dockerfile: Dockerfile
    container_name: atelier-front-public
    working_dir: /app
    ports:
      - "3000:3000"
    environment:
      NODE_ENV: ${NODE_ENV:-development}
      API_URL: https://api.votreapp.com
      MERCURE_PUBLIC_URL: https://mercure.votreapp.com/.well-known/mercure
      NUXT_PUBLIC_API_BASE: /api/public
    volumes:
      - ./front-public:/app
      - /app/node_modules
      - /app/.nuxt
    command: npm run dev
    networks:
      - public-network
      - atelier-network
    depends_on:
      - php

  # ============================================
  # Front Admin - Interface équipe atelier
  # ============================================
  front-admin:
    build:
      context: ./front-admin
      dockerfile: Dockerfile
    container_name: atelier-front-admin
    working_dir: /app
    ports:
      - "3001:3000"
    environment:
      NODE_ENV: ${NODE_ENV:-development}
      API_URL: https://api.votreapp.com
      MERCURE_PUBLIC_URL: https://mercure.votreapp.com/.well-known/mercure
      NUXT_PUBLIC_API_BASE: /api/admin
    volumes:
      - ./front-admin:/app
      - /app/node_modules
      - /app/.nuxt
    command: npm run dev
    networks:
      - admin-network
      - atelier-network
    depends_on:
      - php

  # ============================================
  # RabbitMQ - File d'attente (optionnel)
  # ============================================
  # rabbitmq:
  #   image: rabbitmq:3-management-alpine
  #   container_name: atelier-rabbitmq
  #   ports:
  #     - "5672:5672"
  #     - "15672:15672"
  #   environment:
  #     RABBITMQ_DEFAULT_USER: ${RABBITMQ_USER:-admin}
  #     RABBITMQ_DEFAULT_PASS: ${RABBITMQ_PASSWORD:-admin}
  #   volumes:
  #     - rabbitmq-data:/var/lib/rabbitmq
  #   networks:
  #     - atelier-network

  # ============================================
  # MailHog - Test emails en dev
  # ============================================
  mailhog:
    image: mailhog/mailhog:latest
    container_name: atelier-mailhog
    ports:
      - "1025:1025" # SMTP
      - "8025:8025" # Interface web
    networks:
      - atelier-network
    profiles:
      - dev

  # ============================================
  # Adminer - GUI Base de données
  # ============================================
  adminer:
    image: adminer:latest
    container_name: atelier-adminer
    ports:
      - "8080:8080"
    environment:
      ADMINER_DEFAULT_SERVER: postgres
    networks:
      - atelier-network
    profiles:
      - dev

  # ============================================
  # Blackfire - Profiling performance
  # ============================================
  # blackfire:
  #   image: blackfire/blackfire:2
  #   container_name: atelier-blackfire
  #   environment:
  #     BLACKFIRE_SERVER_ID: ${BLACKFIRE_SERVER_ID}
  #     BLACKFIRE_SERVER_TOKEN: ${BLACKFIRE_SERVER_TOKEN}
  #     BLACKFIRE_CLIENT_ID: ${BLACKFIRE_CLIENT_ID}
  #     BLACKFIRE_CLIENT_TOKEN: ${BLACKFIRE_CLIENT_TOKEN}
  #   networks:
  #     - atelier-network
  #   profiles:
  #     - profiling

# ============================================
# Volumes persistants
# ============================================
volumes:
  postgres-data:
    driver: local
  redis-data:
    driver: local
  elasticsearch-data:
    driver: local
  mercure-data:
    driver: local
  mercure-config:
    driver: local
  # rabbitmq-data:
  #   driver: local

# ============================================
# Réseaux
# ============================================
networks:
  atelier-network:
    driver: bridge
  public-network:
    driver: bridge
  admin-network:
    driver: bridge
```

### Fichier .env associé

```env
# App
APP_ENV=dev
APP_SECRET=change_me_in_production

# Database
DB_NAME=atelier_vo
DB_USER=atelier
DB_PASSWORD=secure_password

# JWT
JWT_PASSPHRASE=jwt_passphrase

# Mercure
MERCURE_JWT_SECRET=mercure_secret_key

# Mailer
MAILER_DSN=smtp://mailhog:1025

# Sentry (prod)
SENTRY_DSN=

# Blackfire (optionnel)
# BLACKFIRE_SERVER_ID=
# BLACKFIRE_SERVER_TOKEN=
# BLACKFIRE_CLIENT_ID=
# BLACKFIRE_CLIENT_TOKEN=

# RabbitMQ (optionnel)
# RABBITMQ_USER=admin
# RABBITMQ_PASSWORD=admin
```

---

## Plan d'implémentation par phases

### 📦 Phase 1 - Fondations (Semaine 1-2)

**Priorité absolue :**

1. **Docker Compose** : Monter l'infra complète
2. **API Platform** : Exposer endpoints REST propres
3. **LexikJWT + NelmioCors** : Sécuriser API
4. **VichUploader + LiipImagine** : Upload photos VO
5. **StofDoctrineExtensions** : Timestampable, Sluggable, Loggable

**Livrables :**
- ✅ Stack Docker opérationnelle
- ✅ API REST `/api/public/vehicles` et `/api/admin/vehicles`
- ✅ Upload photos véhicules avec miniatures auto
- ✅ Authentification JWT pour 2 fronts

---

### 🔐 Phase 2 - Sécurité (Semaine 3)

**Priorité haute :**

6. **SchebTwoFactor** : 2FA pour admin
7. **RateLimiter** : Limiter API publique
8. **NelmioSecurity** : Headers sécurité
9. **Sentry** : Monitoring erreurs prod

**Livrables :**
- ✅ Admin protégé par 2FA
- ✅ API publique limitée (100 req/h par IP)
- ✅ Alertes erreurs temps réel

---

### 🚀 Phase 3 - Fonctionnalités métier (Semaine 4-5)

**Priorité métier :**

10. **Workflow Component** : Statuts véhicules (Réception → Vente)
11. **KnpSnappy** : Génération PDFs (factures, rapports)
12. **Mailer + Notifier** : Emails/SMS clients
13. **Scheduler** : Tâches automatiques (cotations hebdo)
14. **Mercure** : Notifications temps réel atelier

**Livrables :**
- ✅ Workflow complet VO (tracking états)
- ✅ PDFs factures automatiques
- ✅ Emails confirmation réservation
- ✅ Notifs live quand véhicule prêt

---

### ⚡ Phase 4 - Performance (Semaine 6)

**Priorité optimisation :**

15. **Redis Cache** : Cache résultats recherche
16. **Elasticsearch** : Recherche full-text ultra-rapide
17. **Blackfire** : Profiling et optimisation SQL
18. **PHPStan** : Qualité code

**Livrables :**
- ✅ Recherche instantanée (< 50ms)
- ✅ Cache intelligent (TTL adaptatif)
- ✅ Requêtes SQL optimisées
- ✅ Code analysé niveau 9

---

### 📊 Phase 5 - Analytics & Reporting (Semaine 7)

**Priorité business :**

19. **PhpSpreadsheet** : Export Excel listings
20. **Prometheus + Grafana** : Dashboards métier
21. **Alice + Faker** : Données de test réalistes
22. **PHPUnit** : Tests automatisés

**Livrables :**
- ✅ Export Excel stock complet
- ✅ Dashboard temps réel (stock, CA, délais)
- ✅ Tests automatisés (couverture 60%+)

---

### 🎨 Phase 6 - UX/UI (Semaine 8+)

**Priorité expérience utilisateur :**

23. **Frontend design skill** : UI moderne et distinctive
24. **PWA** : App installable mobile
25. **Nuxt modules** : Optimisations images, fonts
26. **EasyAdmin** : Interface admin sans code custom

**Livrables :**
- ✅ Interface publique moderne (conversion +30%)
- ✅ App installable (réduction temps accès -50%)
- ✅ Backoffice admin intuitif

---

## 🎯 Récapitulatif compteur

**Total bundles/packages recommandés : 58+**

### Symfony Backend : 35 packages
- Core Symfony : 15
- Sécurité : 5
- Monitoring : 5
- Testing : 4
- Communications : 4
- Documents : 2

### Frontend Nuxt : 15 packages
- Core Nuxt : 5
- UI/UX : 5
- Performance : 3
- PWA : 2

### Infrastructure Docker : 8 services
- Backend stack : 3 (PHP, Worker, Postgres)
- Cache/Queue : 2 (Redis, RabbitMQ)
- Search/Real-time : 2 (Elasticsearch, Mercure)
- Dev tools : 3 (MailHog, Adminer, Blackfire)

---

## 📚 Ressources & Documentation

### Symfony
- [Symfony Docs](https://symfony.com/doc/current/index.html)
- [API Platform](https://api-platform.com/docs/)
- [Doctrine](https://www.doctrine-project.org/projects/doctrine-orm/en/current/index.html)

### Nuxt
- [Nuxt 3 Docs](https://nuxt.com/docs)
- [Pinia Store](https://pinia.vuejs.org/)
- [VueUse](https://vueuse.org/)

### DevOps
- [Docker Compose Docs](https://docs.docker.com/compose/)
- [Mercure Protocol](https://mercure.rocks/)
- [Elasticsearch Guide](https://www.elastic.co/guide/en/elasticsearch/reference/current/index.html)

### Outils qualité
- [PHPStan](https://phpstan.org/user-guide/getting-started)
- [PHP-CS-Fixer](https://github.com/PHP-CS-Fixer/PHP-CS-Fixer)
- [Blackfire](https://www.blackfire.io/docs/introduction)

---

## ✅ Checklist finale

**Avant de démarrer :**
- [ ] Cloner/initialiser le repo Git
- [ ] Créer les dossiers `symfony/`, `front-public/`, `front-admin/`, `docker/`
- [ ] Copier le `docker-compose.yml` à la racine
- [ ] Créer le `.env` avec credentials sécurisés
- [ ] Générer les clés JWT : `php bin/console lexik:jwt:generate-keypair`

**Premier démarrage :**
```bash
# Build et démarrer
docker-compose up -d --build

# Installer dépendances Symfony
docker-compose exec php composer install

# Créer la BDD
docker-compose exec php php bin/console doctrine:database:create
docker-compose exec php php bin/console doctrine:migrations:migrate

# Installer dépendances Nuxt (x2)
docker-compose exec front-public npm install
docker-compose exec front-admin npm install

# Charger fixtures de test
docker-compose exec php php bin/console doctrine:fixtures:load

# Vérifier que tout fonctionne
curl http://localhost:8000/api/public/vehicles
```

**URLs d'accès :**
- Frontend Public : http://localhost:3000
- Frontend Admin : http://localhost:3001
- API Symfony : http://localhost:8000
- Mercure Hub : http://localhost:3002
- Adminer (BDD) : http://localhost:8080
- MailHog : http://localhost:8025
- Elasticsearch : http://localhost:9200

---

## 🎉 Conclusion

Vous avez maintenant une **stack complète production-ready** pour votre application atelier VO avec :

✅ Architecture scalable (microservices Docker isolés)  
✅ Sécurité renforcée (JWT, 2FA, rate limiting, CORS)  
✅ Performance optimale (Redis cache, Elasticsearch, profiling)  
✅ Temps réel (Mercure pour notifications live)  
✅ Qualité code (PHPStan, tests auto, CI/CD-ready)  
✅ UX moderne (Nuxt 3, PWA, optimisations images)  
✅ Monitoring production (Sentry, Grafana, logs centralisés)

**Prochaines étapes recommandées :**
1. Suivre le plan d'implémentation par phases
2. Configurer CI/CD (GitHub Actions / GitLab CI)
3. Setup environnement staging
4. Documenter API avec OpenAPI/Swagger
5. Former l'équipe sur la stack

**Besoin d'aide sur un composant spécifique ?** Référez-vous aux sections détaillées ci-dessus ! 🚀
