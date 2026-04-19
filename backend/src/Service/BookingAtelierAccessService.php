<?php

namespace App\Service;

use App\Entity\Atelier;
use App\Entity\ConfigAtelier;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class BookingAtelierAccessService
{
    public const FEATURE_RDV_SIEGE = 'rdv_siege';

    public function __construct(private EntityManagerInterface $em)
    {
    }

    /**
     * @return list<Atelier>
     */
    public function getAllowedAteliers(User $user): array
    {
        return $this->withoutTenantFilter(function () use ($user): array {
            if ($this->isSuperAdmin($user)) {
                return $this->em->getRepository(Atelier::class)->findBy(['actif' => true], ['nom' => 'ASC']);
            }

            if ($this->isServiceClient($user)) {
                $allowed = [];
                $assignedAtelierId = $user->getAtelierId();

                if ($assignedAtelierId) {
                    $assignedAtelier = $this->em->getRepository(Atelier::class)->find($assignedAtelierId);
                    if ($assignedAtelier instanceof Atelier && $assignedAtelier->isActif()) {
                        $allowed[(int) $assignedAtelier->getId()] = $assignedAtelier;
                    }
                }

                $ateliers = $this->em->getRepository(Atelier::class)->findBy(['actif' => true], ['nom' => 'ASC']);
                foreach ($ateliers as $atelier) {
                    if ($this->isSiegeBookingEnabledForAtelier((int) $atelier->getId())) {
                        $allowed[(int) $atelier->getId()] = $atelier;
                    }
                }

                return array_values($allowed);
            }

            $atelierId = $user->getAtelierId();
            if (!$atelierId) {
                return [];
            }

            $atelier = $this->em->getRepository(Atelier::class)->find($atelierId);

            return $atelier instanceof Atelier ? [$atelier] : [];
        });
    }

    public function canAccessAtelier(User $user, ?int $atelierId): bool
    {
        if (!$atelierId) {
            return false;
        }

        foreach ($this->getAllowedAteliers($user) as $atelier) {
            if ((int) $atelier->getId() === $atelierId) {
                return true;
            }
        }

        return false;
    }

    public function resolvePreferredAtelierId(User $user, ?int $requestedAtelierId = null): ?int
    {
        if ($requestedAtelierId && $this->canAccessAtelier($user, $requestedAtelierId)) {
            return $requestedAtelierId;
        }

        if (!$this->isServiceClient($user)) {
            $atelierId = $user->getAtelierId();
            return $atelierId && $atelierId > 0 ? $atelierId : null;
        }

        $allowed = $this->getAllowedAteliers($user);
        if ($allowed !== []) {
            return (int) $allowed[0]->getId();
        }

        $fallbackAtelierId = $user->getAtelierId();
        return $fallbackAtelierId && $fallbackAtelierId > 0 ? $fallbackAtelierId : null;
    }

    public function isServiceClient(User $user): bool
    {
        return in_array('ROLE_SERVICE_CLIENT', $user->getRoles(), true)
            || strtolower((string) $user->getRole()) === 'service_client';
    }

    public function isSuperAdmin(User $user): bool
    {
        return in_array('ROLE_SUPER_ADMIN', $user->getRoles(), true)
            || strtolower((string) $user->getRole()) === 'super_admin';
    }

    private function isSiegeBookingEnabledForAtelier(int $atelierId): bool
    {
        if ($atelierId <= 0) {
            return false;
        }

        $config = $this->em->getRepository(ConfigAtelier::class)->findOneBy(['atelierId' => $atelierId]);
        $modules = $config?->getFeatureModules() ?? ConfigAtelier::defaultFeatureModules();

        $rdvEnabled = ($modules['rdv'] ?? true) !== false;
        $siegeEnabled = ($modules[self::FEATURE_RDV_SIEGE] ?? false) === true;

        return $rdvEnabled && $siegeEnabled;
    }

    private function withoutTenantFilter(callable $callback): array
    {
        $filters = $this->em->getFilters();
        $wasEnabled = $filters->isEnabled('tenant_filter');
        $currentAtelierId = null;

        if ($wasEnabled) {
            $filter = $filters->getFilter('tenant_filter');
            try {
                $currentAtelierId = trim((string) $filter->getParameter('atelier_id'), "'");
            } catch (\Throwable) {
                $currentAtelierId = null;
            }

            $filters->disable('tenant_filter');
        }

        try {
            return $callback();
        } finally {
            if ($wasEnabled) {
                $filter = $filters->enable('tenant_filter');
                if ($currentAtelierId !== null && $currentAtelierId !== '') {
                    $filter->setParameter('atelier_id', (int) $currentAtelierId);
                }
            }
        }
    }
}
