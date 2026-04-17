<?php

namespace App\Tests\Unit;

use App\Entity\EssaiRoutier;
use PHPUnit\Framework\TestCase;

class EssaiRoutierCompletenessTest extends TestCase
{
    public function testEssaiIsNotCompleteWhenControlPointsAreUnset(): void
    {
        $essai = (new EssaiRoutier())
            ->setKmDebut(1000)
            ->setKmFin(1010)
            ->setSignatureMecanicien('signed');

        $this->assertFalse($essai->isComplete());
    }

    public function testEssaiIsNotCompleteWhenDistanceIsNotPositive(): void
    {
        $points = array_map(
            static fn (array $point) => [...$point, 'ok' => true],
            EssaiRoutier::defaultPointsControle(),
        );

        $essai = (new EssaiRoutier())
            ->setPointsControle($points)
            ->setKmDebut(1000)
            ->setKmFin(1000)
            ->setSignatureMecanicien('signed');

        $this->assertFalse($essai->isComplete());
    }

    public function testEssaiIsCompleteWhenAllProofFieldsArePresent(): void
    {
        $points = array_map(
            static fn (array $point) => [...$point, 'ok' => true],
            EssaiRoutier::defaultPointsControle(),
        );

        $essai = (new EssaiRoutier())
            ->setPointsControle($points)
            ->setKmDebut(1000)
            ->setKmFin(1012)
            ->setSignatureMecanicien('signed');

        $this->assertTrue($essai->isComplete());
    }
}
