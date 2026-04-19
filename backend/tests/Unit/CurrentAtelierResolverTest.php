<?php

namespace App\Tests\Unit;

use App\Entity\User;
use App\Service\BookingAtelierAccessService;
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

        $resolver = $this->createResolver($user, function (User $resolvedUser, ?int $requestedAtelierId) use ($user): ?int {
            $this->assertSame($user, $resolvedUser);
            return $requestedAtelierId ?? $resolvedUser->getAtelierId();
        }, new Request(cookies: ['active_atelier_id' => '42']));

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

        $resolver = $this->createResolver($user, fn (User $resolvedUser, ?int $requestedAtelierId): ?int => $requestedAtelierId ?? $resolvedUser->getAtelierId(), new Request(cookies: ['active_atelier_id' => 'all']));

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

        $resolver = $this->createResolver($user, fn (User $resolvedUser, ?int $requestedAtelierId): ?int => $resolvedUser->getAtelierId(), new Request(cookies: ['active_atelier_id' => '99']));

        $this->assertSame(9, $resolver->resolveAtelierId());
        $this->assertFalse($resolver->isGlobalScopeRequested());
    }

    public function testAnonymousUserReturnsNull(): void
    {
        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn(null);

        $bookingAccess = $this->createMock(BookingAtelierAccessService::class);
        $resolver = new CurrentAtelierResolver($security, new RequestStack(), $bookingAccess);

        $this->assertNull($resolver->resolveAtelierId());
        $this->assertFalse($resolver->isGlobalScopeRequested());
    }

    public function testJsonBodyAtelierSelectionIsUsedForRdvRequests(): void
    {
        $user = (new User())
            ->setUsername('service-client')
            ->setEmail('service-client@test.local')
            ->setPassword('hashed')
            ->setRole('service_client')
            ->setAtelierId(3);

        $request = new Request(server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'atelier_id' => 15,
        ], JSON_THROW_ON_ERROR));

        $resolver = $this->createResolver($user, fn (User $resolvedUser, ?int $requestedAtelierId): ?int => $requestedAtelierId ?? $resolvedUser->getAtelierId(), $request);

        $this->assertSame(15, $resolver->resolveAtelierId());
    }

    private function createResolver(User $user, callable $callback, ?Request $request = null): CurrentAtelierResolver
    {
        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($user);

        $requestStack = new RequestStack();
        if ($request) {
            $requestStack->push($request);
        }

        $bookingAccess = $this->createMock(BookingAtelierAccessService::class);
        $bookingAccess->method('resolvePreferredAtelierId')->willReturnCallback($callback);

        return new CurrentAtelierResolver($security, $requestStack, $bookingAccess);
    }
}
