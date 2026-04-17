<?php

namespace App\EventSubscriber;

use App\Entity\RoleMetier;
use App\Entity\User;
use App\Service\UserRoleMapper;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;

#[AsDoctrineListener(event: Events::prePersist)]
#[AsDoctrineListener(event: Events::preUpdate)]
final class UserRoleMetierSyncSubscriber
{
    public function __construct(private UserRoleMapper $roleMapper) {}

    public function prePersist(PrePersistEventArgs $args): void
    {
        $this->syncRoleMetier($args->getObject(), $args->getObjectManager());
    }

    public function preUpdate(PreUpdateEventArgs $args): void
    {
        $entity = $args->getObject();
        $this->syncRoleMetier($entity, $args->getObjectManager());

        if ($entity instanceof User) {
            $entityManager = $args->getObjectManager();
            if ($entityManager instanceof EntityManagerInterface) {
                $metadata = $entityManager->getClassMetadata(User::class);
                $entityManager->getUnitOfWork()->recomputeSingleEntityChangeSet($metadata, $entity);
            }
        }
    }

    private function syncRoleMetier(object $entity, object $objectManager): void
    {
        if (!$entity instanceof User || !$objectManager instanceof EntityManagerInterface) {
            return;
        }

        if ($entity->getAccessStatus() === 'pending_validation') {
            $entity->setIsActive(0);
        }

        if ($entity->getAuthProvider() === '') {
            $entity->setAuthProvider('local');
        }

        if ($entity->getAccessStatus() === '') {
            $entity->setAccessStatus($entity->getIsActive() ? 'active' : 'disabled');
        }

        if ($entity->getAccessStatus() === 'archived') {
            $entity->setRoleMetier(null);
            $entity->setRole('user');
            return;
        }

        if ($entity->getRole() === 'super_admin') {
            return;
        }

        if ($entity->getRoleMetier() !== null) {
            $entity->setRole($this->roleMapper->mapRoleMetierToLegacyRole($entity->getRoleMetier()));
            return;
        }

        $targetCode = $this->roleMapper->mapLegacyRoleToRoleMetierCode($entity->getRole());
        if ($targetCode === null) {
            return;
        }

        $repo = $objectManager->getRepository(RoleMetier::class);
        $roleMetier = $repo->findOneBy([
            'atelierId' => $entity->getAtelierId(),
            'code' => $targetCode,
        ]);

        if ($roleMetier === null) {
            $roleMetier = $repo->findOneBy([
                'atelierId' => null,
                'code' => $targetCode,
            ]);
        }

        if ($roleMetier !== null) {
            $entity->setRoleMetier($roleMetier);
        }
    }
}
