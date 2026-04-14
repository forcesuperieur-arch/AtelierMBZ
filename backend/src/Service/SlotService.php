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
        $period = new \DatePeriod($dateDebut, new \DateInterval('P1D'), $dateFin);

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

        // Get active ponts
        $pontQb = $this->em->getRepository(Pont::class)->createQueryBuilder('p')
            ->where('p.isActive = 1');
        if ($atelierId) {
            $pontQb->andWhere('p.atelierId = :atelier')->setParameter('atelier', $atelierId);
        }
        $ponts = $pontQb->getQuery()->getResult();

        if (empty($ponts)) {
            return [];
        }

        // Get existing RDVs for this day
        $rdvs = $this->em->getRepository(RendezVous::class)->createQueryBuilder('r')
            ->where('r.dateRdv = :date')
            ->andWhere('r.statut NOT IN (:excluded)')
            ->setParameter('date', $date->format('Y-m-d'))
            ->setParameter('excluded', ['annule'])
            ->getQuery()->getResult();

        // Get absences for this day
        $absences = $this->em->getRepository(Absence::class)->createQueryBuilder('a')
            ->where('a.dateDebut <= :date')
            ->andWhere('a.dateFin >= :date')
            ->setParameter('date', $date->format('Y-m-d'))
            ->getQuery()->getResult();

        $absentMecanicienIds = array_map(fn(Absence $a) => $a->getMecanicien()->getId(), $absences);

        // Build occupied slots: pontId => [['start' => HH:MM, 'end' => HH:MM], ...]
        $occupied = [];
        foreach ($rdvs as $rdv) {
            $pontId = $rdv->getPontId();
            if (!$pontId) continue;
            $start = $rdv->getHeureRdv()->format('H:i');
            $endMinutes = ((int)$rdv->getHeureRdv()->format('H') * 60 + (int)$rdv->getHeureRdv()->format('i'))
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
        $slots = [];

        for ($t = $startMinutes; $t + $tempsMinutes <= $endMinutes; $t += 30) {
            $slotStart = sprintf('%02d:%02d', intdiv($t, 60), $t % 60);
            $slotEnd = sprintf('%02d:%02d', intdiv($t + $tempsMinutes, 60), ($t + $tempsMinutes) % 60);

            // Skip lunch break
            if ($pauseDebut && $pauseFin) {
                $pStart = $this->timeToMinutes($pauseDebut);
                $pEnd = $this->timeToMinutes($pauseFin);
                if ($t < $pEnd && $t + $tempsMinutes > $pStart) {
                    continue;
                }
            }

            foreach ($ponts as $pont) {
                $pontId = $pont->getId();
                $mecId = $pont->getMecanicienId();

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
                        'pont_id' => $pontId,
                        'pont_nom' => $pont->getNom(),
                        'mecanicien_id' => $mecId,
                    ];
                }
            }
        }

        return $slots;
    }

    private function timeToMinutes(string $time): int
    {
        [$h, $m] = explode(':', $time);
        return (int) $h * 60 + (int) $m;
    }
}
