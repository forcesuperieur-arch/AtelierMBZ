<?php
namespace App\Security;

use App\Entity\RolePermission;
use App\Entity\RolePermissionEntry;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Checks granular permissions via RoleMetier + RolePermissionEntry.
 * New format: perm.MODULE.ACTION (e.g. perm.facture.edit)
 * Legacy format: PERM_<permission> (backward compat with role_permissions JSON)
 */
class RolePermissionVoter extends Voter
{
    private const LEGACY_PERMISSION_MAP = [
        'rdv.create' => 'perm.rdv.create',
        'rdv.edit' => 'perm.rdv.edit',
        'rdv.delete' => 'perm.rdv.delete',
        'client.create' => 'perm.clients.create',
        'client.edit' => 'perm.clients.edit',
        'client.delete' => 'perm.clients.delete',
        'or.edit' => 'perm.or.edit',
        'facturation.create' => 'perm.facturation.create',
        'facturation.edit' => 'perm.facturation.edit',
        'stock.create' => 'perm.stock.create',
        'stock.edit' => 'perm.stock.edit',
        'stock.delete' => 'perm.stock.delete',
        'admin.users' => 'perm.admin.view',
        'admin.config' => 'perm.admin.view',
        'admin.roles' => 'perm.admin.view',
    ];

    public function __construct(private EntityManagerInterface $em) {}

    protected function supports(string $attribute, mixed $subject): bool
    {
        return str_starts_with($attribute, 'perm.') || str_starts_with($attribute, 'PERM_');
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        // Super admins bypass all permission checks
        if (in_array('ROLE_SUPER_ADMIN', $user->getRoles(), true)) {
            return true;
        }

        // New perm.MODULE.ACTION format
        if (str_starts_with($attribute, 'perm.')) {
            return $this->checkNewPermission($attribute, $subject, $user);
        }

        // Legacy PERM_ format
        return $this->checkLegacyPermission($attribute, $user);
    }

    private function checkNewPermission(string $attribute, mixed $subject, User $user): bool
    {
        $parts = explode('.', $attribute);
        if (count($parts) !== 3) {
            return false;
        }
        [, $module, $action] = $parts;

        $roleMetier = $user->getRoleMetier();
        if ($roleMetier === null || !$roleMetier->isActive()) {
            return false;
        }

        $entry = $roleMetier->getPermissionEntry($module, $action);
        if ($entry === null || !$entry->isGranted()) {
            return false;
        }

        return $this->checkScope($entry, $subject, $user);
    }

    private function checkScope(RolePermissionEntry $entry, mixed $subject, User $user): bool
    {
        return match ($entry->getScope()) {
            'all' => true,
            'atelier' => true, // TenantFilter handles atelier-level isolation
            'team' => true,
            'own' => $this->isOwner($subject, $user),
            default => true,
        };
    }

    private function isOwner(mixed $subject, User $user): bool
    {
        if ($subject === null) {
            return true; // No subject to check (e.g. create action)
        }
        if (is_object($subject)) {
            if (method_exists($subject, 'getCreatedBy') && $subject->getCreatedBy() === $user->getId()) {
                return true;
            }
            if (method_exists($subject, 'getUserId') && $subject->getUserId() === $user->getId()) {
                return true;
            }
            if (method_exists($subject, 'getMecanicienId') && $subject->getMecanicienId() === $user->getId()) {
                return true;
            }
        }
        return false;
    }

    private function checkLegacyPermission(string $attribute, User $user): bool
    {
        $permission = substr($attribute, 5); // Remove "PERM_" prefix

        if (isset(self::LEGACY_PERMISSION_MAP[$permission])) {
            return $this->checkNewPermission(self::LEGACY_PERMISSION_MAP[$permission], null, $user);
        }

        $roleEntity = $this->em->getRepository(RolePermission::class)->find($user->getRole());
        if (!$roleEntity) {
            return false;
        }

        return in_array($permission, $roleEntity->getPermissions(), true);
    }
}
