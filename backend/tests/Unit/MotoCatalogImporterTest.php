<?php

namespace App\Tests\Unit;

use App\Service\MotoCatalogImporter;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class MotoCatalogImporterTest extends TestCase
{
    public function testPrepareCatalogRowsGroupsSparkPlugsAndSortsByCcAndYears(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $importer = new MotoCatalogImporter($em, '/tmp');

        $rows = [
            [
                'marque' => 'ADLY',
                'cylindree' => '50',
                'modele' => 'RS 50',
                'designation' => 'Supersonic',
                'annee_debut' => '2000',
                'annee_fin' => '2010',
                'sparkplug' => 'BPR7HS',
            ],
            [
                'marque' => 'ADLY',
                'cylindree' => '50',
                'modele' => 'RS 50',
                'designation' => 'Supersonic',
                'annee_debut' => '2000',
                'annee_fin' => '2010',
                'sparkplug' => 'BR8HS',
            ],
            [
                'marque' => 'ADLY',
                'cylindree' => '50',
                'modele' => 'RS 50',
                'designation' => 'Supersonic',
                'annee_debut' => '1998',
                'annee_fin' => '1999',
                'sparkplug' => 'BPR7HS',
            ],
            [
                'marque' => 'YAMAHA',
                'cylindree' => '125',
                'modele' => 'XMAX 125',
                'designation' => '',
                'annee_debut' => '2006',
                'annee_fin' => '2012',
                'sparkplug' => 'LMAR8A-9',
            ],
        ];

        $payload = $importer->prepareCatalogRows($rows);

        self::assertCount(3, $payload);
        self::assertSame(1998, $payload[0]['annee_debut']);
        self::assertSame(50, $payload[0]['cylindree']);
        self::assertSame('Roadster', $payload[0]['categorie']);

        self::assertSame('RS 50 - Supersonic', $payload[1]['modele']);
        self::assertSame(['BPR7HS', 'BR8HS'], $payload[1]['bougies']);
        self::assertSame('Scooter', $payload[2]['categorie']);
        self::assertSame('2006-2012', $payload[2]['periode_label']);
    }
}
