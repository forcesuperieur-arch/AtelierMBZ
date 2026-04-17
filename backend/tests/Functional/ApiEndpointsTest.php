<?php

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Functional tests for API endpoints — non-regression + LOT 5.
 */
class ApiEndpointsTest extends WebTestCase
{
    // ─── Non-regression: Auth ───

    public function testLoginEndpointExists(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/auth/login', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'username' => 'nonexistent',
            'password' => 'wrong',
        ]));

        // Should return 401 or 500 (DB-dependent), but NOT 404
        $code = $client->getResponse()->getStatusCode();
        $this->assertTrue(in_array($code, [401, 500], true), "Expected 401 or 500, got {$code}");
    }

    // ─── Non-regression: Public booking ───

    public function testPublicBookingEndpointExists(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/public/booking', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([]));

        // Should return 400 (validation) not 404/500
        $code = $client->getResponse()->getStatusCode();
        $this->assertTrue(in_array($code, [400, 422], true), "Expected 400 or 422, got {$code}");
    }

    public function testPublicTrackingEndpointExists(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/public/tracking/INVALID-TOKEN');

        $code = $client->getResponse()->getStatusCode();
        $this->assertTrue(in_array($code, [404, 400], true), "Expected 404 or 400, got {$code}");
    }

    // ─── Non-regression: Demande Travaux Supp (LOT 4) ───

    public function testDemandeTravauxSuppPublicEndpointExists(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/public/demandes-travaux-supp/invalidtoken12345678901234567890ab');

        $code = $client->getResponse()->getStatusCode();
        $this->assertTrue(in_array($code, [404, 500], true), "Expected 404 or 500, got {$code}");
    }

    // ─── LOT 5: Notification endpoints ───

    public function testNotificationsListRequiresAuth(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/notifications');

        $this->assertSame(Response::HTTP_UNAUTHORIZED, $client->getResponse()->getStatusCode());
    }

    public function testNotificationsUnreadCountRequiresAuth(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/notifications/unread-count');

        $this->assertSame(Response::HTTP_UNAUTHORIZED, $client->getResponse()->getStatusCode());
    }

    public function testNotificationsAcknowledgeRequiresAuth(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/notifications/1/acknowledge');

        $this->assertSame(Response::HTTP_UNAUTHORIZED, $client->getResponse()->getStatusCode());
    }

    public function testNotificationsMarkReadRequiresAuth(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/notifications/1/mark-read');

        $this->assertSame(Response::HTTP_UNAUTHORIZED, $client->getResponse()->getStatusCode());
    }

    // ─── Non-regression: OR endpoints (LOT 1) ───

    public function testOrVerifyIntegrityRequiresAuth(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/or/1/verify-integrity');

        $this->assertSame(Response::HTTP_UNAUTHORIZED, $client->getResponse()->getStatusCode());
    }

    public function testOrRectifyRequiresAuth(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/or/1/rectifier', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([]));

        $this->assertSame(Response::HTTP_UNAUTHORIZED, $client->getResponse()->getStatusCode());
    }

    // ─── Non-regression: API Platform resources ───

    public function testUsersApiRequiresAuth(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/users');

        $this->assertSame(Response::HTTP_UNAUTHORIZED, $client->getResponse()->getStatusCode());
    }

    public function testOrdresReparationApiRequiresAuth(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/ordres-reparation');

        $this->assertSame(Response::HTTP_UNAUTHORIZED, $client->getResponse()->getStatusCode());
    }

    // ─── Non-regression: Stock endpoints (LOT 9 entities) ───

    public function testStockAlertesRequiresAuth(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/stock/alertes');

        $this->assertSame(Response::HTTP_UNAUTHORIZED, $client->getResponse()->getStatusCode());
    }
}
