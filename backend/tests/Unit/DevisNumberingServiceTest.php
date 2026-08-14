<?php

namespace App\Tests\Unit;

use App\Service\DevisNumberingService;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

class DevisNumberingServiceTest extends TestCase
{
    public function testNextNumberFormatsWithYearAndSequenceValue(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturn(42);

        $service = new DevisNumberingService($connection);

        $this->assertSame('DEV-' . date('Y') . '-00042', $service->nextNumber());
    }

    public function testNextNumberPadsToFiveDigits(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturn(7);

        $service = new DevisNumberingService($connection);

        $this->assertSame('DEV-' . date('Y') . '-00007', $service->nextNumber());
    }
}
