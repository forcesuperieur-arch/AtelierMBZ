<?php

namespace App\Service;

use App\Entity\Atelier;
use App\Entity\DocumentLayout;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Rend le bandeau d'en-tête composé dans le designer d'administration.
 *
 * Pourquoi seulement l'en-tête : le designer positionne des éléments au
 * millimètre sur une page fixe. Ce modèle ne sait pas exprimer ce qui fait le
 * corps d'un document d'atelier — un tableau de lignes de longueur variable,
 * une grille de photos, un bloc de totaux qui se cale sous les lignes. Le corps
 * reste donc en Twig ; l'en-tête, lui, est un bloc de hauteur fixe, ce que le
 * designer sait très bien décrire (logo, raison sociale, mentions légales).
 *
 * Auparavant les layouts enregistrés n'étaient lus par personne : un
 * administrateur pouvait composer un en-tête sans le moindre effet sur les PDF
 * réellement produits.
 */
class DocumentHeaderRenderer
{
    /** Hauteur maximale du bandeau, en millimètres. */
    private const MAX_BAND_HEIGHT_MM = 60.0;
    private const DEFAULT_BAND_HEIGHT_MM = 32.0;

    public function __construct(
        private EntityManagerInterface $em,
        private PdfTemplateRegistry $registry,
    ) {}

    /**
     * En-tête personnalisé applicable à ce document, ou null pour retomber sur
     * l'en-tête Twig par défaut.
     *
     * @param array<string, string|null> $extraTokens Jetons propres au document
     *                                                (titre, référence…).
     */
    public function renderFor(
        string $code,
        ?int $atelierId,
        ?Atelier $atelier,
        ?string $logoDataUri,
        array $extraTokens = [],
    ): ?string {
        if (!$this->registry->has($code) || !$this->registry->get($code)['customisableHeader']) {
            return null;
        }

        $layout = $this->findLayout($code, $atelierId);
        if ($layout === null) {
            return null;
        }

        $elements = $this->normalizeElements($layout->getLayoutJson());
        if ($elements === []) {
            return null;
        }

        return $this->render($elements, $this->buildTokens($atelier, $logoDataUri, $extraTokens));
    }

    /**
     * @param list<array<string, mixed>> $elements
     * @param array<string, string> $tokens
     */
    public function render(array $elements, array $tokens): string
    {
        $height = self::DEFAULT_BAND_HEIGHT_MM;
        foreach ($elements as $element) {
            $bottom = (float) ($element['y'] ?? 0) + (float) ($element['h'] ?? 0);
            $height = max($height, $bottom);
        }
        $height = min($height, self::MAX_BAND_HEIGHT_MM);

        $rendered = [];
        foreach ($elements as $element) {
            $rendered[] = $this->renderElement($element, $tokens);
        }

        // Conteneur `position: relative` : les enfants absolus se placent par
        // rapport au bandeau et non par rapport à la page entière.
        return sprintf(
            '<div style="position:relative;width:100%%;height:%smm;">%s</div>',
            $this->mm($height),
            implode('', $rendered),
        );
    }

    /**
     * Accepte les deux formes historiquement enregistrées : une liste
     * d'éléments, ou un objet { elements: [...] }.
     *
     * @return list<array<string, mixed>>
     */
    public function normalizeElements(mixed $layoutJson): array
    {
        if (is_array($layoutJson) && isset($layoutJson['elements']) && is_array($layoutJson['elements'])) {
            $layoutJson = $layoutJson['elements'];
        }

        if (!is_array($layoutJson)) {
            return [];
        }

        $elements = [];
        foreach ($layoutJson as $element) {
            if (is_array($element)) {
                $elements[] = $element;
            }
        }

        return $elements;
    }

    /**
     * @param array<string, string|null> $extraTokens
     * @return array<string, string>
     */
    public function buildTokens(?Atelier $atelier, ?string $logoDataUri, array $extraTokens = []): array
    {
        $cpVille = trim(sprintf('%s %s', $atelier?->getCp() ?? '', $atelier?->getVille() ?? ''));

        $tokens = [
            'atelier_nom' => $atelier?->getNom() ?? '',
            'atelier_adresse' => $atelier?->getAdresse() ?? '',
            'atelier_cp_ville' => $cpVille,
            'atelier_telephone' => $atelier?->getTelephone() ?? '',
            'atelier_email' => $atelier?->getEmail() ?? '',
            'atelier_siret' => $atelier?->getSiret() ?? '',
            'logo' => $logoDataUri ?? '',
        ];

        foreach ($extraTokens as $key => $value) {
            $tokens[$key] = (string) ($value ?? '');
        }

        return $tokens;
    }

