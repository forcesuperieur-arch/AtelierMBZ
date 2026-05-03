<?php

namespace App\Tests\Unit;

use App\Entity\VODepotVente;
use PHPUnit\Framework\TestCase;

class VODepotVenteTest extends TestCase
{
    public function testConstructInitializesDefaults(): void
    {
        $d = new VODepotVente();
        $this->assertNotNull($d->getDateDebut());
        $this->assertNotNull($d->getCreatedAt());
        $this->assertNotNull($d->getUpdatedAt());
        $this->assertSame(90, $d->getDureeMandat());
        $this->assertSame('brouillon', $d->getStatus());
    }

    public function testGetCommissionAmountForfait(): void
    {
        $d = new VODepotVente();
        $d->setCommissionType('forfait');
        $d->setCommissionValeur('500.00');

        $this->assertSame('500.00', $d->getCommissionAmount());
        $this->assertSame('500.00', $d->getCommissionAmount('10000.00'));
    }

    public function testGetCommissionAmountPourcentage(): void
    {
        $d = new VODepotVente();
        $d->setCommissionType('pourcentage');
        $d->setCommissionValeur('10.00');
        $d->setPrixVenteSouhaite('10000.00');

        // 10% de 10000 = 1000
        $this->assertSame('1000.00', $d->getCommissionAmount());
    }

    public function testGetCommissionAmountPourcentageWithParam(): void
    {
        $d = new VODepotVente();
        $d->setCommissionType('pourcentage');
        $d->setCommissionValeur('15.00');

        $this->assertSame('1500.00', $d->getCommissionAmount('10000.00'));
    }

    public function testGetCommissionVatAmount(): void
    {
        $d = new VODepotVente();
        $d->setCommissionType('forfait');
        $d->setCommissionValeur('500.00');

        // 20% de 500 = 100
        $this->assertSame('100.00', $d->getCommissionVatAmount());
    }

    public function testGetCommissionTtc(): void
    {
        $d = new VODepotVente();
        $d->setCommissionType('forfait');
        $d->setCommissionValeur('500.00');

        // 500 + 100 = 600
        $this->assertSame('600.00', $d->getCommissionTtc());
    }

    public function testGetDeposantNet(): void
    {
        $d = new VODepotVente();
        $d->setCommissionType('forfait');
        $d->setCommissionValeur('500.00');

        // 10000 - 600 = 9400
        $this->assertSame('9400.00', $d->getDeposantNet('10000.00'));
    }

    public function testIsMandatExpire(): void
    {
        $d = new VODepotVente();
        $d->setDateDebut(new \DateTime('-100 days'));
        $d->setDureeMandat(90);

        $this->assertTrue($d->isMandatExpire());
    }

    public function testIsMandatNotExpire(): void
    {
        $d = new VODepotVente();
        $d->setDateDebut(new \DateTime('-30 days'));
        $d->setDureeMandat(90);

        $this->assertFalse($d->isMandatExpire());
    }

    public function testGetJoursRestantsMandat(): void
    {
        $d = new VODepotVente();
        $d->setDateDebut(new \DateTime('-30 days'));
        $d->setDureeMandat(90);

        $this->assertSame(60, $d->getJoursRestantsMandat());
    }

    public function testGetJoursRestantsMandatExpired(): void
    {
        $d = new VODepotVente();
        $d->setDateDebut(new \DateTime('-100 days'));
        $d->setDureeMandat(90);

        $this->assertSame(0, $d->getJoursRestantsMandat());
    }

    public function testPreUpdateSetsUpdatedAt(): void
    {
        $d = new VODepotVente();
        $old = $d->getUpdatedAt();
        sleep(1);
        $d->preUpdate();

        $this->assertGreaterThan($old, $d->getUpdatedAt());
    }
}
