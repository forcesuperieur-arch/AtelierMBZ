<?php

namespace App\Service;

use App\Controller\CerfaFieldConfigDefaults;
use App\Entity\Atelier;
use App\Entity\CerfaFieldConfig;
use App\Entity\Client;
use App\Entity\VODepotVente;
use App\Entity\VOPurchase;
use App\Entity\Vehicule;
use Doctrine\ORM\EntityManagerInterface;
use setasign\Fpdi\Fpdi;

class CerfaOverlayService
{
    /** @var array<string, array<string, array{x:float,y:float,w:float,fs:float}>> */
    private array $fieldCache = [];

    public function __construct(
        private string $projectDir,
        private EntityManagerInterface $em,
    ) {}

    public function generateDaSivPreparationPdf(VOPurchase $purchase, Atelier $atelier): string
    {
        $outputPath = $this->buildOutputPath('DA-SIV-' . $purchase->getId());
        $pdf = $this->createPdfFromTemplate('assets/cerfa/compat/cerfa_13751.pdf');

        $vehicle = $purchase->getVehicule();
        $purchaseDate = $purchase->getPurchaseDate() ?? new \DateTimeImmutable();
        $atelierAddress = $this->parseAddress($atelier->getAdresse(), $atelier->getCp(), $atelier->getVille());
        $seller = $purchase->getSeller();
        $sellerAddress = $this->parseAddress($seller?->getAdresse());

        $c = $this->loadFields('cerfa_13751');

        // Déclarant professionnel
        $this->markCheckbox($pdf, $c['declarant_checkbox']['x'], $c['declarant_checkbox']['y'], true);
        $this->drawLineText($pdf, $c['declarant_nom']['x'], $c['declarant_nom']['y'], $c['declarant_nom']['w'], $atelier->getNom(), $c['declarant_nom']['fs']);
        $this->drawBoxedText($pdf, $c['declarant_siren']['x'], $c['declarant_siren']['y'], 4.4, 0.25, $this->toSiren($atelier->getSiret()), 9, $c['declarant_siren']['fs']);

        $this->drawLineText($pdf, $c['declarant_num_voie']['x'], $c['declarant_num_voie']['y'], $c['declarant_num_voie']['w'], $atelierAddress['streetNumber'], $c['declarant_num_voie']['fs']);
        $this->drawLineText($pdf, $c['declarant_ext_voie']['x'], $c['declarant_ext_voie']['y'], $c['declarant_ext_voie']['w'], $atelierAddress['streetExtension'], $c['declarant_ext_voie']['fs']);
        $this->drawLineText($pdf, $c['declarant_type_voie']['x'], $c['declarant_type_voie']['y'], $c['declarant_type_voie']['w'], $atelierAddress['streetType'], $c['declarant_type_voie']['fs']);
        $this->drawLineText($pdf, $c['declarant_nom_voie']['x'], $c['declarant_nom_voie']['y'], $c['declarant_nom_voie']['w'], $atelierAddress['streetName'], $c['declarant_nom_voie']['fs']);
        $this->drawBoxedText($pdf, $c['declarant_cp']['x'], $c['declarant_cp']['y'], 5.1, 0.15, $atelierAddress['postalCode'], 5, $c['declarant_cp']['fs']);
        $this->drawLineText($pdf, $c['declarant_ville']['x'], $c['declarant_ville']['y'], $c['declarant_ville']['w'], $atelierAddress['city'], $c['declarant_ville']['fs']);

        $this->drawDateBoxes($pdf, $c['date_acquisition']['x'], $c['date_acquisition']['y'], $purchaseDate, 4.9, 0.2, $c['date_acquisition']['fs']);
        // Cases motif/provenance (non configurables — toujours vides en acquisition pro)
        $this->drawBoxedText($pdf, 95.0, 63.6, 4.9, 0.2, '', 2, 8);
        $this->drawBoxedText($pdf, 107.0, 63.6, 4.9, 0.2, '', 2, 8);

        // Véhicule
        $this->drawLineText($pdf, $c['vehicle_plaque']['x'], $c['vehicle_plaque']['y'], $c['vehicle_plaque']['w'], $vehicle?->getPlaque(), $c['vehicle_plaque']['fs']);
        $this->drawLineText($pdf, $c['vehicle_vin']['x'], $c['vehicle_vin']['y'], $c['vehicle_vin']['w'], $vehicle?->getVin(), $c['vehicle_vin']['fs']);
        $this->drawLineText($pdf, $c['vehicle_marque']['x'], $c['vehicle_marque']['y'], $c['vehicle_marque']['w'], $vehicle?->getMarque(), $c['vehicle_marque']['fs']);
        $this->drawLineText($pdf, $c['vehicle_type_variante']['x'], $c['vehicle_type_variante']['y'], $c['vehicle_type_variante']['w'], $vehicle?->getTypeVariante(), $c['vehicle_type_variante']['fs']);
        $this->drawLineText($pdf, $c['vehicle_denomination']['x'], $c['vehicle_denomination']['y'], $c['vehicle_denomination']['w'], $vehicle?->getDenominationCommerciale(), $c['vehicle_denomination']['fs']);
        $this->drawLineText($pdf, $c['vehicle_genre']['x'], $c['vehicle_genre']['y'], $c['vehicle_genre']['w'], $vehicle?->getGenreNational(), $c['vehicle_genre']['fs']);

        $hasCertificate = trim((string) $vehicle?->getNumeroFormuleCg()) !== '';
        $this->markCheckbox($pdf, 81.7, 97.0, $hasCertificate);
        $this->markCheckbox($pdf, 98.3, 97.0, !$hasCertificate);
        $this->drawBoxedText($pdf, 46.0, 105.8, 5.1, 0.15, '', 8, 8);
        $this->drawLineText($pdf, 113.0, 105.8, 58.0, $vehicle?->getNumeroFormuleCg(), 8);
        $this->drawLineText($pdf, 57.0, 121.0, 142.0, $hasCertificate ? '' : 'Certificat d\'immatriculation non renseigné', 8);

        // Signature déclarant
        $this->drawLineText($pdf, $c['ville_signature']['x'], $c['ville_signature']['y'], $c['ville_signature']['w'], $atelier->getVille(), $c['ville_signature']['fs']);
        $this->drawDateBoxes($pdf, $c['date_signature']['x'], $c['date_signature']['y'], $purchaseDate, 4.9, 0.2, $c['date_signature']['fs']);

        // Vendeur (bas de page)
        $this->drawLineText($pdf, $c['vendeur_nom']['x'], $c['vendeur_nom']['y'], $c['vendeur_nom']['w'], $this->formatPersonLabel($seller), $c['vendeur_nom']['fs']);
        $this->drawBoxedText($pdf, 153.0, 205.1, 4.4, 0.25, '', 9, 8); // SIREN vendeur particulier (vide)
        $this->drawLineText($pdf, $c['vendeur_num_voie']['x'], $c['vendeur_num_voie']['y'], $c['vendeur_num_voie']['w'], $sellerAddress['streetNumber'], $c['vendeur_num_voie']['fs']);
        $this->drawLineText($pdf, $c['vendeur_ext_voie']['x'], $c['vendeur_ext_voie']['y'], $c['vendeur_ext_voie']['w'], $sellerAddress['streetExtension'], $c['vendeur_ext_voie']['fs']);
        $this->drawLineText($pdf, $c['vendeur_type_voie']['x'], $c['vendeur_type_voie']['y'], $c['vendeur_type_voie']['w'], $sellerAddress['streetType'], $c['vendeur_type_voie']['fs']);
        $this->drawLineText($pdf, $c['vendeur_nom_voie']['x'], $c['vendeur_nom_voie']['y'], $c['vendeur_nom_voie']['w'], $sellerAddress['streetName'], $c['vendeur_nom_voie']['fs']);
        $this->drawBoxedText($pdf, $c['vendeur_cp']['x'], $c['vendeur_cp']['y'], 5.1, 0.15, $sellerAddress['postalCode'], 5, $c['vendeur_cp']['fs']);
        $this->drawLineText($pdf, $c['vendeur_ville']['x'], $c['vendeur_ville']['y'], $c['vendeur_ville']['w'], $sellerAddress['city'], $c['vendeur_ville']['fs']);
        $this->drawDateBoxes($pdf, $c['date_cession_vendeur']['x'], $c['date_cession_vendeur']['y'], $purchaseDate, 4.9, 0.2, $c['date_cession_vendeur']['fs']);
        // Deuxième bloc signature vendeur (certifié exact) — non configurable
        $this->drawLineText($pdf, 15.0, 246.9, 45.0, $atelier->getVille(), 7);
        $this->drawDateBoxes($pdf, 70.0, 246.9, $purchaseDate, 4.9, 0.2, 8);

        $pdf->Output('F', $outputPath);

        return $outputPath;
    }

