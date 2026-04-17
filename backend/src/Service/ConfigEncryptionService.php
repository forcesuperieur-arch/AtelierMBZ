<?php

namespace App\Service;

/**
 * Encrypts/decrypts sensitive provider config (API keys, secrets) using APP_SECRET.
 * Uses AES-256-GCM for authenticated encryption.
 */
class ConfigEncryptionService
{
    private string $encryptionKey;

    public function __construct(string $appSecret)
    {
        // Derive a 32-byte key from APP_SECRET using SHA-256
        $this->encryptionKey = hash('sha256', $appSecret, true);
    }

    /**
     * Encrypt an associative array of config values to a base64-encoded string.
     */
    public function encrypt(array $config): string
    {
        $json = json_encode($config, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $nonce = random_bytes(12); // 96-bit nonce for GCM
        $tag = '';

        $ciphertext = openssl_encrypt(
            $json,
            'aes-256-gcm',
            $this->encryptionKey,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            '',
            16, // 128-bit tag
        );

        if ($ciphertext === false) {
            throw new \RuntimeException('Encryption failed');
        }

        // Format: base64(nonce + tag + ciphertext)
        return base64_encode($nonce . $tag . $ciphertext);
    }

    /**
     * Decrypt a base64-encoded encrypted config string back to an associative array.
     */
    public function decrypt(string $encrypted): array
    {
        $raw = base64_decode($encrypted, true);
        if ($raw === false || strlen($raw) < 28) { // 12 nonce + 16 tag = 28 minimum
            throw new \RuntimeException('Invalid encrypted data');
        }

        $nonce = substr($raw, 0, 12);
        $tag = substr($raw, 12, 16);
        $ciphertext = substr($raw, 28);

        $json = openssl_decrypt(
            $ciphertext,
            'aes-256-gcm',
            $this->encryptionKey,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
        );

        if ($json === false) {
            throw new \RuntimeException('Decryption failed — invalid key or corrupted data');
        }

        return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    }
}
