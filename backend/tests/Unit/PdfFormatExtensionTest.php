<?php

namespace App\Tests\Unit;

use App\Twig\PdfFormatExtension;
use PHPUnit\Framework\TestCase;

class PdfFormatExtensionTest extends TestCase
{
    private const NBSP = "\u{00A0}";

    private PdfFormatExtension $format;

    protected function setUp(): void
    {
        $this->format = new PdfFormatExtension();
    }

    /** Doctrine renvoie les décimaux en chaîne : « 1234.56 », pas un float. */
    public function testEurFormatsDoctrineDecimalStringsInFrench(): void
    {
        self::assertSame('1' . self::NBSP . '234,56' . self::NBSP . '€', $this->format->eur('1234.56'));
        self::assertSame('45,00' . self::NBSP . '€', $this->format->eur('45.00'));
        self::assertSame('0,00' . self::NBSP . '€', $this->format->eur(0));
    }

    /**
     * Une valeur absente ne doit jamais devenir « 0,00 € » : un faux zéro sur
     * une facture est pire qu'un tiret.
     */
    public function testEurReturnsPlaceholderForMissingValues(): void
    {
        self::assertSame('—', $this->format->eur(null));
        self::assertSame('—', $this->format->eur(''));
        self::assertSame('—', $this->format->eur('non renseigné'));
        self::assertSame('À compléter', $this->format->eur(null, 'À compléter'));
    }

    /** Une pièce se compte en entier, une main d'œuvre en heures fractionnées. */
    public function testQtyKeepsDecimalsOnlyWhenMeaningful(): void
    {
        self::assertSame('2', $this->format->qty(2));
        self::assertSame('1', $this->format->qty('1.00'));
        self::assertSame('1,50', $this->format->qty(1.5));
    }

    public function testKmUsesNonBreakingThousandsSeparator(): void
    {
        self::assertSame('15' . self::NBSP . '420' . self::NBSP . 'km', $this->format->km(15420));
        self::assertSame('—', $this->format->km(null));
    }

    /** « 1 h 0 » se lit mal : les minutes gardent leur zéro de tête. */
    public function testDureeFormatsHoursAndMinutes(): void
    {
        self::assertSame('45' . self::NBSP . 'min', $this->format->duree(45));
        self::assertSame('1' . self::NBSP . 'h', $this->format->duree(60));
        self::assertSame('1' . self::NBSP . 'h' . self::NBSP . '05', $this->format->duree(65));
        self::assertSame('2' . self::NBSP . 'h' . self::NBSP . '30', $this->format->duree(150));
        self::assertSame('—', $this->format->duree(null));
    }

    public function testPctDropsTrailingZeroes(): void
    {
        self::assertSame('20' . self::NBSP . '%', $this->format->pct('20'));
        self::assertSame('5,50' . self::NBSP . '%', $this->format->pct(5.5));
    }

    /** Un booléen n'est pas un montant : le laisser passer afficherait « 1,00 € ». */
    public function testBooleansAreNotTreatedAsNumbers(): void
    {
        self::assertSame('—', $this->format->eur(true));
        self::assertSame('—', $this->format->eur(false));
    }
}
