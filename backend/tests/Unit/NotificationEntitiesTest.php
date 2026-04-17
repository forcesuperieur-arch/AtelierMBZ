<?php

namespace App\Tests\Unit;

use App\Entity\NotificationLog;
use App\Entity\NotificationProviderConfig;
use App\Entity\NotificationTemplate;
use PHPUnit\Framework\TestCase;

class NotificationEntitiesTest extends TestCase
{
    // ─── NotificationProviderConfig ───

    public function testProviderConfigDefaults(): void
    {
        $config = new NotificationProviderConfig();
        $this->assertNull($config->getId());
        $this->assertFalse($config->isPrimary());
        $this->assertFalse($config->isFallback());
        $this->assertEquals(1, $config->getPriority());
        $this->assertTrue($config->isActive());
        $this->assertNull($config->getLastTestAt());
        $this->assertNull($config->getLastTestSuccess());
        $this->assertEquals('', $config->getConfigEncrypted());
    }

    public function testProviderConfigSetters(): void
    {
        $config = new NotificationProviderConfig();
        $config->setAtelierId(1)
            ->setChannel('sms')
            ->setProvider('twilio')
            ->setIsPrimary(true)
            ->setIsFallback(false)
            ->setPriority(2)
            ->setIsActive(true)
            ->setConfigEncrypted('encrypted-data');

        $this->assertEquals(1, $config->getAtelierId());
        $this->assertEquals('sms', $config->getChannel());
        $this->assertEquals('twilio', $config->getProvider());
        $this->assertTrue($config->isPrimary());
        $this->assertFalse($config->isFallback());
        $this->assertEquals(2, $config->getPriority());
        $this->assertTrue($config->isActive());
        $this->assertEquals('encrypted-data', $config->getConfigEncrypted());
    }

    public function testProviderConfigTestResult(): void
    {
        $config = new NotificationProviderConfig();
        $now = new \DateTime();
        $config->setLastTestAt($now);
        $config->setLastTestSuccess(true);

        $this->assertEquals($now, $config->getLastTestAt());
        $this->assertTrue($config->getLastTestSuccess());
    }

    // ─── NotificationLog ───

    public function testLogDefaults(): void
    {
        $log = new NotificationLog();
        $this->assertNull($log->getId());
        $this->assertEquals('sent', $log->getStatus());
        $this->assertInstanceOf(\DateTimeInterface::class, $log->getSentAt());
        $this->assertNull($log->getDeliveredAt());
        $this->assertNull($log->getReadAt());
        $this->assertNull($log->getErrorMessage());
        $this->assertNull($log->getProviderMessageId());
    }

    public function testLogSetters(): void
    {
        $log = new NotificationLog();
        $log->setAtelierId(1)
            ->setChannel('email')
            ->setProvider('mailgun')
            ->setTemplateCode('rdv_confirme')
            ->setToRecipient('test@example.com')
            ->setSubject('Confirmation RDV')
            ->setStatus('delivered')
            ->setProviderMessageId('msg-123')
            ->setRelatedEntityType('Rdv')
            ->setRelatedEntityId(42);

        $this->assertEquals(1, $log->getAtelierId());
        $this->assertEquals('email', $log->getChannel());
        $this->assertEquals('mailgun', $log->getProvider());
        $this->assertEquals('rdv_confirme', $log->getTemplateCode());
        $this->assertEquals('test@example.com', $log->getToRecipient());
        $this->assertEquals('Confirmation RDV', $log->getSubject());
        $this->assertEquals('delivered', $log->getStatus());
        $this->assertEquals('msg-123', $log->getProviderMessageId());
        $this->assertEquals('Rdv', $log->getRelatedEntityType());
        $this->assertEquals(42, $log->getRelatedEntityId());
    }

    public function testLogDeliveryDates(): void
    {
        $log = new NotificationLog();
        $delivered = new \DateTime('+1 hour');
        $read = new \DateTime('+2 hours');

        $log->setDeliveredAt($delivered);
        $log->setReadAt($read);

        $this->assertEquals($delivered, $log->getDeliveredAt());
        $this->assertEquals($read, $log->getReadAt());
    }

    public function testLogErrorMessage(): void
    {
        $log = new NotificationLog();
        $log->setStatus('failed');
        $log->setErrorMessage('Connection timeout');

        $this->assertEquals('failed', $log->getStatus());
        $this->assertEquals('Connection timeout', $log->getErrorMessage());
    }

    // ─── NotificationTemplate ───

    public function testTemplateDefaults(): void
    {
        $t = new NotificationTemplate();
        $this->assertNull($t->getId());
        $this->assertTrue($t->isActive());
        $this->assertEquals([], $t->getVariables());
    }

    public function testTemplateSetters(): void
    {
        $t = new NotificationTemplate();
        $t->setAtelierId(1)
            ->setCode('rdv_confirme')
            ->setChannel('sms')
            ->setLibelle('Confirmation RDV')
            ->setSujet('Votre RDV est confirmé')
            ->setCorps('Bonjour {{ prenom }}, votre RDV du {{ date }} est confirmé.')
            ->setVariables(['prenom', 'date'])
            ->setIsActive(true);

        $this->assertEquals(1, $t->getAtelierId());
        $this->assertEquals('rdv_confirme', $t->getCode());
        $this->assertEquals('sms', $t->getChannel());
        $this->assertEquals('Confirmation RDV', $t->getLibelle());
        $this->assertEquals('Votre RDV est confirmé', $t->getSujet());
        $this->assertStringContainsString('{{ prenom }}', $t->getCorps());
        $this->assertEquals(['prenom', 'date'], $t->getVariables());
    }

    public function testTemplateRender(): void
    {
        $t = new NotificationTemplate();
        $t->setCorps('Bonjour {{ prenom }}, RDV le {{ date }}.');

        $rendered = $t->render(['prenom' => 'Jean', 'date' => '17/04/2026']);
        $this->assertEquals('Bonjour Jean, RDV le 17/04/2026.', $rendered);
    }

    public function testTemplateRenderWithoutSpaces(): void
    {
        $t = new NotificationTemplate();
        $t->setCorps('Bonjour {{prenom}}, RDV le {{date}}.');

        $rendered = $t->render(['prenom' => 'Marie', 'date' => '18/04']);
        $this->assertEquals('Bonjour Marie, RDV le 18/04.', $rendered);
    }

    public function testTemplateRenderSubject(): void
    {
        $t = new NotificationTemplate();
        $t->setSujet('RDV {{ date }} — {{ atelier }}');

        $rendered = $t->renderSubject(['date' => '17/04', 'atelier' => 'Seclin']);
        $this->assertEquals('RDV 17/04 — Seclin', $rendered);
    }

    public function testTemplateRenderSubjectNull(): void
    {
        $t = new NotificationTemplate();
        $this->assertNull($t->renderSubject(['key' => 'val']));
    }

    public function testTemplateRenderMissingVarsLeftAsIs(): void
    {
        $t = new NotificationTemplate();
        $t->setCorps('Hello {{ name }}, balance {{ amount }}.');

        $rendered = $t->render(['name' => 'Test']);
        $this->assertEquals('Hello Test, balance {{ amount }}.', $rendered);
    }
}
