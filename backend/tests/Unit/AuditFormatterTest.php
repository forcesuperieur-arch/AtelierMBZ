<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Service\AuditFormatter;
use PHPUnit\Framework\TestCase;

class AuditFormatterTest extends TestCase
{
    private AuditFormatter $formatter;

    protected function setUp(): void
    {
        $this->formatter = new AuditFormatter();
    }

    public function testFormatActionCreate(): void
    {
        $this->assertSame(
            'Creation du rendez-vous',
            $this->formatter->formatAction('create', null)
        );
    }

    public function testFormatActionWorkflowTransition(): void
    {
        $this->assertSame(
            'Transition workflow : reception',
            $this->formatter->formatAction('workflow_transition', json_encode(['transition' => 'reception']))
        );
    }

    public function testFormatActionWorkflowTransitionWithoutDetails(): void
    {
        $this->assertSame(
            'Transition workflow',
            $this->formatter->formatAction('workflow_transition', null)
        );
    }

    public function testFormatActionUnknown(): void
    {
        $this->assertSame(
            'Unknown action',
            $this->formatter->formatAction('unknown_action', null)
        );
    }

    public function testFormatDetailsCreate(): void
    {
        $this->assertSame(
            'Type: revision · Statut initial: en_attente',
            $this->formatter->formatDetails('create', json_encode(['type' => 'revision', 'statut' => 'en_attente']))
        );
    }

    public function testFormatDetailsCreateWithoutStatut(): void
    {
        $this->assertSame(
            'Type: revision',
            $this->formatter->formatDetails('create', json_encode(['type' => 'revision']))
        );
    }

    public function testFormatDetailsWorkflowTransition(): void
    {
        $this->assertSame(
            'Nouveau statut: termine',
            $this->formatter->formatDetails('workflow_transition', json_encode(['new_status' => 'termine']))
        );
    }

    public function testFormatDetailsWorkflowTransitionFallbackStatut(): void
    {
        $this->assertSame(
            'Nouveau statut: en_cours',
            $this->formatter->formatDetails('workflow_transition', json_encode(['statut' => 'en_cours']))
        );
    }

    public function testFormatDetailsUnknownAction(): void
    {
        $payload = ['foo' => 'bar'];
        $this->assertSame(
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $this->formatter->formatDetails('custom_event', json_encode($payload))
        );
    }

    public function testFormatDetailsReturnsRawWhenJsonInvalid(): void
    {
        $this->assertSame(
            'not-json',
            $this->formatter->formatDetails('create', 'not-json')
        );
    }

    public function testFormatDetailsWithNullDetails(): void
    {
        $this->assertNull(
            $this->formatter->formatDetails('create', null)
        );
    }
}
