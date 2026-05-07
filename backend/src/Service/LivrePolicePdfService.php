<?php

namespace App\Service;

use App\Entity\Atelier;
use App\Entity\VOLivrePolice;
use Doctrine\ORM\EntityManagerInterface;
use Dompdf\Dompdf;
use Dompdf\Options;
use Twig\Environment;

/**
 * [LOT-0] Génération de l'export PDF portrait du Livre de Police.
 * Utilisé pour contrôle des autorités (Art. 321-7 CP).
 */
class LivrePolicePdfService
{
    public function __construct(
        private Environment $twig,
        private EntityManagerInterface $em,
        private string $projectDir,
    ) {}

    /**
     * @param VOLivrePolice[]       $entries
     * @param array<string, string> $filters
     */
    public function generateExportPdf(array $entries, ?int $atelierId, array $filters = []): string
    {
        ini_set('memory_limit', '512M');

        $atelier = $atelierId ? $this->em->getRepository(Atelier::class)->find($atelierId) : null;
        $exportDate = new \DateTime();

        $stats = [
            'total' => count($entries),
            'hashed' => count(array_filter($entries, static fn (VOLivrePolice $e): bool => $e->getIntegrityHash() !== null)),
        ];

        $globalHash = $this->computeGlobalHash($entries, $filters, $exportDate);

        $html = $this->twig->render('livre_police/export.html.twig', [
            'entries' => $entries,
            'atelier' => $atelier,
            'logo_data_uri' => $this->resolveLogoDataUri($atelier),
            'paddock_logo_data_uri' => $this->resolvePaddockLogoDataUri(),
            'paddock_brand' => 'Paddock',
            'filters' => $filters,
            'exportDate' => $exportDate,
            'globalHash' => $globalHash,
            'stats' => $stats,
        ]);

        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $outputDir = $this->projectDir . '/var/pdf';
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0o755, true);
        }

        $filePath = $outputDir . '/LPE-' . date('Ymd-His') . '.pdf';
        file_put_contents($filePath, $dompdf->output());

        return $filePath;
    }

    /**
     * Hash global du document (entrées + filtres + date d'export).
     */
    private function computeGlobalHash(array $entries, array $filters, \DateTimeInterface $exportDate): string
    {
        $parts = [];
        foreach ($entries as $entry) {
            $parts[] = $entry->getIntegrityHash() ?? 'pending-' . $entry->getId();
        }
        $parts[] = json_encode($filters, JSON_THROW_ON_ERROR);
        $parts[] = $exportDate->format('c');

        return hash('sha256', implode('|', $parts));
    }

    private function resolvePaddockLogoDataUri(): ?string
    {
        return $this->fileToDataUri($this->projectDir . '/assets/branding/paddock-logo-pdf.png');
    }

    private function resolveLogoDataUri(?Atelier $atelier): ?string
    {
        $logoUrl = $atelier?->getLogoUrl();
        if (!$logoUrl) {
            return null;
        }

        $relativePath = parse_url($logoUrl, PHP_URL_PATH) ?: $logoUrl;
        $filePath = $this->projectDir . '/public' . $relativePath;

        return $this->fileToDataUri($filePath);
    }

    private function fileToDataUri(string $filePath): ?string
    {
        if (!is_file($filePath) || !is_readable($filePath)) {
            return null;
        }

        $contents = file_get_contents($filePath);
        if ($contents === false) {
            return null;
        }

        $mimeType = mime_content_type($filePath) ?: 'image/png';

        return sprintf('data:%s;base64,%s', $mimeType, base64_encode($contents));
    }
}
