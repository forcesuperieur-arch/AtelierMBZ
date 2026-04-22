<?php

namespace App\Tests\Unit;

use App\Entity\ConfigAtelier;
use App\Entity\HoraireAtelier;
use App\Service\SlotService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class SlotServiceClosureTest extends TestCase
{
    public function testReturnsEmptyForWeeklyClosureDay(): void
    {
        $config = new ConfigAtelier();
        $config->setJoursFermetureHebdo(['sunday']);

        // Use a known Sunday
        $sunday = new \DateTimeImmutable('2025-01-05'); // 2025-01-05 is a Sunday
        self::assertSame('Sunday', $sunday->format('l'), 'Test date must be a Sunday');

        $configRepo = $this->createMock(EntityRepository::class);
        $configRepo->method('findOneBy')->willReturn($config);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($configRepo);

        $service = new SlotService($em);
        $slots = $service->getSlotsForDay($sunday, 60, 1);

        self::assertEmpty($slots, 'Slots must be empty on a weekly closure day');
    }

    public function testReturnsEmptyForExceptionalClosureDate(): void
    {
        $config = new ConfigAtelier();
        $config->setJoursFermetureHebdo([]);
        $config->setDatesFermetureExceptionnelles(['2025-12-25']);

        $noel = new \DateTimeImmutable('2025-12-25');

        $configRepo = $this->createMock(EntityRepository::class);
        $configRepo->method('findOneBy')->willReturn($config);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($configRepo);

        $service = new SlotService($em);
        $slots = $service->getSlotsForDay($noel, 60, 1);

        self::assertEmpty($slots, 'Slots must be empty on an exceptional closure date');
    }

    public function testDoesNotFilterWhenNoAtelierIdProvided(): void
    {
        // When atelierId is null, the closure check is skipped entirely.
        // The function should fall through to the horaire check (which returns nothing for no horaire).
        $em = $this->createMock(EntityManagerInterface::class);

        $query = $this->createMock(Query::class);
        $query->method('getOneOrNullResult')->willReturn(null); // No HoraireAtelier found

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('where')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('setMaxResults')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $repo = $this->createMock(EntityRepository::class);
        $repo->method('createQueryBuilder')->willReturn($qb);

        $em->method('getRepository')->willReturn($repo);

        $service = new SlotService($em);
        $sunday = new \DateTimeImmutable('2025-01-05');
        $slots = $service->getSlotsForDay($sunday, 60, null);

        // No atelierId → no closure check → falls through to HoraireAtelier (not found → empty)
        self::assertEmpty($slots);
    }
}
