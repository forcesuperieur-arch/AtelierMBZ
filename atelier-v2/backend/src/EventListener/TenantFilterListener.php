<?php
namespace App\EventListener;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Activates the TenantFilter on every request based on the authenticated user's atelier_id.
 * Super admins bypass the filter to see all data.
 */
#[AsEventListener(event: 'kernel.request', priority: -10)]
class TenantFilterListener
{
    public function __construct(
        private EntityManagerInterface $em,
        private TokenStorageInterface $tokenStorage,
    ) {}

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $token = $this->tokenStorage->getToken();
        if (!$token) {
            return;
        }

        $user = $token->getUser();
        if (!$user instanceof User) {
            return;
        }

        // Super admins see all data — no tenant filter
        if (in_array('ROLE_SUPER_ADMIN', $user->getRoles(), true)) {
            return;
        }

        $atelierId = $user->getAtelierId();
        if ($atelierId === null) {
            return;
        }

        $filter = $this->em->getFilters()->enable('tenant_filter');
        $filter->setParameter('atelier_id', $atelierId);
    }
}
