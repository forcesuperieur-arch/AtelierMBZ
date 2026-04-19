<?php
namespace App\EventListener;

use App\Entity\Atelier;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Activates the TenantFilter on every request based on the authenticated user's atelier_id.
 * Super admins must also work inside a concrete atelier context.
 */
#[AsEventListener(event: 'kernel.request', priority: -10)]
class TenantFilterListener
{
    public function __construct(
        private EntityManagerInterface $em,
        private TokenStorageInterface $tokenStorage,
        private RequestStack $requestStack,
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

        // SuperAdmin: always resolve to one concrete atelier, never to a global cross-atelier scope.
        if (in_array('ROLE_SUPER_ADMIN', $user->getRoles(), true)) {
            $request = $this->requestStack->getCurrentRequest();
            $selectedAtelierId = $request?->cookies->get('active_atelier_id');

            $activeAtelierId = null;
            if (is_string($selectedAtelierId) && ctype_digit($selectedAtelierId)) {
                $parsedAtelierId = (int) $selectedAtelierId;
                if ($parsedAtelierId > 0) {
                    $activeAtelierId = $parsedAtelierId;
                }
            }

            if ($activeAtelierId === null) {
                $activeAtelierId = $user->getAtelierId();
            }

            if ($activeAtelierId === null) {
                $fallbackAtelier = $this->em->getRepository(Atelier::class)->findOneBy(['actif' => true], ['id' => 'ASC'])
                    ?? $this->em->getRepository(Atelier::class)->findOneBy([], ['id' => 'ASC']);
                $activeAtelierId = $fallbackAtelier?->getId();
            }

            if ($activeAtelierId === null) {
                return;
            }

            $filter = $this->em->getFilters()->enable('tenant_filter');
            $filter->setParameter('atelier_id', (int) $activeAtelierId);
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
