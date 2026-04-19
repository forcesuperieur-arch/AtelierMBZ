<?php

namespace App\Tests\Unit;

use App\Entity\User;
use App\Service\CurrentAtelierResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class CurrentAtelierResolverTest extends TestCase
{
    public function testSuperAdminUsesActiveAtelierCookieWhenNumeric(): void
    {
        $user = (new User())
            ->setUsername('super')
            ->setEmail('super@test.local')
            ->setPassword('hashed')
            ->setRole('super_admin')
            ->setAtelierId(3);

        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($user);

        $request = new Request();
        $request->cookies->set('active_atelier_id', '42');
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $resolver = new CurrentAtelierResolver($security, $requestStack);

        $this->assertSame(42, $resolver->resolveAtelierId());
    }

    public function testSuperAdminFallsBackToOwnAtelierWhenLegacyGlobalScopeCookieIsSelected(): void
    {
        $user = (new User())
            ->setUsername('super')
            ->setEmail('super@test.local')
            ->setPassword('hashed')
            ->setRole('super_admin')
            ->setAtelierId(7);

        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($user);

        $request = new Request();
        $request->cookies->set('active_atelier_id', 'all');
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $resolver = new CurrentAtelierResolver($security, $requestStack);

        $this->assertSame(7, $resolver->resolveAtelierId());
        $this->assertFalse($resolver->isGlobalScopeRequested());
    }

    public function testRegularUserAlwaysUsesAssignedAtelier(): void
    {
        $user = (new User())
            ->setUsername('admin')
            ->setEmail('admin@test.local')
            ->setPassword('hashed')
            ->setRole('admin')
            ->setAtelierId(9);

        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($user);

        $request = new Request();
        $request->cookies->set('active_atelier_id', '99');
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $resolver = new CurrentAtelierResolver($security, $requestStack);

        $this->assertSame(9, $resolver->resolveAtelierId());
        $this->assertFalse($resolver->isGlobalScopeRequested());
    }

    public function testAnonymousUserReturnsNull(): void
    {
        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn(null);

        $resolver = new CurrentAtelierResolver($security, new RequestStack());

        $this->assertNull($resolver->resolveAtelierId());
        $this->assertFalse($resolver->isGlobalScopeRequested());
    }
}
