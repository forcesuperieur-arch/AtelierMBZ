<?php
namespace App\Security;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Ensures users can only access entities belonging to their atelier.
 * Votes on any entity that has an atelierId property.
 */
class AtelierVoter extends Voter
{
    public const ACCESS = 'ATELIER_ACCESS';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $attribute === self::ACCESS && is_object($subject) && method_exists($subject, 'getAtelierId');
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user || !method_exists($user, 'getAtelierId')) {
            return false;
        }

        // Super admins bypass tenant check
        if (in_array('ROLE_SUPER_ADMIN', $user->getRoles(), true)) {
            return true;
        }

        $entityAtelierId = $subject->getAtelierId();

        // Entities without atelier_id (global) are accessible
        if ($entityAtelierId === null) {
            return true;
        }

        return $entityAtelierId === $user->getAtelierId();
    }
}
