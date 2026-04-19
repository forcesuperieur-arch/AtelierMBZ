<?php

namespace App\Tests\Unit;

use App\Entity\ClauseLegale;
use App\Service\ClauseLegaleVisibilityService;
use PHPUnit\Framework\TestCase;

class ClauseLegaleVisibilityServiceTest extends TestCase
{
    public function testPrefersAtelierSpecificClauseOverGlobalDefaultForSameCode(): void
    {
        $global = (new ClauseLegale())
            ->setCode('rgpd')
            ->setLibelle('RGPD global')
            ->setTexte('Global')
            ->setVersion(1)
            ->setIsActive(true)
            ->setAtelierId(null);

        $local = (new ClauseLegale())
            ->setCode('rgpd')
            ->setLibelle('RGPD atelier')
            ->setTexte('Local')
            ->setVersion(2)
            ->setIsActive(true)
            ->setAtelierId(3);

        $service = new ClauseLegaleVisibilityService();
        $visible = $service->pickVisibleClauses([$global, $local], true);

        $this->assertCount(1, $visible);
        $this->assertSame('RGPD atelier', $visible[0]->getLibelle());
    }

    public function testKeepsGlobalDefaultWhenNoAtelierSpecificClauseExists(): void
    {
        $global = (new ClauseLegale())
            ->setCode('retention')
            ->setLibelle('Rétention')
            ->setTexte('Texte')
            ->setVersion(1)
            ->setIsActive(true)
            ->setAtelierId(null);

        $service = new ClauseLegaleVisibilityService();
        $visible = $service->pickVisibleClauses([$global], true);

        $this->assertCount(1, $visible);
        $this->assertSame('retention', $visible[0]->getCode());
    }
}