    public function generateMandatImmatriculationPdf(VOPurchase|VODepotVente $record, Atelier $atelier, ?Client $buyer = null): string
    {
        $suffix = $record instanceof VOPurchase ? 'ACHAT-' . $record->getId() : 'DEPOT-' . $record->getId();
        $outputPath = $this->buildOutputPath('MANDAT-IMMAT-' . $suffix);
        $pdf = $this->createPdfFromTemplate('assets/cerfa/compat/cerfa_13757.pdf');

        $vehicle = $record->getVehicule();
        $buyerAddress = $this->parseAddress($buyer?->getAdresse());
        $signatureDate = $record instanceof VOPurchase
            ? ($record->getSaleDate() ?? new \DateTimeImmutable())
            : new \DateTimeImmutable();

        $c = $this->loadFields('cerfa_13757');

        // Mandant (acheteur)
        $this->drawLineText($pdf, $c['mandant_nom']['x'], $c['mandant_nom']['y'], $c['mandant_nom']['w'], $this->formatPersonLabel($buyer), $c['mandant_nom']['fs']);
        $this->drawLineText($pdf, 142.5, 46.5, 55.8, '', 8); // SIREN acheteur particulier (vide)

        $this->drawLineText($pdf, $c['mandant_num_voie']['x'], $c['mandant_num_voie']['y'], $c['mandant_num_voie']['w'], $buyerAddress['streetNumber'], $c['mandant_num_voie']['fs']);
        $this->drawLineText($pdf, $c['mandant_ext_voie']['x'], $c['mandant_ext_voie']['y'], $c['mandant_ext_voie']['w'], $buyerAddress['streetExtension'], $c['mandant_ext_voie']['fs']);
        $this->drawLineText($pdf, $c['mandant_type_voie']['x'], $c['mandant_type_voie']['y'], $c['mandant_type_voie']['w'], $buyerAddress['streetType'], $c['mandant_type_voie']['fs']);
        $this->drawLineText($pdf, $c['mandant_nom_voie']['x'], $c['mandant_nom_voie']['y'], $c['mandant_nom_voie']['w'], $buyerAddress['streetName'], $c['mandant_nom_voie']['fs']);
        $this->drawLineText($pdf, $c['mandant_cp']['x'], $c['mandant_cp']['y'], $c['mandant_cp']['w'], $buyerAddress['postalCode'], $c['mandant_cp']['fs']);
        $this->drawLineText($pdf, $c['mandant_ville']['x'], $c['mandant_ville']['y'], $c['mandant_ville']['w'], $buyerAddress['city'], $c['mandant_ville']['fs']);
        $this->drawLineText($pdf, $c['mandant_pays']['x'], $c['mandant_pays']['y'], $c['mandant_pays']['w'], $buyerAddress['country'] ?: 'France', $c['mandant_pays']['fs']);

        // Mandataire (atelier)
        $this->drawLineText($pdf, $c['mandataire_nom']['x'], $c['mandataire_nom']['y'], $c['mandataire_nom']['w'], $atelier->getNom(), $c['mandataire_nom']['fs']);
        $this->drawLineText($pdf, $c['mandataire_siren']['x'], $c['mandataire_siren']['y'], $c['mandataire_siren']['w'], $this->toSiren($atelier->getSiret()), $c['mandataire_siren']['fs']);
        $this->drawLineText($pdf, $c['objet_mandat']['x'], $c['objet_mandat']['y'], $c['objet_mandat']['w'], 'Demande d\'immatriculation du véhicule désigné', $c['objet_mandat']['fs']);

        // Véhicule
        $this->drawLineText($pdf, $c['vehicle_marque']['x'], $c['vehicle_marque']['y'], $c['vehicle_marque']['w'], $vehicle?->getMarque(), $c['vehicle_marque']['fs']);
        $this->drawBoxedText($pdf, $c['vehicle_vin']['x'], $c['vehicle_vin']['y'], 5.25, 0.2, $vehicle?->getVin(), 17, $c['vehicle_vin']['fs']);
        $this->drawLineText($pdf, $c['vehicle_plaque']['x'], $c['vehicle_plaque']['y'], $c['vehicle_plaque']['w'], $vehicle?->getPlaque(), $c['vehicle_plaque']['fs']);

        // Signature
        $this->drawLineText($pdf, $c['ville_signature']['x'], $c['ville_signature']['y'], $c['ville_signature']['w'], $atelier->getVille(), $c['ville_signature']['fs']);
        $this->drawBoxedText($pdf, $c['date_signature_jour']['x'], $c['date_signature_jour']['y'], 4.2, 0.15, $signatureDate->format('d'), 2, $c['date_signature_jour']['fs']);
        $this->drawBoxedText($pdf, $c['date_signature_mois']['x'], $c['date_signature_mois']['y'], 4.2, 0.15, $signatureDate->format('m'), 2, $c['date_signature_mois']['fs']);
        $this->drawBoxedText($pdf, $c['date_signature_annee']['x'], $c['date_signature_annee']['y'], 4.2, 0.15, $signatureDate->format('Y'), 4, $c['date_signature_annee']['fs']);

        $pdf->Output('F', $outputPath);

        return $outputPath;
    }

