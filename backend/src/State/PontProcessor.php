<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Pont;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Validates the "1 mechanic = 1 lift max" rule before persisting a Pont.
 */
final class PontProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private TokenStorageInterface $tokenStorage,
    ) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof Pont) {
            return $data;
        }

        $mecanicien = $data->getMecanicien();
        if ($mecanicien !== null) {
            $qb = $this->em->getRepository(Pont::class)->createQueryBuilder('p')
                ->where('p.mecanicien = :mecanicien')
                ->andWhere('p.id != :pontId')
                ->setParameter('mecanicien', $mecanicien)
                ->setParameter('pontId', $data->getId() ?? 0)
                ->setMaxResults(1);

            $existing = $qb->getQuery()->getOneOrNullResult();
            if ($existing !== null) {
                throw new BadRequestHttpException(
                    sprintf(
                        'Le mécanicien %s %s est déjà assigné au pont "%s". Un mécanicien ne peut être assigné qu\'à un seul pont.',
                        $mecanicien->getPrenom(),
                        $mecanicien->getNom(),
                        $existing->getNom()
                    )
                );
            }
        }

        $this->em->persist($data);
        $this->em->flush();

        return $data;
    }
}
