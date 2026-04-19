<?php

namespace App\Tests\Unit;

use App\Service\NotificationProviderConfigSanitizer;
use PHPUnit\Framework\TestCase;

class NotificationProviderConfigSanitizerTest extends TestCase
{
    public function testMergePreservesExistingSecretsWhenSubmittedValuesAreBlank(): void
    {
        $sanitizer = new NotificationProviderConfigSanitizer();

        $merged = $sanitizer->merge(
            [
                'account_sid' => 'AC123',
                'auth_token' => 'secret-token',
                'from' => '+33600000000',
            ],
            [
                'account_sid' => '',
                'auth_token' => '',
                'from' => '+33611111111',
            ],
        );

        self::assertSame('AC123', $merged['account_sid']);
        self::assertSame('secret-token', $merged['auth_token']);
        self::assertSame('+33611111111', $merged['from']);
    }

    public function testMergeKeepsNewNonEmptyValues(): void
    {
        $sanitizer = new NotificationProviderConfigSanitizer();

        $merged = $sanitizer->merge(
            ['host' => 'old.smtp.local'],
            ['host' => 'smtp.example.com', 'port' => 587],
        );

        self::assertSame('smtp.example.com', $merged['host']);
        self::assertSame(587, $merged['port']);
    }
}
