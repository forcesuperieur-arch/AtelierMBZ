<?php

namespace App\Tests\Unit;

use App\Entity\PieceDetachee;
use PHPUnit\Framework\TestCase;

class PieceDetacheeTest extends TestCase
{
    public function testDefaults(): void
    {
        $p = new PieceDetachee();
        $this->assertSame(0, $p->getQuantiteStock());
        $this->assertSame(5, $p->getQuantiteMinimale());
        $this->assertSame(50, $p->getQuantiteMaximale());
        $this->assertSame('0.00', $p->getPrixAchatHt());
        $this->assertSame('0.00', $p->getPrixVenteHt());
        $this->assertSame(20.0, $p->getTvaTaux());
        $this->assertSame(1, $p->getIsActive());
    }

    public function testIsStockBas(): void
    {
        $p = new PieceDetachee();
        $p->setQuantiteStock(3);
        $p->setQuantiteMinimale(5);
        $this->assertTrue($p->isStockBas());

        $p->setQuantiteStock(10);
        $this->assertFalse($p->isStockBas());
    }

    public function testGetPrixVenteTtc(): void
    {
        $p = new PieceDetachee();
        $p->setPrixVenteHt('100.00');
        $p->setTvaTaux(20.0);

        $this->assertSame(120.0, $p->getPrixVenteTtc());
    }

    public function testPreUpdateSetsUpdatedAt(): void
    {
        $p = new PieceDetachee();
        $old = $p->getUpdatedAt();
        sleep(1);
        $p->preUpdate();

        $this->assertGreaterThan($old, $p->getUpdatedAt());
    }
}