    private function findLayout(string $code, ?int $atelierId): ?DocumentLayout
    {
        $repository = $this->em->getRepository(DocumentLayout::class);

        if ($atelierId !== null) {
            $custom = $repository->findOneBy(['atelierId' => $atelierId, 'code' => $code]);
            if ($custom !== null) {
                return $custom;
            }
        }

        return $repository->findOneBy(['code' => $code, 'isDefault' => true]);
    }

    /**
     * @param array<string, mixed> $element
     * @param array<string, string> $tokens
     */
    private function renderElement(array $element, array $tokens): string
    {
        $type = is_string($element['type'] ?? null) ? $element['type'] : 'text';
        $style = is_array($element['style'] ?? null) ? $element['style'] : [];
        $content = $this->interpolate((string) ($element['content'] ?? ''), $tokens);

        $css = sprintf(
            'position:absolute;left:%smm;top:%smm;width:%smm;height:%smm;',
            $this->mm($element['x'] ?? 0),
            $this->mm($element['y'] ?? 0),
            $this->mm($element['w'] ?? 50),
            $this->mm($element['h'] ?? 8),
        );

        $css .= sprintf('font-size:%spx;', $this->number($style['fontSize'] ?? 10, 1, 40, 10));

        if (!empty($style['bold'])) {
            $css .= 'font-weight:bold;';
        }
        if (!empty($style['italic'])) {
            $css .= 'font-style:italic;';
        }
        if ($this->isColor($style['color'] ?? null)) {
            $css .= sprintf('color:%s;', $style['color']);
        }
        if (in_array($style['align'] ?? null, ['left', 'center', 'right'], true)) {
            $css .= sprintf('text-align:%s;', $style['align']);
        }
        if ($this->isColor($style['backgroundColor'] ?? null)) {
            $css .= sprintf('background-color:%s;', $style['backgroundColor']);
        }

        $borderColor = $this->isColor($style['color'] ?? null) ? $style['color'] : '#1f2937';

        return match ($type) {
            // Seules les data-URI sont acceptées : une URL distante dans un
            // layout déclencherait une requête sortante au rendu.
            'image' => str_starts_with($content, 'data:image/')
                ? sprintf('<div style="%s"><img src="%s" style="max-width:100%%;max-height:100%%;" alt=""></div>', $css, htmlspecialchars($content, ENT_QUOTES))
                : sprintf('<div style="%s"></div>', $css),
            'line' => sprintf('<div style="%sborder-top:1px solid %s;"></div>', $css, htmlspecialchars($borderColor, ENT_QUOTES)),
            'rect' => sprintf('<div style="%sborder:1px solid %s;"></div>', $css, htmlspecialchars($borderColor, ENT_QUOTES)),
            default => sprintf('<div style="%s">%s</div>', $css, nl2br(htmlspecialchars($content, ENT_QUOTES))),
        };
    }

    /**
     * Remplace les jetons {{cle}}. Un jeton inconnu est effacé plutôt que laissé
     * en clair : « {{client_nom}} » imprimé sur une facture ferait plus de dégâts
     * qu'un blanc.
     *
     * @param array<string, string> $tokens
     */
    private function interpolate(string $content, array $tokens): string
    {
        return preg_replace_callback(
            '/\{\{\s*(\w+)\s*\}\}/',
            static fn (array $matches): string => $tokens[$matches[1]] ?? '',
            $content,
        ) ?? $content;
    }

    private function mm(mixed $value): string
    {
        return $this->number($value, 0, 300, 0);
    }

    private function number(mixed $value, float $min, float $max, float $fallback): string
    {
        $number = is_numeric($value) ? (float) $value : $fallback;
        $number = max($min, min($max, $number));

        return rtrim(rtrim(number_format($number, 2, '.', ''), '0'), '.') ?: '0';
    }

    private function isColor(mixed $value): bool
    {
        return is_string($value) && preg_match('/^#[0-9a-fA-F]{3,8}$/', $value) === 1;
    }
}
