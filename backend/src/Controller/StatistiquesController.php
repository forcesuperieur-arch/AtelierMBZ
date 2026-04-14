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
        $user = $this->getUser();
        $atelierId = $user?->getAtelierId();
        $conn = $this->em->getConnection();

        // Today's RDVs count
        $today = (new \DateTime())->format('Y-m-d');
        $rdvsToday = $conn->fetchOne(
            'SELECT COUNT(*) FROM rendez_vous WHERE date_rdv = :date AND atelier_id = :a AND statut != :s',
            ['date' => $today, 'a' => $atelierId, 's' => 'annule']
        );

        // Week's RDVs count
        $weekStart = (new \DateTime('monday this week'))->format('Y-m-d');
        $weekEnd = (new \DateTime('sunday this week'))->format('Y-m-d');
        $rdvsWeek = $conn->fetchOne(
            'SELECT COUNT(*) FROM rendez_vous WHERE date_rdv BETWEEN :s AND :e AND atelier_id = :a AND statut != :st',
            ['s' => $weekStart, 'e' => $weekEnd, 'a' => $atelierId, 'st' => 'annule']
        );

        // Month's CA (Chiffre d'Affaires)
        $monthStart = (new \DateTime('first day of this month'))->format('Y-m-d');
        $monthEnd = (new \DateTime('last day of this month'))->format('Y-m-d');
        $caMonth = $conn->fetchOne(
            'SELECT COALESCE(SUM(total_ttc), 0) FROM factures WHERE date_creation BETWEEN :s AND :e AND atelier_id = :a AND statut != :st',
            ['s' => $monthStart, 'e' => $monthEnd, 'a' => $atelierId, 'st' => 'annulee']
        );

        // Unpaid invoices
        $impayees = $conn->fetchOne(
            'SELECT COUNT(*) FROM factures WHERE statut IN (:s1, :s2) AND atelier_id = :a',
            ['s1' => 'emise', 's2' => 'partiellement_payee', 'a' => $atelierId]
        );

        // Active RDVs by status
        $activeByStatus = $conn->fetchAllAssociative(
            'SELECT statut, COUNT(*) as count FROM rendez_vous WHERE date_rdv >= :d AND atelier_id = :a GROUP BY statut',
            ['d' => $today, 'a' => $atelierId]
        );

        // Stock alerts (low stock pieces)
        $stockAlerts = $conn->fetchOne(
            'SELECT COUNT(*) FROM pieces_detachees WHERE quantite_stock <= quantite_minimale AND is_active = 1 AND atelier_id = :a',
            ['a' => $atelierId]
        );

        return $this->json([
            'rdvs_today' => (int) $rdvsToday,
            'rdvs_week' => (int) $rdvsWeek,
            'ca_month' => round((float) $caMonth, 2),
            'impayees_count' => (int) $impayees,
            'stock_alerts' => (int) $stockAlerts,
            'active_by_status' => $activeByStatus,
        ]);
    }

    /**
     * Monthly revenue breakdown.
     */
    #[Route('/ca', methods: ['GET'])]
    public function chiffreAffaires(Request $request): JsonResponse
    {
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
        $user = $this->getUser();
        $atelierId = $user?->getAtelierId();
        $conn = $this->em->getConnection();

        $monthStart = (new \DateTime('first day of this month'))->format('Y-m-d');
        $monthEnd = (new \DateTime('last day of this month'))->format('Y-m-d');

        $stats = $conn->fetchAllAssociative(
            "SELECT m.id, m.nom, m.prenom,
                    COUNT(r.id) as nb_rdvs,
                    COALESCE(SUM(r.temps_effectif_minutes), 0) as total_minutes,
                    COALESCE(AVG(r.temps_effectif_minutes), 0) as avg_minutes
             FROM mecaniciens m
             LEFT JOIN rendez_vous r ON r.mecanicien_id = m.id
                AND r.date_rdv BETWEEN :s AND :e
                AND r.statut NOT IN ('annule', 'en_attente')
             WHERE m.atelier_id = :a AND m.is_active = 1
             GROUP BY m.id, m.nom, m.prenom
             ORDER BY nb_rdvs DESC",
            ['s' => $monthStart, 'e' => $monthEnd, 'a' => $atelierId]
        );

        return $this->json($stats);
    }
}
