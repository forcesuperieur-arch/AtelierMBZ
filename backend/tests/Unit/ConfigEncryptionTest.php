<?php

namespace App\Tests\Unit;

use App\Service\ConfigEncryptionService;
use PHPUnit\Framework\TestCase;

class ConfigEncryptionTest extends TestCase
{
    private ConfigEncryptionService $service;

    protected function setUp(): void
    {
        $this->service = new ConfigEncryptionService('test-app-secret-key-for-testing');
    }

    public function testEncryptDecryptRoundTrip(): void
    {
        $config = [
            'account_sid' => 'AC1234567890',
            'auth_token' => 'secret-token-value',
            'from' => '+33600000000',
        ];

        $encrypted = $this->service->encrypt($config);
        $this->assertNotEmpty($encrypted);
        $this->assertNotEquals(json_encode($config), $encrypted);

        $decrypted = $this->service->decrypt($encrypted);
        $this->assertEquals($config, $decrypted);
    }

    public function testEncryptProducesDifferentCiphertextEachTime(): void
    {
        $config = ['key' => 'value'];
        $a = $this->service->encrypt($config);
        $b = $this->service->encrypt($config);

        // Different nonces → different ciphertexts
        $this->assertNotEquals($a, $b);

        // Both decrypt to the same value
        $this->assertEquals($this->service->decrypt($a), $this->service->decrypt($b));
    }

    public function testDecryptWithWrongKeyThrows(): void
    {
        $service1 = new ConfigEncryptionService('key-one');
        $service2 = new ConfigEncryptionService('key-two');

        $encrypted = $service1->encrypt(['secret' => 'data']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Decryption failed');
        $service2->decrypt($encrypted);
    }

    public function testDecryptInvalidDataThrows(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->service->decrypt('not-valid-base64!!!');
    }

    public function testDecryptTruncatedDataThrows(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->service->decrypt(base64_encode('short'));
    }

    public function testEncryptEmptyArray(): void
    {
        $encrypted = $this->service->encrypt([]);
        $decrypted = $this->service->decrypt($encrypted);
        $this->assertEquals([], $decrypted);
    }

    public function testEncryptUnicodeValues(): void
    {
        $config = ['nom' => 'Atelier Moto Café', 'emoji' => '🔧'];
        $encrypted = $this->service->encrypt($config);
        $decrypted = $this->service->decrypt($encrypted);
        $this->assertEquals($config, $decrypted);
    }

    public function testEncryptedDataIsBase64(): void
    {
        $encrypted = $this->service->encrypt(['test' => true]);
        $decoded = base64_decode($encrypted, true);
        $this->assertNotFalse($decoded);
        $this->assertGreaterThan(28, strlen($decoded)); // nonce(12) + tag(16) + ciphertext
    }
}
