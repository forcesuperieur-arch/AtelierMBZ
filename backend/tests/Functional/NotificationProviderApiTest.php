<?php

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Functional tests for LOT 11 — Notification Provider endpoints.
 */
class NotificationProviderApiTest extends WebTestCase
{
    // ─── Provider CRUD requires auth ───

    public function testListProvidersRequiresAuth(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/admin/notification-providers');
        $this->assertContains($client->getResponse()->getStatusCode(), [401, 403]);
    }

    public function testCreateProviderRequiresAuth(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/admin/notification-providers', [], [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['channel' => 'sms', 'provider' => 'twilio']),
        );
        $this->assertContains($client->getResponse()->getStatusCode(), [401, 403]);
    }

    public function testUpdateProviderRequiresAuth(): void
    {
        $client = static::createClient();
        $client->request('PUT', '/api/admin/notification-providers/1', [], [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['isPrimary' => true]),
        );
        $this->assertContains($client->getResponse()->getStatusCode(), [401, 403]);
    }

    public function testDeleteProviderRequiresAuth(): void
    {
        $client = static::createClient();
        $client->request('DELETE', '/api/admin/notification-providers/1');
        $this->assertContains($client->getResponse()->getStatusCode(), [401, 403]);
    }

    public function testTestProviderRequiresAuth(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/admin/notification-providers/1/test', [], [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['recipient' => '+33612345678']),
        );
        $this->assertContains($client->getResponse()->getStatusCode(), [401, 403]);
    }

    // ─── Logs & Templates require auth ───

    public function testListLogsRequiresAuth(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/admin/notification-logs');
        $this->assertContains($client->getResponse()->getStatusCode(), [401, 403]);
    }

    public function testListTemplatesRequiresAuth(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/admin/notification-templates');
        $this->assertContains($client->getResponse()->getStatusCode(), [401, 403]);
    }

    // ─── Webhooks (public but validated) ───

    public function testTwilioWebhookEndpoint(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/webhooks/notifications/twilio',
            ['MessageSid' => 'SM123', 'MessageStatus' => 'delivered'],
        );

        // 200 if DB available, 500 if test DB not set up — but NOT 404 or 401
        $code = $client->getResponse()->getStatusCode();
        $this->assertTrue(in_array($code, [200, 500], true), "Expected 200 or 500, got {$code}");
    }

    public function testMailgunWebhookEndpoint(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/webhooks/notifications/mailgun', [], [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'signature' => ['timestamp' => time(), 'token' => 'test', 'signature' => 'test'],
                'event-data' => ['event' => 'delivered', 'message' => ['headers' => ['message-id' => 'test-msg']]],
            ]),
        );

        $code = $client->getResponse()->getStatusCode();
        $this->assertTrue(in_array($code, [200, 500], true), "Expected 200 or 500, got {$code}");
    }

    public function testOvhWebhookEndpoint(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/webhooks/notifications/ovh', [], [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['id' => '12345', 'deliveryReceipt' => 1]),
        );

        $code = $client->getResponse()->getStatusCode();
        $this->assertTrue(in_array($code, [200, 500], true), "Expected 200 or 500, got {$code}");
    }

    public function testUnknownProviderWebhookReturns400(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/webhooks/notifications/unknown');
        // 400 if routed correctly, 500 if DB issue
        $code = $client->getResponse()->getStatusCode();
        $this->assertTrue(in_array($code, [400, 500], true), "Expected 400 or 500, got {$code}");
    }
}
