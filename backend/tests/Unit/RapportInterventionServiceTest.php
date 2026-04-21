<?php

namespace App\Tests\Unit;

use App\Entity\EssaiRoutier;
use App\Entity\PhotoIntervention;
use App\Entity\RapportIntervention;
use App\Entity\RendezVous;
use App\Service\RapportInterventionService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;

class RapportInterventionServiceTest extends TestCase
{
    public function testMecanicienSignatureValidationDoesNotRequireRestitutionPhotos(): void
    {
        $service = $this->createServiceWithPhotoCount(0);
        $rapport = $this->createReadyRapport();

        $this->assertSame([], $service->validateForMecanicienSignature($rapport));
        $this->assertContains('Minimum 3 photos de restitution requises (0 actuellement)', $service->validateCompleteness($rapport));
    }

    public function testMecanicienSignatureValidationRequiresKilometrageRestitution(): void
    {
        $service = $this->createServiceWithPhotoCount(3);
        $rapport = $this->createReadyRapport()->setKilometrageRestitution(null);

        $this->assertContains('Kilométrage restitution non renseigné', $service->validateForMecanicienSignature($rapport));
    }

    private function createServiceWithPhotoCount(int $photoCount): RapportInterventionService
    {
        $photoRepository = $this->createMock(EntityRepository::class);
        $photoRepository
            ->method('count')
            ->willReturn($photoCount);

        $em = $this->createMock(EntityManagerInterface::class);
        $em
            ->method('getRepository')
            ->willReturnMap([
                [PhotoIntervention::class, $photoRepository],
            ]);

        return new RapportInterventionService($em);
    }

    private function createReadyRapport(): RapportIntervention
    {
        $rdv = new RendezVous();
        $essai = (new EssaiRoutier())
            ->setRendezVous($rdv)
            ->setKmDebut(12000)
            ->setKmFin(12012)
            ->setDureeMinutes(15)
            ->setPointsControle(array_map(
                static fn (array $point) => [...$point, 'ok' => true],
                EssaiRoutier::defaultPointsControle(),
            ));

        return (new RapportIntervention())
            ->setRendezVous($rdv)
            ->setEssaiRoutier($essai)
            ->setTravauxRealises('Remplacement des consommables et controle complet apres intervention.')
            ->setRecommandations('Controle dans 10000 km.')
            ->setKilometrageRestitution(12012);
    }
}