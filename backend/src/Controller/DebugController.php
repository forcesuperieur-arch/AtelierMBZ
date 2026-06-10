<?php
namespace App\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/debug')]
class DebugController
{
    #[Route('/tenant', methods: ['GET'])]
    public function tenant(EntityManagerInterface $em, Security $security): JsonResponse
    {
        $filters = $em->getFilters();
        $user = $security->getUser();
        
        $data = [
            'user_class' => $user ? get_class($user) : null,
            'user_roles' => $user ? $user->getRoles() : [],
            'user_atelier_id' => $user && method_exists($user, 'getAtelierId') ? $user->getAtelierId() : null,
            'tenant_filter_enabled' => $filters->isEnabled('tenant_filter'),
        ];
        
        if ($filters->isEnabled('tenant_filter')) {
            try {
                $data['tenant_filter_atelier_id'] = $filters->getFilter('tenant_filter')->getParameter('atelier_id');
            } catch (\Throwable $e) {
                $data['tenant_filter_error'] = $e->getMessage();
            }
        }
        
        return new JsonResponse($data);
    }
}
