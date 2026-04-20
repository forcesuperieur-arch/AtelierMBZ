<?php

namespace App\Tests\Unit;

use App\Command\ProcessNotificationEscalationsCommand;
use App\Entity\Notification;
use App\Entity\NotificationEscalation;
use App\Service\NotificationDispatcher;
use App\Service\NotificationMessage;
use App\Service\NotificationResult;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Tester\CommandTester;
use App\Service\MercureNotifier;

#[AllowMockObjectsWithoutExpectations]
class ProcessNotificationEscalationsCommandTest extends TestCase
{
    public function testEmailEscalationUsesDispatcherAndMarksSuccess(): void
    {
        $notification = (new Notification())
            ->setAtelierId(1)
            ->setType('relance_stockage')
            ->setTitle('Relance client')
            ->setMessage('Votre moto est prête à être récupérée.');

        $escalation = (new NotificationEscalation())
            ->setNotification($notification)
            ->setChannel('email')
            ->setScheduledAt(new \DateTimeImmutable('-5 minutes'))
            ->setTargetInfo('client@example.com');

        $query = $this->createMock(Query::class);
        $query->method('getResult')->willReturn([$escalation]);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('join')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('orderBy')->willReturnSelf();
        $qb->method('setMaxResults')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('createQueryBuilder')->willReturn($qb);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repository);
        $em->expects($this->once())->method('flush');

        $mercure = $this->createMock(MercureNotifier::class);
        $logger = $this->createMock(LoggerInterface::class);

        $dispatcher = $this->createMock(NotificationDispatcher::class);
        $dispatcher
            ->expects($this->once())
            ->method('send')
            ->with($this->callback(function (NotificationMessage $message): bool {
                return $message->getChannel() === 'email'
                    && $message->getAtelierId() === 1
                    && $message->getRecipient() === 'client@example.com'
                    && str_contains($message->getBody(), 'prête');
            }))
            ->willReturn(NotificationResult::ok('smtp_custom', 'mail-123'));

        $command = new ProcessNotificationEscalationsCommand($em, $mercure, $dispatcher, $logger);
        $tester = new CommandTester($command);

        self::assertSame(0, $tester->execute([]));
        self::assertSame('success', $escalation->getResult());
        self::assertNotNull($escalation->getExecutedAt());
    }
}
