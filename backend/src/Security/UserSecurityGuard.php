<?php

namespace App\Security;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class UserSecurityGuard
{
    public function __construct(
        private EntityManagerInterface $em,
        private Security $security,
    ) {}

    /**
     * Prevent deleting or downgrading the last super_admin.
     */
    public function ensureLastSuperAdmin(User $targetUser, ?string $newRole = null): void
    {
        $isCurrentlySuperAdmin = $targetUser->getRole() === 'super_admin';
        if (!$isCurrentlySuperAdmin) {
            return;
        }

        // If role is being changed away from super_admin, or user is being deleted
        $isDowngrade = $newRole !== null && $newRole !== 'super_admin';
        $isDelete = $newRole === null;

        if (!$isDowngrade && !$isDelete) {
            return;
        }

        $count = $this->em->createQueryBuilder()
            ->select('COUNT(u.id)')
            ->from(User::class, 'u')
            ->where('u.role = :role')
            ->andWhere('u.isActive = 1')
            ->setParameter('role', 'super_admin')
            ->getQuery()
            ->getSingleScalarResult();

        if ((int) $count <= 1) {
            throw new AccessDeniedHttpException('Au moins un SuperAdmin doit rester actif.');
        }
    }

    /**
     * Prevent privilege escalation: user cannot assign a role higher than their own.
     */
    public function preventEscalation(string $targetRole): void
    {
        $currentUser = $this->security->getUser();
        if (!$currentUser instanceof User) {
            return;
        }

        // Super admin can assign any role
        if (in_array('ROLE_SUPER_ADMIN', $currentUser->getRoles(), true)) {
            return;
        }

        $hierarchy = ['receptionnaire' => 1, 'mecanicien' => 1, 'comptable' => 1, 'vo_manager' => 1, 'service_client' => 1, 'admin' => 2, 'super_admin' => 3];
        $currentLevel = $hierarchy[$currentUser->getRole()] ?? 0;
        $targetLevel = $hierarchy[$targetRole] ?? 0;

        if ($targetLevel > $currentLevel) {
            throw new AccessDeniedHttpException('Vous ne pouvez pas attribuer un rôle supérieur au vôtre.');
        }
    }
}
