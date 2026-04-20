<?php

namespace App\Tests\Unit;

use App\Entity\VOPurchase;
use App\Entity\VORemiseEnEtat;
use App\Entity\VORemiseEnEtatLigne;
use App\Entity\VORemiseEnEtatPiece;
use App\Entity\Vehicule;
use App\Service\VORemiseEnEtatService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class VORemiseEnEtatServiceTest extends TestCase
{
    public function testCreateCampaignForPurchasePersistsAndAppliesDefaults(): void
    {
        $repository = $this->createCampaignRepository(activeCampaign: null, count: 2);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->exactly(2))
            ->method('getRepository')
            ->with(VORemiseEnEtat::class)
            ->willReturn($repository);
        $em->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(VORemiseEnEtat::class));

        $service = new VORemiseEnEtatService($em);

        $purchase = (new VOPurchase())
            ->setAtelierId(12)
            ->setStatus('brouillon');

        $campaign = $service->createCampaignForRecord($purchase, null, [
            'titre' => '  Prépa freinage  ',
            'priority' => 'haute',
            'diagnosticNotes' => '  pneus à revoir  ',
            'workshopNotes' => '   ',
        ]);

        $this->assertSame($purchase, $campaign->getVoPurchase());
        $this->assertSame(12, $campaign->getAtelierId());
        $this->assertSame(3, $campaign->getCampaignIndex());
        $this->assertSame('Prépa freinage', $campaign->getTitre());
        $this->assertSame(VORemiseEnEtat::PRIORITY_HAUTE, $campaign->getPriority());
        $this->assertSame('pneus à revoir', $campaign->getDiagnosticNotes());
        $this->assertNull($campaign->getWorkshopNotes());
        $this->assertTrue($campaign->isBlockingSale());
    }

    public function testCreateCampaignForRecordRejectsExistingActiveCampaign(): void
    {
        $repository = $this->createCampaignRepository(activeCampaign: new VORemiseEnEtat(), count: 0);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())
            ->method('getRepository')
            ->with(VORemiseEnEtat::class)
            ->willReturn($repository);
        $em->expects($this->never())->method('persist');

        $service = new VORemiseEnEtatService($em);
        $purchase = (new VOPurchase())->setAtelierId(9);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Une campagne active de remise en etat VO existe deja pour ce dossier.');

        $service->createCampaignForRecord($purchase, null);
    }

    public function testNormalizeQueueItemBuildsFrontPayloadWithCostsAndPaths(): void
    {
        $service = new VORemiseEnEtatService($this->createStub(EntityManagerInterface::class));

        $vehicle = (new Vehicule())
            ->setPlaque('AA-123-BB')
            ->setMarque('BMW')
            ->setModele('R1250GS');
        $this->setPrivateId($vehicle, 7);

        $purchase = (new VOPurchase())
            ->setAtelierId(3)
            ->setVehicule($vehicle)
            ->setStatus('en_stock');
        $this->setPrivateId($purchase, 12);

        $campaign = (new VORemiseEnEtat())
            ->setAtelierId(3)
            ->setVoPurchase($purchase)
            ->setCampaignIndex(2)
            ->setTitre('Préparation vente')
            ->setPriority(VORemiseEnEtat::PRIORITY_HAUTE)
            ->setStatus(VORemiseEnEtat::STATUS_EN_ATTENTE_PIECES);
        $this->setPrivateId($campaign, 44);

        $line = (new VORemiseEnEtatLigne())
            ->setLibelle('Révision freinage')
            ->setEstimatedUnitHt('100.00')
            ->setQuantity(2)
            ->setEstimatedMinutes(90)
            ->setActualTotalHt('180.00')
            ->setActualMinutes(95)
            ->setStatus(VORemiseEnEtatLigne::STATUS_EN_COURS)
            ->setSortOrder(1);
        $this->setPrivateId($line, 101);
        $campaign->addLigne($line);

        $piece = (new VORemiseEnEtatPiece())
            ->setLibelle('Kit plaquettes avant')
            ->setReference('REF-PLAQ')
            ->setSupplier('Bihr')
            ->setEstimatedUnitCostHt('50.00')
            ->setQuantity(2)
            ->setActualTotalCostHt('95.00')
            ->setStatus(VORemiseEnEtatPiece::STATUS_COMMANDEE);
        $this->setPrivateId($piece, 202);
        $campaign->addPiece($piece);

        $payload = $service->normalizeQueueItem($campaign);

        $this->assertSame('Rachat', $payload['sourceLabel']);
        $this->assertSame('/vo/rachats/12', $payload['dossierPath']);
        $this->assertSame('purchase', $payload['sourceType']);
        $this->assertSame(12, $payload['sourceId']);
        $this->assertSame('AA-123-BB', $payload['vehicle']['plaque']);
        $this->assertTrue($payload['isBlockingSale']);
        $this->assertSame(1, $payload['pendingPiecesCount']);
        $this->assertSame('200.00', $payload['costSummary']['estimatedMoCost']);
        $this->assertSame('100.00', $payload['costSummary']['estimatedPartsCost']);
        $this->assertSame('300.00', $payload['costSummary']['estimatedTotalCost']);
        $this->assertSame('275.00', $payload['costSummary']['actualTotalCost']);
        $this->assertSame('-25.00', $payload['costSummary']['varianceTotal']);
        $this->assertCount(1, $payload['lignes']);
        $this->assertCount(1, $payload['pieces']);
    }

    private function createCampaignRepository(?VORemiseEnEtat $activeCampaign, int $count): EntityRepository
    {
        $query = $this->createMock(Query::class);
        $query->method('getOneOrNullResult')->willReturn($activeCampaign);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('where')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('orderBy')->willReturnSelf();
        $qb->method('setMaxResults')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('createQueryBuilder')->willReturn($qb);
        $repository->method('count')->willReturn($count);

        return $repository;
    }

    private function setPrivateId(object $entity, int $id): void
    {
        $reflection = new \ReflectionObject($entity);
        $property = $reflection->getProperty('id');
        $property->setValue($entity, $id);
    }
}