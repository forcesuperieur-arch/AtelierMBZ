<?php

namespace App\Tests\Unit;

use App\Service\NotificationMessage;
use App\Service\NotificationResult;
use PHPUnit\Framework\TestCase;

class NotificationDispatcherDTOTest extends TestCase
{
    // ─── NotificationMessage ───

    public function testMessageConstruction(): void
    {
        $msg = new NotificationMessage(
            'sms',
            1,
            '+33612345678',
            'Test body',
            'Test subject',
            'rdv_confirme',
            'Rdv',
            42,
        );

        $this->assertEquals('sms', $msg->getChannel());
        $this->assertEquals(1, $msg->getAtelierId());
        $this->assertEquals('+33612345678', $msg->getRecipient());
        $this->assertEquals('Test body', $msg->getBody());
        $this->assertEquals('Test subject', $msg->getSubject());
        $this->assertEquals('rdv_confirme', $msg->getTemplateCode());
        $this->assertEquals('Rdv', $msg->getRelatedEntityType());
        $this->assertEquals(42, $msg->getRelatedEntityId());
    }

    public function testMessageMinimalConstruction(): void
    {
        $msg = new NotificationMessage('email', 1, 'test@example.com', 'Body');

        $this->assertEquals('email', $msg->getChannel());
        $this->assertNull($msg->getSubject());
        $this->assertNull($msg->getTemplateCode());
        $this->assertNull($msg->getRelatedEntityType());
        $this->assertNull($msg->getRelatedEntityId());
    }

    // ─── NotificationResult ───

    public function testResultOk(): void
    {
        $result = NotificationResult::ok('twilio', 'SM123');
        $this->assertTrue($result->isSuccess());
        $this->assertEquals('twilio', $result->getProvider());
        $this->assertEquals('SM123', $result->getProviderMessageId());
        $this->assertNull($result->getErrorMessage());
    }

    public function testResultFail(): void
    {
        $result = NotificationResult::fail('ovh', 'Connection refused');
        $this->assertFalse($result->isSuccess());
        $this->assertEquals('ovh', $result->getProvider());
        $this->assertEquals('Connection refused', $result->getErrorMessage());
        $this->assertNull($result->getProviderMessageId());
    }

    public function testResultAllFailed(): void
    {
        $result = NotificationResult::allFailed();
        $this->assertFalse($result->isSuccess());
        $this->assertNull($result->getProvider());
        $this->assertEquals('All providers failed', $result->getErrorMessage());
    }

    public function testResultOkWithoutMessageId(): void
    {
        $result = NotificationResult::ok('mailgun');
        $this->assertTrue($result->isSuccess());
        $this->assertNull($result->getProviderMessageId());
    }
}
