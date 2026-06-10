<?php

namespace App\Controller;

use App\Service\AnalyticsExportService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/analytics')]
class AnalyticsController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em) {}

    private function assertStatsAccess(): void
    {
        if ($this->isGranted('ROLE_SUPER_ADMIN')) return;
        $roleMetierCode = $this->getUser()?->getRoleMetier()?->getCode();
        if (in_array($roleMetierCode, ['responsable_atelier', 'responsable_magasin'], true)) return;
        if ($this->isGranted('ROLE_ADMIN') && $roleMetierCode === null) return;
        throw $this->createAccessDeniedException('La page Stat est réservée au responsable atelier et aux profils supérieurs.');
    }

    private function resolveDateRange(Request $request): array
    {
        $today = new \DateTimeImmutable('today');
        $fromInput = $request->query->get('from');
        $toInput = $request->query->get('to');
        $periodKey = '30d';

        if ($fromInput && $toInput) {
            try {
                $fromDate = new \DateTimeImmutable((string) $fromInput);
                $toDate = new \DateTimeImmutable((string) $toInput);
                if ($fromDate > $toDate) {
                    [$fromDate, $toDate] = [$toDate, $fromDate];
                }
                return [$fromDate, $toDate, 'custom'];
            } catch (\Throwable) {
            }
        }

        $periodKey = (string) $request->query->get('period', '30d');
        $days = match ($periodKey) {
            '7d' => 7,
            '30d' => 30,
            '90d' => 90,
            'ytd' => (int) $today->format('z') + 1,
            default => 30,
        };

        $toDate = $today;
        $fromDate = $toDate->modify(sprintf('-%d days', $days - 1));

        return [$fromDate, $toDate, $periodKey];
    }

    private function compareMetric(float $current, float $previous): array
    {
        if ($previous == 0) {
            return ['current' => $current, 'previous' => $previous, 'delta_percent' => $current > 0 ? 100 : 0, 'trend' => $current > 0 ? 'up' : 'flat'];
        }
        $delta = (($current - $previous) / $previous) * 100;
        return [
            'current' => round($current, 2),
            'previous' => round($previous, 2),
            'delta_percent' => round($delta, 1),
            'trend' => $delta > 0 ? 'up' : ($delta < 0 ? 'down' : 'flat'),
        ];
    }

    #[Route('/dashboard', methods: ['GET'])]
    public function dashboard(Request $request): JsonResponse
    {
        $this->assertStatsAccess();
        $atelierId = $this->getUser()?->getAtelierId();
        $conn = $this->em->getConnection();

        [$fromDate, $toDate, $periodKey] = $this->resolveDateRange($request);
        $from = $fromDate->format('Y-m-d');
        $to = $toDate->format('Y-m-d');
        $days = max(1, (int) $fromDate->diff($toDate)->days + 1);

        $prevFromDate = (clone $fromDate)->modify(sprintf('-%d days', $days));
        $prevToDate = (clone $fromDate)->modify('-1 day');
        $prevFrom = $prevFromDate->format('Y-m-d');
        $prevTo = $prevToDate->format('Y-m-d');

        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
        $weekStart = (new \DateTimeImmutable('monday this week'))->format('Y-m-d');
        $weekEnd = (new \DateTimeImmutable('sunday this week'))->format('Y-m-d');
        $monthStart = (new \DateTimeImmutable('first day of this month'))->format('Y-m-d');
        $monthEnd = (new \DateTimeImmutable('last day of this month'))->format('Y-m-d');

        // ── CURRENT PERIOD from analytics facts ──
        $currentStats = $conn->fetchAssociative(
            "SELECT COUNT(*) as nb_rdvs,
                    COUNT(*) FILTER (WHERE statut_rdv IN ('termine','restitue','facture','paye')) as completed,
                    COALESCE(SUM(ca_ht), 0) as ca_ht,
                    COALESCE(SUM(ca_mo_ht), 0) as mo_ht,
                    COALESCE(SUM(ca_pieces_ht), 0) as pieces_ht,
                    COUNT(*) FILTER (WHERE is_facture = true) as nb_factures,
                    COALESCE(AVG(ca_ht) FILTER (WHERE ca_ht IS NOT NULL), 0) as avg_ticket,
                    COALESCE(SUM(temps_estime), 0) as planned_min,
                    COALESCE(SUM(temps_effectif), 0) as actual_min
             FROM analytics_rdv_facts
             WHERE atelier_id = :a AND date_rdv BETWEEN :s AND :e",
            ['a' => $atelierId, 's' => $from, 'e' => $to]
        ) ?: [];

        // ── PREVIOUS PERIOD from analytics facts ──
        $prevStats = $conn->fetchAssociative(
            "SELECT COUNT(*) as nb_rdvs,
                    COUNT(*) FILTER (WHERE statut_rdv IN ('termine','restitue','facture','paye')) as completed,
                    COALESCE(SUM(ca_ht), 0) as ca_ht,
                    COALESCE(SUM(ca_mo_ht), 0) as mo_ht,
                    COALESCE(SUM(ca_pieces_ht), 0) as pieces_ht,
                    COUNT(*) FILTER (WHERE is_facture = true) as nb_factures,
                    COALESCE(AVG(ca_ht) FILTER (WHERE ca_ht IS NOT NULL), 0) as avg_ticket,
                    COALESCE(SUM(temps_estime), 0) as planned_min,
                    COALESCE(SUM(temps_effectif), 0) as actual_min
             FROM analytics_rdv_facts
             WHERE atelier_id = :a AND date_rdv BETWEEN :s AND :e",
            ['a' => $atelierId, 's' => $prevFrom, 'e' => $prevTo]
        ) ?: [];

        // ── REALTIME / FIXED PERIOD from source tables ──
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
            "SELECT COUNT(*) FROM rendez_vous
             WHERE date_rdv BETWEEN :s AND :e
               AND atelier_id = :a
               AND statut IN ('termine', 'restitue', 'facture', 'paye')",
            ['s' => $from, 'e' => $to, 'a' => $atelierId]
        );

        $rdvsEnCours = $conn->fetchOne(
            "SELECT COUNT(*) FROM rendez_vous
             WHERE atelier_id = :a AND statut IN ('reception', 'en_cours')",
            ['a' => $atelierId]
        );

        $plannedMinutesToday = $conn->fetchOne(
            "SELECT COALESCE(SUM(COALESCE(temps_estime, 60)), 0)
             FROM rendez_vous
             WHERE date_rdv = :d AND atelier_id = :a AND statut != :s",
            ['d' => $today, 'a' => $atelierId, 's' => 'annule']
        );

        // ── TRENDS & BREAKDOWNS from analytics ──
        $dailyTrend = $conn->fetchAllAssociative(
            "SELECT snapshot_date as date, nb_rdv_total as rdvs, ca_du_jour_ht as revenue, charge_planifiee_minutes as minutes
             FROM analytics_daily_snapshots
             WHERE atelier_id = :a AND snapshot_date BETWEEN :s AND :e
             ORDER BY snapshot_date ASC",
            ['a' => $atelierId, 's' => $from, 'e' => $to]
        );

        $topServices = $conn->fetchAllAssociative(
            "SELECT type_intervention as label, COUNT(*) as count,
                    COALESCE(SUM(temps_estime), 0) as minutes,
                    COALESCE(SUM(ca_ht), 0) as revenue
             FROM analytics_rdv_facts
             WHERE atelier_id = :a AND date_rdv BETWEEN :s AND :e
             GROUP BY type_intervention
             ORDER BY revenue DESC, count DESC
             LIMIT 8",
            ['a' => $atelierId, 's' => $from, 'e' => $to]
        );

        $activeByStatus = $conn->fetchAllAssociative(
            "SELECT statut_rdv as statut, COUNT(*) as count
             FROM analytics_rdv_facts
             WHERE atelier_id = :a AND date_rdv BETWEEN :s AND :e
             GROUP BY statut_rdv",
            ['a' => $atelierId, 's' => $from, 'e' => $to]
        );

        $mecaPerf = $conn->fetchAllAssociative(
            "SELECT mecanicien_id as id, mecanicien_nom as nom,
                    COUNT(*) as nb_rdvs,
                    COALESCE(SUM(temps_effectif), 0) as total_minutes,
                    COALESCE(AVG(temps_effectif) FILTER (WHERE temps_effectif IS NOT NULL), 0) as avg_minutes,
                    COALESCE(SUM(temps_estime), 0) as planned_minutes,
                    COALESCE(SUM(ca_ht), 0) as ca_genere
             FROM analytics_rdv_facts
             WHERE atelier_id = :a AND date_rdv BETWEEN :s AND :e AND mecanicien_id IS NOT NULL
             GROUP BY mecanicien_id, mecanicien_nom
             ORDER BY ca_genere DESC",
            ['a' => $atelierId, 's' => $from, 'e' => $to]
        );

        $segments = $conn->fetchAllAssociative(
            "SELECT client_segment as segment, COUNT(DISTINCT client_id) as clients, SUM(ca_ht) as ca
             FROM analytics_rdv_facts
             WHERE atelier_id = :a AND date_rdv BETWEEN :s AND :e AND client_segment IS NOT NULL
             GROUP BY client_segment",
            ['a' => $atelierId, 's' => $from, 'e' => $to]
        );

        // ── Revenue mix ──
        $revenueMix = [
            'mo_ht' => round((float) ($currentStats['mo_ht'] ?? 0), 2),
            'pieces_ht' => round((float) ($currentStats['pieces_ht'] ?? 0), 2),
            'total_ht' => round((float) ($currentStats['ca_ht'] ?? 0), 2),
            'total_ttc' => round((float) ($currentStats['ca_ht'] ?? 0) * 1.2, 2), // approx
            'nb_factures' => (int) ($currentStats['nb_factures'] ?? 0),
        ];

        // ── Comparison ──
        $comparison = [
            'rdvs' => $this->compareMetric((float) ($currentStats['nb_rdvs'] ?? 0), (float) ($prevStats['nb_rdvs'] ?? 0)),
            'ca' => $this->compareMetric((float) ($currentStats['ca_ht'] ?? 0), (float) ($prevStats['ca_ht'] ?? 0)),
            'avg_ticket' => $this->compareMetric((float) ($currentStats['avg_ticket'] ?? 0), (float) ($prevStats['avg_ticket'] ?? 0)),
            'planned_minutes' => $this->compareMetric((float) ($currentStats['planned_min'] ?? 0), (float) ($prevStats['planned_min'] ?? 0)),
            'completed' => $this->compareMetric((float) ($currentStats['completed'] ?? 0), (float) ($prevStats['completed'] ?? 0)),
            'occupation' => $this->compareMetric(
                (float) ($currentStats['actual_min'] ?? 0) / max(1, (float) ($currentStats['planned_min'] ?? 1)) * 100,
                (float) ($prevStats['actual_min'] ?? 0) / max(1, (float) ($prevStats['planned_min'] ?? 1)) * 100
            ),
        ];

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
            'revenue_mix' => $revenueMix,
            'comparison' => $comparison,
            'mecaniciens' => $mecaPerf,
            'client_segments' => $segments,
            'kpis' => [
                'rdvs' => (int) ($currentStats['nb_rdvs'] ?? 0),
                'completed' => (int) ($currentStats['completed'] ?? 0),
                'ca_ht' => round((float) ($currentStats['ca_ht'] ?? 0), 2),
                'mo_ht' => round((float) ($currentStats['mo_ht'] ?? 0), 2),
                'pieces_ht' => round((float) ($currentStats['pieces_ht'] ?? 0), 2),
                'nb_factures' => (int) ($currentStats['nb_factures'] ?? 0),
                'avg_ticket' => round((float) ($currentStats['avg_ticket'] ?? 0), 2),
                'planned_minutes' => (int) ($currentStats['planned_min'] ?? 0),
                'actual_minutes' => (int) ($currentStats['actual_min'] ?? 0),
            ],
        ]);
    }

    #[Route('/explore', methods: ['GET'])]
    public function explore(Request $request): JsonResponse
    {
        $this->assertStatsAccess();
        $atelierId = $this->getUser()?->getAtelierId();
        $conn = $this->em->getConnection();

        $dimension = $request->query->get('dimension', 'type_intervention');
        $from = $request->query->get('from', date('Y-m-d', strtotime('-29 days')));
        $to = $request->query->get('to', date('Y-m-d'));

        $allowedDimensions = ['type_intervention', 'statut_rdv', 'mecanicien_nom', 'client_segment', 'vehicule_marque', 'pont_nom'];
        $allowedMetrics = ['ca_ht', 'ca_mo_ht', 'ca_pieces_ht', 'temps_estime', 'temps_effectif', 'count'];

        if (!in_array($dimension, $allowedDimensions, true)) {
            return $this->json(['error' => 'Dimension non autorisée'], 400);
        }

        $metrics = $request->query->all('metrics');
        if (empty($metrics)) {
            $metrics = [(string) $request->query->get('metric', 'ca_ht')];
        }
        $metrics = array_values(array_filter($metrics, static fn($m) => in_array($m, $allowedMetrics, true)));
        if (empty($metrics)) {
            $metrics = ['ca_ht'];
        }

        $selectCols = ["{$dimension} as label"];
        foreach ($metrics as $m) {
            $alias = str_replace(['.', ' ', '-'], '_', $m);
            if ($m === 'count') {
                $selectCols[] = "COUNT(*)::int as {$alias}";
            } else {
                $selectCols[] = "COALESCE(SUM({$m}), 0) as {$alias}";
            }
        }
        $selectCols[] = "COUNT(*)::int as count";

        $orderCol = $metrics[0] === 'count' ? 'COUNT(*)' : "COALESCE(SUM({$metrics[0]}), 0)";

        $rows = $conn->fetchAllAssociative(
            "SELECT " . implode(', ', $selectCols) . "
             FROM analytics_rdv_facts
             WHERE atelier_id = :a AND date_rdv BETWEEN :s AND :e
             GROUP BY {$dimension}
             ORDER BY {$orderCol} DESC
             LIMIT 50",
            ['a' => $atelierId, 's' => $from, 'e' => $to]
        );

        return $this->json([
            'dimension' => $dimension,
            'metrics' => $metrics,
            'period' => ['from' => $from, 'to' => $to],
            'rows' => $rows,
        ]);
    }

    #[Route('/clientele', methods: ['GET'])]
    public function clientele(Request $request): JsonResponse
    {
        $this->assertStatsAccess();
        $atelierId = $this->getUser()?->getAtelierId();
        $conn = $this->em->getConnection();

        $annee = (int) $request->query->get('annee', date('Y'));
        $mois = $request->query->get('mois');

        $params = ['a' => $atelierId, 'y' => $annee];
        $sql = "SELECT client_id, client_segment, nb_rdv, ca_total_ht, ca_moyen_par_rdv,
                       dernier_rdv_date, jours_depuis_dernier_rdv, nb_vehicules
                FROM analytics_client_facts
                WHERE atelier_id = :a AND periode_annee = :y";

        if ($mois) {
            $sql .= " AND periode_mois = :m";
            $params['m'] = (int) $mois;
        }

        $sql .= " ORDER BY ca_total_ht DESC LIMIT 200";

        $rows = $conn->fetchAllAssociative($sql, $params);

        return $this->json([
            'annee' => $annee,
            'mois' => $mois,
            'clients' => $rows,
        ]);
    }

    #[Route('/snapshots', methods: ['GET'])]
    public function snapshots(Request $request): JsonResponse
    {
        $this->assertStatsAccess();
        $atelierId = $this->getUser()?->getAtelierId();
        $conn = $this->em->getConnection();

        $from = $request->query->get('from', date('Y-m-d', strtotime('-29 days')));
        $to = $request->query->get('to', date('Y-m-d'));

        $rows = $conn->fetchAllAssociative(
            "SELECT * FROM analytics_daily_snapshots
             WHERE atelier_id = :a AND snapshot_date BETWEEN :s AND :e
             ORDER BY snapshot_date ASC",
            ['a' => $atelierId, 's' => $from, 'e' => $to]
        );

        return $this->json(['snapshots' => $rows]);
    }

    #[Route('/alert-rules', methods: ['GET'])]
    public function listAlertRules(): JsonResponse
    {
        $this->assertStatsAccess();
        $atelierId = $this->getUser()?->getAtelierId();
        $rules = $this->em->getRepository(\App\Entity\AnalyticsAlertRule::class)
            ->findBy(['atelierId' => $atelierId], ['id' => 'ASC']);
        return $this->json(['rules' => $rules]);
    }

    #[Route('/alert-rules/{id}', methods: ['PUT'])]
    public function updateAlertRule(int $id, Request $request): JsonResponse
    {
        $this->assertStatsAccess();
        $atelierId = $this->getUser()?->getAtelierId();
        $rule = $this->em->getRepository(\App\Entity\AnalyticsAlertRule::class)->find($id);
        if (!$rule || $rule->getAtelierId() !== $atelierId) {
            return $this->json(['error' => 'Règle non trouvée'], 404);
        }
        $data = json_decode($request->getContent(), true);
        if (isset($data['isActive'])) $rule->setIsActive((bool) $data['isActive']);
        if (isset($data['thresholdValue'])) $rule->setThresholdValue((string) $data['thresholdValue']);
        if (isset($data['cooldownMinutes'])) $rule->setCooldownMinutes((int) $data['cooldownMinutes']);
        $this->em->flush();
        return $this->json(['rule' => $rule]);
    }

    #[Route('/export/pdf', methods: ['GET'])]
    public function exportPdf(Request $request, AnalyticsExportService $exportService): BinaryFileResponse
    {
        $this->assertStatsAccess();
        $atelierId = $this->getUser()?->getAtelierId();
        $from = $request->query->get('from', date('Y-m-d', strtotime('-29 days')));
        $to = $request->query->get('to', date('Y-m-d'));
        $path = $exportService->generatePdf($this->em->getConnection(), $atelierId, $from, $to);
        $response = new BinaryFileResponse($path);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, 'rapport_analytics_' . $from . '_' . $to . '.pdf');
        $response->deleteFileAfterSend(true);
        return $response;
    }

    #[Route('/export/excel', methods: ['GET'])]
    public function exportExcel(Request $request, AnalyticsExportService $exportService): BinaryFileResponse
    {
        $this->assertStatsAccess();
        $atelierId = $this->getUser()?->getAtelierId();
        $from = $request->query->get('from', date('Y-m-d', strtotime('-29 days')));
        $to = $request->query->get('to', date('Y-m-d'));
        $path = $exportService->generateExcel($this->em->getConnection(), $atelierId, $from, $to);
        $response = new BinaryFileResponse($path);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, 'rapport_analytics_' . $from . '_' . $to . '.xlsx');
        $response->deleteFileAfterSend(true);
        return $response;
    }

    #[Route('/alert-rules/init', methods: ['POST'])]
    public function initAlertRules(): JsonResponse
    {
        $this->assertStatsAccess();
        $atelierId = $this->getUser()?->getAtelierId();
        $repo = $this->em->getRepository(\App\Entity\AnalyticsAlertRule::class);
        $existing = $repo->findBy(['atelierId' => $atelierId]);
        if (count($existing) > 0) {
            return $this->json(['message' => 'Règles déjà initialisées'], 400);
        }

        $defaults = [
            ['name' => 'Occupation ponts trop faible', 'metric' => 'occupation_ponts_pct', 'operator' => '<', 'thresholdValue' => '30', 'severity' => 'warning', 'cooldownMinutes' => 120],
            ['name' => 'Trop de retards dépassement', 'metric' => 'nb_retards_depassement', 'operator' => '>', 'thresholdValue' => '3', 'severity' => 'warning', 'cooldownMinutes' => 60],
            ['name' => 'Rendement global insuffisant', 'metric' => 'rendement_global', 'operator' => '<', 'thresholdValue' => '70', 'severity' => 'critical', 'cooldownMinutes' => 240],
            ['name' => 'CA du jour faible', 'metric' => 'ca_du_jour_ht', 'operator' => '<', 'thresholdValue' => '500', 'severity' => 'warning', 'cooldownMinutes' => 360],
            ['name' => 'Attente restitution élevée', 'metric' => 'nb_attente_restitution', 'operator' => '>', 'thresholdValue' => '5', 'severity' => 'warning', 'cooldownMinutes' => 120],
        ];

        foreach ($defaults as $d) {
            $rule = new \App\Entity\AnalyticsAlertRule();
            $rule->setAtelierId($atelierId);
            $rule->setName($d['name']);
            $rule->setMetric($d['metric']);
            $rule->setOperator($d['operator']);
            $rule->setThresholdValue($d['thresholdValue']);
            $rule->setSeverity($d['severity']);
            $rule->setCooldownMinutes($d['cooldownMinutes']);
            $this->em->persist($rule);
        }
        $this->em->flush();

        return $this->json(['message' => 'Règles initialisées', 'count' => count($defaults)]);
    }

    #[Route('/forecast', methods: ['GET'])]
    public function forecast(Request $request): JsonResponse
    {
        $this->assertStatsAccess();
        $atelierId = $this->getUser()?->getAtelierId();
        $conn = $this->em->getConnection();

        $forecastDays = min(90, max(1, (int) $request->query->get('days', 14)));
        $historyDays = min(180, max(14, (int) $request->query->get('history', 30)));

        $from = (new \DateTimeImmutable())->modify("-{$historyDays} days")->format('Y-m-d');
        $to = (new \DateTimeImmutable())->format('Y-m-d');

        $rows = $conn->fetchAllAssociative(
            "SELECT snapshot_date, ca_du_jour_ht, ca_mo_ht, ca_pieces_ht
             FROM analytics_daily_snapshots
             WHERE atelier_id = :a AND snapshot_date BETWEEN :s AND :e
             ORDER BY snapshot_date ASC",
            ['a' => $atelierId, 's' => $from, 'e' => $to]
        );

        $historical = array_map(fn($r) => [
            'date' => $r['snapshot_date'],
            'ca_ht' => (float) $r['ca_du_jour_ht'],
            'ca_mo_ht' => (float) $r['ca_mo_ht'],
            'ca_pieces_ht' => (float) $r['ca_pieces_ht'],
        ], $rows);

        $forecast = [];
        if (count($historical) >= 7) {
            $forecast = $this->computeForecast($historical, $forecastDays);
        }

        return $this->json([
            'historical' => $historical,
            'forecast' => $forecast,
            'days' => $forecastDays,
            'history_days' => $historyDays,
        ]);
    }

    private function computeForecast(array $historical, int $days): array
    {
        $metrics = ['ca_ht', 'ca_mo_ht', 'ca_pieces_ht'];
        $forecast = [];

        foreach ($metrics as $metric) {
            $values = array_column($historical, $metric);
            $n = count($values);
            if ($n < 2) continue;

            // Linear regression (least squares)
            $sumX = $sumY = $sumXY = $sumX2 = 0;
            for ($i = 0; $i < $n; $i++) {
                $x = $i;
                $y = $values[$i];
                $sumX += $x;
                $sumY += $y;
                $sumXY += $x * $y;
                $sumX2 += $x * $x;
            }
            $denom = ($n * $sumX2) - ($sumX * $sumX);
            if ($denom == 0) continue;
            $slope = (($n * $sumXY) - ($sumX * $sumY)) / $denom;
            $intercept = ($sumY - $slope * $sumX) / $n;

            // Seasonal factor (dow average / global average)
            $dowSums = array_fill(0, 7, 0);
            $dowCounts = array_fill(0, 7, 0);
            for ($i = 0; $i < $n; $i++) {
                $date = new \DateTimeImmutable($historical[$i]['date']);
                $dow = (int) $date->format('w');
                $dowSums[$dow] += $values[$i];
                $dowCounts[$dow]++;
            }
            $globalAvg = $sumY / $n;
            $seasonal = [];
            for ($d = 0; $d < 7; $d++) {
                $seasonal[$d] = $dowCounts[$d] > 0 ? ($dowSums[$d] / $dowCounts[$d]) / $globalAvg : 1.0;
            }

            // Compute std dev for confidence band
            $mean = $sumY / $n;
            $variance = 0;
            for ($i = 0; $i < $n; $i++) {
                $variance += pow($values[$i] - $mean, 2);
            }
            $stdDev = sqrt($variance / $n);

            $lastDate = new \DateTimeImmutable($historical[$n - 1]['date']);
            for ($i = 1; $i <= $days; $i++) {
                $predDate = $lastDate->modify("+{$i} days");
                $dow = (int) $predDate->format('w');
                $trendValue = $intercept + $slope * ($n - 1 + $i);
                $predicted = max(0, $trendValue * $seasonal[$dow]);

                $forecast[$i - 1][$metric] = round($predicted, 2);
                $forecast[$i - 1][$metric . '_lower'] = round(max(0, $predicted - $stdDev), 2);
                $forecast[$i - 1][$metric . '_upper'] = round($predicted + $stdDev, 2);
                $forecast[$i - 1]['date'] = $predDate->format('Y-m-d');
            }
        }

        return $forecast;
    }
}
