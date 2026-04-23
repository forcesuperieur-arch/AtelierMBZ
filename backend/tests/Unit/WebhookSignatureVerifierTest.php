<?php

namespace App\Tests\Unit;

use App\Service\WebhookSignatureVerifier;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

class WebhookSignatureVerifierTest extends TestCase
{
    private WebhookSignatureVerifier $verifier;

    protected function setUp(): void
    {
        $this->verifier = new WebhookSignatureVerifier();
    }

    // ─── Twilio ───

    public function testTwilioValidSignature(): void
    {
        $authToken = 'twilio_auth_token_secret';
        $url = 'https://example.com/api/webhooks/notifications/twilio';
        $params = ['MessageSid' => 'SM123', 'MessageStatus' => 'delivered', 'AccountSid' => 'AC1'];
        ksort($params);

        $data = $url;
        foreach ($params as $k => $v) {
            $data .= $k . $v;
        }
        $signature = base64_encode(hash_hmac('sha1', $data, $authToken, true));

        $request = Request::create($url, 'POST', $params);
        $request->headers->set('X-Twilio-Signature', $signature);

        $this->assertTrue($this->verifier->verifyTwilio($request, $authToken));
    }

    public function testTwilioInvalidSignatureRejected(): void
    {
        $request = Request::create('https://example.com/webhook', 'POST', ['MessageSid' => 'SM1']);
        $request->headers->set('X-Twilio-Signature', 'invalid_signature');

        $this->assertFalse($this->verifier->verifyTwilio($request, 'secret'));
    }

    public function testTwilioMissingSignatureRejected(): void
    {
        $request = Request::create('https://example.com/webhook', 'POST', ['MessageSid' => 'SM1']);
        $this->assertFalse($this->verifier->verifyTwilio($request, 'secret'));
    }

    public function testTwilioEmptyAuthTokenRejected(): void
    {
        $request = Request::create('https://example.com/webhook', 'POST', []);
        $request->headers->set('X-Twilio-Signature', 'anything');
        $this->assertFalse($this->verifier->verifyTwilio($request, ''));
    }

    // ─── Mailgun ───

    public function testMailgunValidSignature(): void
    {
        $signingKey = 'mailgun_signing_key_secret';
        $timestamp = (string)time();
        $token = 'random_token_abc123';
        $signature = hash_hmac('sha256', $timestamp . $token, $signingKey);

        $this->assertTrue($this->verifier->verifyMailgun($signingKey, $timestamp, $token, $signature));
    }

    public function testMailgunReplayOldTimestampRejected(): void
    {
        $signingKey = 'key';
        $timestamp = (string)(time() - 3600); // 1h ago
        $token = 'tok';
        $signature = hash_hmac('sha256', $timestamp . $token, $signingKey);

        $this->assertFalse($this->verifier->verifyMailgun($signingKey, $timestamp, $token, $signature));
    }

    public function testMailgunInvalidSignatureRejected(): void
    {
        $this->assertFalse($this->verifier->verifyMailgun('key', (string)time(), 'tok', 'wrong_sig'));
    }

    public function testMailgunMissingFieldsRejected(): void
    {
        $this->assertFalse($this->verifier->verifyMailgun('', '1234', 'tok', 'sig'));
        $this->assertFalse($this->verifier->verifyMailgun('key', '', 'tok', 'sig'));
        $this->assertFalse($this->verifier->verifyMailgun('key', '1234', '', 'sig'));
        $this->assertFalse($this->verifier->verifyMailgun('key', '1234', 'tok', ''));
    }

    // ─── OVH ───

    public function testOvhValidSecret(): void
    {
        $request = Request::create('https://example.com/webhook', 'POST');
        $request->headers->set('X-Webhook-Token', 'shared_secret_value');
        $this->assertTrue($this->verifier->verifyOvh($request, 'shared_secret_value'));
    }

    public function testOvhInvalidSecretRejected(): void
    {
        $request = Request::create('https://example.com/webhook', 'POST');
        $request->headers->set('X-Webhook-Token', 'wrong_value');
        $this->assertFalse($this->verifier->verifyOvh($request, 'shared_secret_value'));
    }

    public function testOvhMissingHeaderRejected(): void
    {
        $request = Request::create('https://example.com/webhook', 'POST');
        $this->assertFalse($this->verifier->verifyOvh($request, 'shared_secret_value'));
    }

    public function testOvhEmptyExpectedSecretRejected(): void
    {
        $request = Request::create('https://example.com/webhook', 'POST');
        $request->headers->set('X-Webhook-Token', 'anything');
        // Pas de secret config => refus syst\u00e9matique pour forcer la configuration.
        $this->assertFalse($this->verifier->verifyOvh($request, ''));
    }
}
