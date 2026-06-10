<?php

declare(strict_types=1);

namespace App\Service;

use Doctrine\DBAL\Connection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Twig\Environment;

class AnalyticsExportService
{
    public function __construct(
        private PdfService $pdfService,
        private Environment $twig,
    ) {}

    public function generateExcel(Connection $conn, int $atelierId, string $from, string $to): string
    {
        $spreadsheet = new Spreadsheet();

        // Sheet 1: KPIs
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('KPIs');
        $kpi = $conn->fetchAssociative(
            "SELECT COUNT(*) as nb_rdvs, SUM(ca_du_jour_ht) as ca_ht, SUM(ca_mo_ht) as ca_mo_ht, SUM(ca_pieces_ht) as ca_pieces_ht, AVG(panier_moyen) as panier_moyen
             FROM analytics_daily_snapshots
             WHERE atelier_id = :a AND snapshot_date BETWEEN :s AND :e",
            ['a' => $atelierId, 's' => $from, 'e' => $to]
        );
        $sheet->setCellValue('A1', 'Période');
        $sheet->setCellValue('B1', $from . ' → ' . $to);
        $sheet->setCellValue('A2', 'RDVs');
        $sheet->setCellValue('B2', $kpi['nb_rdvs'] ?? 0);
        $sheet->setCellValue('A3', 'CA HT');
        $sheet->setCellValue('B3', (float) ($kpi['ca_ht'] ?? 0));
        $sheet->setCellValue('A4', 'CA MO HT');
        $sheet->setCellValue('B4', (float) ($kpi['ca_mo_ht'] ?? 0));
        $sheet->setCellValue('A5', 'CA Pièces HT');
        $sheet->setCellValue('B5', (float) ($kpi['ca_pieces_ht'] ?? 0));
        $sheet->setCellValue('A6', 'Panier moyen');
        $sheet->setCellValue('B6', round((float) ($kpi['panier_moyen'] ?? 0), 2));

        // Sheet 2: Tendance journalière
        $sheet2 = $spreadsheet->createSheet();
        $sheet2->setTitle('Tendance');
        $trend = $conn->fetchAllAssociative(
            "SELECT snapshot_date, nb_rdv_total, ca_du_jour_ht, ca_mo_ht, ca_pieces_ht
             FROM analytics_daily_snapshots
             WHERE atelier_id = :a AND snapshot_date BETWEEN :s AND :e
             ORDER BY snapshot_date ASC",
            ['a' => $atelierId, 's' => $from, 'e' => $to]
        );
        $sheet2->fromArray(['Date', 'RDVs', 'CA HT', 'CA MO HT', 'CA Pièces HT'], null, 'A1');
        $row = 2;
        foreach ($trend as $r) {
            $sheet2->fromArray([
                $r['snapshot_date'],
                (int) $r['nb_rdv_total'],
                (float) $r['ca_du_jour_ht'],
                (float) $r['ca_mo_ht'],
                (float) $r['ca_pieces_ht'],
            ], null, "A{$row}");
            $row++;
        }

        // Sheet 3: Mécaniciens
        $sheet3 = $spreadsheet->createSheet();
        $sheet3->setTitle('Mécaniciens');
        $meca = $conn->fetchAllAssociative(
            "SELECT mecanicien_nom, COUNT(*) as nb_rdvs, SUM(ca_ht) as ca_genere, SUM(temps_effectif) as total_minutes
             FROM analytics_rdv_facts
             WHERE atelier_id = :a AND date_rdv BETWEEN :s AND :e AND mecanicien_nom IS NOT NULL
             GROUP BY mecanicien_nom
             ORDER BY ca_genere DESC",
            ['a' => $atelierId, 's' => $from, 'e' => $to]
        );
        $sheet3->fromArray(['Mécanicien', 'RDVs', 'CA généré', 'Minutes'], null, 'A1');
        $row = 2;
        foreach ($meca as $r) {
            $sheet3->fromArray([
                $r['mecanicien_nom'],
                (int) $r['nb_rdvs'],
                (float) $r['ca_genere'],
                (int) $r['total_minutes'],
            ], null, "A{$row}");
            $row++;
        }

        // Sheet 4: Services
        $sheet4 = $spreadsheet->createSheet();
        $sheet4->setTitle('Services');
        $services = $conn->fetchAllAssociative(
            "SELECT type_intervention, COUNT(*) as count, SUM(ca_ht) as revenue, SUM(temps_estime) as minutes
             FROM analytics_rdv_facts
             WHERE atelier_id = :a AND date_rdv BETWEEN :s AND :e AND type_intervention IS NOT NULL
             GROUP BY type_intervention
             ORDER BY revenue DESC",
            ['a' => $atelierId, 's' => $from, 'e' => $to]
        );
        $sheet4->fromArray(['Type', 'Count', 'Revenue', 'Minutes'], null, 'A1');
        $row = 2;
        foreach ($services as $r) {
            $sheet4->fromArray([
                $r['type_intervention'],
                (int) $r['count'],
                (float) $r['revenue'],
                (int) $r['minutes'],
            ], null, "A{$row}");
            $row++;
        }

        $writer = new Xlsx($spreadsheet);
        $path = sys_get_temp_dir() . '/analytics_export_' . uniqid() . '.xlsx';
        $writer->save($path);
        return $path;
    }

    public function generatePdf(Connection $conn, int $atelierId, string $from, string $to): string
    {
        $kpi = $conn->fetchAssociative(
            "SELECT COUNT(*) as nb_rdvs, SUM(ca_du_jour_ht) as ca_ht, SUM(ca_mo_ht) as ca_mo_ht, SUM(ca_pieces_ht) as ca_pieces_ht
             FROM analytics_daily_snapshots
             WHERE atelier_id = :a AND snapshot_date BETWEEN :s AND :e",
            ['a' => $atelierId, 's' => $from, 'e' => $to]
        );
        $trend = $conn->fetchAllAssociative(
            "SELECT snapshot_date, ca_du_jour_ht FROM analytics_daily_snapshots
             WHERE atelier_id = :a AND snapshot_date BETWEEN :s AND :e ORDER BY snapshot_date ASC",
            ['a' => $atelierId, 's' => $from, 'e' => $to]
        );
        $topServices = $conn->fetchAllAssociative(
            "SELECT type_intervention, COUNT(*) as count, SUM(ca_ht) as revenue
             FROM analytics_rdv_facts
             WHERE atelier_id = :a AND date_rdv BETWEEN :s AND :e AND type_intervention IS NOT NULL
             GROUP BY type_intervention ORDER BY revenue DESC LIMIT 10",
            ['a' => $atelierId, 's' => $from, 'e' => $to]
        );
        $mecas = $conn->fetchAllAssociative(
            "SELECT mecanicien_nom, COUNT(*) as nb_rdvs, SUM(ca_ht) as ca_genere
             FROM analytics_rdv_facts
             WHERE atelier_id = :a AND date_rdv BETWEEN :s AND :e AND mecanicien_nom IS NOT NULL
             GROUP BY mecanicien_nom ORDER BY ca_genere DESC LIMIT 10",
            ['a' => $atelierId, 's' => $from, 'e' => $to]
        );

        $html = $this->twig->render('pdf/dashboard_report.html.twig', [
            'period' => ['from' => $from, 'to' => $to],
            'kpi' => $kpi,
            'trend' => $trend,
            'topServices' => $topServices,
            'mecas' => $mecas,
        ]);

        return $this->pdfService->generateFromHtml($html, 'dashboard_report_' . uniqid());
    }
}
