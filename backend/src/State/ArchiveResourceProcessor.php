<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\User;
use App\Service\UserArchiveService;
use Doctrine\ORM\EntityManagerInterface;

final class ArchiveResourceProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserArchiveService $userArchiveService,
    ) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if ($data instanceof User) {
            $this->userArchiveService->archive($data);
            $this->em->persist($data);
            $this->em->flush();

            return null;
        }

        if (is_object($data) && method_exists($data, 'setIsActive')) {
            $data->setIsActive(0);
            $this->em->persist($data);
            $this->em->flush();
        }

        return null;
    }
}
