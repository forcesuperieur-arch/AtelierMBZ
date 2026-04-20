<?php

namespace App\Tests\Unit;

use App\Entity\ConfigAtelier;
use App\Service\PricingService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;

class PricingServiceTest extends TestCase
{
    private function createService(?ConfigAtelier $config): PricingService
    {
        $repo = $this->createMock(EntityRepository::class);
        $repo->method('findOneBy')->willReturn($config);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repo);

        return new PricingService($em);
    }

    private function makeConfig(array $overrides = []): ConfigAtelier
    {
        $config = new ConfigAtelier();
        $config->setAtelierId(1);

        $defaults = [
            'tauxHoraireMoStandard' => '65.00',
            'tauxHoraireMoComplexe' => '85.00',
            'tauxHoraireMoExpert' => '95.00',
            'forfaitMoMinimum' => '25.00',
            'tvaMoTaux' => 20.0,
            'margePiecesStandard' => 30.0,
            'margePiecesConsommable' => 50.0,
            'margePiecesPneumatique' => 25.0,
            'tvaPiecesTaux' => 20.0,
        ];

        foreach (array_merge($defaults, $overrides) as $key => $value) {
            $setter = 'set' . ucfirst($key);
            if (method_exists($config, $setter)) {
                $config->$setter($value);
            }
        }

        return $config;
    }

    public function testCalculateMoPriceStandard(): void
    {
        $service = $this->createService($this->makeConfig());
        $result = $service->calculateMoPrice(60, 'standard', 1);

        $this->assertSame('65.00', $result['prix_ht']);
        $this->assertSame('78.00', $result['prix_ttc']);
        $this->assertSame('65.00', $result['taux_horaire']);
    }

    public function testCalculateMoPriceComplexe30min(): void
    {
        $service = $this->createService($this->makeConfig());
        $result = $service->calculateMoPrice(30, 'complexe', 1);

        // 30min * 85/60 = 42.50 → above forfait min 25 → 42.50
        $this->assertSame('42.50', $result['prix_ht']);
        // 42.50 * 1.20 = 51.00
        $this->assertSame('51.00', $result['prix_ttc']);
    }

    public function testCalculateMoPriceForfaitMinimum(): void
    {
        $service = $this->createService($this->makeConfig(['forfaitMoMinimum' => '50.00']));
        $result = $service->calculateMoPrice(10, 'standard', 1);

        // 10min * 65/60 = 10.83 → below forfait 50 → use 50.00
        $this->assertSame('50.00', $result['prix_ht']);
        // 50 * 1.20 = 60.00
        $this->assertSame('60.00', $result['prix_ttc']);
    }

    public function testCalculateMoPriceThrowsWithoutConfig(): void
    {
        $service = $this->createService(null);
        $this->expectException(\RuntimeException::class);
        $service->calculateMoPrice(60, 'standard', 1);
    }

    public function testApplyPieceMarginStandard(): void
    {
        $service = $this->createService($this->makeConfig());
        $result = $service->applyPieceMargin('100.00', 'standard', 1);

        // 100 * 1.30 = 130.00
        $this->assertSame('130.00', $result['prix_vente_ht']);
        // 130 * 1.20 = 156.00
        $this->assertSame('156.00', $result['prix_vente_ttc']);
        $this->assertSame('30.00', $result['marge_montant']);
    }

    public function testApplyPieceMarginConsommable(): void
    {
        $service = $this->createService($this->makeConfig());
        $result = $service->applyPieceMargin('10.00', 'consommable', 1);

        // 10 * 1.50 = 15.00
        $this->assertSame('15.00', $result['prix_vente_ht']);
        // 15 * 1.20 = 18.00
        $this->assertSame('18.00', $result['prix_vente_ttc']);
        $this->assertSame('5.00', $result['marge_montant']);
    }

    public function testApplyPieceMarginThrowsWithoutConfig(): void
    {
        $service = $this->createService(null);
        $this->expectException(\RuntimeException::class);
        $service->applyPieceMargin('100.00', 'standard', 1);
    }

    public function testBcmathPrecision(): void
    {
        // Verify that 0.1 + 0.2 type issues don't exist with bcmath
        $service = $this->createService($this->makeConfig(['tauxHoraireMoStandard' => '33.33']));
        $result = $service->calculateMoPrice(90, 'standard', 1);

        // 90min = 1.5h → 1.5 * 33.33 = 49.99
        $this->assertSame('49.99', $result['prix_ht']);
    }
}
