<?php

namespace App\Service;

use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

class CurrentAtelierResolver
{
    public function __construct(
        private Security $security,
        private RequestStack $requestStack,
    ) {}

    public function resolveAtelierId(): ?int
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return null;
        }

        if ($this->isSuperAdmin($user)) {
            $selectedAtelierId = $this->requestStack->getCurrentRequest()?->cookies->get('active_atelier_id');
            if (is_string($selectedAtelierId) && ctype_digit($selectedAtelierId)) {
                $atelierId = (int) $selectedAtelierId;
                if ($atelierId > 0) {
                    return $atelierId;
                }
            }
        }

        $atelierId = $user->getAtelierId();

        return $atelierId && $atelierId > 0 ? $atelierId : null;
    }

    public function isGlobalScopeRequested(): bool
    {
        return false;
    }

    private function isSuperAdmin(User $user): bool
    {
        return in_array('ROLE_SUPER_ADMIN', $user->getRoles(), true);
    }
}
