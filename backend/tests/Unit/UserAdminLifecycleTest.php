<?php

namespace App\Tests\Unit;

use App\Controller\AuthController;
use App\Entity\RoleMetier;
use App\Entity\RolePermissionEntry;
use App\Entity\User;
use App\Service\UserArchiveService;
use App\Service\UserRoleMapper;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AllowMockObjectsWithoutExpectations]
class UserAdminLifecycleTest extends TestCase
{
    public function testArchiveServiceAnonymizesAndDisablesUser(): void
    {
        $user = (new User())
            ->setUsername('jean.dupont')
            ->setEmail('jean.dupont@example.com')
            ->setPrenom('Jean')
            ->setNom('Dupont')
            ->setHashedPassword('hashed')
            ->setAuthProvider('google')
            ->setGoogleSub('google-sub-123')
            ->setAccessStatus('active')
            ->setIsActive(1);

        $service = new UserArchiveService();
        $service->archive($user, 'Départ collaborateur');

        $this->assertSame('archived', $user->getAccessStatus());
        $this->assertSame(0, $user->getIsActive());
        $this->assertNull($user->getGoogleSub());
        $this->assertSame('local', $user->getAuthProvider());
        $this->assertStringStartsWith('archive.', $user->getUsername());
        $this->assertStringStartsWith('archived-user-', $user->getEmail());
        $this->assertSame('Compte', $user->getPrenom());
        $this->assertSame('Archivé', $user->getNom());
    }

    public function testRoleMapperMapsRoleMetierToLegacyRole(): void
    {
        $roleMetier = (new RoleMetier())
            ->setCode('responsable_atelier')
            ->setLibelle('Responsable atelier')
            ->setBaseRole('ROLE_ADMIN');

        $mapper = new UserRoleMapper();

        $this->assertSame('admin', $mapper->mapRoleMetierToLegacyRole($roleMetier));
        $this->assertSame('receptionniste', $mapper->mapLegacyRoleToRoleMetierCode('receptionnaire'));
    }

    public function testLegacyReceptionnaireStillGetsRolePermissionsWithoutExplicitRoleMetier(): void
    {
        $roleMetier = (new RoleMetier())
            ->setCode('receptionniste')
            ->setLibelle('Réceptionniste')
            ->setBaseRole('ROLE_USER');

        $permission = (new RolePermissionEntry())
            ->setModule('rdv')
            ->setAction('view')
            ->setGranted(true);
        $roleMetier->addPermission($permission);

        $roleRepo = new class($roleMetier) extends EntityRepository {
            public function __construct(private RoleMetier $roleMetier) {}
            public function findOneBy(array $criteria, array|null $orderBy = null): ?object
            {
                return ($criteria['code'] ?? null) === 'receptionniste' ? $this->roleMetier : null;
            }
        };

        $atelierRepo = new class extends EntityRepository {
            public function __construct() {}
            public function find(mixed $id, mixed $lockMode = null, mixed $lockVersion = null): ?object
            {
                return null;
            }
        };

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturnCallback(static function (string $entityClass) use ($roleRepo, $atelierRepo) {
            return match ($entityClass) {
                \App\Entity\RoleMetier::class => $roleRepo,
                \App\Entity\Atelier::class => $atelierRepo,
                default => new class extends EntityRepository {
                    public function __construct() {}
                    public function findOneBy(array $criteria, array|null $orderBy = null): ?object
                    {
                        return null;
                    }

                    public function find(mixed $id, mixed $lockMode = null, mixed $lockVersion = null): ?object
                    {
                        return null;
                    }
                },
            };
        });

        $controller = new AuthController(
            $em,
            $this->createMock(JWTTokenManagerInterface::class),
            $this->createMock(UserPasswordHasherInterface::class),
            $this->createMock(HttpClientInterface::class),
            $this->createMock(MailerInterface::class),
            new UserRoleMapper(),
        );

        $user = (new User())
            ->setUsername('recep')
            ->setEmail('recep@test.local')
            ->setPassword('hashed')
            ->setRole('receptionnaire')
            ->setAtelierId(1);

        $method = new \ReflectionMethod(AuthController::class, 'buildUserPayload');
        $payload = $method->invoke($controller, $user);

        $this->assertIsArray($payload['role_permissions']);
        $this->assertContains('rdv', $payload['role_permissions']['sections_json']);
    }
}
