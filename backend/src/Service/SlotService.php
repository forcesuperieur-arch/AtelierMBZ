<?php
namespace App\Service;

use App\Entity\Absence;
use App\Entity\HoraireAtelier;
use App\Entity\Mecanicien;
use App\Entity\Pont;
use App\Entity\RendezVous;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Calculates available appointment slots based on workshop hours,
 * mechanic availability, and existing appointments.
 */
class SlotService
{
    public function __construct(private EntityManagerInterface $em) {}

    /**
     * Get available slots for a date range.
     *
     * @return array<string, array<array{heure: string, pont_id: int, mecanicien_id: int|null}>>
     */
    public function getAvailableSlots(
        \DateTimeInterface $dateDebut,
        \DateTimeInterface $dateFin,
        int $tempsMinutes = 60,
        ?int $atelierId = null,
    ): array {
        $slots = [];
        $inclusiveEnd = (new \DateTimeImmutable($dateFin->format('Y-m-d')))->modify('+1 day');
        $period = new \DatePeriod(
            new \DateTimeImmutable($dateDebut->format('Y-m-d')),
            new \DateInterval('P1D'),
            $inclusiveEnd,
        );

        foreach ($period as $date) {
            $daySlots = $this->getSlotsForDay($date, $tempsMinutes, $atelierId);
            if (!empty($daySlots)) {
                $slots[$date->format('Y-m-d')] = $daySlots;
            }
        }

        return $slots;
    }

