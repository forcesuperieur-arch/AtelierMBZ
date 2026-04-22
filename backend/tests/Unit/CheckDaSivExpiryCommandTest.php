<?php

namespace App\Tests\Unit;

use App\Command\CheckDaSivExpiryCommand;
use App\Entity\VOPurchase;
use App\Service\AuditService;
use App\Service\MercureNotifier;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

#[AllowMockObjectsWithoutExpectations]
class CheckDaSivExpiryCommandTest extends TestCase
{
    public function testPassesToExpiredAfterJ15(): void
    {
        $purchase = new VOPurchase();
        // createdAt is set in constructor to new DateTime — we need to set it to 16 days ago
        $reflection = new \ReflectionProperty(VOPurchase::class, 'createdAt');
        $reflection->setValue($purchase, new \DateTime('-16 days'));
        $purchase->setSivStatus(VOPurchase::SIV_STATUS_EN_COURS);

        $idReflection = new \ReflectionProperty(VOPurchase::class, 'id');
        $idReflection->setValue($purchase, 1);

        $atelierId = new \ReflectionProperty(VOPurchase::class, 'atelierId');
        $atelierId->setValue($purchase, 1);

        $query = $this->createMock(Query::class);
        $query->method('getResult')->willReturn([$purchase]);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('where')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $repo = $this->createMock(EntityRepository::class);
        $repo->method('createQueryBuilder')->willReturn($qb);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repo);
        // persist is called twice: once for VOPurchase (status update), once for in-app Notification
        $em->expects($this->exactly(2))->method('persist');
        $em->expects($this->once())->method('flush');

        $mercure = $this->createMock(MercureNotifier::class);
        $audit = $this->createMock(AuditService::class);
        $audit->expects($this->once())->method('log')->with('da_siv_expired', 'VOPurchase', 1);

        $command = new CheckDaSivExpiryCommand($em, $mercure, $audit);
        $tester = new CommandTester($command);

        self::assertSame(0, $tester->execute([]));
        self::assertSame(VOPurchase::SIV_STATUS_EXPIREE, $purchase->getSivStatus());
    }

    public function testSendsJ10AlertWithoutChangingStatus(): void
    {
        $purchase = new VOPurchase();
        $reflection = new \ReflectionProperty(VOPurchase::class, 'createdAt');
        $reflection->setValue($purchase, new \DateTime('-12 days'));
        $purchase->setSivStatus(VOPurchase::SIV_STATUS_EN_COURS);

        $idReflection = new \ReflectionProperty(VOPurchase::class, 'id');
        $idReflection->setValue($purchase, 2);

        $atelierId = new \ReflectionProperty(VOPurchase::class, 'atelierId');
        $atelierId->setValue($purchase, 1);

        $query = $this->createMock(Query::class);
        $query->method('getResult')->willReturn([$purchase]);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('where')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $repo = $this->createMock(EntityRepository::class);
        $repo->method('createQueryBuilder')->willReturn($qb);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repo);
        // persist is for the Notification entity (in-app), NOT for VOPurchase
        $em->expects($this->once())->method('persist');
        $em->expects($this->once())->method('flush');

        $mercure = $this->createMock(MercureNotifier::class);
        $audit = $this->createMock(AuditService::class);
        // No audit log for J+10 — only J+15 triggers audit
        $audit->expects($this->never())->method('log');

        $command = new CheckDaSivExpiryCommand($em, $mercure, $audit);
        $tester = new CommandTester($command);

        self::assertSame(0, $tester->execute([]));
        // Status must NOT have changed
        self::assertSame(VOPurchase::SIV_STATUS_EN_COURS, $purchase->getSivStatus());
    }
}
