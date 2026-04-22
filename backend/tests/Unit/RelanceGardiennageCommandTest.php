<?php

namespace App\Tests\Unit;

use App\Command\RelanceClientStockageCommand;
use App\Entity\Client;
use App\Entity\ConfigAtelier;
use App\Entity\RendezVous;
use App\Message\SendGardiennageRappelMessage;
use App\Service\AuditService;
use App\Service\JoursOuvresService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

#[AllowMockObjectsWithoutExpectations]
class RelanceGardiennageCommandTest extends TestCase
{
    public function testDispatchesJ15MessageWhenThresholdReached(): void
    {
        $client = (new Client())
            ->setNom('Dupont')
            ->setPrenom('Jean')
            ->setTelephone('0600000000');

        $config = new ConfigAtelier();
        // Defaults: j15=15, j30=30, j45=45, j180=180

        $rdv = $this->createMock(RendezVous::class);
        $rdv->method('getId')->willReturn(42);
        $rdv->method('getAtelierId')->willReturn(1);
        $rdv->method('getStatut')->willReturn('termine');
        $rdv->method('getClient')->willReturn($client);
        $rdv->method('getHeureFinTravail')->willReturn(null);
        $rdv->method('getDateRdv')->willReturn(new \DateTimeImmutable('-20 days'));

        $query = $this->createMock(Query::class);
        $query->method('getResult')->willReturn([$rdv]);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $configRepo = $this->createMock(EntityRepository::class);
        $configRepo->method('findOneBy')->willReturn($config);

        $em = $this->createMock(EntityManagerInterface::class);
        // RelanceClientStockageCommand uses $em->createQueryBuilder() directly for the RDV query
        $em->method('createQueryBuilder')->willReturn($qb);
        // ConfigAtelier uses $em->getRepository(ConfigAtelier::class)->findOneBy(...)
        $em->method('getRepository')->willReturn($configRepo);

        // JoursOuvresService: returns 16 days (just over j15 threshold)
        $joursOuvres = $this->createMock(JoursOuvresService::class);
        $joursOuvres->method('compterJoursOuvres')->willReturn(16);

        $dispatchedMessages = [];
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (SendGardiennageRappelMessage $msg) use (&$dispatchedMessages): bool {
                $dispatchedMessages[] = $msg;
                return $msg->rdvId === 42
                    && $msg->templateCode === 'relance_gardiennage_j15'
                    && $msg->atelierId === 1
                    && $msg->seuilJours === 15;
            }))
            ->willReturn(new Envelope(new \stdClass()));

        $audit = $this->createMock(AuditService::class);

        $command = new RelanceClientStockageCommand($em, $joursOuvres, $bus, $audit);
        $tester = new CommandTester($command);

        self::assertSame(0, $tester->execute([]));
    }

    public function testDispatchesJ180MessageWhenAbandonThresholdReached(): void
    {
        $client = (new Client())
            ->setNom('Martin')
            ->setPrenom('Pierre')
            ->setTelephone('0600000001');

        $config = new ConfigAtelier();

        $rdv = $this->createMock(RendezVous::class);
        $rdv->method('getId')->willReturn(99);
        $rdv->method('getAtelierId')->willReturn(2);
        $rdv->method('getStatut')->willReturn('en_gardiennage');
        $rdv->method('getClient')->willReturn($client);
        $rdv->method('getHeureFinTravail')->willReturn(null);
        $rdv->method('getDateRdv')->willReturn(new \DateTimeImmutable('-200 days'));

        $query = $this->createMock(Query::class);
        $query->method('getResult')->willReturn([$rdv]);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $configRepo = $this->createMock(EntityRepository::class);
        $configRepo->method('findOneBy')->willReturn($config);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('createQueryBuilder')->willReturn($qb);
        $em->method('getRepository')->willReturn($configRepo);

        $joursOuvres = $this->createMock(JoursOuvresService::class);
        $joursOuvres->method('compterJoursOuvres')->willReturn(185);

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (SendGardiennageRappelMessage $msg): bool {
                return $msg->templateCode === 'relance_gardiennage_j180' && $msg->seuilJours === 180;
            }))
            ->willReturn(new Envelope(new \stdClass()));

        $audit = $this->createMock(AuditService::class);

        $command = new RelanceClientStockageCommand($em, $joursOuvres, $bus, $audit);
        $tester = new CommandTester($command);

        self::assertSame(0, $tester->execute([]));
    }
}
