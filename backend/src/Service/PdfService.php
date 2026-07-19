<?php
namespace App\Service;

use App\Entity\Atelier;
use App\Entity\Client;
use App\Entity\Devis;
use App\Entity\EtatDesLieux;
use App\Entity\Facture;
use App\Entity\OrdreReparation;
use App\Entity\PhotoIntervention;
use App\Entity\RendezVous;
use App\Entity\VODepotVente;
use App\Entity\VOFacture;
use App\Entity\VOLivrePolice;
use App\Entity\VOPurchase;
use Doctrine\ORM\EntityManagerInterface;
use Dompdf\Dompdf;
use Dompdf\Options;
use Psr\Log\LoggerInterface;
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
        private LoggerInterface $logger,
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
            ...$this->buildRdvPhotoContext($or->getRendezVous()),
            ...$this->buildBrandingContext($atelier),
        ]);

        return $this->renderPdf($html, 'OR-' . $or->getNumeroOr());
    }

    /**
     * Get the stored path for an OR PDF.
     */
    public function getOrPdfPath(OrdreReparation $or): string
    {
        return $this->projectDir . '/var/pdf/OR-' . $or->getNumeroOr() . '.pdf';
    }

    private const OR_ARCHIVE_SUBDIR = '/var/pdf/ordre-reparation';

    /**
     * Rend le PDF de l'OR et l'archive sous un nom aléatoire (hors webroot).
     * Le document archivé est la référence : jamais régénéré. Pattern
     * EtatDesLieuxDocumentService::archivePdf.
     */
    public function archiveOrPdf(OrdreReparation $or): string
    {
        $generatedFilePath = $this->generateOrPdf($or);
        if (!is_file($generatedFilePath)) {
            throw new \RuntimeException(sprintf('Fichier PDF introuvable : %s', $generatedFilePath));
        }

        $archiveDir = $this->projectDir . self::OR_ARCHIVE_SUBDIR;
        if (!is_dir($archiveDir)) {
            mkdir($archiveDir, 0755, true);
        }

        $safeFilename = bin2hex(random_bytes(16)) . '.pdf';
        if (!copy($generatedFilePath, $archiveDir . '/' . $safeFilename)) {
            throw new \RuntimeException('Impossible d\'archiver le PDF de l\'ordre de réparation.');
        }

        // Pas de copie « vivante » résiduelle : le document de référence est l'archive.
        @unlink($generatedFilePath);

        return $safeFilename;
    }

    /**
     * Chemin réel du PDF archivé d'un OR (avec garde anti-traversée). Null si absent.
     */
    public function getArchivedOrPdfPath(OrdreReparation $or): ?string
    {
        $filename = $or->getPdfArchiveName();
        if ($filename === null || $filename === '') {
            return null;
        }

        $archiveDir = realpath($this->projectDir . self::OR_ARCHIVE_SUBDIR);
        if ($archiveDir === false) {
            return null;
        }

        $realPath = realpath($archiveDir . '/' . basename($filename));
        if ($realPath === false || !str_starts_with($realPath, $archiveDir . '/') || !is_file($realPath)) {
            return null;
        }

        return $realPath;
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

    /**
     * Generate a prefilled SIV declaration preparation PDF.
     */
    public function generateDaSivPreparationPdf(VOPurchase $purchase, array $blockers = []): string
    {
        $atelier = $this->resolveAtelier($purchase->getAtelierId());

        $html = $this->twig->render('pdf/vo_da_siv.html.twig', [
            'purchase' => $purchase,
            'blockers' => $blockers,
            ...$this->buildBrandingContext($atelier),
        ]);

        return $this->renderPdf($html, 'DA-SIV-' . $purchase->getId());
    }

    /**
     * Generate a prefilled immatriculation mandate PDF support.
     */
    public function generateMandatImmatriculationPdf(VOPurchase|VODepotVente $record, ?Client $buyer = null): string
    {
        $atelierId = $record instanceof VOPurchase ? $record->getAtelierId() : $record->getAtelierId();
        $atelier = $this->resolveAtelier($atelierId);
        $vehicle = $record->getVehicule();
        $seller = $record instanceof VOPurchase ? $record->getSeller() : $record->getDeposant();

        $html = $this->twig->render('pdf/vo_mandat_immatriculation.html.twig', [
            'record' => $record,
            'vehicle' => $vehicle,
            'seller' => $seller,
            'buyer' => $buyer,
            ...$this->buildBrandingContext($atelier),
        ]);

        $reference = $record instanceof VOPurchase ? 'MANDAT-IMMAT-ACHAT-' . $record->getId() : 'MANDAT-IMMAT-DEPOT-' . $record->getId();

        return $this->renderPdf($html, $reference);
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

    /**
     * Generate the frozen check-in report PDF (Lot B — état des lieux d'entrée).
     * Appelé UNE SEULE FOIS à la signature par EtatDesLieuxDocumentService,
     * qui archive ensuite le fichier sous nom aléatoire. Jamais régénéré.
     */
    public function generateEtatDesLieuxPdf(EtatDesLieux $etatDesLieux): string
    {
        $rdv = $etatDesLieux->getRendezVous();
        $atelier = $this->resolveAtelier($etatDesLieux->getAtelierId() ?? $rdv->getAtelierId());

        $html = $this->twig->render('pdf/etat_des_lieux.html.twig', [
            'edl' => $etatDesLieux,
            'rdv' => $rdv,
            'niveau_carburant_label' => EtatDesLieux::NIVEAUX_CARBURANT_LABELS[$etatDesLieux->getNiveauCarburant()] ?? 'Non renseigné',
            'photos_entree' => $this->extractCheckinPhotos($rdv),
            ...$this->buildBrandingContext($atelier),
        ]);

        return $this->renderPdf($html, 'EDL-' . ($etatDesLieux->getId() ?? $rdv->getId()) . '-' . bin2hex(random_bytes(4)));
    }

    /**
     * Photos d'entrée du check-in (types checkin/reception), en data-URI,
     * ordre déterministe. Distinct d'extractReceptionPhotos : pas de photos
     * inline booking, pas de type vide, plafond plus haut (grille complète).
     *
     * @return array<int, array{src: string, label: string, takenAt: ?string}>
     */
    private function extractCheckinPhotos(?RendezVous $rdv): array
    {
        if (!$rdv) {
            return [];
        }

        $candidates = [];
        foreach ($rdv->getPhotosIntervention() as $photo) {
            $type = strtolower((string) ($photo->getType() ?? ''));
            if (in_array($type, ['checkin', 'reception'], true)) {
                $candidates[] = $photo;
            }
        }

        usort($candidates, static fn (PhotoIntervention $left, PhotoIntervention $right): int =>
            [$left->getFilename(), $left->getId() ?? 0] <=> [$right->getFilename(), $right->getId() ?? 0]);

        $photos = [];
        foreach ($candidates as $photo) {
            $src = $this->fileToDataUri($this->projectDir . '/var/photos/' . basename($photo->getFilename()));
            if (!$src) {
                // Jamais silencieux : le PDF d'état des lieux est archivé UNE
                // SEULE FOIS — une photo manquante ici l'ampute pour toujours
                $this->logger->warning('PDF état des lieux : photo d\'entrée impossible à inliner (fichier absent ou illisible), elle n\'apparaîtra pas dans le document archivé.', [
                    'photo_id' => $photo->getId(),
                    'filename' => $photo->getFilename(),
                    'rdv_id' => $rdv->getId(),
                ]);
                continue;
            }

            if (str_starts_with($src, 'data:image/heic') || str_starts_with($src, 'data:image/heif')) {
                $this->logger->warning('PDF état des lieux : photo d\'entrée au format HEIC/HEIF, non affichable par dompdf — elle sera rendue cassée dans le document archivé.', [
                    'photo_id' => $photo->getId(),
                    'filename' => $photo->getFilename(),
                    'rdv_id' => $rdv->getId(),
                ]);
            }

            $photos[] = [
                'src' => $src,
                'label' => $photo->getDescription() ?: 'Photo d\'entrée',
                'takenAt' => $photo->getTakenAt()?->format('d/m/Y H:i'),
            ];
        }

        return array_slice($photos, 0, 12);
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
            return $this->em->getRepository(Atelier::class)->find($atelierId);
        }

        return null;
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
            'intervention_photos' => $this->extractInterventionPhotos($rdv),
            'restitution_photos' => $this->extractRestitutionPhotos($rdv),
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
    private function extractInterventionPhotos(?RendezVous $rdv): array
    {
        if (!$rdv) {
            return [];
        }

        $photos = [];
        foreach ($rdv->getPhotosIntervention() as $photo) {
            $type = strtolower((string) ($photo->getType() ?? ''));
            if (in_array($type, ['avant_travaux', 'en_cours', 'apres_travaux', 'probleme', 'intervention', 'before', 'after'], true)) {
                $this->appendStoredPhoto($photos, $photo);
            }
        }

        return array_slice($photos, 0, 6);
    }

    /**
     * @return array<int, array{src: string, label: string, takenAt: ?string}>
     */
    private function extractRestitutionPhotos(?RendezVous $rdv): array
    {
        if (!$rdv) {
            return [];
        }

        $photos = [];
        foreach ($rdv->getPhotosIntervention() as $photo) {
            $type = strtolower((string) ($photo->getType() ?? ''));
            if ($type === 'restitution') {
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
            $this->logger->warning('PDF : photo impossible à inliner (fichier absent ou illisible), elle n\'apparaîtra pas dans le document.', [
                'photo_id' => $photo->getId(),
                'filename' => $photo->getFilename(),
                'type' => $photo->getType(),
            ]);
            return;
        }

        if (str_starts_with($src, 'data:image/heic') || str_starts_with($src, 'data:image/heif')) {
            $this->logger->warning('PDF : photo au format HEIC/HEIF, non affichable par dompdf — elle sera rendue cassée dans le document.', [
                'photo_id' => $photo->getId(),
                'filename' => $photo->getFilename(),
                'type' => $photo->getType(),
            ]);
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
    public function generateFromHtml(string $html, string $filename): string
    {
        return $this->renderPdf($html, $filename);
    }

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
