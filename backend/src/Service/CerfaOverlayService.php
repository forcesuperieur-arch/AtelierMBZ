<?php

namespace App\Service;

use App\Entity\Atelier;
use App\Entity\Client;
use App\Entity\VODepotVente;
use App\Entity\VOPurchase;
use App\Entity\Vehicule;
use setasign\Fpdi\Fpdi;

class CerfaOverlayService
{
    public function __construct(
        private string $projectDir,
    ) {}

    public function generateDaSivPreparationPdf(VOPurchase $purchase, Atelier $atelier): string
    {
        $outputPath = $this->buildOutputPath('DA-SIV-' . $purchase->getId());
        $pdf = $this->createPdfFromTemplate('assets/cerfa/compat/cerfa_13751.pdf');

        $vehicle = $purchase->getVehicule();
        $purchaseDate = $purchase->getPurchaseDate() ?? new \DateTimeImmutable();
        $atelierAddress = $this->parseAddress($atelier->getAdresse(), $atelier->getCp(), $atelier->getVille());

        // Coordonnées recalibrées — cerfa_13751 (compat PDF 1.4), positions BBox extraites programmatiquement
        $this->markCheckbox($pdf, 60.2, 27.5, true);
        $this->drawLineText($pdf, 36.0, 38.4, 122.0, $atelier->getNom(), 9);
        $this->drawBoxedText($pdf, 163.3, 38.4, 4.4, 0.25, $this->toSiren($atelier->getSiret()), 9, 8);

        $this->drawLineText($pdf, 35.0, 47.3, 14.0, $atelierAddress['streetNumber'], 8);
        $this->drawLineText($pdf, 52.5, 47.3, 14.0, $atelierAddress['streetExtension'], 8);
        $this->drawLineText($pdf, 70.5, 47.3, 24.0, $atelierAddress['streetType'], 8);
        $this->drawLineText($pdf, 97.8, 47.3, 101.0, $atelierAddress['streetName'], 8);
        $this->drawBoxedText($pdf, 29.8, 55.8, 5.1, 0.15, $atelierAddress['postalCode'], 5, 8);
        $this->drawLineText($pdf, 58.5, 55.8, 140.0, $atelierAddress['city'], 8);

        $this->drawDateBoxes($pdf, 44.0, 63.6, $purchaseDate, 4.9, 0.2, 8);
        $this->drawBoxedText($pdf, 95.0, 63.6, 4.9, 0.2, '', 2, 8);
        $this->drawBoxedText($pdf, 107.0, 63.6, 4.9, 0.2, '', 2, 8);

        $this->drawLineText($pdf, 10.5, 75.5, 59.0, $vehicle?->getPlaque(), 9);
        $this->drawLineText($pdf, 75.0, 78.0, 60.0, $vehicle?->getVin(), 8);
        $this->drawLineText($pdf, 139.0, 78.0, 60.0, $vehicle?->getMarque(), 8);
        $this->drawLineText($pdf, 10.5, 86.6, 84.0, $vehicle?->getTypeVariante(), 8);
        $this->drawLineText($pdf, 99.5, 86.6, 54.0, $vehicle?->getDenominationCommerciale(), 8);
        $this->drawLineText($pdf, 157.0, 86.6, 42.0, $vehicle?->getGenreNational(), 8);

        $hasCertificate = trim((string) $vehicle?->getNumeroFormuleCg()) !== '';
        $this->markCheckbox($pdf, 81.7, 97.0, $hasCertificate);
        $this->markCheckbox($pdf, 98.3, 97.0, !$hasCertificate);
        $this->drawBoxedText($pdf, 46.0, 105.8, 5.1, 0.15, '', 8, 8);
        $this->drawLineText($pdf, 113.0, 105.8, 58.0, $vehicle?->getNumeroFormuleCg(), 8);

        $this->drawLineText($pdf, 57.0, 121.0, 142.0, $hasCertificate ? '' : 'Certificat d\'immatriculation non renseigné', 8);
        $this->drawLineText($pdf, 15.0, 140.1, 70.0, $atelier->getVille(), 8);
        $this->drawDateBoxes($pdf, 97.0, 140.1, $purchaseDate, 4.9, 0.2, 8);

        $seller = $purchase->getSeller();
        $sellerAddress = $this->parseAddress($seller?->getAdresse());

        $this->drawLineText($pdf, 36.0, 205.1, 116.0, $this->formatPersonLabel($seller), 9);
        $this->drawBoxedText($pdf, 153.0, 205.1, 4.4, 0.25, '', 9, 8);
        $this->drawLineText($pdf, 30.0, 215.7, 14.0, $sellerAddress['streetNumber'], 8);
        $this->drawLineText($pdf, 47.5, 215.7, 14.0, $sellerAddress['streetExtension'], 8);
        $this->drawLineText($pdf, 66.0, 215.7, 24.0, $sellerAddress['streetType'], 8);
        $this->drawLineText($pdf, 93.0, 215.7, 106.0, $sellerAddress['streetName'], 8);
        $this->drawBoxedText($pdf, 30.0, 225.4, 5.1, 0.15, $sellerAddress['postalCode'], 5, 7);
        $this->drawLineText($pdf, 59.0, 225.4, 139.0, $sellerAddress['city'], 7);
        $this->drawDateBoxes($pdf, 132.0, 234.5, $purchaseDate, 4.9, 0.2, 8);
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

        // Coordonnées recalibrées — cerfa_13757, positionné d'après analyse PNG
        $this->drawLineText($pdf, 38.6, 46.0, 96.3, $this->formatPersonLabel($buyer), 9);
        $this->drawLineText($pdf, 142.5, 46.5, 55.8, '', 8);

        $this->drawLineText($pdf, 36.5, 70.5, 14.7, $buyerAddress['streetNumber'], 8);
        $this->drawLineText($pdf, 52.7, 70.5, 14.7, $buyerAddress['streetExtension'], 8);
        $this->drawLineText($pdf, 69.0, 70.5, 14.7, $buyerAddress['streetType'], 8);
        $this->drawLineText($pdf, 96.3, 70.5, 101.2, $buyerAddress['streetName'], 8);
        $this->drawLineText($pdf, 36.7, 86.3, 24.6, $buyerAddress['postalCode'], 8);
        $this->drawLineText($pdf, 64.6, 86.3, 65.3, $buyerAddress['city'], 8);
        $this->drawLineText($pdf, 133.1, 86.3, 64.6, $buyerAddress['country'] ?: 'France', 8);

        $this->drawLineText($pdf, 40.1, 102.4, 96.3, $atelier->getNom(), 9);
        $this->drawLineText($pdf, 142.3, 102.4, 55.8, $this->toSiren($atelier->getSiret()), 8);
        $this->drawLineText($pdf, 38.2, 126.8, 119.8, 'Demande d\'immatriculation du véhicule désigné', 8);
        $this->drawLineText($pdf, 40.5, 142.8, 101.6, $vehicle?->getMarque(), 8);
        $this->drawBoxedText($pdf, 40.1, 159.4, 5.25, 0.2, $vehicle?->getVin(), 17, 8);
        $this->drawLineText($pdf, 83.3, 176.6, 79.3, $vehicle?->getPlaque(), 8);

        $this->drawLineText($pdf, 21.0, 211.2, 68.4, $atelier->getVille(), 8);
        $this->drawBoxedText($pdf, 99.9, 212.0, 4.2, 0.15, $signatureDate->format('d'), 2, 8);
        $this->drawBoxedText($pdf, 111.9, 212.0, 4.2, 0.15, $signatureDate->format('m'), 2, 8);
        $this->drawBoxedText($pdf, 124.0, 212.0, 4.2, 0.15, $signatureDate->format('Y'), 4, 8);

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

        for ($page = 1; $page <= 2; $page++) {
            $this->addTemplatePage($pdf, 'assets/cerfa/compat/cerfa_15776.pdf', $page);

            // Coordonnées recalibrées — cerfa_15776 (compat PDF 1.6), positions mesurées sur PNG 1406x1988px
            // Ligne 1 véhicule : cases immat/VIN/MEC (bord haut case ≈32mm, bord bas ≈42mm → centre texte ≈35mm)
            $this->drawLineText($pdf, 12.0, 35.0, 44.0, $vehicle?->getPlaque(), 7);
            $this->drawBoxedText($pdf, 63.0, 35.0, 5.0, 0.2, $vehicle?->getVin(), 17, 7);
            $this->drawDateBoxes($pdf, 155.0, 35.0, $vehicle?->getDatePremiereMiseEnCirculation(), 4.6, 0.15, 7);
            // Ligne 2 véhicule : cases marque/type/genre (bord haut ≈42mm, bord bas ≈50mm → centre texte ≈44mm)
            $this->drawLineText($pdf, 12.0, 44.0, 44.0, $vehicle?->getMarque(), 7);
            $this->drawLineText($pdf, 63.0, 44.0, 45.0, $vehicle?->getTypeVariante(), 7);
            $this->drawLineText($pdf, 115.0, 44.0, 38.0, $vehicle?->getGenreNational(), 7);
            $this->drawLineText($pdf, 160.0, 44.0, 37.0, $vehicle?->getDenominationCommerciale(), 7);
            $this->drawLineText($pdf, 77.0, 53.5, 20.0, $vehicle?->getMileage() !== null ? (string) $vehicle->getMileage() : '', 7);

            $hasCertificate = trim((string) $vehicle?->getNumeroFormuleCg()) !== '';
            $this->markCheckbox($pdf, 12.7, 65.0, $hasCertificate);
            $this->drawBoxedText($pdf, 48.0, 64.5, 4.7, 0.15, $vehicle?->getNumeroFormuleCg(), 11, 7);
            $this->markCheckbox($pdf, 123.0, 65.0, !$hasCertificate);
            $this->drawLineText($pdf, 154.0, 64.5, 43.0, $hasCertificate ? '' : 'Absence du certificat à régulariser', 6.5);
            $this->drawDateBoxes($pdf, 74.0, 77.0, null, 4.6, 0.15, 7);

            $this->markCheckbox($pdf, 12.7, 89.0, true);
            $this->drawLineText($pdf, 36.0, 100.5, 103.0, $this->formatPersonLabel($seller), 8.5);
            $this->drawBoxedText($pdf, 147.0, 100.5, 4.4, 0.25, '', 9, 7);
            $this->drawLineText($pdf, 39.0, 110.5, 14.0, $sellerAddress['streetNumber'], 7);
            $this->drawLineText($pdf, 56.0, 110.5, 14.0, $sellerAddress['streetExtension'], 7);
            $this->drawLineText($pdf, 68.0, 110.5, 24.0, $sellerAddress['streetType'], 7);
            $this->drawLineText($pdf, 94.0, 110.5, 103.0, $sellerAddress['streetName'], 7);
            $this->drawBoxedText($pdf, 37.0, 119.0, 5.1, 0.15, $sellerAddress['postalCode'], 5, 7);
            $this->drawLineText($pdf, 68.0, 119.0, 128.0, $sellerAddress['city'], 7);

            $this->markCheckbox($pdf, 65.5, 129.5, true);
            $this->drawDateBoxes($pdf, 19.0, 136.0, $date, 4.6, 0.15, 7);
            $this->drawBoxedText($pdf, 56.0, 136.0, 4.6, 0.15, '', 2, 7);
            $this->drawBoxedText($pdf, 67.0, 136.0, 4.6, 0.15, '', 2, 7);
            $this->markCheckbox($pdf, 12.7, 146.5, true);
            $this->markCheckbox($pdf, 12.7, 153.5, true);
            $this->drawLineText($pdf, 17.0, 181.8, 42.0, $atelier->getVille(), 7);
            $this->drawDateBoxes($pdf, 69.0, 181.8, $date, 4.6, 0.15, 7);

            $this->markCheckbox($pdf, 12.7, 215.5, true);
            $this->drawLineText($pdf, 36.0, 220.5, 103.0, $atelier->getNom(), 8.5);
            $this->drawBoxedText($pdf, 147.0, 220.5, 4.4, 0.25, $this->toSiren($atelier->getSiret()), 9, 7);
            $this->drawDateBoxes($pdf, 24.0, 230.0, null, 4.6, 0.15, 7);
            $this->drawLineText($pdf, 63.0, 230.0, 133.0, '', 7);
            $this->drawLineText($pdf, 39.0, 239.0, 14.0, $atelierAddress['streetNumber'], 7);
            $this->drawLineText($pdf, 56.0, 239.0, 14.0, $atelierAddress['streetExtension'], 7);
            $this->drawLineText($pdf, 68.0, 239.0, 24.0, $atelierAddress['streetType'], 7);
            $this->drawLineText($pdf, 94.0, 239.0, 103.0, $atelierAddress['streetName'], 7);
            $this->drawBoxedText($pdf, 37.0, 247.5, 5.1, 0.15, $atelierAddress['postalCode'], 5, 7);
            $this->drawLineText($pdf, 68.0, 247.5, 128.0, $atelierAddress['city'], 7);
            $this->markCheckbox($pdf, 12.7, 259.0, true);
            $this->markCheckbox($pdf, 12.7, 263.5, true);
            $this->drawLineText($pdf, 17.0, 268.5, 42.0, $atelier->getVille(), 7);
            $this->drawDateBoxes($pdf, 69.0, 268.5, $date, 4.6, 0.15, 7);
        }

        $pdf->Output('F', $outputPath);

        return $outputPath;
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