    public function generateCerfaCessionAchatPdf(VOPurchase $purchase, Atelier $atelier): string
    {
        $outputPath = $this->buildOutputPath('CERFA-CESSION-ACHAT-' . $purchase->getId());
        $pdf = $this->createPdfFromTemplate('assets/cerfa/compat/cerfa_15776.pdf');

        $vehicle = $purchase->getVehicule();
        $seller = $purchase->getSeller();
        $sellerAddress = $this->parseAddress($seller?->getAdresse());
        $atelierAddress = $this->parseAddress($atelier->getAdresse(), $atelier->getCp(), $atelier->getVille());
        $date = $purchase->getPurchaseDate() ?? new \DateTimeImmutable();

        $c = $this->loadFields('cerfa_15776');

        for ($page = 1; $page <= 2; $page++) {
            $this->addTemplatePage($pdf, 'assets/cerfa/compat/cerfa_15776.pdf', $page);

            // Véhicule
            $this->drawLineText($pdf, $c['vehicle_plaque']['x'], $c['vehicle_plaque']['y'], $c['vehicle_plaque']['w'], $vehicle?->getPlaque(), $c['vehicle_plaque']['fs']);
            $this->drawBoxedText($pdf, $c['vehicle_vin']['x'], $c['vehicle_vin']['y'], 5.0, 0.2, $vehicle?->getVin(), 17, $c['vehicle_vin']['fs']);
            $this->drawDateBoxes($pdf, $c['vehicle_mec']['x'], $c['vehicle_mec']['y'], $vehicle?->getDatePremiereMiseEnCirculation(), 4.6, 0.15, $c['vehicle_mec']['fs']);
            $this->drawLineText($pdf, $c['vehicle_marque']['x'], $c['vehicle_marque']['y'], $c['vehicle_marque']['w'], $vehicle?->getMarque(), $c['vehicle_marque']['fs']);
            $this->drawLineText($pdf, $c['vehicle_type_variante']['x'], $c['vehicle_type_variante']['y'], $c['vehicle_type_variante']['w'], $vehicle?->getTypeVariante(), $c['vehicle_type_variante']['fs']);
            $this->drawLineText($pdf, $c['vehicle_genre']['x'], $c['vehicle_genre']['y'], $c['vehicle_genre']['w'], $vehicle?->getGenreNational(), $c['vehicle_genre']['fs']);
            $this->drawLineText($pdf, $c['vehicle_denomination']['x'], $c['vehicle_denomination']['y'], $c['vehicle_denomination']['w'], $vehicle?->getDenominationCommerciale(), $c['vehicle_denomination']['fs']);
            $this->drawLineText($pdf, $c['vehicle_kilometrage']['x'], $c['vehicle_kilometrage']['y'], $c['vehicle_kilometrage']['w'], $vehicle?->getMileage() !== null ? (string) $vehicle->getMileage() : '', $c['vehicle_kilometrage']['fs']);

            $hasCertificate = trim((string) $vehicle?->getNumeroFormuleCg()) !== '';
            $this->markCheckbox($pdf, 12.7, 65.0, $hasCertificate);
            $this->drawBoxedText($pdf, $c['vehicle_num_formule_cg']['x'], $c['vehicle_num_formule_cg']['y'], 4.7, 0.15, $vehicle?->getNumeroFormuleCg(), 11, $c['vehicle_num_formule_cg']['fs']);
            $this->markCheckbox($pdf, 123.0, 65.0, !$hasCertificate);
            $this->drawLineText($pdf, 154.0, 64.5, 43.0, $hasCertificate ? '' : 'Absence du certificat à régulariser', 6.5);
            $this->drawDateBoxes($pdf, 74.0, 77.0, null, 4.6, 0.15, 7);

            // Vendeur
            $this->markCheckbox($pdf, 12.7, 89.0, true);
            $this->drawLineText($pdf, $c['vendeur_nom']['x'], $c['vendeur_nom']['y'], $c['vendeur_nom']['w'], $this->formatPersonLabel($seller), $c['vendeur_nom']['fs']);
            $this->drawBoxedText($pdf, 147.0, 100.5, 4.4, 0.25, '', 9, 7); // SIREN vendeur particulier (vide)
            $this->drawLineText($pdf, $c['vendeur_num_voie']['x'], $c['vendeur_num_voie']['y'], $c['vendeur_num_voie']['w'], $sellerAddress['streetNumber'], $c['vendeur_num_voie']['fs']);
            $this->drawLineText($pdf, $c['vendeur_ext_voie']['x'], $c['vendeur_ext_voie']['y'], $c['vendeur_ext_voie']['w'], $sellerAddress['streetExtension'], $c['vendeur_ext_voie']['fs']);
            $this->drawLineText($pdf, $c['vendeur_type_voie']['x'], $c['vendeur_type_voie']['y'], $c['vendeur_type_voie']['w'], $sellerAddress['streetType'], $c['vendeur_type_voie']['fs']);
            $this->drawLineText($pdf, $c['vendeur_nom_voie']['x'], $c['vendeur_nom_voie']['y'], $c['vendeur_nom_voie']['w'], $sellerAddress['streetName'], $c['vendeur_nom_voie']['fs']);
            $this->drawBoxedText($pdf, $c['vendeur_cp']['x'], $c['vendeur_cp']['y'], 5.1, 0.15, $sellerAddress['postalCode'], 5, $c['vendeur_cp']['fs']);
            $this->drawLineText($pdf, $c['vendeur_ville']['x'], $c['vendeur_ville']['y'], $c['vendeur_ville']['w'], $sellerAddress['city'], $c['vendeur_ville']['fs']);

            $this->markCheckbox($pdf, 65.5, 129.5, true);
            $this->drawDateBoxes($pdf, $c['date_cession']['x'], $c['date_cession']['y'], $date, 4.6, 0.15, $c['date_cession']['fs']);
            $this->drawBoxedText($pdf, 56.0, 136.0, 4.6, 0.15, '', 2, 7); // heure (non utilisée)
            $this->drawBoxedText($pdf, 67.0, 136.0, 4.6, 0.15, '', 2, 7); // minutes (non utilisées)
            $this->markCheckbox($pdf, 12.7, 146.5, true);
            $this->markCheckbox($pdf, 12.7, 153.5, true);
            $this->drawLineText($pdf, $c['ville_cession_vendeur']['x'], $c['ville_cession_vendeur']['y'], $c['ville_cession_vendeur']['w'], $atelier->getVille(), $c['ville_cession_vendeur']['fs']);
            $this->drawDateBoxes($pdf, $c['date_cession_vendeur']['x'], $c['date_cession_vendeur']['y'], $date, 4.6, 0.15, $c['date_cession_vendeur']['fs']);

            // Acheteur (atelier pro)
            $this->markCheckbox($pdf, 12.7, 215.5, true);
            $this->drawLineText($pdf, $c['acheteur_nom']['x'], $c['acheteur_nom']['y'], $c['acheteur_nom']['w'], $atelier->getNom(), $c['acheteur_nom']['fs']);
            $this->drawBoxedText($pdf, $c['acheteur_siren']['x'], $c['acheteur_siren']['y'], 4.4, 0.25, $this->toSiren($atelier->getSiret()), 9, $c['acheteur_siren']['fs']);
            $this->drawDateBoxes($pdf, 24.0, 230.0, null, 4.6, 0.15, 7); // date naissance (non utilisée)
            $this->drawLineText($pdf, $c['acheteur_num_voie']['x'], $c['acheteur_num_voie']['y'], $c['acheteur_num_voie']['w'], $atelierAddress['streetNumber'], $c['acheteur_num_voie']['fs']);
            $this->drawLineText($pdf, $c['acheteur_ext_voie']['x'], $c['acheteur_ext_voie']['y'], $c['acheteur_ext_voie']['w'], $atelierAddress['streetExtension'], $c['acheteur_ext_voie']['fs']);
            $this->drawLineText($pdf, $c['acheteur_type_voie']['x'], $c['acheteur_type_voie']['y'], $c['acheteur_type_voie']['w'], $atelierAddress['streetType'], $c['acheteur_type_voie']['fs']);
            $this->drawLineText($pdf, $c['acheteur_nom_voie']['x'], $c['acheteur_nom_voie']['y'], $c['acheteur_nom_voie']['w'], $atelierAddress['streetName'], $c['acheteur_nom_voie']['fs']);
            $this->drawBoxedText($pdf, $c['acheteur_cp']['x'], $c['acheteur_cp']['y'], 5.1, 0.15, $atelierAddress['postalCode'], 5, $c['acheteur_cp']['fs']);
            $this->drawLineText($pdf, $c['acheteur_ville']['x'], $c['acheteur_ville']['y'], $c['acheteur_ville']['w'], $atelierAddress['city'], $c['acheteur_ville']['fs']);
            $this->markCheckbox($pdf, 12.7, 259.0, true);
            $this->markCheckbox($pdf, 12.7, 263.5, true);
            $this->drawLineText($pdf, $c['ville_cession_acheteur']['x'], $c['ville_cession_acheteur']['y'], $c['ville_cession_acheteur']['w'], $atelier->getVille(), $c['ville_cession_acheteur']['fs']);
            $this->drawDateBoxes($pdf, $c['date_cession_acheteur']['x'], $c['date_cession_acheteur']['y'], $date, 4.6, 0.15, $c['date_cession_acheteur']['fs']);
        }

        $pdf->Output('F', $outputPath);

        return $outputPath;
    }

