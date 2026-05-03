<?php

namespace App\Service;

use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;

/**
 * Helpers pour les calculs et la sécurité du module statistiques.
 */
class StatisticsService
{
    public function __construct(private Security $security) {}

    public function assertStatsAccess(): void
    {
        if (
            $this->security->isGranted('ROLE_SUPER_ADMIN')
            || $this->security->isGranted('ROLE_RESPONSABLE_ATELIER')
            || $this->security->isGranted('ROLE_RESPONSABLE_MAGASIN')
            || $this->security->isGranted('ROLE_COMPTABLE')
        ) {
            return;
        }

        $user = $this->security->getUser();
        $roleMetierCode = $user instanceof User ? $user->getRoleMetier()?->getCode() : null;

        if (in_array($roleMetierCode, ['responsable_atelier', 'responsable_magasin', 'comptable'], true)) {
            return;
        }

        if ($this->security->isGranted('ROLE_ADMIN') && $roleMetierCode === null) {
            return;
        }

        throw new \Symfony\Component\Security\Core\Exception\AccessDeniedException(
            'La page Stat est réservée au responsable atelier et aux profils supérieurs.'
        );
    }

    /**
     * @return array{\DateTimeImmutable, \DateTimeImmutable, string}
     */
    public function resolveDateRange(Request $request): array
    {
        $periodKey = (string) $request->query->get('period', '30d');
        $today = new \DateTimeImmutable('today');

        try {
            $fromInput = $request->query->get('from');
            $toInput = $request->query->get('to');

            if ($fromInput && $toInput) {
                $fromDate = new \DateTimeImmutable((string) $fromInput);
                $toDate = new \DateTimeImmutable((string) $toInput);

                if ($fromDate > $toDate) {
                    [$fromDate, $toDate] = [$toDate, $fromDate];
                }

                return [$fromDate, $toDate, 'custom'];
            }
        } catch (\Throwable) {
        }

        return match ($periodKey) {
            'today' => [$today, $today, 'today'],
            '7d' => [$today->modify('-6 days'), $today, '7d'],
            '90d' => [$today->modify('-89 days'), $today, '90d'],
            default => [$today->modify('-29 days'), $today, '30d'],
        };
    }

    /**
     * @return array{current: float, previous: float, delta: float, delta_pct: float}
     */
    public function compareMetric(float|int $current, float|int $previous): array
    {
        $delta = $current - $previous;
        $deltaPct = $previous !== 0.0 && $previous !== 0
            ? round(($delta / $previous) * 100, 1)
            : ($current > 0 ? 100.0 : 0.0);

        return [
            'current' => round((float) $current, 2),
            'previous' => round((float) $previous, 2),
            'delta' => round((float) $delta, 2),
            'delta_pct' => $deltaPct,
        ];
    }
}
