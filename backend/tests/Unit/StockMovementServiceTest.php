<?php

namespace App\Tests\Unit;

use App\Entity\CommandeFournisseur;
use App\Entity\Fournisseur;
use App\Entity\LigneCommandeFournisseur;
use App\Entity\MouvementStock;
use App\Entity\PieceDetachee;
use App\Entity\RendezVous;
use App\Entity\User;
use App\Service\AuditService;
use App\Service\StockMovementService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class StockMovementServiceTest extends TestCase
{
    private function createService(): array
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $audit = $this->createMock(AuditService::class);
        $service = new StockMovementService($em, $audit);
        return [$service, $em, $audit];
    }

    private function makePiece(int $stock = 10): PieceDetachee
    {
        $p = new PieceDetachee();
        $p->setReference('REF-TEST-' . uniqid());
        $p->setNom('Pièce test');
        $p->setQuantiteStock($stock);
        $p->setQuantiteMinimale(5);
        return $p;
    }

    public function testRecordMovementEntreeIncreasesStock(): void
    {
        [$service, $em] = $this->createService();
        $piece = $this->makePiece(10);

        $em->expects($this->exactly(2))->method('persist');

        $mv = $service->recordMovement($piece, MouvementStock::TYPE_ENTREE, 5, '12.00', 'Réception');

        $this->assertSame(MouvementStock::TYPE_ENTREE, $mv->getType());
        $this->assertSame(5, $mv->getQuantite());
        $this->assertSame(15, $piece->getQuantiteStock());
    }

    public function testRecordMovementSortieDecreasesStock(): void
    {
        [$service, $em] = $this->createService();
        $piece = $this->makePiece(10);

        $em->expects($this->exactly(2))->method('persist');

        $mv = $service->recordMovement($piece, MouvementStock::TYPE_SORTIE, 3, null, 'Sortie atelier');

        $this->assertSame(MouvementStock::TYPE_SORTIE, $mv->getType());
        $this->assertSame(7, $piece->getQuantiteStock());
    }

    public function testRecordMovementAjustementSetsExactQty(): void
    {
        [$service, $em] = $this->createService();
        $piece = $this->makePiece(10);

        $em->expects($this->exactly(2))->method('persist');

        $mv = $service->recordMovement($piece, MouvementStock::TYPE_AJUSTEMENT, 8, null, 'Inventaire');

        $this->assertSame(MouvementStock::TYPE_AJUSTEMENT, $mv->getType());
        $this->assertSame(8, $piece->getQuantiteStock());
    }

    public function testRecordMovementReceptionIncreasesStock(): void
    {
        [$service, $em] = $this->createService();
        $piece = $this->makePiece(5);

        $em->expects($this->exactly(2))->method('persist');

        $mv = $service->recordMovement($piece, MouvementStock::TYPE_RECEPTION, 10, '25.00', 'Réception CF-001');

        $this->assertSame(15, $piece->getQuantiteStock());
    }

    public function testRecordMovementInvalidTypeThrows(): void
    {
        [$service] = $this->createService();
        $piece = $this->makePiece();

        $this->expectException(\InvalidArgumentException::class);
        $service->recordMovement($piece, 'invalide', 5);
    }

    public function testRecordMovementZeroQuantityThrows(): void
    {
        [$service] = $this->createService();
        $piece = $this->makePiece();

        $this->expectException(\InvalidArgumentException::class);
        $service->recordMovement($piece, MouvementStock::TYPE_ENTREE, 0);
    }

    public function testRecordMovementNegativeResultClampsToZero(): void
    {
        [$service, $em] = $this->createService();
        $piece = $this->makePiece(2);

        $em->expects($this->exactly(2))->method('persist');

        $mv = $service->recordMovement($piece, MouvementStock::TYPE_SORTIE, 5);
        $this->assertSame(0, $piece->getQuantiteStock());
    }

    public function testConsumeForRdvValidStock(): void
    {
        [$service, $em] = $this->createService();
        $piece = $this->makePiece(10);
        $rdv = new RendezVous();
        $rdv->setAtelierId(1);

        $em->expects($this->exactly(2))->method('persist');

        $service->consumeForRdv($piece, 3, $rdv);
        $this->assertSame(7, $piece->getQuantiteStock());
    }

    public function testConsumeForRdvInsufficientStockThrows(): void
    {
        [$service] = $this->createService();
        $piece = $this->makePiece(2);
        $rdv = new RendezVous();
        $rdv->setAtelierId(1);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Stock insuffisant');
        $service->consumeForRdv($piece, 5, $rdv);
    }

    public function testAdjustStock(): void
    {
        [$service, $em, $audit] = $this->createService();
        $piece = $this->makePiece(10);
        $user = new User();
        $user->setUsername('admin');

        $em->expects($this->exactly(2))->method('persist');
        $audit->expects($this->once())->method('log');

        $service->adjustStock($piece, 7, 'Perte inventaire', $user);
        $this->assertSame(7, $piece->getQuantiteStock());
    }

    public function testReceiveCommande(): void
    {
        [$service, $em, $audit] = $this->createService();

        $fournisseur = new Fournisseur();
        $fournisseur->setNom('Test Fournisseur');

        $piece = $this->makePiece(5);
        $piece->setAtelierId(1);

        $commande = new CommandeFournisseur();
        $commande->setNumeroCommande('CF-TEST-001');
        $commande->setFournisseur($fournisseur);
        $commande->setAtelierId(1);
        $commande->setStatut('en_attente');

        $ligne = new LigneCommandeFournisseur();
        $ligne->setCommande($commande);
        $ligne->setPiece($piece);
        $ligne->setQuantiteDemandee(10);
        $ligne->setQuantiteRecue(0);
        $ligne->setPrixUnitaireHt('15.00');
        $commande->getLignes()->add($ligne);

        $repo = $this->createMock(EntityRepository::class);
        $repo->method('find')->willReturnMap([
            [$ligne->getId(), null, null, $ligne],
        ]);
        $em->method('getRepository')->willReturnMap([
            [LigneCommandeFournisseur::class, $repo],
        ]);

        $em->expects($this->exactly(3))->method('persist'); // ligne, mouvement, piece
        $em->expects($this->once())->method('flush');

        $service->receiveCommande($commande, [['ligne_id' => $ligne->getId(), 'quantite_recue' => 5]]);

        $this->assertSame('recue', $commande->getStatut());
        $this->assertSame(5, $ligne->getQuantiteRecue());
        $this->assertSame(10, $piece->getQuantiteStock());
    }

    public function testReceiveCommandeAlreadyReceivedThrows(): void
    {
        [$service] = $this->createService();

        $fournisseur = new Fournisseur();
        $fournisseur->setNom('Test');

        $commande = new CommandeFournisseur();
        $commande->setNumeroCommande('CF-TEST-002');
        $commande->setFournisseur($fournisseur);
        $commande->setStatut('recue');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('déjà été réceptionnée');
        $service->receiveCommande($commande, []);
    }
}
