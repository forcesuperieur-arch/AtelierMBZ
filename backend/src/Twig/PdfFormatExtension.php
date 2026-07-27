<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * Formatting filters for PDF templates.
 *
 * Les documents sortis de l'atelier sont des pièces commerciales françaises :
 * les montants doivent s'écrire « 1 234,56 € », pas « 1234.56 ». Les valeurs
 * arrivent de Doctrine en decimal (donc en string « 1234.56 »), d'où le
 * passage systématique par ces filtres plutôt qu'un affichage brut.
 */
class PdfFormatExtension extends AbstractExtension
{
    /** Espace insécable : évite qu'un montant soit coupé en fin de ligne. */
    private const NBSP = "\u{00A0}";

    public function getFilters(): array
    {
        return [
            new TwigFilter('eur', [$this, 'eur']),
            new TwigFilter('num', [$this, 'num']),
            new TwigFilter('qty', [$this, 'qty']),
            new TwigFilter('km', [$this, 'km']),
            new TwigFilter('pct', [$this, 'pct']),
            new TwigFilter('duree', [$this, 'duree']),
        ];
    }

    /**
     * Montant en euros : 1234.5 → « 1 234,50 € ». Null/vide → placeholder.
     */
    public function eur(mixed $value, string $placeholder = '—'): string
    {
        $number = $this->toFloat($value);
        if ($number === null) {
            return $placeholder;
        }

        return $this->num($number, 2) . self::NBSP . '€';
    }

    /**
     * Nombre à la française : séparateur de milliers espace insécable,
     * virgule décimale.
     */
    public function num(mixed $value, int $decimals = 2, string $placeholder = '—'): string
    {
        $number = $this->toFloat($value);
        if ($number === null) {
            return $placeholder;
        }

        return number_format($number, $decimals, ',', self::NBSP);
    }

    /**
     * Quantité : entière quand elle l'est (« 2 »), décimale sinon (« 1,5 »)
     * — une main d'œuvre se compte en heures fractionnées, une pièce non.
     */
    public function qty(mixed $value, string $placeholder = '—'): string
    {
        $number = $this->toFloat($value);
        if ($number === null) {
            return $placeholder;
        }

        $decimals = $this->hasFraction($number) ? 2 : 0;

        return $this->num($number, $decimals);
    }

    public function km(mixed $value, string $placeholder = '—'): string
    {
        $number = $this->toFloat($value);
        if ($number === null) {
            return $placeholder;
        }

        return $this->num($number, 0) . self::NBSP . 'km';
    }

    public function pct(mixed $value, string $placeholder = '—'): string
    {
        $number = $this->toFloat($value);
        if ($number === null) {
            return $placeholder;
        }

        $decimals = $this->hasFraction($number) ? 2 : 0;

        return $this->num($number, $decimals) . self::NBSP . '%';
    }

    /**
     * Durée en minutes → « 1 h 30 » / « 45 min ». Le zéro de tête compte :
     * « 1 h 0 » se lit mal sur un document remis au client.
     */
    public function duree(mixed $value, string $placeholder = '—'): string
    {
        $number = $this->toFloat($value);
        if ($number === null) {
            return $placeholder;
        }

        $totalMinutes = (int) round($number);
        if ($totalMinutes < 60) {
            return $totalMinutes . self::NBSP . 'min';
        }

        $hours = intdiv($totalMinutes, 60);
        $minutes = $totalMinutes % 60;
        if ($minutes === 0) {
            return $hours . self::NBSP . 'h';
        }

        return sprintf('%d%sh%s%02d', $hours, self::NBSP, self::NBSP, $minutes);
    }

    /**
     * Null si la valeur n'est pas un nombre exploitable. Une chaîne vide ou
     * non numérique ne doit jamais devenir « 0,00 € » : afficher un faux zéro
     * sur une facture est pire que d'afficher un tiret.
     */
    private function toFloat(mixed $value): ?float
    {
        if ($value === null || $value === '' || is_bool($value)) {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            return is_nan((float) $value) || is_infinite((float) $value) ? null : (float) $value;
        }

        if (is_string($value)) {
            $normalized = str_replace([' ', "\u{00A0}", ','], ['', '', '.'], trim($value));

            return is_numeric($normalized) ? (float) $normalized : null;
        }

        return null;
    }

    private function hasFraction(float $number): bool
    {
        return abs($number - round($number)) > 0.0001;
    }
}
