<?php

namespace App\Tests\Unit;

use App\Controller\AdminTemplatePreviewController;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Twig\Environment;

#[AllowMockObjectsWithoutExpectations]
class AdminTemplatePreviewControllerTest extends TestCase
{
    private function createController(): AdminTemplatePreviewController
    {
        return new AdminTemplatePreviewController(
            $this->createMock(Environment::class),
            $this->createMock(EntityManagerInterface::class),
            '/tmp'
        );
    }

    private function invokePrivate(AdminTemplatePreviewController $controller, string $method, array $args = []): mixed
    {
        $reflection = new \ReflectionClass($controller);
        $refMethod = $reflection->getMethod($method);
        $refMethod->setAccessible(true);

        return $refMethod->invokeArgs($controller, $args);
    }

    public function testListExposesCompleteTemplateMetadata(): void
    {
        $reflection = new \ReflectionClass(AdminTemplatePreviewController::class);
        $templates = $reflection->getConstant('TEMPLATES');

        self::assertCount(12, $templates);

        foreach ($templates as $template) {
            self::assertArrayHasKey('label', $template);
            self::assertArrayHasKey('category', $template);
            self::assertArrayHasKey('template', $template);
            self::assertArrayHasKey('description', $template);
            self::assertNotSame('', trim((string) $template['description']));
        }
    }

    public function testDevisPreviewDataContainsValidityAndTotals(): void
    {
        $controller = $this->createController();
        $data = $this->invokePrivate($controller, 'buildSampleData', ['devis']);

        self::assertArrayHasKey('devis', $data);
        self::assertArrayHasKey('dateValidite', $data['devis']);
        self::assertArrayHasKey('totalMoHt', $data['devis']);
        self::assertArrayHasKey('totalPiecesHt', $data['devis']);
        self::assertArrayHasKey('typeLigne', $data['devis']['lignes'][0]);
        self::assertArrayHasKey('totalLigneTtc', $data['devis']['lignes'][0]);
    }

    public function testRapportPreviewDataContainsEssaiRoutierDetails(): void
    {
        $controller = $this->createController();
        $data = $this->invokePrivate($controller, 'buildSampleData', ['rapport_intervention']);

        self::assertArrayHasKey('rapport', $data);
        self::assertArrayHasKey('essai', $data);
        self::assertArrayHasKey('report_photos', $data);
        self::assertArrayHasKey('travauxRealises', $data['rapport']);
        self::assertArrayHasKey('pointsControle', $data['essai']);
        self::assertNotEmpty($data['essai']['pointsControle']);
        self::assertNotEmpty($data['report_photos']);
    }

    public function testVoRemiseEnEtatPreviewDataMatchesTemplateShape(): void
    {
        $controller = $this->createController();
        $data = $this->invokePrivate($controller, 'buildSampleData', ['vo_remise_en_etat']);

        self::assertArrayHasKey('campaign', $data);
        self::assertArrayHasKey('summary', $data);
        self::assertArrayHasKey('notes', $data);
        self::assertArrayHasKey('lines', $data);
        self::assertArrayHasKey('pieces', $data);

        self::assertArrayHasKey('title', $data['campaign']);
        self::assertArrayHasKey('estimatedMoCost', $data['summary']);
        self::assertArrayHasKey('diagnostic', $data['notes']);
        self::assertArrayHasKey('libelle', $data['lines'][0]);
        self::assertArrayHasKey('reference', $data['pieces'][0]);
    }

    public function testVoAdministrativePreviewDataContainsRequiredKeys(): void
    {
        $controller = $this->createController();

        $pv = $this->invokePrivate($controller, 'buildSampleData', ['vo_pv_rachat']);
        self::assertArrayHasKey('expert', $pv['purchase']);
        self::assertArrayHasKey('nonGageDate', $pv['purchase']);
        self::assertArrayHasKey('couleur', $pv['purchase']['vehicule']);

        $da = $this->invokePrivate($controller, 'buildSampleData', ['vo_da_siv']);
        self::assertArrayHasKey('sellerIdDate', $da['purchase']);
        self::assertArrayHasKey('controleTechniqueOk', $da['purchase']);

        $lp = $this->invokePrivate($controller, 'buildSampleData', ['vo_livre_police']);
        self::assertArrayHasKey('vendeurIdNumber', $lp['entries'][0]);
    }
}
