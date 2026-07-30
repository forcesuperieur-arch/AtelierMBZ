<?php

namespace App\Tests\Unit;

use App\Service\MotoCatalogImporter;
use PHPUnit\Framework\TestCase;

class MotoCatalogImporterTest extends TestCase
{
    public function testExtractTeethParsesFrontSprocketDescription(): void
    {
        $result = MotoCatalogImporter::extractTeeth('Pignon AFAM 20313, 12 dents, 520, 20CrMnTi');

        self::assertSame(['dents' => 12, 'pas' => 520], $result);
    }

    public function testExtractTeethParsesRearSprocketDescriptionWithoutPignonPrefix(): void
    {
        $result = MotoCatalogImporter::extractTeeth('AFAM, acier, C45N, 92324, 32 dents, 520, cataphorese, noir');

        self::assertSame(['dents' => 32, 'pas' => 520], $result);
    }

    public function testExtractTeethReturnsNullWhenNoMatch(): void
    {
        self::assertNull(MotoCatalogImporter::extractTeeth('Afam standard steel kit for ADLY 300 Thunderbike 2004 - 2005'));
    }

    public function testExtractChainParsesAfamDescription(): void
    {
        $result = MotoCatalogImporter::extractChain('Chaîne AFAM 520, 86 maillons, Xs-ring renforcée, acier');

        self::assertSame(['pas' => 520, 'maillons' => 86], $result);
    }

    public function testExtractChainParsesDcAfamDescription(): void
    {
        $result = MotoCatalogImporter::extractChain('Chaîne DC-AFAM 428, 124 maillons, MX racing GP, or');

        self::assertSame(['pas' => 428, 'maillons' => 124], $result);
    }

    public function testMergeRowIntoSnapshotPrefersOriginalSparkPlugAndCollectsAlternatives(): void
    {
        $base = MotoCatalogImporter::emptySnapshot($this->bikeRow());

        $snapshot = MotoCatalogImporter::mergeRowIntoSnapshot($base, $this->partRow([
            'Part category' => 'Batteries',
            'Part type' => 'Lead-acid',
            'Part brand' => 'Nitro',
            'Part id' => '101443',
            'Part name' => 'NT4L SLA',
            'Part description french' => 'Batterie plomb-acide Nitro, 4 Ah, CCA 75 A, AGM, GEL',
        ]));
        $snapshot = MotoCatalogImporter::mergeRowIntoSnapshot($snapshot, $this->partRow([
            'Part category' => 'Batteries',
            'Part type' => 'Lithium ion',
            'Part brand' => 'Shido',
            'Part id' => '101882',
            'Part name' => 'LTX4L-BS LION -S-',
            'Part description french' => 'Batterie lithium Shido, 1.6 Ah, CCA 120 A',
        ]));

        $final = MotoCatalogImporter::finalizeSnapshot($snapshot);

        self::assertCount(2, $final['batteries']);
        self::assertSame('Nitro', $final['batteries'][0]['marque']);
        self::assertSame('Shido', $final['batteries'][1]['marque']);
    }

    public function testFinalizeSnapshotPrefersStandardSprocketOverAlternative(): void
    {
        $base = MotoCatalogImporter::emptySnapshot($this->bikeRow());

        $snapshot = MotoCatalogImporter::mergeRowIntoSnapshot($base, $this->partRow([
            'Part category' => 'Sprockets',
            'Part type' => 'Alternative front sprocket',
            'Part brand' => 'AFAM',
            'Part name' => '20313-12',
            'Part description french' => 'Pignon AFAM 20313, 12 dents, 520, 20CrMnTi',
        ]));
        $snapshot = MotoCatalogImporter::mergeRowIntoSnapshot($snapshot, $this->partRow([
            'Part category' => 'Sprockets',
            'Part type' => 'Front sprocket',
            'Part brand' => 'AFAM',
            'Part name' => '20313-13',
            'Part description french' => 'Pignon AFAM 20313, 13 dents, 520, 20CrMnTi',
        ]));

        $final = MotoCatalogImporter::finalizeSnapshot($snapshot);

        self::assertSame(13, $final['transmission']['pignon_avant_dents']);
    }

    public function testGroupIntoGenerationsSplitsOnTransmissionChangeAndMergesContiguousYears(): void
    {
        // MT-07 : même transmission 2014-2017, changement de pignon en 2018 -> deux générations.
        $snapshots = [
            $this->specSnapshot(2014, 15, 43),
            $this->specSnapshot(2015, 15, 43),
            $this->specSnapshot(2016, 15, 43),
            $this->specSnapshot(2017, 15, 43),
            $this->specSnapshot(2018, 16, 43),
            $this->specSnapshot(2019, 16, 43),
            $this->specSnapshot(2020, 16, 43),
        ];

        $generations = MotoCatalogImporter::groupIntoGenerations($snapshots);

        self::assertCount(2, $generations);
        self::assertSame(2014, $generations[0]['annee_debut']);
        self::assertSame(2017, $generations[0]['annee_fin']);
        self::assertSame(2018, $generations[1]['annee_debut']);
        self::assertSame(2020, $generations[1]['annee_fin']);
    }

    public function testGroupIntoGenerationsDoesNotFragmentOnMissingYearData(): void
    {
        $snapshots = [
            $this->specSnapshot(2014, 15, 43),
            $this->specSnapshotWithoutTransmission(2015),
            $this->specSnapshot(2016, 15, 43),
        ];

        $generations = MotoCatalogImporter::groupIntoGenerations($snapshots);

        self::assertCount(1, $generations);
        self::assertSame(2014, $generations[0]['annee_debut']);
        self::assertSame(2016, $generations[0]['annee_fin']);
    }

    private function bikeRow(array $overrides = []): array
    {
        return array_merge([
            'Bike brand' => 'YAMAHA',
            'Bike model' => 'MT-07',
            'Bike cc' => '689',
            'Bike year' => '2018',
            'Bike type' => 'Road',
        ], $overrides);
    }

    private function partRow(array $overrides): array
    {
        return array_merge($this->bikeRow(), $overrides);
    }

    private function specSnapshot(int $annee, int $dentsAvant, int $dentsArriere): array
    {
        return [
            'marque' => 'YAMAHA',
            'modele' => 'MT-07',
            'cylindree' => 689,
            'annee' => $annee,
            'categorie' => 'Road',
            'transmission' => [
                'pignon_avant_dents' => $dentsAvant,
                'pignon_arriere_dents' => $dentsArriere,
                'chaine_pas' => 520,
                'chaine_maillons' => 112,
            ],
            'bougies' => [],
            'batteries' => [],
            'filtres' => [],
        ];
    }

    private function specSnapshotWithoutTransmission(int $annee): array
    {
        $s = $this->specSnapshot($annee, 0, 0);
        $s['transmission'] = null;

        return $s;
    }
}
