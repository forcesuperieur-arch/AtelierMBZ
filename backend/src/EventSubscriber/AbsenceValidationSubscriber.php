<?php

namespace App\EventSubscriber;

use App\Entity\Absence;
use App\Service\AbsenceConflictChecker;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

#[AsDoctrineListener(event: Events::prePersist)]
#[AsDoctrineListener(event: Events::preUpdate)]
final class AbsenceValidationSubscriber
{
    public function __construct(private AbsenceConflictChecker $checker) {}

    public function prePersist(PrePersistEventArgs $args): void
    {
        $this->validate($args->getObject(), $args->getObjectManager());
    }

    public function preUpdate(PreUpdateEventArgs $args): void
    {
        $this->validate($args->getObject(), $args->getObjectManager());
    }

    private function validate(object $entity, object $objectManager): void
    {
        if (!$entity instanceof Absence || !$objectManager instanceof EntityManagerInterface) {
            return;
        }

        $start = \DateTimeImmutable::createFromInterface($entity->getDateDebut());
        $end = \DateTimeImmutable::createFromInterface($entity->getDateFin());

        if (!$this->checker->isDateRangeValid($start, $end)) {
            throw new BadRequestHttpException('La date de fin doit être postérieure ou égale à la date de début.');
        }

        $qb = $objectManager->getRepository(Absence::class)->createQueryBuilder('a')
            ->where('a.mecanicien = :mecanicien')
            ->andWhere('a.dateDebut <= :end')
            ->andWhere('a.dateFin >= :start')
            ->setParameter('mecanicien', $entity->getMecanicien())
            ->setParameter('start', $start)
            ->setParameter('end', $end);

        if ($entity->getId() !== null) {
            $qb->andWhere('a.id != :currentId')->setParameter('currentId', $entity->getId());
        }

        $rows = $qb->getQuery()->getResult();
        $ranges = array_map(static fn (Absence $absence) => [
            'start' => $absence->getDateDebut(),
            'end' => $absence->getDateFin(),
        ], $rows);

        if ($this->checker->hasConflict($start, $end, $ranges)) {
            throw new ConflictHttpException('Une absence existe déjà sur cette période pour ce mécanicien.');
        }
    }
}
