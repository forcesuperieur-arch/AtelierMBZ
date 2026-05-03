# Symfony API Testing — Skill Projet

## Framework
PHPUnit ^12.5 + Symfony `WebTestCase` / `KernelTestCase`
Config : `phpunit.dist.xml` (préféré) ou `phpunit.xml.dist`

## Structure des tests
```
tests/
  Unit/           — PHPUnit\Framework\TestCase, mocks autorisés
  Functional/     — Symfony\Bundle\FrameworkBundle\Test\WebTestCase, requêtes HTTP réelles
```

## Patterns obligatoires

### Tests unitaires (Service, Entity)
```php
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

#[AllowMockObjectsWithoutExpectations]
class MonServiceTest extends TestCase
{
    private function createService(): array
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $audit = $this->createMock(AuditService::class);
        return [new MonService($em, $audit), $em, $audit];
    }
}
```

### Tests fonctionnels (Controller, API)
```php
class MonControllerTest extends WebTestCase
{
    public function testEndpoint(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $fixture = $this->createFixture($em);

        $client->request('GET', '/api/endpoint', [], [], $this->authHeaders($fixture['user']));
        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $payload = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
    }

    private function authHeaders(User $user): array
    {
        $jwtManager = static::getContainer()->get(JWTTokenManagerInterface::class);
        return [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $jwtManager->create($user),
        ];
    }
}
```

## Règles de fixture
- Toujours suffixer avec `bin2hex(random_bytes(4))` pour éviter les collisions
- Créer un user mécanicien (`ROLE_USER`) et un admin (`ROLE_ADMIN`) par test fonctionnel
- Persister + flush dans la méthode fixture, retourner un array typé
- `$em->clear()` si on re-lecture après mutation

## Bonnes pratiques
- Tester le statut HTTP **ET** le payload JSON
- Tester la sécurité (accès refusé pour rôle insuffisant)
- Tester les cas d'erreur (404, 409, 400)
- Pas de base de données réelle : les tests utilisent la même DB que l'app (config doctrine test)
- `JSON_THROW_ON_ERROR` obligatoire sur `json_decode`

## Lancer les tests
```bash
# Docker (recommandé)
docker compose exec php php bin/phpunit tests/Unit/MonTest.php
docker compose exec php php bin/phpunit tests/Functional/MonTest.php
docker compose exec php php bin/phpunit --testsuite unit
docker compose exec php php bin/phpunit --testsuite functional
```
