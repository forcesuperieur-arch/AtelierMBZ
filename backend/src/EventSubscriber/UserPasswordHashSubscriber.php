<?php

namespace App\EventSubscriber;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsDoctrineListener(event: Events::prePersist)]
#[AsDoctrineListener(event: Events::preUpdate)]
final class UserPasswordHashSubscriber
{
    public function __construct(private UserPasswordHasherInterface $passwordHasher)
    {
    }

    public function prePersist(PrePersistEventArgs $args): void
    {
        $this->hashPassword($args->getObject());
    }

    public function preUpdate(PreUpdateEventArgs $args): void
    {
        $entity = $args->getObject();
        $this->hashPassword($entity);

        if ($entity instanceof User) {
            $entityManager = $args->getObjectManager();
            if ($entityManager instanceof EntityManagerInterface) {
                $metadata = $entityManager->getClassMetadata(User::class);
                $entityManager->getUnitOfWork()->recomputeSingleEntityChangeSet($metadata, $entity);
            }
        }
    }

    private function hashPassword(object $entity): void
    {
        if (!$entity instanceof User) {
            return;
        }

        $plainPassword = $entity->getPlainPassword();
        if (!$plainPassword) {
            return;
        }

        $entity->setHashedPassword($this->passwordHasher->hashPassword($entity, $plainPassword));
        $entity->eraseCredentials();
    }
}
