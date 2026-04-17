<?php

namespace App\Tests\Unit;

use App\Entity\RoleMetier;
use App\Entity\User;
use App\Service\UserArchiveService;
use App\Service\UserRoleMapper;
use PHPUnit\Framework\TestCase;

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
}
