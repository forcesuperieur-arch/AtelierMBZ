<?php

namespace App\Tests\Unit;

use App\Entity\User;
use PHPUnit\Framework\TestCase;

/**
 * Non-regression tests for User entity (LOT 6 - Roles & Permissions).
 */
class UserRolesTest extends TestCase
{
    public function testDefaultRoleIsReceptionnaire(): void
    {
        $user = new User();
        $user->setUsername('test');
        $user->setEmail('test@test.local');
        $user->setPassword('hashed');

        $this->assertSame('receptionnaire', $user->getRole());
    }

    public function testGetRolesAlwaysIncludesRoleUser(): void
    {
        $user = new User();
        $user->setUsername('test');
        $user->setEmail('test@test.local');
        $user->setPassword('hashed');

        $this->assertContains('ROLE_USER', $user->getRoles());
    }

    public function testAdminRoleMapping(): void
    {
        $user = new User();
        $user->setUsername('admin');
        $user->setEmail('admin@test.local');
        $user->setPassword('hashed');
        $user->setRole('admin');

        $roles = $user->getRoles();
        $this->assertContains('ROLE_ADMIN', $roles);
        $this->assertContains('ROLE_USER', $roles);
    }

    public function testSuperAdminIncludesRoleAdmin(): void
    {
        $user = new User();
        $user->setUsername('super');
        $user->setEmail('super@test.local');
        $user->setPassword('hashed');
        $user->setRole('super_admin');

        $roles = $user->getRoles();
        $this->assertContains('ROLE_SUPER_ADMIN', $roles);
        $this->assertContains('ROLE_ADMIN', $roles);
        $this->assertContains('ROLE_USER', $roles);
    }

    public function testMecanicienRoleMapping(): void
    {
        $user = new User();
        $user->setUsername('meca');
        $user->setEmail('meca@test.local');
        $user->setPassword('hashed');
        $user->setRole('mecanicien');

        $roles = $user->getRoles();
        $this->assertContains('ROLE_MECANICIEN', $roles);
        $this->assertNotContains('ROLE_ADMIN', $roles);
    }

    public function testReceptionnaireRoleMapping(): void
    {
        $user = new User();
        $user->setUsername('recep');
        $user->setEmail('recep@test.local');
        $user->setPassword('hashed');
        $user->setRole('receptionnaire');

        $this->assertContains('ROLE_RECEPTIONNAIRE', $roles = $user->getRoles());
    }

    public function testComptableRoleMapping(): void
    {
        $user = new User();
        $user->setUsername('compta');
        $user->setEmail('compta@test.local');
        $user->setPassword('hashed');
        $user->setRole('comptable');

        $this->assertContains('ROLE_COMPTABLE', $user->getRoles());
    }

    public function testVoManagerRoleMapping(): void
    {
        $user = new User();
        $user->setUsername('vo');
        $user->setEmail('vo@test.local');
        $user->setPassword('hashed');
        $user->setRole('vo_manager');

        $this->assertContains('ROLE_VO_MANAGER', $user->getRoles());
    }

    public function testServiceClientRoleMapping(): void
    {
        $user = new User();
        $user->setUsername('svc');
        $user->setEmail('svc@test.local');
        $user->setPassword('hashed');
        $user->setRole('service_client');

        $this->assertContains('ROLE_SERVICE_CLIENT', $user->getRoles());
    }

    public function testEraseCredentialsClearsPlainPassword(): void
    {
        $user = new User();
        $user->setUsername('test');
        $user->setEmail('test@test.local');
        $user->setPassword('hashed');
        $user->setPlainPassword('secret');

        $this->assertSame('secret', $user->getPlainPassword());
        $user->eraseCredentials();
        $this->assertNull($user->getPlainPassword());
    }

    public function testIsActiveDefault(): void
    {
        $user = new User();
        $user->setUsername('test');
        $user->setEmail('test@test.local');
        $user->setPassword('hashed');

        $this->assertSame(1, $user->getIsActive());
    }

    public function testUserIdentifier(): void
    {
        $user = new User();
        $user->setUsername('johndoe');
        $user->setEmail('john@test.local');
        $user->setPassword('hashed');

        $this->assertSame('johndoe', $user->getUserIdentifier());
    }

    public function testRolesAreUnique(): void
    {
        $user = new User();
        $user->setUsername('super');
        $user->setEmail('super@test.local');
        $user->setPassword('hashed');
        $user->setRole('super_admin');

        $roles = $user->getRoles();
        $this->assertCount(count(array_unique($roles)), $roles, 'Roles should be unique');
    }
}
