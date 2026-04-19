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
        private BookingAtelierAccessService $bookingAtelierAccess,
    ) {}

    public function resolveAtelierId(): ?int
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return null;
        }

        $request = $this->requestStack->getCurrentRequest();
        $bodyAtelierId = null;

        if ($request) {
            $contentType = strtolower((string) $request->headers->get('Content-Type', ''));
            if (str_contains($contentType, 'json')) {
                $payload = json_decode($request->getContent(), true);
                if (is_array($payload)) {
                    $bodyAtelierId = $payload['atelier_id'] ?? $payload['atelierId'] ?? null;
                }
            }
        }

        $requestedValue = $request?->query->get('atelier_id')
            ?? $request?->request->get('atelier_id')
            ?? $bodyAtelierId
            ?? $request?->cookies->get('active_atelier_id');

        $requestedAtelierId = is_scalar($requestedValue) && ctype_digit((string) $requestedValue)
            ? (int) $requestedValue
            : null;

        $atelierId = $this->bookingAtelierAccess->resolvePreferredAtelierId($user, $requestedAtelierId);

        return $atelierId && $atelierId > 0 ? $atelierId : null;
    }

    public function isGlobalScopeRequested(): bool
    {
        return false;
    }
}