    /**
     * Charge les positions d'un CERFA depuis la DB (avec fallback sur CerfaFieldConfigDefaults).
     * Résultat mis en cache par cerfaRef pour éviter N+1 dans les boucles multi-pages.
     *
     * @return array<string, array{x:float,y:float,w:float,fs:float}>
     */
    private function loadFields(string $cerfaRef): array
    {
        if (isset($this->fieldCache[$cerfaRef])) {
            return $this->fieldCache[$cerfaRef];
        }

        $map = [];

        // Charger depuis la DB (une seule requête par ref)
        $configs = $this->em->getRepository(CerfaFieldConfig::class)
            ->findBy(['cerfaRef' => $cerfaRef, 'isActive' => true]);
        foreach ($configs as $config) {
            $map[$config->getFieldKey()] = [
                'x'  => (float) $config->getX(),
                'y'  => (float) $config->getY(),
                'w'  => (float) $config->getWidth(),
                'fs' => (float) $config->getFontSize(),
            ];
        }

        // Compléter avec les defaults pour les champs absents/désactivés
        $defaults = CerfaFieldConfigDefaults::all()[$cerfaRef] ?? [];
        foreach ($defaults as $fieldKey => $d) {
            if (!isset($map[$fieldKey])) {
                $map[$fieldKey] = [
                    'x'  => (float) $d['x'],
                    'y'  => (float) $d['y'],
                    'w'  => (float) $d['width'],
                    'fs' => (float) $d['font_size'],
                ];
            }
        }

        $this->fieldCache[$cerfaRef] = $map;

        return $map;
    }

