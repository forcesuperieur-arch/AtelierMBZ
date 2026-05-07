<?php
namespace App\Service;

use App\Entity\Atelier;
use App\Entity\ClauseLegale;
use App\Entity\Client;
use App\Entity\Devis;
use App\Entity\Facture;
use App\Entity\OrdreReparation;
use App\Entity\PhotoIntervention;
use App\Entity\RapportIntervention;
use App\Entity\RendezVous;
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
        private ClauseLegaleVisibilityService $clauseLegaleVisibilityService,
        private CerfaOverlayService $cerfaOverlayService,
    ) {}

    /**
     * Generate an OR PDF.
     */
    public function generateOrPdf(OrdreReparation $or): string
    {
        $atelier = $this->resolveAtelier($or->getRendezVous()?->getAtelierId());
        $clauses = $this->resolveOrClauses($or->getRendezVous()?->getAtelierId());
        // [SPRINT-5] I23 — Garantie travaux configurable
        $configAtelier = $or->getRendezVous()?->getAtelierId()
            ? $this->em->getRepository(\App\Entity\ConfigAtelier::class)->findOneBy(['atelierId' => $or->getRendezVous()->getAtelierId()])
            : null;

        $html = $this->twig->render('pdf/ordre_reparation.html.twig', [
            'or' => $or,
            'rdv' => $or->getRendezVous(),
            'clauses' => $clauses,
            'configAtelier' => $configAtelier,
            ...$this->buildRdvPhotoContext($or->getRendezVous()),
            ...$this->buildBrandingContext($atelier),
        ]);

        return $this->renderPdf($html, 'OR-' . $or->getNumeroOr());
    }

    /**
     * Generate a rapport d'intervention PDF.
     */
    public function generateRapportPdf(RapportIntervention $rapport, ?object $essai = null): string
    {
        $rdv = $rapport->getRendezVous();
        $atelier = $this->resolveAtelier($rapport->getAtelierId());

        $html = $this->twig->render('pdf/rapport_intervention.html.twig', [
            'rapport' => $rapport,
            'rdv' => $rdv,
            'essai' => $essai,
            'client' => $rdv->getClient(),
            'vehicule' => $rdv->getVehicule(),
            ...$this->buildRdvPhotoContext($rdv),
            ...$this->buildBrandingContext($atelier),
        ]);

        return $this->renderPdf($html, 'RAP-' . $rapport->getId());
    }

    /**
     * Generate an invoice PDF.
     */
    public function generateFacturePdf(Facture $facture): string
    {
        $atelier = $this->resolveAtelier($facture->getAtelierId());
        $clauses = $this->resolveDocClauses(['cgv', 'garantie', 'rgpd'], $facture->getAtelierId());

        $html = $this->twig->render('pdf/facture.html.twig', [
            'facture' => $facture,
            'clauses' => $clauses,
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
        $clauses = $this->resolveDocClauses(['cgv', 'rgpd'], $devis->getAtelierId());

        $html = $this->twig->render('pdf/devis.html.twig', [
            'devis' => $devis,
            'clauses' => $clauses,
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
        // LP avec de nombreuses entrées et 13 colonnes peut épuiser la limite mémoire par défaut.
        // La restauration est intentionnellement omise : chaque requête HTTP a son propre cycle
        // mémoire, et tenter de restaurer une limite inférieure à l'usage courant lèverait une erreur.
        ini_set('memory_limit', '256M');

        $atelier = $this->resolveAtelier($atelierId);

        $html = $this->twig->render('pdf/vo_livre_police.html.twig', [
            'entries' => $entries,
            ...$this->buildBrandingContext($atelier),
        ]);

        return $this->renderPdf($html, 'LP-' . date('Ymd-His'), 'landscape');
    }

    /**
     * Generate contrat de dépôt-vente PDF.
     */
    public function generateContratDepotVentePdf(VODepotVente $depot): string
    {
        $atelier = $this->resolveAtelier($depot->getAtelierId());
        $clauses = $this->resolveDocClauses(['vo_depot_vente_conditions'], $depot->getAtelierId());

        $html = $this->twig->render('pdf/vo_contrat_depot_vente.html.twig', [
            'depot' => $depot,
            'clauses' => $clauses,
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

    /**
     * Generate a prefilled SIV declaration preparation PDF.
     */
    public function generateDaSivPreparationPdf(VOPurchase $purchase, array $blockers = []): string
    {
        $atelier = $this->resolveAtelier($purchase->getAtelierId());

        if (!$atelier instanceof Atelier) {
            throw new \RuntimeException('Atelier introuvable pour générer le CERFA 13751.');
        }

        return $this->cerfaOverlayService->generateDaSivPreparationPdf($purchase, $atelier);
    }

    /**
     * Generate a prefilled immatriculation mandate PDF support.
     */
    public function generateMandatImmatriculationPdf(VOPurchase|VODepotVente $record, ?Client $buyer = null): string
    {
        $atelierId = $record instanceof VOPurchase ? $record->getAtelierId() : $record->getAtelierId();
        $atelier = $this->resolveAtelier($atelierId);

        if (!$atelier instanceof Atelier) {
            throw new \RuntimeException('Atelier introuvable pour générer le CERFA 13757.');
        }

        return $this->cerfaOverlayService->generateMandatImmatriculationPdf($record, $atelier, $buyer);
    }

    public function generateCerfaCessionAchatPdf(VOPurchase $purchase): string
    {
        $atelier = $this->resolveAtelier($purchase->getAtelierId());
        if (!$atelier instanceof Atelier) {
            throw new \RuntimeException('Atelier introuvable pour générer le CERFA 15776 achat.');
        }

        return $this->cerfaOverlayService->generateCerfaCessionAchatPdf($purchase, $atelier);
    }

    /**
     * Generate refurbishment document PDF.
     *
     * @param array<string, mixed> $document
     */
    public function generateVoRemiseEnEtatPdf(array $document, ?int $atelierId = null): string
    {
        $atelier = $this->resolveAtelier($atelierId);
        $snapshot = $document['snapshot'] ?? [];
        $campaign = is_array($snapshot['campaign'] ?? null) ? $snapshot['campaign'] : [];

        $html = $this->twig->render('pdf/vo_remise_en_etat.html.twig', [
            'document' => $document,
            'snapshot' => $snapshot,
            'campaign' => $campaign,
            'record' => is_array($snapshot['record'] ?? null) ? $snapshot['record'] : [],
            'vehicle' => is_array($snapshot['vehicle'] ?? null) ? $snapshot['vehicle'] : [],
            'notes' => is_array($snapshot['notes'] ?? null) ? $snapshot['notes'] : [],
            'summary' => is_array($snapshot['summary'] ?? null) ? $snapshot['summary'] : [],
            'lines' => is_array($snapshot['lines'] ?? null) ? $snapshot['lines'] : [],
            'pieces' => is_array($snapshot['pieces'] ?? null) ? $snapshot['pieces'] : [],
            ...$this->buildBrandingContext($atelier),
        ]);

        $reference = (string) ($document['reference'] ?? sprintf('REVO-%s', $campaign['label'] ?? date('Ymd-His')));

        return $this->renderPdf($html, $reference);
    }

    private function buildBrandingContext(?Atelier $atelier): array
    {
        return [
            'atelier' => $atelier,
            'logo_data_uri' => $this->resolveLogoDataUri($atelier),
            'paddock_logo_data_uri' => $this->resolvePaddockLogoDataUri(),
            'paddock_brand' => 'Paddock',
        ];
    }

    private function resolvePaddockLogoDataUri(): ?string
    {
        // PNG haute résolution rendu depuis le SVG officiel — dompdf gère mal les SVG complexes.
        return $this->fileToDataUri($this->projectDir . '/assets/branding/paddock-logo-pdf.png');
    }

    private function resolveAtelier(?int $atelierId = null): ?Atelier
    {
        if ($atelierId) {
            return $this->em->getRepository(Atelier::class)->find($atelierId);
        }

        return null;
    }

    /**
     * Fetch clauses for a given set of codes + atelier (atelier-specific preferred over global).
     *
     * @param string[]     $codes
     * @return ClauseLegale[]
     */
    private function resolveDocClauses(array $codes, ?int $atelierId): array
    {
        if (empty($codes)) {
            return [];
        }

        $qb = $this->em->createQueryBuilder()
            ->select('c')
            ->from(ClauseLegale::class, 'c')
            ->andWhere('c.code IN (:codes)')
            ->andWhere('c.isActive = true')
            ->andWhere('c.atelierId IS NULL OR c.atelierId = :atelierId')
            ->setParameter('codes', $codes)
            ->setParameter('atelierId', $atelierId)
            ->orderBy('c.code', 'ASC')
            ->addOrderBy('c.atelierId', 'DESC');

        /** @var ClauseLegale[] $all */
        $all = $qb->getQuery()->getResult();

        return $this->clauseLegaleVisibilityService->pickVisibleClauses($all, true);
    }

    /**
     * Fetch OR-relevant clauses (mandat_reparation, garantie, retention, rgpd, accessoires).
     *
     * @return ClauseLegale[]
     */
    private function resolveOrClauses(?int $atelierId): array
    {
        return $this->resolveDocClauses(
            ['mandat_reparation', 'garantie', 'retention', 'rgpd', 'accessoires'],
            $atelierId
        );
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

    public function buildRdvPhotoContext(?RendezVous $rdv): array
    {
        return [
            'reception_photos' => $this->extractReceptionPhotos($rdv),
            'report_photos' => $this->extractReportPhotos($rdv),
        ];
    }

    /**
     * @return array<int, array{src: string, label: string, takenAt: ?string}>
     */
    private function extractReceptionPhotos(?RendezVous $rdv): array
    {
        if (!$rdv) {
            return [];
        }

        $photos = [];
        $raw = $rdv->getPhotosEtat();
        if (is_string($raw) && trim($raw) !== '') {
            $decoded = json_decode($raw, true);
            $candidatePhotos = is_array($decoded) ? ($decoded['photos'] ?? []) : [];

            if (is_array($candidatePhotos)) {
                foreach ($candidatePhotos as $photo) {
                    $this->appendInlinePhoto($photos, $photo, 'Photo réception');
                }
            }
        }

        foreach ($rdv->getPhotosIntervention() as $photo) {
            $type = strtolower((string) ($photo->getType() ?? ''));
            if ($type === '' || in_array($type, ['reception', 'checkin', 'etat'], true)) {
                $this->appendStoredPhoto($photos, $photo);
            }
        }

        return array_slice($photos, 0, 6);
    }

    /**
     * @return array<int, array{src: string, label: string, takenAt: ?string}>
     */
    private function extractReportPhotos(?RendezVous $rdv): array
    {
        if (!$rdv) {
            return [];
        }

        $photos = [];
        foreach ($rdv->getPhotosIntervention() as $photo) {
            $type = strtolower((string) ($photo->getType() ?? 'intervention'));
            if (!in_array($type, ['reception', 'checkin', 'etat'], true)) {
                $this->appendStoredPhoto($photos, $photo);
            }
        }

        return array_slice($photos, 0, 6);
    }

    /**
     * @param array<int, array{src: string, label: string, takenAt: ?string}> $photos
     */
    private function appendInlinePhoto(array &$photos, mixed $photo, string $fallbackLabel): void
    {
        $src = null;
        $label = $fallbackLabel;

        if (is_string($photo)) {
            $src = $photo;
        } elseif (is_array($photo)) {
            $src = $photo['src'] ?? $photo['data'] ?? $photo['url'] ?? null;
            $label = (string) ($photo['label'] ?? $photo['description'] ?? $fallbackLabel);
        }

        if (!is_string($src) || trim($src) === '' || !str_starts_with($src, 'data:image/')) {
            return;
        }

        $photos[] = [
            'src' => $src,
            'label' => $label,
            'takenAt' => null,
        ];
    }

    /**
     * @param array<int, array{src: string, label: string, takenAt: ?string}> $photos
     */
    private function appendStoredPhoto(array &$photos, PhotoIntervention $photo): void
    {
        $path = $this->projectDir . '/var/photos/' . basename($photo->getFilename());
        $src = $this->fileToDataUri($path);
        if (!$src) {
            return;
        }

        $typeLabel = match (strtolower((string) $photo->getType())) {
            'restitution' => 'Photo restitution',
            'intervention' => 'Photo intervention',
            'before' => 'Photo avant travaux',
            'after' => 'Photo après travaux',
            default => 'Photo atelier',
        };

        $photos[] = [
            'src' => $src,
            'label' => $photo->getDescription() ?: $typeLabel,
            'takenAt' => $photo->getTakenAt()?->format('d/m/Y H:i'),
        ];
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

    /**
     * Render HTML to PDF and return file path.
     */
    /**
     * [SPRINT-4] I14 — Check if a pre-generated PDF already exists (cache hit from async worker).
     * Returns the cached path if found, null otherwise.
     */
    public function getCachedPdfPath(string $filename): ?string
    {
        $filePath = $this->projectDir . '/var/pdf/' . $filename . '.pdf';

        return is_file($filePath) ? $filePath : null;
    }

    private function renderPdf(string $html, string $filename, string $orientation = 'portrait'): string
    {
        // Dompdf + php-font-lib consomment beaucoup de mémoire lors du rendu,
        // notamment au premier chargement des polices. On augmente la limite
        // pour éviter les fatal error dans les suites de tests fonctionnels
        // ou les documents avec beaucoup de pages.
        ini_set('memory_limit', '512M');

        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('fontDir', $this->projectDir . '/vendor/dompdf/dompdf/lib/fonts');
        $options->set('fontCache', $this->projectDir . '/var/dompdf-font-cache');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', $orientation);
        $dompdf->render();

        $outputDir = $this->projectDir . '/var/pdf';
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $filePath = $outputDir . '/' . $filename . '.pdf';
        file_put_contents($filePath, $dompdf->output());

        // Libérer explicitement la mémoire lourde de Dompdf pour éviter les fuites
        // entre les appels successifs (tests fonctionnels, workers, etc.).
        $dompdf = null;
        $html = null;
        gc_collect_cycles();

        return $filePath;
    }
}
