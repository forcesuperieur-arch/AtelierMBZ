<?php

namespace App\Service;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Resolves role strings into actual phone numbers of active users.
 */
class RolePhoneResolver
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {}

    /**
     * @return string[] Phone numbers of active users having the given role in the atelier.
     */
    public function resolvePhones(string $role, int $atelierId): array
    {
        $users = $this->em->getRepository(User::class)
            ->createQueryBuilder('u')
            ->where('u.atelierId = :atId')
            ->andWhere('u.isActive = 1')
            ->andWhere('u.phoneNumber IS NOT NULL')
            ->andWhere('u.roles LIKE :role')
            ->setParameter('atId', $atelierId)
            ->setParameter('role', '%' . $role . '%')
            ->getQuery()
            ->getResult();

        return array_values(array_filter(array_map(
            fn(User $u) => $u->getPhoneNumber(),
            $users
        )));
    }
}