    private function createPdfFromTemplate(string $relativePath): Fpdi
    {
        $pdf = new Fpdi();
        $pdf->SetAutoPageBreak(false);
        $this->addTemplatePage($pdf, $relativePath, 1);

        return $pdf;
    }

    private function addTemplatePage(Fpdi $pdf, string $relativePath, int $page): void
    {
        $absolutePath = $this->projectDir . '/' . ltrim($relativePath, '/');
        if (!is_file($absolutePath)) {
            throw new \RuntimeException(sprintf('Template CERFA introuvable : %s', $absolutePath));
        }

        $pdf->setSourceFile($absolutePath);
        $template = $pdf->importPage($page);
        $size = $pdf->getTemplateSize($template);
        $orientation = ($size['width'] ?? 210) > ($size['height'] ?? 297) ? 'L' : 'P';
        $pdf->AddPage($orientation, [$size['width'], $size['height']]);
        $pdf->useTemplate($template, 0, 0, $size['width'], $size['height']);
        $pdf->SetTextColor(0, 0, 0);
    }

    private function buildOutputPath(string $filename): string
    {
        $outputDir = $this->projectDir . '/var/pdf';
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        return $outputDir . '/' . $filename . '.pdf';
    }

    private function drawLineText(Fpdi $pdf, float $x, float $y, float $width, ?string $text, float $fontSize = 8): void
    {
        $value = trim((string) $text);
        if ($value === '') {
            return;
        }

        $pdf->SetFont('Helvetica', '', $fontSize);
        $pdf->SetXY($x, $y);
        $pdf->Cell($width, 4.0, $this->encode($value), 0, 0, 'L');
    }

