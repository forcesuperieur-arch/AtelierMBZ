<?php

namespace App\Tests\Unit;

use App\Entity\RendezVous;
use App\Service\RendezVousWorkflowService;
use PHPUnit\Framework\TestCase;

class RendezVousWorkflowServiceTest extends TestCase
{
    public function testRecordCancellationCreatesTraceableEntry(): void
    {
        $rdv = (new RendezVous())
            ->setStatut('reception')
            ->setHeureRdv(new \DateTime('2026-04-17 09:30:00'));

        $service = new RendezVousWorkflowService();
        $entry = $service->recordCancellation(
            $rdv,
            'client_no_show',
            'atelier',
            'Client absent après 30 min',
            99,
        );

        $this->assertSame($rdv, $entry->getRendezVous());
        $this->assertSame('client_no_show', $entry->getMotif());
        $this->assertSame('atelier', $entry->getSource());
        $this->assertSame('Client absent après 30 min', $entry->getCommentaire());
        $this->assertSame(99, $entry->getAnnulePar());
        $this->assertSame('reception', $entry->getStatutAvantAnnulation());
        $this->assertSame('09:30', $entry->getHeureRdvOriginal()?->format('H:i'));
    }

    public function testFinalizeWorkSessionAccumulatesMinutes(): void
    {
        $rdv = (new RendezVous())
            ->setHeureDebutTravail(new \DateTimeImmutable('-50 minutes'))
            ->setTempsEffectifMinutes(10);

        $service = new RendezVousWorkflowService();
        $service->finalizeWorkSession($rdv, new \DateTimeImmutable());

        $this->assertGreaterThanOrEqual(59, (int) $rdv->getTempsEffectifMinutes());
        $this->assertNotNull($rdv->getHeureFinTravail());
    }
}
