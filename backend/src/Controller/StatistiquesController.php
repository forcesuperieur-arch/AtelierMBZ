<?php
namespace App\Controller;

use App\Entity\Facture;
use App\Entity\RendezVous;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Statistics and dashboard endpoints.
 */
#[Route('/api/statistiques')]
class StatistiquesController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em) {}

    /**
     * Dashboard KPIs.
     */
    #[Route('/dashboard', methods: ['GET'])]
    public function dashboard(Request $request): JsonResponse
    {
        $this->assertStatsAccess();

        $user = $this->getUser();
        $atelierId = $user?->getAtelierId();
        $conn = $this->em->getConnection();

        [$fromDate, $toDate, $periodKey] = $this->resolveDateRange($request);
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
            "SELECT COALESCE(SUM(total_ttc), 0) FROM factures WHERE DATE(date_creation) BETWEEN :s AND :e AND atelier_id = :a AND statut != :st",
            ['s' => $monthStart, 'e' => $monthEnd, 'a' => $atelierId, 'st' => 'annulee']
        );

        $impayees = $conn->fetchOne(
            'SELECT COUNT(*) FROM factures WHERE statut IN (:s1, :s2) AND atelier_id = :a',
            ['s1' => 'emise', 's2' => 'partiellement_payee', 'a' => $atelierId]
        );

        $activeByStatus = $conn->fetchAllAssociative(
            'SELECT statut, COUNT(*) as count FROM rendez_vous WHERE date_rdv BETWEEN :s AND :e AND atelier_id = :a GROUP BY statut',
            ['s' => $from, 'e' => $to, 'a' => $atelierId]
        );

        $stockAlerts = $conn->fetchOne(
            'SELECT COUNT(*) FROM pieces_detachees WHERE quantite_stock <= quantite_minimale AND is_active = 1 AND atelier_id = :a',
            ['a' => $atelierId]
        );

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
               AND statut != :st",
            ['s' => $from, 'e' => $to, 'a' => $atelierId, 'st' => 'annulee']
        ) ?: [];

        $caPeriod = (float) ($revenueMix['total_ttc'] ?? 0);
        $nbFactures = (int) ($revenueMix['nb_factures'] ?? 0);

        $revenueMixPrev = $conn->fetchAssociative(
            "SELECT COALESCE(SUM(total_ttc), 0) AS total_ttc,
                    COUNT(*) AS nb_factures
             FROM factures
             WHERE DATE(date_creation) BETWEEN :s AND :e
               AND atelier_id = :a
               AND statut != :st",
            ['s' => $prevFrom, 'e' => $prevTo, 'a' => $atelierId, 'st' => 'annulee']
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
                'rdvs' => $this->compareMetric($rdvsPeriod, $rdvsPrev),
                'ca' => $this->compareMetric($caPeriod, $caPrev),
                'avg_ticket' => $this->compareMetric($avgTicket, $avgTicketPrev),
                'planned_minutes' => $this->compareMetric($plannedMinutes, $plannedMinutesPrev),
                'completed' => $this->compareMetric($completedCurrent, $completedPrev),
                'occupation' => $this->compareMetric($occupationCurrent, $occupationPrev),
            ],
        ]);
    }

    private function assertStatsAccess(): void
    {
        if ($this->isGranted('ROLE_SUPER_ADMIN')) {
            return;
        }

        $roleMetierCode = $this->getUser()?->getRoleMetier()?->getCode();

        if (in_array($roleMetierCode, ['responsable_atelier', 'responsable_magasin'], true)) {
            return;
        }

        if ($this->isGranted('ROLE_ADMIN') && $roleMetierCode === null) {
            return;
        }

        throw $this->createAccessDeniedException('La page Stat est réservée au responsable atelier et aux profils supérieurs.');
    }

    private function resolveDateRange(Request $request): array
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

    private function compareMetric(float|int $current, float|int $previous): array
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

    /**
     * Monthly revenue breakdown.
     */
    #[Route('/ca', methods: ['GET'])]
    public function chiffreAffaires(Request $request): JsonResponse
    {
        $this->assertStatsAccess();

        $user = $this->getUser();
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
               AND atelier_id = :a AND statut != 'annulee'
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
        $this->assertStatsAccess();

        $user = $this->getUser();
        $atelierId = $user?->getAtelierId();
        $conn = $this->em->getConnection();

        [$fromDate, $toDate] = $this->resolveDateRange($request);
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

    /**
     * Real-time operational KPIs for the live banner.
     */
    #[Route('/realtime', methods: ['GET'])]
    public function realtime(): JsonResponse
    {
        $this->assertStatsAccess();
        $user = $this->getUser();
        $atelierId = $user?->getAtelierId();
        $conn = $this->em->getConnection();
        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        // OR currently in progress with potential overrun
        $orEnCours = $conn->fetchAllAssociative(
            "SELECT o.id, o.numero_or,
                    COALESCE(v.marque || ' ' || v.modele, o.snap_vehicule_marque || ' ' || o.snap_vehicule_modele, 'N/A') as vehicule_info,
                    COALESCE(c.nom || ' ' || c.prenom, o.snap_client_nom || ' ' || o.snap_client_prenom, 'N/A') as client_nom,
                    r.temps_estime, r.heure_debut_travail, r.mecanicien_id,
                    m.prenom as mecano_prenom, m.nom as mecano_nom
             FROM ordres_reparation o
             INNER JOIN rendez_vous r ON r.id = o.rendez_vous_id
             LEFT JOIN mecaniciens m ON m.id = r.mecanicien_id
             LEFT JOIN vehicules v ON v.id = r.vehicule_id
             LEFT JOIN clients c ON c.id = r.client_id
             WHERE r.atelier_id = :a
               AND o.statut IN ('en_cours', 'attente_pieces', 'attente_validation')
               AND r.statut IN ('reception', 'en_cours')",
            ['a' => $atelierId]
        );

        // Ponts status with current RDV
        $pontsStatus = $conn->fetchAllAssociative(
            "SELECT p.id, p.nom, p.is_active,
                    r.id as rdv_id,
                    COALESCE(v.marque || ' ' || v.modele, 'N/A') as vehicule_info,
                    COALESCE(c.nom || ' ' || c.prenom, 'N/A') as client_nom,
                    r.statut,
                    r.heure_debut_travail, r.temps_estime, r.mecanicien_id,
                    m.prenom as mecano_prenom, m.nom as mecano_nom
             FROM ponts p
             LEFT JOIN rendez_vous r ON r.pont_id = p.id
                 AND r.date_rdv = :today
                 AND r.statut IN ('reception', 'en_cours', 'termine')
             LEFT JOIN mecaniciens m ON m.id = r.mecanicien_id
             LEFT JOIN vehicules v ON v.id = r.vehicule_id
             LEFT JOIN clients c ON c.id = r.client_id
             WHERE p.atelier_id = :a
             ORDER BY p.nom",
            ['today' => $today, 'a' => $atelierId]
        );

        // Restitutions waiting
        $attenteRestitution = $conn->fetchAllAssociative(
            "SELECT r.id,
                    COALESCE(v.marque || ' ' || v.modele, 'N/A') as vehicule_info,
                    COALESCE(c.nom || ' ' || c.prenom, 'N/A') as client_nom,
                    r.heure_fin_travail, r.date_rdv
             FROM rendez_vous r
             LEFT JOIN vehicules v ON v.id = r.vehicule_id
             LEFT JOIN clients c ON c.id = r.client_id
             WHERE r.atelier_id = :a
               AND r.statut = 'termine'
               AND r.heure_fin_travail IS NOT NULL
             ORDER BY r.heure_fin_travail ASC",
            ['a' => $atelierId]
        );

        // Today's planned vs actual load
        $chargeJour = $conn->fetchAssociative(
            "SELECT COALESCE(SUM(COALESCE(temps_estime, 60)), 0) as planned,
                    COALESCE(SUM(COALESCE(temps_effectif_minutes, 0)), 0) as actual
             FROM rendez_vous
             WHERE date_rdv = :today AND atelier_id = :a AND statut != 'annule'",
            ['today' => $today, 'a' => $atelierId]
        ) ?: ['planned' => 0, 'actual' => 0];

        // Active mechanics today
        $mecanosActifs = $conn->fetchAllAssociative(
            "SELECT m.id, m.prenom, m.nom,
                    COUNT(r.id) as nb_interventions,
                    COALESCE(SUM(COALESCE(r.temps_estime, 60)), 0) as planned_min,
                    COALESCE(SUM(COALESCE(r.temps_effectif_minutes, 0)), 0) as actual_min
             FROM mecaniciens m
             LEFT JOIN rendez_vous r ON r.mecanicien_id = m.id
                 AND r.date_rdv = :today
                 AND r.statut NOT IN ('annule', 'en_attente')
             WHERE m.atelier_id = :a AND m.is_active = 1
             GROUP BY m.id, m.prenom, m.nom
             ORDER BY nb_interventions DESC",
            ['today' => $today, 'a' => $atelierId]
        );

        return $this->json([
            'now' => $now,
            'or_en_cours' => $orEnCours,
            'ponts' => $pontsStatus,
            'attente_restitution' => $attenteRestitution,
            'charge_jour' => [
                'planned_minutes' => (int) $chargeJour['planned'],
                'actual_minutes' => (int) $chargeJour['actual'],
                'ratio' => $chargeJour['planned'] > 0 ? round($chargeJour['actual'] / $chargeJour['planned'] * 100, 1) : 0,
            ],
            'mecaniciens_actifs' => $mecanosActifs,
        ]);
    }

    /**
     * Performance analysis : rendement, écarts, SAV.
     */
    #[Route('/performance', methods: ['GET'])]
    public function performance(Request $request): JsonResponse
    {
        $this->assertStatsAccess();
        $user = $this->getUser();
        $atelierId = $user?->getAtelierId();
        $conn = $this->em->getConnection();

        [$fromDate, $toDate] = $this->resolveDateRange($request);
        $from = $fromDate->format('Y-m-d');
        $to = $toDate->format('Y-m-d');

        // Ecart temps effectif vs estimé par mécano
        $ecartsMecano = $conn->fetchAllAssociative(
            "SELECT m.id, m.prenom, m.nom,
                    COUNT(r.id) as nb_rdvs,
                    COALESCE(AVG(NULLIF(COALESCE(r.temps_effectif_minutes, 0) - COALESCE(r.temps_estime, 0), 0)), 0) as avg_ecart_min,
                    COALESCE(SUM(COALESCE(r.temps_effectif_minutes, 0)), 0) as total_effectif,
                    COALESCE(SUM(COALESCE(r.temps_estime, 60)), 0) as total_estime
             FROM mecaniciens m
             LEFT JOIN rendez_vous r ON r.mecanicien_id = m.id
                 AND r.date_rdv BETWEEN :s AND :e
                 AND r.statut IN ('termine', 'restitue', 'facture', 'paye')
                 AND r.temps_effectif_minutes IS NOT NULL
             WHERE m.atelier_id = :a AND m.is_active = 1
             GROUP BY m.id, m.prenom, m.nom
             ORDER BY avg_ecart_min DESC",
            ['s' => $from, 'e' => $to, 'a' => $atelierId]
        );

        // Taux de rendement global (effectif / estime)
        $rendementGlobal = $conn->fetchAssociative(
            "SELECT COALESCE(SUM(temps_effectif_minutes), 0) as effectif,
                    COALESCE(SUM(COALESCE(temps_estime, 60)), 0) as estime
             FROM rendez_vous
             WHERE date_rdv BETWEEN :s AND :e
               AND atelier_id = :a
               AND statut IN ('termine', 'restitue', 'facture', 'paye')
               AND temps_effectif_minutes IS NOT NULL",
            ['s' => $from, 'e' => $to, 'a' => $atelierId]
        ) ?: ['effectif' => 0, 'estime' => 0];

        // Délai moyen entre fin travaux et restitution (approximé via updated_at)
        $delaiRestitution = $conn->fetchAssociative(
            "SELECT COALESCE(AVG(
                EXTRACT(EPOCH FROM (r.updated_at - r.heure_fin_travail)) / 60
             ), 0) as avg_minutes,
             COUNT(*) as count
             FROM rendez_vous r
             WHERE r.date_rdv BETWEEN :s AND :e
               AND r.atelier_id = :a
               AND r.statut IN ('restitue', 'facture', 'paye')
               AND r.heure_fin_travail IS NOT NULL",
            ['s' => $from, 'e' => $to, 'a' => $atelierId]
        ) ?: ['avg_minutes' => 0, 'count' => 0];

        // Taux de retour SAV (2nd OR sur même véhicule dans les 30 jours)
        $savCount = $conn->fetchOne(
            "SELECT COUNT(*) FROM (
                SELECT r.vehicule_id, MIN(r.date_rdv) as first_date
                FROM rendez_vous r
                WHERE r.atelier_id = :a AND r.statut IN ('termine', 'restitue', 'facture', 'paye')
                GROUP BY r.vehicule_id
                HAVING COUNT(*) > 1
            ) t",
            ['a' => $atelierId]
        );
        $totalVehicules = $conn->fetchOne(
            "SELECT COUNT(DISTINCT vehicule_id)
             FROM rendez_vous
             WHERE atelier_id = :a AND statut IN ('termine', 'restitue', 'facture', 'paye')",
            ['a' => $atelierId]
        );

        // Ecart par type d'intervention
        $ecartsType = $conn->fetchAllAssociative(
            "SELECT COALESCE(NULLIF(type_intervention, ''), 'Atelier') as type,
                    COUNT(*) as count,
                    COALESCE(AVG(NULLIF(COALESCE(temps_effectif_minutes, 0) - COALESCE(temps_estime, 0), 0)), 0) as avg_ecart_min,
                    COALESCE(AVG(temps_effectif_minutes), 0) as avg_effectif,
                    COALESCE(AVG(temps_estime), 0) as avg_estime
             FROM rendez_vous
             WHERE date_rdv BETWEEN :s AND :e
               AND atelier_id = :a
               AND statut IN ('termine', 'restitue', 'facture', 'paye')
               AND temps_effectif_minutes IS NOT NULL
             GROUP BY COALESCE(NULLIF(type_intervention, ''), 'Atelier')
             ORDER BY avg_ecart_min DESC",
            ['s' => $from, 'e' => $to, 'a' => $atelierId]
        );

        return $this->json([
            'period' => ['from' => $from, 'to' => $to],
            'ecarts_mecaniciens' => $ecartsMecano,
            'rendement_global' => [
                'effectif' => (int) $rendementGlobal['effectif'],
                'estime' => (int) $rendementGlobal['estime'],
                'ratio' => $rendementGlobal['estime'] > 0
                    ? round($rendementGlobal['effectif'] / $rendementGlobal['estime'] * 100, 1)
                    : 0,
            ],
            'delai_restitution' => [
                'avg_minutes' => round((float) $delaiRestitution['avg_minutes'], 1),
                'count' => (int) $delaiRestitution['count'],
            ],
            'taux_retour_sav' => [
                'sav_count' => (int) $savCount,
                'total_vehicules' => (int) $totalVehicules,
                'taux_pct' => $totalVehicules > 0 ? round($savCount / $totalVehicules * 100, 1) : 0,
            ],
            'ecarts_par_type' => $ecartsType,
        ]);
    }

    /**
     * Rentability analysis per intervention type.
     */
    #[Route('/rentabilite', methods: ['GET'])]
    public function rentabilite(Request $request): JsonResponse
    {
        $this->assertStatsAccess();
        $user = $this->getUser();
        $atelierId = $user?->getAtelierId();
        $conn = $this->em->getConnection();

        [$fromDate, $toDate] = $this->resolveDateRange($request);
        $from = $fromDate->format('Y-m-d');
        $to = $toDate->format('Y-m-d');

        // Rentabilité par type d'intervention (facturé vs estimé)
        $parType = $conn->fetchAllAssociative(
            "SELECT COALESCE(NULLIF(r.type_intervention, ''), 'Atelier') as type,
                    COUNT(DISTINCT r.id) as nb_rdvs,
                    COALESCE(SUM(f.total_ht), 0) as ca_ht,
                    COALESCE(SUM(f.total_mo_ht), 0) as mo_ht,
                    COALESCE(SUM(f.total_pieces_ht), 0) as pieces_ht,
                    COALESCE(AVG(f.total_ht), 0) as avg_ticket
             FROM rendez_vous r
             LEFT JOIN factures f ON f.rendez_vous_id = r.id AND f.statut != 'annulee'
             WHERE r.date_rdv BETWEEN :s AND :e
               AND r.atelier_id = :a
               AND r.statut IN ('termine', 'restitue', 'facture', 'paye')
             GROUP BY COALESCE(NULLIF(r.type_intervention, ''), 'Atelier')
             ORDER BY ca_ht DESC",
            ['s' => $from, 'e' => $to, 'a' => $atelierId]
        );

        // Rentabilité globale période
        $global = $conn->fetchAssociative(
            "SELECT COALESCE(SUM(total_ht), 0) as ca_ht,
                    COALESCE(SUM(total_mo_ht), 0) as mo_ht,
                    COALESCE(SUM(total_pieces_ht), 0) as pieces_ht,
                    COUNT(*) as nb_factures,
                    COALESCE(AVG(total_ht), 0) as avg_ticket
             FROM factures
             WHERE DATE(date_creation) BETWEEN :s AND :e
               AND atelier_id = :a AND statut != 'annulee'",
            ['s' => $from, 'e' => $to, 'a' => $atelierId]
        ) ?: ['ca_ht' => 0, 'mo_ht' => 0, 'pieces_ht' => 0, 'nb_factures' => 0, 'avg_ticket' => 0];

        // Marge MO estimée (basée sur taux horaire config)
        $config = $conn->fetchAssociative(
            "SELECT taux_horaire_mo_standard FROM config_atelier WHERE atelier_id = :a LIMIT 1",
            ['a' => $atelierId]
        );
        $tauxMo = (float) ($config['taux_horaire_mo_standard'] ?? 65);

        // Heures MO facturées vs coût théorique
        $moDetails = $conn->fetchAssociative(
            "SELECT COALESCE(SUM(total_mo_ht), 0) as mo_facture_ht,
                    COALESCE(SUM(total_mo_ht / :taux), 0) as heures_facturees,
                    COALESCE(SUM(COALESCE(r.temps_effectif_minutes, r.temps_estime, 60) / 60.0), 0) as heures_reelles
             FROM factures f
             LEFT JOIN rendez_vous r ON r.id = f.rendez_vous_id
             WHERE DATE(f.date_creation) BETWEEN :s AND :e
               AND f.atelier_id = :a AND f.statut != 'annulee'",
            ['s' => $from, 'e' => $to, 'a' => $atelierId, 'taux' => $tauxMo]
        ) ?: ['mo_facture_ht' => 0, 'heures_facturees' => 0, 'heures_reelles' => 0];

        return $this->json([
            'period' => ['from' => $from, 'to' => $to],
            'global' => [
                'ca_ht' => round((float) $global['ca_ht'], 2),
                'mo_ht' => round((float) $global['mo_ht'], 2),
                'pieces_ht' => round((float) $global['pieces_ht'], 2),
                'nb_factures' => (int) $global['nb_factures'],
                'avg_ticket' => round((float) $global['avg_ticket'], 2),
                'taux_mo_pct' => $global['ca_ht'] > 0 ? round($global['mo_ht'] / $global['ca_ht'] * 100, 1) : 0,
            ],
            'mo_analysis' => [
                'mo_facture_ht' => round((float) $moDetails['mo_facture_ht'], 2),
                'heures_facturees' => round((float) $moDetails['heures_facturees'], 1),
                'heures_reelles' => round((float) $moDetails['heures_reelles'], 1),
                'productivite_pct' => $moDetails['heures_reelles'] > 0
                    ? round($moDetails['heures_facturees'] / $moDetails['heures_reelles'] * 100, 1)
                    : 0,
                'taux_horaire_config' => $tauxMo,
            ],
            'par_type' => array_map(fn($row) => [
                'type' => $row['type'],
                'nb_rdvs' => (int) $row['nb_rdvs'],
                'ca_ht' => round((float) $row['ca_ht'], 2),
                'mo_ht' => round((float) $row['mo_ht'], 2),
                'pieces_ht' => round((float) $row['pieces_ht'], 2),
                'avg_ticket' => round((float) $row['avg_ticket'], 2),
                'taux_mo_pct' => $row['ca_ht'] > 0 ? round($row['mo_ht'] / $row['ca_ht'] * 100, 1) : 0,
            ], $parType),
        ]);
    }
}
