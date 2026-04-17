<?php

namespace App\Tests\Unit;

use App\Enum\ModeTarification;
use PHPUnit\Framework\TestCase;

class ModeTarificationTest extends TestCase
{
    public function testAllCasesExist(): void
    {
        $cases = ModeTarification::cases();
        $this->assertCount(3, $cases);
    }

    public function testForfaitValue(): void
    {
        $this->assertSame('forfait', ModeTarification::FORFAIT->value);
    }

    public function testHoraireValue(): void
    {
        $this->assertSame('horaire', ModeTarification::HORAIRE->value);
    }

    public function testSurDevisValue(): void
    {
        $this->assertSame('sur_devis', ModeTarification::SUR_DEVIS->value);
    }

    public function testFromValidString(): void
    {
        $this->assertSame(ModeTarification::FORFAIT, ModeTarification::from('forfait'));
        $this->assertSame(ModeTarification::HORAIRE, ModeTarification::from('horaire'));
        $this->assertSame(ModeTarification::SUR_DEVIS, ModeTarification::from('sur_devis'));
    }

    public function testTryFromInvalidReturnsNull(): void
    {
        $this->assertNull(ModeTarification::tryFrom('invalid'));
        $this->assertNull(ModeTarification::tryFrom(''));
    }
}
