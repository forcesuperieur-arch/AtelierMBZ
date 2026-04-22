<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\EssaiRoutier;
use App\Entity\RapportIntervention;
use App\Entity\RendezVous;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Workflow\Event\GuardEvent;

/**
 * [C1] Bloque la transition "terminer" si :
 *  - l'essai routier n'existe pas ou n'est pas validé (isValide() === false)
 *  - le rapport d'intervention n'a pas été signé par le mécanicien
 */
#[AsEventListener(event: 'workflow.rendez_vous.guard.terminer')]
final class RdvTerminationGuardSubscriber
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    public function __invoke(GuardEvent $event): void
    {
        /** @var RendezVous $rdv */
        $rdv = $event->getSubject();

        // Guard 1 : essai routier obligatoire et valide
        $essai = $rdv->getEssaiRoutier();
        if (!$essai instanceof EssaiRoutier || !$essai->isValide()) {
            $event->setBlocked(true, 'Essai routier obligatoire et valide avant clôture de l\'intervention.');
            return;
        }

        // Guard 2 : rapport d'intervention signé par le mécanicien
        $rapport = $this->em->getRepository(RapportIntervention::class)->findOneBy(
            ['rendezVous' => $rdv],
            ['id' => 'DESC'],
        );

        if (!$rapport instanceof RapportIntervention || $rapport->getSignatureMecanicien() === null) {
            $event->setBlocked(true, 'Le rapport d\'intervention doit être signé par le mécanicien avant clôture.');
        }
    }
}