    private function drawBoxedText(Fpdi $pdf, float $x, float $y, float $boxWidth, float $gap, ?string $text, int $maxChars, float $fontSize = 8): void
    {
        $value = preg_replace('/\s+/', '', (string) $text) ?? '';
        if ($value === '') {
            return;
        }

        $chars = preg_split('//u', mb_substr($value, 0, $maxChars), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $pdf->SetFont('Helvetica', '', $fontSize);

        foreach ($chars as $index => $char) {
            $pdf->SetXY($x + ($index * ($boxWidth + $gap)), $y);
            $pdf->Cell($boxWidth, 4.0, $this->encode($char), 0, 0, 'C');
        }
    }

    private function drawDateBoxes(Fpdi $pdf, float $x, float $y, ?\DateTimeInterface $date, float $boxWidth, float $gap, float $fontSize = 8): void
    {
        if (!$date) {
            return;
        }

        $this->drawBoxedText($pdf, $x, $y, $boxWidth, $gap, $date->format('d'), 2, $fontSize);
        $this->drawBoxedText($pdf, $x + (3 * ($boxWidth + $gap)), $y, $boxWidth, $gap, $date->format('m'), 2, $fontSize);
        $this->drawBoxedText($pdf, $x + (6 * ($boxWidth + $gap)), $y, $boxWidth, $gap, $date->format('Y'), 4, $fontSize);
    }

    private function markCheckbox(Fpdi $pdf, float $x, float $y, bool $checked): void
    {
        if (!$checked) {
            return;
        }

        $pdf->SetFont('Helvetica', 'B', 9);
        $pdf->SetXY($x, $y);
        $pdf->Cell(3.5, 3.5, 'X', 0, 0, 'C');
    }

    private function formatPersonLabel(?Client $person): string
    {
        if (!$person instanceof Client) {
            return '';
        }

        return trim($this->upper($person->getNom()) . ' ' . $person->getPrenom());
    }

    private function toSiren(?string $siret): string
    {
        return substr(preg_replace('/\D+/', '', (string) $siret) ?? '', 0, 9);
    }

    private function parseAddress(?string $rawAddress, ?string $fallbackPostalCode = null, ?string $fallbackCity = null): array
    {
        $address = trim((string) $rawAddress);
        $address = preg_replace('/\s+/', ' ', $address) ?? '';

        $country = '';
        if (preg_match('/,\s*(France)$/i', $address, $countryMatch) === 1) {
            $country = $countryMatch[1];
            $address = trim((string) preg_replace('/,\s*France$/i', '', $address));
        }

        $postalCode = trim((string) $fallbackPostalCode);
        $city = trim((string) $fallbackCity);
        if (preg_match('/(?:,|\s)(\d{5})\s+([^,]+)$/u', $address, $match) === 1) {
            $postalCode = $match[1];
            $city = trim($match[2]);
            $address = trim((string) preg_replace('/(?:,|\s)\d{5}\s+[^,]+$/u', '', $address));
        }

        $streetNumber = '';
        $streetExtension = '';
        $streetType = '';
        $streetName = $address;

        if (preg_match('/^(\d+)\s+(bis|ter|quater)\s+(.+)$/iu', $address, $match) === 1) {
            $streetNumber = $match[1];
            $streetExtension = $match[2];
            $streetName = $match[3];
        } elseif (preg_match('/^(\d+)\s+(.+)$/u', $address, $match) === 1) {
            $streetNumber = $match[1];
            $streetName = $match[2];
        }

        if (preg_match('/^(av(?:enue)?|bd|boulevard|rue|route|chemin|impasse|allee|allée|place|quai|cours|faubourg|lieu-dit)\s+(.+)$/iu', $streetName, $match) === 1) {
            $streetType = $match[1];
            $streetName = $match[2];
        }

        return [
            'streetNumber' => trim($streetNumber),
            'streetExtension' => trim($streetExtension),
            'streetType' => trim($streetType),
            'streetName' => trim($streetName),
            'postalCode' => trim($postalCode),
            'city' => trim($city),
            'country' => trim($country),
        ];
    }

    private function upper(string $value): string
    {
        return function_exists('mb_strtoupper') ? mb_strtoupper($value, 'UTF-8') : strtoupper($value);
    }

    private function encode(string $value): string
    {
        $encoded = iconv('UTF-8', 'windows-1252//TRANSLIT', $value);

        return $encoded === false ? $value : $encoded;
    }
}