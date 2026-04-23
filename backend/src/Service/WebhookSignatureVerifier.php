<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\Request;

/**
 * Verifies HMAC signatures for incoming notification provider webhooks.
 *
 * - Twilio: HMAC-SHA1 of (URL + sorted POST params concatenated), header X-Twilio-Signature
 *   https://www.twilio.com/docs/usage/webhooks/webhooks-security
 * - Mailgun: HMAC-SHA256 of (timestamp + token) using signing_key, value in payload signature.signature
 *   https://documentation.mailgun.com/docs/mailgun/user-manual/tracking-messages/#webhooks
 * - OVH: pas de standard HMAC documenté pour les webhooks SMS. On supporte un secret
 *   partagé optionnel passé en header X-Webhook-Token (à configurer côté OVH si possible,
 *   sinon recommander un IP whitelist au niveau Caddy).
 */
class WebhookSignatureVerifier
{
    private const MAILGUN_MAX_AGE_SECONDS = 300; // 5 min anti-replay

    public function verifyTwilio(Request $request, string $authToken): bool
    {
        $signature = $request->headers->get('X-Twilio-Signature', '');
        if ($signature === '' || $authToken === '') {
            return false;
        }

        // Twilio signs: full URL + concatenated sorted POST params (key + value)
        $url = $request->getSchemeAndHttpHost() . $request->getRequestUri();
        $params = $request->request->all();
        ksort($params);

        $data = $url;
        foreach ($params as $key => $value) {
            $data .= $key . (is_scalar($value) ? (string)$value : json_encode($value));
        }

        $expected = base64_encode(hash_hmac('sha1', $data, $authToken, true));

        return hash_equals($expected, $signature);
    }

    public function verifyMailgun(string $signingKey, string $timestamp, string $token, string $signature): bool
    {
        if ($signingKey === '' || $timestamp === '' || $token === '' || $signature === '') {
            return false;
        }

        // Anti-replay: refuse signatures older than MAILGUN_MAX_AGE_SECONDS
        $now = time();
        $ts = (int)$timestamp;
        if ($ts <= 0 || abs($now - $ts) > self::MAILGUN_MAX_AGE_SECONDS) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp . $token, $signingKey);

        return hash_equals($expected, $signature);
    }

    /**
     * OVH n'a pas de mécanisme HMAC standard pour ses webhooks SMS.
     * On vérifie un secret partagé optionnel passé en header X-Webhook-Token,
     * comparé à la valeur stockée dans la config provider (clef "webhookSecret").
     * Si aucun secret n'est configuré, le webhook est rejeté pour forcer la configuration.
     */
    public function verifyOvh(Request $request, string $expectedSecret): bool
    {
        if ($expectedSecret === '') {
            return false;
        }

        $token = $request->headers->get('X-Webhook-Token', '');
        if ($token === '') {
            return false;
        }

        return hash_equals($expectedSecret, $token);
    }
}
