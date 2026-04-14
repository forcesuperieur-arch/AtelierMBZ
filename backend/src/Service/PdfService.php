<?php
namespace App\Service;

use App\Entity\Facture;
use App\Entity\OrdreReparation;
use App\Entity\Devis;
use Dompdf\Dompdf;
use Dompdf\Options;
use Twig\Environment;

/**
 * Generates PDF documents (OR, Facture, Devis) using DomPDF + Twig templates.
 */
class PdfService
{
    public function __construct(
        private Environment $twig,
        private string $projectDir,
    ) {}

    /**
     * Generate an OR PDF.
     */
    public function generateOrPdf(OrdreReparation $or): string
    {
        $html = $this->twig->render('pdf/ordre_reparation.html.twig', [
            'or' => $or,
            'rdv' => $or->getRendezVous(),
        ]);

        return $this->renderPdf($html, 'OR-' . $or->getNumeroOr());
    }

    /**
     * Generate an invoice PDF.
     */
    public function generateFacturePdf(Facture $facture): string
    {
        $html = $this->twig->render('pdf/facture.html.twig', [
            'facture' => $facture,
        ]);

        return $this->renderPdf($html, 'FAC-' . $facture->getNumeroFacture());
    }

    /**
     * Generate a quote PDF.
     */
    public function generateDevisPdf(Devis $devis): string
    {
        $html = $this->twig->render('pdf/devis.html.twig', [
            'devis' => $devis,
        ]);

        return $this->renderPdf($html, 'DEV-' . $devis->getNumeroDevis());
    }

    /**
     * Render HTML to PDF and return file path.
     */
    private function renderPdf(string $html, string $filename): string
    {
        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $outputDir = $this->projectDir . '/var/pdf';
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $filePath = $outputDir . '/' . $filename . '.pdf';
        file_put_contents($filePath, $dompdf->output());

        return $filePath;
    }
}
