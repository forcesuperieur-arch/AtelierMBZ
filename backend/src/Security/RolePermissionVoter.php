<?php
namespace App\Security;

use App\Entity\RolePermission;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Checks granular permissions based on the role_permissions table.
 * Attribute format: "PERM_<permission>" e.g. PERM_rdv.edit, PERM_facturation.create
 */
class RolePermissionVoter extends Voter
{
    private array $cache = [];

    public function __construct(private EntityManagerInterface $em) {}

    protected function supports(string $attribute, mixed $subject): bool
    {
        return str_starts_with($attribute, 'PERM_');
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user || !method_exists($user, 'getRole')) {
            return false;
        }

        // Super admins have all permissions
        if (in_array('ROLE_SUPER_ADMIN', $user->getRoles(), true)) {
            return true;
        }

        $permission = substr($attribute, 5); // Remove "PERM_" prefix
        $role = $user->getRole();

        return $this->hasPermission($role, $permission);
    }

    private function hasPermission(string $role, string $permission): bool
    {
        if (!isset($this->cache[$role])) {
            $roleEntity = $this->em->getRepository(RolePermission::class)->find($role);
            if (!$roleEntity) {
                $this->cache[$role] = [];
                return false;
            }
            $this->cache[$role] = $roleEntity->getPermissions();
        }

        return in_array($permission, $this->cache[$role], true);
    }
}
