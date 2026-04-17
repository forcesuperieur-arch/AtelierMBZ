<?php

namespace App\Tests\Unit;

use App\Entity\Notification;
use App\Entity\NotificationEscalation;
use PHPUnit\Framework\TestCase;

class NotificationTest extends TestCase
{
    // ─── Notification Entity ───

    public function testDefaultValues(): void
    {
        $notif = new Notification();

        $this->assertNull($notif->getId());
        $this->assertNull($notif->getAtelierId());
        $this->assertSame('info', $notif->getSeverity());
        $this->assertSame('', $notif->getTitle());
        $this->assertSame('normal', $notif->getPriority());
        $this->assertFalse($notif->getIsRead());
        $this->assertNull($notif->getReadAt());
        $this->assertNull($notif->getAcknowledgedAt());
        $this->assertNull($notif->getExpiresAt());
        $this->assertEmpty($notif->getTargetRoles());
        $this->assertInstanceOf(\DateTimeInterface::class, $notif->getCreatedAt());
    }

    public function testSettersAndGetters(): void
    {
        $notif = new Notification();
        $notif->setAtelierId(42);
        $notif->setType('demande_complementaire');
        $notif->setSeverity('critical');
        $notif->setTitle('Demande urgente');
        $notif->setMessage('Un message de test');
        $notif->setActionUrl('/planning#rdv-123');
        $notif->setRelatedEntityType('DemandeTravauxSupp');
        $notif->setRelatedEntityId(99);
        $notif->setTargetRole('ROLE_RECEPTIONNAIRE');
        $notif->setTargetRoles(['ROLE_ADMIN', 'ROLE_RECEPTIONNAIRE']);
        $notif->setTargetUserId(5);
        $notif->setPriority('high');
        $notif->setIsRead(true);

        $this->assertSame(42, $notif->getAtelierId());
        $this->assertSame('demande_complementaire', $notif->getType());
        $this->assertSame('critical', $notif->getSeverity());
        $this->assertSame('Demande urgente', $notif->getTitle());
        $this->assertSame('Un message de test', $notif->getMessage());
        $this->assertSame('/planning#rdv-123', $notif->getActionUrl());
        $this->assertSame('DemandeTravauxSupp', $notif->getRelatedEntityType());
        $this->assertSame(99, $notif->getRelatedEntityId());
        $this->assertSame('ROLE_RECEPTIONNAIRE', $notif->getTargetRole());
        $this->assertSame(['ROLE_ADMIN', 'ROLE_RECEPTIONNAIRE'], $notif->getTargetRoles());
        $this->assertSame(5, $notif->getTargetUserId());
        $this->assertSame('high', $notif->getPriority());
        $this->assertTrue($notif->getIsRead());
    }

    public function testReadAtAndReadBy(): void
    {
        $notif = new Notification();
        $readAt = new \DateTime('2026-01-15 10:00:00');
        $notif->setReadAt($readAt);
        $notif->setReadBy(7);

        $this->assertSame($readAt, $notif->getReadAt());
        $this->assertSame(7, $notif->getReadBy());
    }

    public function testAcknowledgedAtAndBy(): void
    {
        $notif = new Notification();
        $ackAt = new \DateTime('2026-01-15 10:05:00');
        $notif->setAcknowledgedAt($ackAt);
        $notif->setAcknowledgedBy(3);

        $this->assertSame($ackAt, $notif->getAcknowledgedAt());
        $this->assertSame(3, $notif->getAcknowledgedBy());
    }

    public function testExpiresAt(): void
    {
        $notif = new Notification();
        $expires = new \DateTime('+1 hour');
        $notif->setExpiresAt($expires);

        $this->assertSame($expires, $notif->getExpiresAt());

        $notif->setExpiresAt(null);
        $this->assertNull($notif->getExpiresAt());
    }

    public function testFluentSetters(): void
    {
        $notif = new Notification();
        $result = $notif->setType('test')
            ->setSeverity('warning')
            ->setTitle('Title')
            ->setMessage('Msg')
            ->setAtelierId(1)
            ->setTargetRole('ROLE_ADMIN');

        $this->assertInstanceOf(Notification::class, $result);
        $this->assertSame('test', $result->getType());
    }

    // ─── NotificationEscalation Entity ───

    public function testEscalationDefaults(): void
    {
        $esc = new NotificationEscalation();
        $this->assertNull($esc->getId());
        $this->assertSame(1, $esc->getLevel());
        $this->assertSame('push', $esc->getChannel());
        $this->assertNull($esc->getExecutedAt());
        $this->assertNull($esc->getResult());
        $this->assertNull($esc->getSkipReason());
        $this->assertNull($esc->getTargetInfo());
    }

    public function testEscalationSettersAndGetters(): void
    {
        $notif = new Notification();
        $notif->setType('test');
        $notif->setMessage('msg');

        $esc = new NotificationEscalation();
        $esc->setNotification($notif);
        $esc->setLevel(3);
        $esc->setChannel('sms');
        $scheduled = new \DateTime('+10 minutes');
        $esc->setScheduledAt($scheduled);
        $esc->setTargetInfo('ROLE_RESPONSABLE_ATELIER');

        $this->assertSame($notif, $esc->getNotification());
        $this->assertSame(3, $esc->getLevel());
        $this->assertSame('sms', $esc->getChannel());
        $this->assertSame($scheduled, $esc->getScheduledAt());
        $this->assertSame('ROLE_RESPONSABLE_ATELIER', $esc->getTargetInfo());
    }

    public function testEscalationExecution(): void
    {
        $esc = new NotificationEscalation();
        $now = new \DateTime();
        $esc->setExecutedAt($now);
        $esc->setResult('success');

        $this->assertSame($now, $esc->getExecutedAt());
        $this->assertSame('success', $esc->getResult());
    }

    public function testEscalationSkipped(): void
    {
        $esc = new NotificationEscalation();
        $esc->setResult('skipped');
        $esc->setSkipReason('acknowledged_before');

        $this->assertSame('skipped', $esc->getResult());
        $this->assertSame('acknowledged_before', $esc->getSkipReason());
    }
}
