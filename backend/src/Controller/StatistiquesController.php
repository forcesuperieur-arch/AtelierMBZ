<?php
namespace App\Controller;

use App\Entity\Facture;
use App\Entity\RendezVous;
use App\Entity\User;
use App\Service\StatisticsService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Statistics and dashboard endpoints.
 */
#[Route('/api/statistiques')]
#[IsGranted('ROLE_USER')]
class StatistiquesController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private StatisticsService $statsService,
    ) {}

    private function getAuthenticatedUser(): ?User
    {
        $user = $this->getUser();

        return $user instanceof User ? $user : null;
    }

    /**
     * Dashboard KPIs.
     */
    #[Route('/dashboard', methods: ['GET'])]
    public function dashboard(Request $request): JsonResponse
    {
        $this->statsService->assertStatsAccess();

        $user = $this->getAuthenticatedUser();
        $atelierId = $user?->getAtelierId();
        $conn = $this->em->getConnection();

        [$fromDate, $toDate, $periodKey] = $this->statsService->resolveDateRange($request);
        $from = $fromDate->format('Y-m-d');
        $to = $toDate->format('Y-m-d');
        $days = max(1, (int) $fromDate->diff($toDate)->days + 1);
        $prevFromDate = $fromDate->modify(sprintf('-%d days', $days));
        $prevToDate = $fromDate->modify('-1 day');
        $prevFrom = $prevFromDate->format('Y-m-d');
        $prevTo = $prevToDate->format('Y-m-d');

        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
        $weekStart = (new \DateTimeImmutable('monday this week'))->format('Y-m-d');
        $weekEnd = (new \DateTimeImmutable('sunday this week'))->format('Y-m-d');
        $monthStart = (new \DateTimeImmutable('first day of this month'))->format('Y-m-d');
        $monthEnd = (new \DateTimeImmutable('last day of this month'))->format('Y-m-d');

        $rdvsToday = $conn->fetchOne(
            'SELECT COUNT(*) FROM rendez_vous WHERE date_rdv = :date AND atelier_id = :a AND statut != :s',
            ['date' => $today, 'a' => $atelierId, 's' => 'annule']
        );

        $rdvsWeek = $conn->fetchOne(
            'SELECT COUNT(*) FROM rendez_vous WHERE date_rdv BETWEEN :s AND :e AND atelier_id = :a AND statut != :st',
            ['s' => $weekStart, 'e' => $weekEnd, 'a' => $atelierId, 'st' => 'annule']
        );

        $caMonth = $conn->fetchOne(
            "SELECT COALESCE(SUM(total_ttc), 0) FROM factures WHERE DATE(date_creation) BETWEEN :s AND :e AND atelier_id = :a AND statut NOT IN ('annulee', 'corrigee')",
            ['s' => $monthStart, 'e' => $monthEnd, 'a' => $atelierId]
        );

        $impayees = $conn->fetchOne(
            'SELECT COUNT(*) FROM factures WHERE statut IN (:s1, :s2) AND nature = :nature AND atelier_id = :a',
            ['s1' => 'emise', 's2' => 'partiellement_payee', 'nature' => 'facture', 'a' => $atelierId]
        );

        $activeByStatus = $conn->fetchAllAssociative(
            'SELECT statut, COUNT(*) as count FROM rendez_vous WHERE date_rdv BETWEEN :s AND :e AND atelier_id = :a GROUP BY statut',
            ['s' => $from, 'e' => $to, 'a' => $atelierId]
        );

        // [I15] Guard module stock — skip query if module disabled
        $configAtelier = $this->em->getRepository(\App\Entity\ConfigAtelier::class)->findOneBy(['atelierId' => $atelierId]);
        $featureModules = $configAtelier?->getFeatureModules() ?? \App\Entity\ConfigAtelier::defaultFeatureModules();
        $stockEnabled = ($featureModules['stock'] ?? true) !== false;

        $stockAlerts = $stockEnabled
            ? $conn->fetchOne(
                'SELECT COUNT(*) FROM pieces_detachees WHERE quantite_stock <= quantite_minimale AND is_active = 1 AND atelier_id = :a',
                ['a' => $atelierId]
            )
            : 0;

        $orOuverts = $conn->fetchOne(
            "SELECT COUNT(o.id)
             FROM ordres_reparation o
             INNER JOIN rendez_vous r ON r.id = o.rendez_vous_id
             WHERE r.atelier_id = :a
               AND o.statut NOT IN ('termine', 'execute', 'rectifie')",
            ['a' => $atelierId]
        );

        $restitutions = $conn->fetchOne(
            "SELECT COUNT(*)
             FROM rendez_vous
             WHERE date_rdv BETWEEN :s AND :e
               AND atelier_id = :a
               AND statut IN ('termine', 'restitue', 'facture', 'paye')",
            ['s' => $from, 'e' => $to, 'a' => $atelierId]
        );

        $rdvsEnCours = $conn->fetchOne(
            "SELECT COUNT(*)
             FROM rendez_vous
             WHERE atelier_id = :a
               AND statut IN ('reception', 'en_cours')",
            ['a' => $atelierId]
        );

        $plannedMinutesToday = $conn->fetchOne(
            "SELECT COALESCE(SUM(COALESCE(temps_estime, 60)), 0)
             FROM rendez_vous
             WHERE date_rdv = :date
               AND atelier_id = :a
               AND statut != :st",
            ['date' => $today, 'a' => $atelierId, 'st' => 'annule']
        );

        $rdvsPeriod = (int) $conn->fetchOne(
            'SELECT COUNT(*) FROM rendez_vous WHERE date_rdv BETWEEN :s AND :e AND atelier_id = :a AND statut != :st',
            ['s' => $from, 'e' => $to, 'a' => $atelierId, 'st' => 'annule']
        );
        $rdvsPrev = (int) $conn->fetchOne(
            'SELECT COUNT(*) FROM rendez_vous WHERE date_rdv BETWEEN :s AND :e AND atelier_id = :a AND statut != :st',
            ['s' => $prevFrom, 'e' => $prevTo, 'a' => $atelierId, 'st' => 'annule']
        );

        $plannedMinutes = (int) $conn->fetchOne(
            "SELECT COALESCE(SUM(COALESCE(temps_estime, 60)), 0)
             FROM rendez_vous
             WHERE date_rdv BETWEEN :s AND :e
               AND atelier_id = :a
               AND statut != :st",
            ['s' => $from, 'e' => $to, 'a' => $atelierId, 'st' => 'annule']
        );
        $plannedMinutesPrev = (int) $conn->fetchOne(
            "SELECT COALESCE(SUM(COALESCE(temps_estime, 60)), 0)
             FROM rendez_vous
             WHERE date_rdv BETWEEN :s AND :e
               AND atelier_id = :a
               AND statut != :st",
            ['s' => $prevFrom, 'e' => $prevTo, 'a' => $atelierId, 'st' => 'annule']
        );

        $completedCurrent = (int) $conn->fetchOne(
            "SELECT COUNT(*) FROM rendez_vous
             WHERE date_rdv BETWEEN :s AND :e
               AND atelier_id = :a
               AND statut IN ('termine', 'restitue', 'facture', 'paye')",
            ['s' => $from, 'e' => $to, 'a' => $atelierId]
        );
        $completedPrev = (int) $conn->fetchOne(
            "SELECT COUNT(*) FROM rendez_vous
             WHERE date_rdv BETWEEN :s AND :e
               AND atelier_id = :a
               AND statut IN ('termine', 'restitue', 'facture', 'paye')",
            ['s' => $prevFrom, 'e' => $prevTo, 'a' => $atelierId]
        );

        $activePonts = (int) $conn->fetchOne(
            'SELECT COUNT(*) FROM ponts WHERE atelier_id = :a AND is_active = 1',
            ['a' => $atelierId]
        );
        $occupationCurrent = $activePonts > 0 ? min(100, round($plannedMinutes / max(1, $activePonts * $days * 480) * 100)) : 0;
        $occupationPrev = $activePonts > 0 ? min(100, round($plannedMinutesPrev / max(1, $activePonts * $days * 480) * 100)) : 0;

        $revenueMix = $conn->fetchAssociative(
            "SELECT COALESCE(SUM(total_mo_ht), 0) AS mo_ht,
                    COALESCE(SUM(total_pieces_ht), 0) AS pieces_ht,
                    COALESCE(SUM(total_ht), 0) AS total_ht,
                    COALESCE(SUM(total_ttc), 0) AS total_ttc,
                    COUNT(*) AS nb_factures
             FROM factures
             WHERE DATE(date_creation) BETWEEN :s AND :e
               AND atelier_id = :a
                    AND statut NOT IN ('annulee', 'corrigee')",
                ['s' => $from, 'e' => $to, 'a' => $atelierId]
        ) ?: [];

        $caPeriod = (float) ($revenueMix['total_ttc'] ?? 0);
        $nbFactures = (int) ($revenueMix['nb_factures'] ?? 0);

        $revenueMixPrev = $conn->fetchAssociative(
            "SELECT COALESCE(SUM(total_ttc), 0) AS total_ttc,
                    COUNT(*) AS nb_factures
             FROM factures
             WHERE DATE(date_creation) BETWEEN :s AND :e
               AND atelier_id = :a
                    AND statut NOT IN ('annulee', 'corrigee')",
                ['s' => $prevFrom, 'e' => $prevTo, 'a' => $atelierId]
        ) ?: [];

        $caPrev = (float) ($revenueMixPrev['total_ttc'] ?? 0);
        $nbFacturesPrev = (int) ($revenueMixPrev['nb_factures'] ?? 0);

        $avgTicket = $nbFactures > 0 ? round($caPeriod / $nbFactures, 2) : 0.0;
        $avgTicketPrev = $nbFacturesPrev > 0 ? round($caPrev / $nbFacturesPrev, 2) : 0.0;

        $topServices = $conn->fetchAllAssociative(
            "SELECT COALESCE(NULLIF(type_intervention, ''), 'Atelier') AS label,
                    COUNT(*) AS count,
                    COALESCE(SUM(COALESCE(temps_estime, 60)), 0) AS minutes,
                    COALESCE(SUM(COALESCE(prix_final, prix_estime, 0)), 0) AS revenue
             FROM rendez_vous
             WHERE date_rdv BETWEEN :s AND :e
               AND atelier_id = :a
               AND statut != :st
             GROUP BY COALESCE(NULLIF(type_intervention, ''), 'Atelier')
             ORDER BY revenue DESC, count DESC, minutes DESC
             LIMIT 8",
            ['s' => $from, 'e' => $to, 'a' => $atelierId, 'st' => 'annule']
        );

        $trendRows = $conn->fetchAllAssociative(
            "SELECT TO_CHAR(date_rdv, 'YYYY-MM-DD') AS day,
                    COUNT(*) AS rdvs,
                    COALESCE(SUM(COALESCE(prix_final, prix_estime, 0)), 0) AS revenue,
                    COALESCE(SUM(COALESCE(temps_estime, 60)), 0) AS minutes
             FROM rendez_vous
             WHERE date_rdv BETWEEN :s AND :e
               AND atelier_id = :a
               AND statut != :st
             GROUP BY date_rdv
             ORDER BY date_rdv ASC",
            ['s' => $from, 'e' => $to, 'a' => $atelierId, 'st' => 'annule']
        );

        $trendByDay = [];
        foreach ($trendRows as $row) {
            $trendByDay[$row['day']] = [
                'date' => $row['day'],
                'rdvs' => (int) ($row['rdvs'] ?? 0),
                'revenue' => round((float) ($row['revenue'] ?? 0), 2),
                'minutes' => (int) ($row['minutes'] ?? 0),
            ];
        }

        $dailyTrend = [];
        for ($cursor = $fromDate; $cursor <= $toDate; $cursor = $cursor->modify('+1 day')) {
            $key = $cursor->format('Y-m-d');
            $dailyTrend[] = $trendByDay[$key] ?? [
                'date' => $key,
                'rdvs' => 0,
                'revenue' => 0.0,
                'minutes' => 0,
            ];
        }

        return $this->json([
            'period' => [
                'key' => $periodKey,
                'from' => $from,
                'to' => $to,
                'days' => $days,
                'compare_from' => $prevFrom,
                'compare_to' => $prevTo,
            ],
            'rdvs_today' => (int) $rdvsToday,
            'rdvs_week' => (int) $rdvsWeek,
            'ca_month' => round((float) $caMonth, 2),
            'impayees_count' => (int) $impayees,
            'stock_alerts' => (int) $stockAlerts,
            'or_ouverts' => (int) $orOuverts,
            'restitutions' => (int) $restitutions,
            'rdvs_en_cours' => (int) $rdvsEnCours,
            'planned_minutes_today' => (int) $plannedMinutesToday,
            'active_by_status' => $activeByStatus,
            'top_services' => $topServices,
            'daily_trend' => $dailyTrend,
            'revenue_mix' => [
                'mo_ht' => round((float) ($revenueMix['mo_ht'] ?? 0), 2),
                'pieces_ht' => round((float) ($revenueMix['pieces_ht'] ?? 0), 2),
                'total_ht' => round((float) ($revenueMix['total_ht'] ?? 0), 2),
                'total_ttc' => round((float) ($revenueMix['total_ttc'] ?? 0), 2),
                'nb_factures' => $nbFactures,
            ],
            'comparison' => [
                'rdvs' => $this->statsService->compareMetric($rdvsPeriod, $rdvsPrev),
                'ca' => $this->statsService->compareMetric($caPeriod, $caPrev),
                'avg_ticket' => $this->statsService->compareMetric($avgTicket, $avgTicketPrev),
                'planned_minutes' => $this->statsService->compareMetric($plannedMinutes, $plannedMinutesPrev),
                'completed' => $this->statsService->compareMetric($completedCurrent, $completedPrev),
                'occupation' => $this->statsService->compareMetric($occupationCurrent, $occupationPrev),
            ],
        ]);
    }



    /**
     * Monthly revenue breakdown.
     */
    #[Route('/ca', methods: ['GET'])]
    public function chiffreAffaires(Request $request): JsonResponse
    {
        $this->statsService->assertStatsAccess();

        $user = $this->getAuthenticatedUser();
        $atelierId = $user?->getAtelierId();
        $year = (int) $request->query->get('year', date('Y'));
        $conn = $this->em->getConnection();

        $monthly = $conn->fetchAllAssociative(
            "SELECT EXTRACT(MONTH FROM date_creation) as mois,
                    COALESCE(SUM(total_ht), 0) as total_ht,
                    COALESCE(SUM(total_ttc), 0) as total_ttc,
                    COALESCE(SUM(total_mo_ht), 0) as total_mo_ht,
                    COALESCE(SUM(total_pieces_ht), 0) as total_pieces_ht,
                    COUNT(*) as nb_factures
             FROM factures
             WHERE EXTRACT(YEAR FROM date_creation) = :year
               AND atelier_id = :a AND statut NOT IN ('annulee', 'corrigee')
             GROUP BY EXTRACT(MONTH FROM date_creation)
             ORDER BY mois",
            ['year' => $year, 'a' => $atelierId]
        );

        return $this->json([
            'year' => $year,
            'monthly' => $monthly,
        ]);
    }

    /**
     * Mechanic performance stats.
     */
    #[Route('/mecaniciens', methods: ['GET'])]
    public function mecaniciens(Request $request): JsonResponse
    {
        $this->statsService->assertStatsAccess();

        $user = $this->getAuthenticatedUser();
        $atelierId = $user?->getAtelierId();
        $conn = $this->em->getConnection();

        [$fromDate, $toDate] = $this->statsService->resolveDateRange($request);
        $from = $fromDate->format('Y-m-d');
        $to = $toDate->format('Y-m-d');

        $stats = $conn->fetchAllAssociative(
            "SELECT m.id, m.nom, m.prenom,
                    COUNT(r.id) as nb_rdvs,
                    COALESCE(SUM(COALESCE(r.temps_effectif_minutes, r.temps_estime, 0)), 0) as total_minutes,
                    COALESCE(AVG(NULLIF(COALESCE(r.temps_effectif_minutes, r.temps_estime, 0), 0)), 0) as avg_minutes,
                    COALESCE(SUM(COALESCE(r.temps_estime, 0)), 0) as planned_minutes,
                    COALESCE(SUM(COALESCE(r.prix_final, r.prix_estime, 0)), 0) as ca_genere
             FROM mecaniciens m
             LEFT JOIN rendez_vous r ON r.mecanicien_id = m.id
                AND r.date_rdv BETWEEN :s AND :e
                AND r.statut NOT IN ('annule', 'en_attente')
             WHERE m.atelier_id = :a AND m.is_active = 1
             GROUP BY m.id, m.nom, m.prenom
             ORDER BY ca_genere DESC, nb_rdvs DESC, total_minutes DESC",
            ['s' => $from, 'e' => $to, 'a' => $atelierId]
        );

        return $this->json($stats);
    }
}
