<?php

namespace App\Service;

use App\Entity\ConfigAtelier;
use Doctrine\ORM\EntityManagerInterface;

class JoursOuvresService
{
    private array $configCache = [];

    public function __construct(
        private EntityManagerInterface $em,
    ) {}

    public function compterJoursOuvres(\DateTime $debut, \DateTime $fin, int $atelierId): int
    {
        $count = 0;
        $current = clone $debut;
        $current->setTime(0, 0, 0);
        $finNorm = clone $fin;
        $finNorm->setTime(0, 0, 0);

        while ($current < $finNorm) {
            $current->modify('+1 day');
            if (!$this->estJourFerme($current, $atelierId)) {
                $count++;
            }
        }

        return $count;
    }

    public function estJourFerie(\DateTime $date): bool
    {
        $year = (int) $date->format('Y');
        $m = (int) $date->format('n');
        $d = (int) $date->format('j');

        // Fixed holidays
        $fixes = [
            [1, 1],   // Jour de l'an
            [5, 1],   // Fête du travail
            [5, 8],   // Victoire 1945
            [7, 14],  // Fête nationale
            [8, 15],  // Assomption
            [11, 1],  // Toussaint
            [11, 11], // Armistice
            [12, 25], // Noël
        ];

        foreach ($fixes as [$fm, $fd]) {
            if ($m === $fm && $d === $fd) return true;
        }

        // Easter-based holidays
        $easter = $this->computeEaster($year);
        $lundiPaques = (clone $easter)->modify('+1 day');
        $ascension = (clone $easter)->modify('+39 days');
        $lundiPentecote = (clone $easter)->modify('+50 days');

        $dateStr = $date->format('Y-m-d');
        foreach ([$lundiPaques, $ascension, $lundiPentecote] as $mobile) {
            if ($mobile->format('Y-m-d') === $dateStr) return true;
        }

        return false;
    }

    public function estJourFerme(\DateTime $date, int $atelierId): bool
    {
        // Public holiday
        if ($this->estJourFerie($date)) return true;

        $config = $this->getConfig($atelierId);

        // Weekly closure days
        $dayName = strtolower($date->format('l'));
        if (in_array($dayName, $config->getJoursFermetureHebdo(), true)) return true;

        // Exceptional closure dates
        $dateStr = $date->format('Y-m-d');
        if (in_array($dateStr, $config->getDatesFermetureExceptionnelles(), true)) return true;

        return false;
    }

    public function ajouterJoursOuvres(\DateTime $debut, int $jours, int $atelierId): \DateTime
    {
        $current = clone $debut;
        $added = 0;
        while ($added < $jours) {
            $current->modify('+1 day');
            if (!$this->estJourFerme($current, $atelierId)) {
                $added++;
            }
        }
        return $current;
    }

    private function computeEaster(int $year): \DateTime
    {
        // Meeus-Jones-Butcher algorithm — no ext-calendar dependency
        $a = $year % 19;
        $b = intdiv($year, 100);
        $c = $year % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $month = intdiv($h + $l - 7 * $m + 114, 31);
        $day   = (($h + $l - 7 * $m + 114) % 31) + 1;

        return new \DateTime(sprintf('%d-%02d-%02d', $year, $month, $day));
    }

    private function getConfig(int $atelierId): ConfigAtelier
    {
        if (!isset($this->configCache[$atelierId])) {
            $config = $this->em->getRepository(ConfigAtelier::class)->findOneBy(['atelierId' => $atelierId]);
            $this->configCache[$atelierId] = $config ?? new ConfigAtelier();
        }
        return $this->configCache[$atelierId];
    }
}
