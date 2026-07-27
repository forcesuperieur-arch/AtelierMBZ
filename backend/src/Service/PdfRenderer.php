<?php

namespace App\Service;

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Point de passage unique HTML → PDF.
 *
 * Centralisé pour deux raisons :
 *  - les options dompdf étaient dupliquées dans trois classes et divergeaient ;
 *  - la pagination ne peut pas être faite en CSS. dompdf n'implémente pas les
 *    boîtes de marge @page (`@bottom-center`, `counter(pages)`…) : le seul
 *    moyen d'obtenir « Page 1 / 3 » est de tamponner le canvas après le rendu.
 */
class PdfRenderer
{
    /** A4 portrait en points PostScript. */
    private const PAGE_WIDTH = 595.28;
    private const PAGE_HEIGHT = 841.89;

    /** Doit rester cohérent avec la marge basse de @page dans _layout.html.twig. */
    private const SIDE_MARGIN = 37.0;

    private const FOOTER_FONT_SIZE = 7.0;
    private const FOOTER_COLOR = [0.55, 0.58, 0.62];

    /**
     * Rend le HTML et renvoie les octets du PDF.
     *
     * @param string|null $footerLeft  Mention discrète en bas à gauche
     *                                 (raison sociale, référence du document).
     * @param string      $orientation 'portrait' ou 'landscape' — le livre de
     *                                 police tient onze colonnes, illisibles
     *                                 sur une A4 portrait.
     */
    public function render(
        string $html,
        ?string $footerLeft = null,
        bool $withPageNumbers = true,
        string $orientation = 'portrait',
    ): string {
        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        // Les templates n'embarquent que des data-URI : aucun accès disque
        // ni HTTP ne doit être déclenché par le contenu rendu.
        $options->set('isPhpEnabled', false);
        $options->set('isJavascriptEnabled', false);

        $isLandscape = $orientation === 'landscape';

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', $isLandscape ? 'landscape' : 'portrait');
        $dompdf->render();

        $this->stampFooter($dompdf, $footerLeft, $withPageNumbers, $isLandscape);

        $output = $dompdf->output();

        return $output ?? '';
    }

    private function stampFooter(Dompdf $dompdf, ?string $footerLeft, bool $withPageNumbers, bool $isLandscape): void
    {
        if ($footerLeft === null && !$withPageNumbers) {
            return;
        }

        // En paysage les dimensions de la feuille sont permutées : sans cela
        // le pied de page serait tamponné hors de la zone imprimable.
        $pageWidth = $isLandscape ? self::PAGE_HEIGHT : self::PAGE_WIDTH;
        $baseline = ($isLandscape ? self::PAGE_WIDTH : self::PAGE_HEIGHT) - 30.0;

        $canvas = $dompdf->getCanvas();
        $font = $dompdf->getFontMetrics()->getFont('DejaVu Sans', 'normal');

        if ($footerLeft !== null && $footerLeft !== '') {
            $canvas->page_text(
                self::SIDE_MARGIN,
                $baseline,
                $this->truncateFooter($footerLeft),
                $font,
                self::FOOTER_FONT_SIZE,
                self::FOOTER_COLOR,
            );
        }

        if ($withPageNumbers) {
            // page_text interprète {PAGE_NUM} / {PAGE_COUNT} au moment de la
            // sérialisation, quand le nombre total de pages est enfin connu.
            $canvas->page_text(
                $pageWidth - self::SIDE_MARGIN - 60.0,
                $baseline,
                'Page {PAGE_NUM} / {PAGE_COUNT}',
                $font,
                self::FOOTER_FONT_SIZE,
                self::FOOTER_COLOR,
            );
        }
    }

    /**
     * Le pied de page n'a qu'une ligne : au-delà il chevaucherait la
     * pagination, que dompdf ne sait pas repousser.
     */
    private function truncateFooter(string $text): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
        $limit = 110;

        if (mb_strlen($text) <= $limit) {
            return $text;
        }

        return mb_substr($text, 0, $limit - 1) . '…';
    }
}
