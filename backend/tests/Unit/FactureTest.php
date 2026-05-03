<?php

namespace App\Tests\Unit;

use App\Entity\Facture;
use App\Entity\Paiement;
use PHPUnit\Framework\TestCase;

class FactureTest extends TestCase
{
    public function testConstructInitializesDefaults(): void
    {
        $f = new Facture();
        $this->assertNotNull($f->getDateCreation());
        $this->assertNotNull($f->getCreatedAt());
        $this->assertNotNull($f->getUpdatedAt());
        $this->assertEmpty($f->getPaiements());
        $this->assertEmpty($f->getLignes());
    }

    public function testIsAvoir(): void
    {
        $f = new Facture();
        $f->setNature(Facture::NATURE_FACTURE);
        $this->assertFalse($f->isAvoir());

        $f->setNature(Facture::NATURE_AVOIR);
        $this->assertTrue($f->isAvoir());
    }

    public function testHasSnapshot(): void
    {
        $f = new Facture();
        $this->assertFalse($f->hasSnapshot());

        $f->setSnapClientNom('Dupont');
        $this->assertTrue($f->hasSnapshot());
    }

    public function testGetMontantPayeForFacture(): void
    {
        $f = new Facture();
        $f->setNature(Facture::NATURE_FACTURE);
        $f->setTotalTtc('120.00');

        $p1 = new Paiement();
        $p1->setTypeOperation(Paiement::TYPE_ENCAISSEMENT);
        $p1->setMontant('50.00');
        $f->addPaiement($p1);

        $p2 = new Paiement();
        $p2->setTypeOperation(Paiement::TYPE_ENCAISSEMENT);
        $p2->setMontant('30.00');
        $f->addPaiement($p2);

        $this->assertSame('80.00', $f->getMontantPaye());
    }

    public function testGetMontantPayeForAvoir(): void
    {
        $f = new Facture();
        $f->setNature(Facture::NATURE_AVOIR);
        $f->setTotalTtc('120.00');

        $p1 = new Paiement();
        $p1->setTypeOperation(Paiement::TYPE_REMBOURSEMENT);
        $p1->setMontant('40.00');
        $f->addPaiement($p1);

        $this->assertSame('40.00', $f->getMontantPaye());
    }

    public function testGetMontantRembourse(): void
    {
        $f = new Facture();
        $f->setNature(Facture::NATURE_AVOIR);

        $p = new Paiement();
        $p->setTypeOperation(Paiement::TYPE_REMBOURSEMENT);
        $p->setMontant('25.00');
        $f->addPaiement($p);

        $this->assertSame('25.00', $f->getMontantRembourse());
    }

    public function testGetResteAPayer(): void
    {
        $f = new Facture();
        $f->setTotalTtc('120.00');
        $f->setNature(Facture::NATURE_FACTURE);

        $p = new Paiement();
        $p->setTypeOperation(Paiement::TYPE_ENCAISSEMENT);
        $p->setMontant('50.00');
        $f->addPaiement($p);

        $this->assertSame('70.00', $f->getResteAPayer());
    }

    public function testGetResteAPayerClampsToZero(): void
    {
        $f = new Facture();
        $f->setTotalTtc('100.00');
        $f->setNature(Facture::NATURE_FACTURE);

        $p = new Paiement();
        $p->setTypeOperation(Paiement::TYPE_ENCAISSEMENT);
        $p->setMontant('150.00');
        $f->addPaiement($p);

        $this->assertSame('0.00', $f->getResteAPayer());
    }

    public function testAddPaiementSetsInverseSide(): void
    {
        $f = new Facture();
        $p = new Paiement();
        $f->addPaiement($p);

        $this->assertSame($f, $p->getFacture());
    }

    public function testAddPaiementDoesNotDuplicate(): void
    {
        $f = new Facture();
        $p = new Paiement();
        $f->addPaiement($p);
        $f->addPaiement($p);

        $this->assertCount(1, $f->getPaiements());
    }

    public function testSnapshotClientDataOnlyIfEmpty(): void
    {
        $f = new Facture();
        $f->setSnapClientNom('Exist');

        $client = new \App\Entity\Client();
        $client->setNom('New');
        $f->setClient($client);

        $f->snapshotClientData();
        $this->assertSame('Exist', $f->getSnapClientNom());
    }

    public function testPreUpdateSetsUpdatedAt(): void
    {
        $f = new Facture();
        $old = $f->getUpdatedAt();
        sleep(1);
        $f->preUpdate();

        $this->assertGreaterThan($old, $f->getUpdatedAt());
    }
}
