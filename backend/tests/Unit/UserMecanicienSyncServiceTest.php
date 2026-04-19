<?php

namespace App\Tests\Unit;

use App\Entity\Mecanicien;
use App\Entity\User;
use App\Service\CurrentAtelierResolver;
use App\Service\UserMecanicienSyncService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;

class UserMecanicienSyncServiceTest extends TestCase
{
    public function testCreatesLinkedMecanicienForMechanicUser(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('findOneBy')->willReturn(null);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repository);

        $persisted = [];
        $em->expects(self::once())->method('persist')->willReturnCallback(function ($entity) use (&$persisted): void {
            $persisted[] = $entity;
        });

        $resolver = $this->createMock(CurrentAtelierResolver::class);
        $resolver->method('resolveAtelierId')->willReturn(7);

        $service = new UserMecanicienSyncService($em, $resolver);

        $user = new User();
        $user->setPrenom('Marc');
        $user->setNom('Durand');
        $user->setUsername('marc.durand');
        $user->setEmail('marc@example.test');
        $user->setRole('mecanicien');
        $user->setAtelierId(7);
        $user->setIsActive(1);
        $this->forceUserId($user, 42);

        $mecanicien = $service->syncForUser($user);

        self::assertInstanceOf(Mecanicien::class, $mecanicien);
        self::assertSame(42, $mecanicien->getUserId());
        self::assertSame(7, $mecanicien->getAtelierId());
        self::assertSame('Durand', $mecanicien->getNom());
        self::assertSame('Marc', $mecanicien->getPrenom());
        self::assertCount(1, $persisted);
    }

    public function testDeactivatesLinkedMecanicienWhenRoleChanges(): void
    {
        $existing = new Mecanicien();
        $existing->setUserId(42);
        $existing->setAtelierId(3);
        $existing->setNom('Durand');
        $existing->setPrenom('Marc');
        $existing->setIsActive(1);

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('findOneBy')->willReturn($existing);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repository);
        $em->expects(self::never())->method('persist');

        $resolver = $this->createMock(CurrentAtelierResolver::class);
        $resolver->method('resolveAtelierId')->willReturn(3);

        $service = new UserMecanicienSyncService($em, $resolver);

        $user = new User();
        $user->setPrenom('Marc');
        $user->setNom('Durand');
        $user->setUsername('marc.durand');
        $user->setEmail('marc@example.test');
        $user->setRole('receptionnaire');
        $user->setAtelierId(3);
        $user->setIsActive(1);
        $this->forceUserId($user, 42);

        $mecanicien = $service->syncForUser($user);

        self::assertSame($existing, $mecanicien);
        self::assertSame(0, $existing->getIsActive());
    }

    public function testUpdatesExistingMecanicienAtelierFromLogin(): void
    {
        $existing = new Mecanicien();
        $existing->setUserId(42);
        $existing->setAtelierId(1);
        $existing->setNom('Durand');
        $existing->setPrenom('Marc');
        $existing->setIsActive(0);

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('findOneBy')->willReturn($existing);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repository);
        $em->expects(self::never())->method('persist');

        $resolver = $this->createMock(CurrentAtelierResolver::class);
        $resolver->method('resolveAtelierId')->willReturn(9);

        $service = new UserMecanicienSyncService($em, $resolver);

        $user = new User();
        $user->setPrenom('Marc');
        $user->setNom('Durand');
        $user->setUsername('marc.durand');
        $user->setEmail('marc@example.test');
        $user->setRole('mecanicien');
        $user->setAtelierId(9);
        $user->setIsActive(1);
        $this->forceUserId($user, 42);

        $mecanicien = $service->syncForUser($user);

        self::assertSame($existing, $mecanicien);
        self::assertSame(9, $existing->getAtelierId());
        self::assertSame(1, $existing->getIsActive());
    }

    private function forceUserId(User $user, int $id): void
    {
        $reflection = new \ReflectionProperty($user, 'id');
        $reflection->setValue($user, $id);
    }
}
