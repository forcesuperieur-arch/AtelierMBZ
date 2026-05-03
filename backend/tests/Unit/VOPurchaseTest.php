<?php

namespace App\Tests\Unit;

use App\Entity\VOPurchase;
use PHPUnit\Framework\TestCase;

class VOPurchaseTest extends TestCase
{
    public function testConstructInitializesDates(): void
    {
        $v = new VOPurchase();
        $this->assertNotNull($v->getCreatedAt());
        $this->assertNotNull($v->getUpdatedAt());
    }

    public function testSetStatusValid(): void
    {
        $v = new VOPurchase();
        foreach (VOPurchase::STATUSES as $status) {
            $v->setStatus($status);
            $this->assertSame($status, $v->getStatus());
        }
    }

    public function testSetStatusInvalidThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $v = new VOPurchase();
        $v->setStatus('invalide');
    }

    public function testSetSivStatusValid(): void
    {
        $v = new VOPurchase();
        $v->setSivStatus(VOPurchase::SIV_STATUS_ENREGISTREE);
        $this->assertSame(VOPurchase::SIV_STATUS_ENREGISTREE, $v->getSivStatus());
    }

    public function testSetSivStatusInvalidFallsBack(): void
    {
        $v = new VOPurchase();
        $v->setSivStatus('invalide');
        $this->assertSame(VOPurchase::SIV_STATUS_A_PREPARER, $v->getSivStatus());
    }

    public function testIsSivRegistered(): void
    {
        $v = new VOPurchase();
        $v->setSivStatus(VOPurchase::SIV_STATUS_ENREGISTREE);
        $this->assertTrue($v->isSivRegistered());

        $v->setSivStatus(VOPurchase::SIV_STATUS_A_PREPARER);
        $this->assertFalse($v->isSivRegistered());
    }

    public function testGetTotalFreEmpty(): void
    {
        $v = new VOPurchase();
        $v->setRepairEstimates([]);
        $this->assertSame('0.00', $v->getTotalFre());
    }

    public function testGetTotalFreWithEstimates(): void
    {
        $v = new VOPurchase();
        $v->setRepairEstimates([
            ['amount' => '150.00'],
            ['amount' => '250.50'],
        ]);
        $this->assertSame('400.50', $v->getTotalFre());
    }

    public function testGetMargin(): void
    {
        $v = new VOPurchase();
        $v->setPurchasePrice('5000.00');
        $v->setTargetSalePrice('8990.00');
        $v->setRepairEstimates([
            ['amount' => '500.00'],
            ['amount' => '300.00'],
        ]);

        // 8990 - (5000 + 500 + 300) = 3190
        $this->assertSame('3190.00', $v->getMargin());
    }

    public function testGetMarginNoEstimates(): void
    {
        $v = new VOPurchase();
        $v->setPurchasePrice('4000.00');
        $v->setTargetSalePrice('6000.00');
        $v->setRepairEstimates([]);

        $this->assertSame('2000.00', $v->getMargin());
    }

    public function testPreUpdateSetsUpdatedAt(): void
    {
        $v = new VOPurchase();
        $old = $v->getUpdatedAt();
        sleep(1);
        $v->preUpdate();

        $this->assertGreaterThan($old, $v->getUpdatedAt());
    }
}
