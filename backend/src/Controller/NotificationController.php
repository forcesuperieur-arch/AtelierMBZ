<?php

namespace App\Controller;

use App\Entity\Notification;
use App\Service\CurrentAtelierResolver;
use App\Service\MercureNotifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/notifications')]
#[IsGranted('ROLE_USER')]
class NotificationController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private MercureNotifier $mercureNotifier,
        private CurrentAtelierResolver $currentAtelierResolver,
    ) {}

    /**
     * GET /api/notifications?status=unread&page=1&limit=50
     */
    #[Route('', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $userId = method_exists($user, 'getId') ? (int) $user->getId() : 0;
        $userRoles = method_exists($user, 'getRoles') ? $user->getRoles() : [];

        $qb = $this->em->getRepository(Notification::class)->createQueryBuilder('n')
            ->orderBy('n.createdAt', 'DESC');

        // Filter by current atelier context
        $atelierId = $this->currentAtelierResolver->resolveAtelierId();
        if (!$atelierId && method_exists($user, 'getAtelierId') && $user->getAtelierId()) {
            $atelierId = (int) $user->getAtelierId();
        }

        if ($atelierId) {
            $qb->andWhere('n.atelierId = :atelierId')
                ->setParameter('atelierId', $atelierId);
        }

        // Filter by status
        $status = $request->query->get('status');
        if ($status === 'unread') {
            $qb->andWhere('n.readAt IS NULL');
        } elseif ($status === 'unacknowledged') {
            $qb->andWhere('n.acknowledgedAt IS NULL');
        }

        // Filter by type
        $type = $request->query->get('type');
        if ($type) {
            $qb->andWhere('n.type = :type')->setParameter('type', $type);
        }

        // Exclude expired
        $qb->andWhere('n.expiresAt IS NULL OR n.expiresAt > :now')
            ->setParameter('now', new \DateTime());

        // Pagination: fetch a larger batch to compensate for PHP-side ownership filtering
        $limit = min((int)($request->query->get('limit', 50)), 100);
        $page = max((int)($request->query->get('page', 1)), 1);
        $qb->setMaxResults($limit * 5)
            ->setFirstResult(($page - 1) * $limit);

        $notifications = $qb->getQuery()->getResult();

        // Apply ownership filter in PHP (PostgreSQL JSON DQL compatibility)
        $notifications = array_values(array_filter(
            $notifications,
            fn(Notification $n) => $this->isNotificationVisibleToUser($n, $userId, $userRoles)
        ));

        // Slice to requested limit
        $notifications = array_slice($notifications, 0, $limit);

        return $this->json([
            'items' => array_map(fn(Notification $n) => $this->serializeNotif($n), $notifications),
            'page' => $page,
            'limit' => $limit,
        ]);
    }

    /**
     * GET /api/notifications/unread-count
     */
    #[Route('/unread-count', methods: ['GET'])]
    public function unreadCount(): JsonResponse
    {
        $user = $this->getUser();
        $userId = method_exists($user, 'getId') ? (int) $user->getId() : 0;
        $userRoles = method_exists($user, 'getRoles') ? $user->getRoles() : [];

        $qb = $this->em->getRepository(Notification::class)->createQueryBuilder('n')
            ->where('n.readAt IS NULL')
            ->andWhere('n.expiresAt IS NULL OR n.expiresAt > :now')
            ->setParameter('now', new \DateTime());

        $atelierId = $this->currentAtelierResolver->resolveAtelierId();
        if (!$atelierId && method_exists($user, 'getAtelierId') && $user->getAtelierId()) {
            $atelierId = (int) $user->getAtelierId();
        }

        if ($atelierId) {
            $qb->andWhere('n.atelierId = :atelierId')
                ->setParameter('atelierId', $atelierId);
        }

        $notifications = $qb->getQuery()->getResult();
        $count = count(array_filter(
            $notifications,
            fn(Notification $n) => $this->isNotificationVisibleToUser($n, $userId, $userRoles)
        ));

        return $this->json(['count' => $count]);
    }

    /**
     * POST /api/notifications/{id}/acknowledge
     * Atomic: UPDATE WHERE acknowledged_at IS NULL → if 0 rows → 409 Conflict
     */
    #[Route('/{id}/acknowledge', methods: ['POST'])]
    public function acknowledge(int $id): JsonResponse
    {
        $user = $this->getUser();
        $userId = method_exists($user, 'getId') ? (int) $user->getId() : 0;
        $userRoles = method_exists($user, 'getRoles') ? $user->getRoles() : [];

        // Ownership check
        $notif = $this->em->getRepository(Notification::class)->find($id);
        if (!$notif || !$this->isNotificationVisibleToUser($notif, $userId, $userRoles)) {
            return $this->json(['error' => 'Notification introuvable'], Response::HTTP_NOT_FOUND);
        }

        $rowsAffected = $this->em->getConnection()->executeStatement(
            'UPDATE notifications SET acknowledged_at = NOW(), acknowledged_by = :userId WHERE id = :id AND acknowledged_at IS NULL',
            ['userId' => $userId, 'id' => $id],
        );

        if ($rowsAffected === 0) {
            return $this->json(
                ['error' => 'Notification déjà acquittée'],
                Response::HTTP_CONFLICT,
            );
        }

        $this->em->refresh($notif);
        try {
            $this->mercureNotifier->publishAcknowledged(
                $notif->getAtelierId() ?? 0,
                $id,
                $userId,
            );
        } catch (\Throwable $e) {
            // Mercure failure is non-blocking
        }

        return $this->json(['acknowledged' => true, 'id' => $id]);
    }

    /**
     * POST /api/notifications/{id}/mark-read
     */
    #[Route('/{id}/mark-read', methods: ['POST'])]
    public function markRead(int $id): JsonResponse
    {
        $user = $this->getUser();
        $userId = method_exists($user, 'getId') ? (int) $user->getId() : 0;
        $userRoles = method_exists($user, 'getRoles') ? $user->getRoles() : [];

        $notif = $this->em->getRepository(Notification::class)->find($id);
        if (!$notif || !$this->isNotificationVisibleToUser($notif, $userId, $userRoles)) {
            return $this->json(['error' => 'Notification introuvable'], Response::HTTP_NOT_FOUND);
        }

        if ($notif->getReadAt() !== null) {
            return $this->json(['already_read' => true, 'id' => $id]);
        }

        $notif->setReadAt(new \DateTime());
        $notif->setReadBy($userId);
        $notif->setIsRead(true);
        $this->em->flush();

        return $this->json(['read' => true, 'id' => $id]);
    }

    /**
     * Apply ownership filter using native SQL for PostgreSQL JSON column compatibility.
     * User sees: their own targeted notifs + broadcasts (null target, empty roles) + role-matched notifs.
     *
     * @deprecated Filtering is now done in PHP after fetch for PostgreSQL JSON compatibility.
     */
    private function applyOwnershipFilter(\Doctrine\ORM\QueryBuilder $qb, int $userId, array $userRoles): void
    {
        // No-op: ownership filtering moved to PHP-side isNotificationVisibleToUser()
        // to avoid DQL/PostgreSQL JSON operator incompatibility.
    }

    /**
     * Check if a single notification is visible to a given user.
     */
    private function isNotificationVisibleToUser(Notification $notif, int $userId, array $userRoles): bool
    {
        // Super admin bypass
        if (in_array('ROLE_SUPER_ADMIN', $userRoles, true)) {
            return true;
        }

        // Directly targeted
        if ($notif->getTargetUserId() === $userId) {
            return true;
        }

        // Broadcast (no specific target)
        if ($notif->getTargetUserId() === null && $notif->getTargetRole() === null && ($notif->getTargetRoles() === [] || $notif->getTargetRoles() === null)) {
            return true;
        }

        // Role match on single targetRole
        if ($notif->getTargetRole() !== null && in_array($notif->getTargetRole(), $userRoles, true)) {
            return true;
        }

        // Role match on targetRoles JSON array
        if ($notif->getTargetRoles() !== []) {
            foreach ($notif->getTargetRoles() as $role) {
                if (in_array($role, $userRoles, true)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function serializeNotif(Notification $n): array
    {
        return [
            'id' => $n->getId(),
            'type' => $n->getType(),
            'severity' => $n->getSeverity(),
            'title' => $n->getTitle(),
            'message' => $n->getMessage(),
            'actionUrl' => $n->getActionUrl(),
            'relatedEntityType' => $n->getRelatedEntityType(),
            'relatedEntityId' => $n->getRelatedEntityId(),
            'targetRoles' => $n->getTargetRoles(),
            'isRead' => $n->getIsRead(),
            'readAt' => $n->getReadAt()?->format('c'),
            'acknowledgedAt' => $n->getAcknowledgedAt()?->format('c'),
            'priority' => $n->getPriority(),
            'createdAt' => $n->getCreatedAt()->format('c'),
            'expiresAt' => $n->getExpiresAt()?->format('c'),
        ];
    }
}
