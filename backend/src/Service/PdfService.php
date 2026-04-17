<?php
namespace App\Service;

use App\Entity\Atelier;
use App\Entity\Devis;
use App\Entity\Facture;
use App\Entity\OrdreReparation;
use App\Entity\VODepotVente;
use App\Entity\VOFacture;
use App\Entity\VOLivrePolice;
use App\Entity\VOPurchase;
use Doctrine\ORM\EntityManagerInterface;
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
        private EntityManagerInterface $em,
        private string $projectDir,
    ) {}

    /**
     * Generate an OR PDF.
     */
    public function generateOrPdf(OrdreReparation $or): string
    {
        $atelier = $this->resolveAtelier($or->getRendezVous()?->getAtelierId());

        $html = $this->twig->render('pdf/ordre_reparation.html.twig', [
            'or' => $or,
            'rdv' => $or->getRendezVous(),
            ...$this->buildBrandingContext($atelier),
        ]);

        return $this->renderPdf($html, 'OR-' . $or->getNumeroOr());
    }

    /**
     * Generate an invoice PDF.
     */
    public function generateFacturePdf(Facture $facture): string
    {
        $atelier = $this->resolveAtelier($facture->getAtelierId());

        $html = $this->twig->render('pdf/facture.html.twig', [
            'facture' => $facture,
            ...$this->buildBrandingContext($atelier),
        ]);

        return $this->renderPdf($html, 'FAC-' . $facture->getNumeroFacture());
    }

    /**
     * Generate a quote PDF.
     */
    public function generateDevisPdf(Devis $devis): string
    {
        $atelier = $this->resolveAtelier($devis->getAtelierId());

        $html = $this->twig->render('pdf/devis.html.twig', [
            'devis' => $devis,
            ...$this->buildBrandingContext($atelier),
        ]);

        return $this->renderPdf($html, 'DEV-' . $devis->getNumeroDevis());
    }

    /**
     * Generate VO invoice PDF.
     */
    public function generateVoFacturePdf(VOFacture $facture): string
    {
        $atelier = $this->resolveAtelier($facture->getAtelierId());

        $html = $this->twig->render('pdf/vo_facture.html.twig', [
            'facture' => $facture,
            ...$this->buildBrandingContext($atelier),
        ]);

        return $this->renderPdf($html, 'VOF-' . $facture->getNumeroFacture());
    }

    /**
     * Generate Livre de Police extract PDF.
     * @param VOLivrePolice[] $entries
     */
    public function generateLivrePolicePdf(array $entries, ?int $atelierId = null): string
    {
        $atelier = $this->resolveAtelier($atelierId);

        $html = $this->twig->render('pdf/vo_livre_police.html.twig', [
            'entries' => $entries,
            ...$this->buildBrandingContext($atelier),
        ]);

        return $this->renderPdf($html, 'LP-' . date('Ymd-His'));
    }

    /**
     * Generate contrat de dépôt-vente PDF.
     */
    public function generateContratDepotVentePdf(VODepotVente $depot): string
    {
        $atelier = $this->resolveAtelier($depot->getAtelierId());

        $html = $this->twig->render('pdf/vo_contrat_depot_vente.html.twig', [
            'depot' => $depot,
            'companion_signature' => $depot->getCompanionSignatureData(),
            ...$this->buildBrandingContext($atelier),
        ]);

        return $this->renderPdf($html, 'CDV-' . $depot->getId());
    }

    /**
     * Generate PV de rachat (purchase certificate) PDF.
     */
    public function generatePvRachatPdf(VOPurchase $purchase): string
    {
        $atelier = $this->resolveAtelier($purchase->getAtelierId());

        $html = $this->twig->render('pdf/vo_pv_rachat.html.twig', [
            'purchase' => $purchase,
            'companion_signature' => $purchase->getCompanionSignatureData(),
            ...$this->buildBrandingContext($atelier),
        ]);

        return $this->renderPdf($html, 'PVR-' . $purchase->getId());
    }

    private function buildBrandingContext(?Atelier $atelier): array
    {
        return [
            'atelier' => $atelier,
            'logo_data_uri' => $this->resolveLogoDataUri($atelier),
        ];
    }

    private function resolveAtelier(?int $atelierId = null): ?Atelier
    {
        if ($atelierId) {
            $atelier = $this->em->getRepository(Atelier::class)->find($atelierId);
            if ($atelier) {
                return $atelier;
            }
        }

        return $this->em->getRepository(Atelier::class)->findOneBy([]);
    }

    private function resolveLogoDataUri(?Atelier $atelier): ?string
    {
        $logoUrl = $atelier?->getLogoUrl();
        if (!$logoUrl) {
            return null;
        }

        $relativePath = parse_url($logoUrl, PHP_URL_PATH) ?: $logoUrl;
        $filePath = $this->projectDir . '/public' . $relativePath;

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
