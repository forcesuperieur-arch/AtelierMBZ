<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\EssaiRoutier;
use App\Entity\RapportIntervention;
use App\Entity\RendezVous;
use App\EventSubscriber\RdvTerminationGuardSubscriber;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Workflow\Event\GuardEvent;
use Symfony\Component\Workflow\Marking;
use Symfony\Component\Workflow\Transition;

#[AllowMockObjectsWithoutExpectations]
class RdvTerminationGuardTest extends TestCase
{
    private function createGuardEvent(RendezVous $rdv): GuardEvent
    {
        return new GuardEvent(
            $rdv,
            new Marking(['en_cours' => 1]),
            new Transition('terminer', 'en_cours', 'termine'),
            null
        );
    }

    private function createSubscriber(?RapportIntervention $rapport = null): RdvTerminationGuardSubscriber
    {
        $repo = $this->createMock(EntityRepository::class);
        $repo->method('findOneBy')->willReturn($rapport);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->with(RapportIntervention::class)->willReturn($repo);

        return new RdvTerminationGuardSubscriber($em);
    }

    private function createRdvMock(?EssaiRoutier $essai = null): RendezVous
    {
        $rdv = $this->createMock(RendezVous::class);
        $rdv->method('getEssaiRoutier')->willReturn($essai);

        return $rdv;
    }

    public function testBlocksWhenEssaiRoutierIsMissing(): void
    {
        $rdv = $this->createRdvMock(null);
        $event = $this->createGuardEvent($rdv);
        $subscriber = $this->createSubscriber();

        $subscriber($event);

        $this->assertTrue($event->isBlocked());
    }

    public function testBlocksWhenEssaiRoutierIsNotValide(): void
    {
        $essai = (new EssaiRoutier())
            ->setStatut(EssaiRoutier::STATUT_BROUILLON)
            ->setKmDebut(1000)
            ->setKmFin(1010);

        $rdv = $this->createRdvMock($essai);
        $event = $this->createGuardEvent($rdv);
        $subscriber = $this->createSubscriber();

        $subscriber($event);

        $this->assertTrue($event->isBlocked());
    }

    public function testBlocksWhenRapportIsMissing(): void
    {
        $essai = (new EssaiRoutier())
            ->setStatut(EssaiRoutier::STATUT_VALIDE)
            ->setKmDebut(1000)
            ->setKmFin(1010);

        $rdv = $this->createRdvMock($essai);
        $event = $this->createGuardEvent($rdv);
        $subscriber = $this->createSubscriber(null);

        $subscriber($event);

        $this->assertTrue($event->isBlocked());
    }

    public function testBlocksWhenRapportIsNotSignedByMecanicien(): void
    {
        $essai = (new EssaiRoutier())
            ->setStatut(EssaiRoutier::STATUT_VALIDE)
            ->setKmDebut(1000)
            ->setKmFin(1010);

        $rdv = $this->createRdvMock($essai);

        $rapport = new RapportIntervention();
        $rapport->setSignatureMecanicien(null);

        $event = $this->createGuardEvent($rdv);
        $subscriber = $this->createSubscriber($rapport);

        $subscriber($event);

        $this->assertTrue($event->isBlocked());
    }

    public function testAllowsTransitionWhenAllGuardsPass(): void
    {
        $essai = (new EssaiRoutier())
            ->setStatut(EssaiRoutier::STATUT_VALIDE)
            ->setKmDebut(1000)
            ->setKmFin(1010);

        $rdv = $this->createRdvMock($essai);

        $rapport = new RapportIntervention();
        $rapport->setSignatureMecanicien('data:image/png;base64,signed');

        $event = $this->createGuardEvent($rdv);
        $subscriber = $this->createSubscriber($rapport);

        $subscriber($event);

        $this->assertFalse($event->isBlocked());
    }

    public function testBlocksWhenAnomalieCritiqueIsUnresolvedViaInvalidEssai(): void
    {
        // An essai with "anomalie_detectee" but missing kmFin is considered invalid,
        // which represents an unresolved critical anomaly scenario for the guard.
        $essai = (new EssaiRoutier())
            ->setStatut(EssaiRoutier::STATUT_ANOMALIE)
            ->setKmDebut(1000)
            ->setKmFin(null);

        $rdv = $this->createRdvMock($essai);
        $event = $this->createGuardEvent($rdv);
        $subscriber = $this->createSubscriber();

        $subscriber($event);

        $this->assertTrue($event->isBlocked());
    }
}