    /**
     * Get available slots for a single day.
     */
    public function getSlotsForDay(
        \DateTimeInterface $date,
        int $tempsMinutes = 60,
        ?int $atelierId = null,
    ): array {
        $jourSemaine = (int) $date->format('N') - 1; // 0=Monday

        // Get workshop hours for this day
        $qb = $this->em->getRepository(HoraireAtelier::class)->createQueryBuilder('h')
            ->where('h.jourSemaine = :jour')
            ->andWhere('h.isOuvert = 1')
            ->setParameter('jour', $jourSemaine);

        if ($atelierId) {
            $qb->andWhere('h.atelierId = :atelier')->setParameter('atelier', $atelierId);
        }

        /** @var HoraireAtelier|null $horaire */
        $horaire = $qb->setMaxResults(1)->getQuery()->getOneOrNullResult();
        if (!$horaire) {
            return [];
        }

        // Get active ponts with an assigned mechanic only
        $pontQb = $this->em->getRepository(Pont::class)->createQueryBuilder('p')
            ->where('p.isActive = 1')
            ->andWhere('p.mecanicien IS NOT NULL');
        if ($atelierId) {
            $pontQb->andWhere('p.atelierId = :atelier')->setParameter('atelier', $atelierId);
        }
        $ponts = $pontQb->getQuery()->getResult();

        if (empty($ponts)) {
            return [];
        }

        // Get existing RDVs for this day
        $rdvQb = $this->em->getRepository(RendezVous::class)->createQueryBuilder('r')
            ->where('r.dateRdv = :date')
            ->andWhere('r.statut NOT IN (:excluded)')
            ->setParameter('date', $date->format('Y-m-d'))
            ->setParameter('excluded', ['annule']);
        if ($atelierId) {
            $rdvQb->andWhere('r.atelierId = :atelier')->setParameter('atelier', $atelierId);
        }
        $rdvs = $rdvQb->getQuery()->getResult();

        // Get absences for this day
        $absQb = $this->em->getRepository(Absence::class)->createQueryBuilder('a')
            ->where('a.dateDebut <= :date')
            ->andWhere('a.dateFin >= :date')
            ->setParameter('date', $date->format('Y-m-d'));
        if ($atelierId) {
            $absQb->andWhere('a.atelierId = :atelier')->setParameter('atelier', $atelierId);
        }
        $absences = $absQb->getQuery()->getResult();

        $absentMecanicienIds = array_values(array_filter(array_map(
            fn(Absence $a) => $a->getMecanicien()?->getId(),
            $absences,
        )));

        // Build occupied slots: pontId => [['start' => HH:MM, 'end' => HH:MM], ...]
        $occupied = [];
        foreach ($rdvs as $rdv) {
            $pontId = $rdv->getPont()?->getId();
            if (!$pontId) {
                continue;
            }
            $start = $rdv->getHeureRdv()->format('H:i');
            $endMinutes = ((int) $rdv->getHeureRdv()->format('H') * 60 + (int) $rdv->getHeureRdv()->format('i'))
                + ($rdv->getTempsEstime() ?: 60);
            $end = sprintf('%02d:%02d', intdiv($endMinutes, 60), $endMinutes % 60);
            $occupied[$pontId][] = ['start' => $start, 'end' => $end];
        }

        // Generate time slots
        $ouverture = $horaire->getHeureOuverture() ?: '08:00';
        $fermeture = $horaire->getHeureFermeture() ?: '18:00';
        $pauseDebut = $horaire->getPauseDebut();
        $pauseFin = $horaire->getPauseFin();

        $startMinutes = $this->timeToMinutes($ouverture);
        $endMinutes = $this->timeToMinutes($fermeture);
        $sameDayMinStart = $this->sameDayMinStartMinutes($date);
        $slots = [];

        $pStart = $pauseDebut ? $this->timeToMinutes($pauseDebut) : null;
        $pEnd = $pauseFin ? $this->timeToMinutes($pauseFin) : null;

        for ($t = $startMinutes; $t < $endMinutes; $t += 30) {
            if ($sameDayMinStart !== null && $t < $sameDayMinStart) {
                continue;
            }

            if ($pStart !== null && $pEnd !== null && $t >= $pStart && $t < $pEnd) {
                continue;
            }

            $effectiveEnd = $this->computeEffectiveEndMinutes($t, $tempsMinutes, $pStart, $pEnd);
            if ($effectiveEnd > $endMinutes) {
                continue;
            }

            $slotStart = sprintf('%02d:%02d', intdiv($t, 60), $t % 60);
            $slotEnd = sprintf('%02d:%02d', intdiv($effectiveEnd, 60), $effectiveEnd % 60);
            $pauseAppliquee = $effectiveEnd > ($t + $tempsMinutes);

            foreach ($ponts as $pont) {
                $pontId = $pont->getId();
                $mecId = $pont->getMecanicien()?->getId();

                // Skip if assigned mechanic is absent
                if ($mecId && in_array($mecId, $absentMecanicienIds, true)) {
                    continue;
                }

                // Check if pont is free
                $isFree = true;
                foreach ($occupied[$pontId] ?? [] as $occ) {
                    if ($slotStart < $occ['end'] && $slotEnd > $occ['start']) {
                        $isFree = false;
                        break;
                    }
                }

                if ($isFree) {
                    $slots[] = [
                        'heure' => $slotStart,
                        'heure_fin' => $slotEnd,
                        'pause_appliquee' => $pauseAppliquee,
                        'disponible' => true,
                        'pont_id' => $pontId,
                        'pont_nom' => $pont->getNom(),
                        'mecanicien_id' => $mecId,
                    ];
                }
            }
        }

        return $slots;
    }

    private function sameDayMinStartMinutes(\DateTimeInterface $date): ?int
    {
        $now = new \DateTimeImmutable();
        if ($date->format('Y-m-d') !== $now->format('Y-m-d')) {
            return null;
        }

        $minAllowed = ((int) $now->format('H') * 60) + (int) $now->format('i') + 120;
        return (int) (ceil($minAllowed / 30) * 30);
    }

    private function computeEffectiveEndMinutes(
        int $startMinutes,
        int $tempsMinutes,
        ?int $pauseStart,
        ?int $pauseEnd,
    ): int {
        $effectiveEnd = $startMinutes + max(15, $tempsMinutes);
        if ($pauseStart !== null && $pauseEnd !== null && $startMinutes < $pauseStart && $effectiveEnd > $pauseStart) {
            $effectiveEnd += ($pauseEnd - $pauseStart);
        }
        return $effectiveEnd;
    }

    private function timeToMinutes(string $time): int
    {
        [$h, $m] = explode(':', $time);
        return (int) $h * 60 + (int) $m;
    }
}
