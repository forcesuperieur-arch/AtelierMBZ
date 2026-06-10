<?php

namespace App\Controller;

use App\Entity\AuditLog;
use App\Service\CurrentAtelierResolver;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/admin/audit-logs')]
#[IsGranted('ROLE_ADMIN')]
class AdminAuditController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private CurrentAtelierResolver $atelierResolver,
    ) {}

    #[Route('', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $atelierId = $this->atelierResolver->resolveAtelierId();
        $isSuperAdmin = $this->isGranted('ROLE_SUPER_ADMIN');

        $qb = $this->em->getRepository(AuditLog::class)->createQueryBuilder('a')
            ->orderBy('a.createdAt', 'DESC');

        if ($isSuperAdmin && !$atelierId) {
            // Super admin sans atelier actif → voir tout
        } else {
            $effectiveAtelierId = $atelierId ?? 0;
            if ($effectiveAtelierId > 0) {
                $qb->andWhere('a.atelierId = :atelierId OR a.atelierId IS NULL')
                   ->setParameter('atelierId', $effectiveAtelierId);
            } else {
                $qb->andWhere('a.atelierId IS NULL');
            }
        }

        // Filter by action
        $action = $request->query->get('action');
        if ($action) {
            $qb->andWhere('a.action = :action')->setParameter('action', $action);
        }

        // Filter by date range (on createdAt)
        $dateFrom = $request->query->get('dateFrom');
        if ($dateFrom) {
            $qb->andWhere('a.createdAt >= :dateFrom')->setParameter('dateFrom', new \DateTime($dateFrom . ' 00:00:00'));
        }
        $dateTo = $request->query->get('dateTo');
        if ($dateTo) {
            $qb->andWhere('a.createdAt <= :dateTo')->setParameter('dateTo', new \DateTime($dateTo . ' 23:59:59'));
        }

        // Search
        $search = $request->query->get('search');
        if ($search) {
            $qb->andWhere(
                'a.username LIKE :search OR a.entityType LIKE :search OR a.details LIKE :search'
            )->setParameter('search', '%' . $search . '%');
        }

        $page = max((int)$request->query->get('page', 1), 1);
        $limit = min((int)$request->query->get('limit', 50), 200);

        $countQb = clone $qb;
        $total = (int) $countQb->select('COUNT(a.id)')->resetDQLPart('orderBy')->getQuery()->getSingleScalarResult();

        $qb->setMaxResults($limit)->setFirstResult(($page - 1) * $limit);
        $logs = $qb->getQuery()->getResult();

        return $this->json([
            'items' => array_map(fn(AuditLog $l) => [
                'id' => $l->getId(),
                'createdAt' => $l->getCreatedAt()->format('c'),
                'action' => $l->getAction(),
                'entityType' => $l->getEntityType(),
                'entityId' => $l->getEntityId(),
                'description' => $l->getDetails(),
                'userEmail' => $l->getUsername(),
                'ipAddress' => $l->getIpAddress(),
                'atelierId' => $l->getAtelierId(),
            ], $logs),
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'totalPages' => (int) ceil($total / $limit),
        ]);
    }
}
