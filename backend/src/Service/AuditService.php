<?php
namespace App\Service;

use App\Entity\AuditLog;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Records audit trail entries for critical business actions.
 */
class AuditService
{
    public function __construct(
        private EntityManagerInterface $em,
        private Security $security,
        private RequestStack $requestStack,
    ) {}

    public function log(
        string $action,
        ?string $entityType = null,
        ?int $entityId = null,
        ?string $details = null,
    ): void {
        $user = $this->security->getUser();
        $request = $this->requestStack->getCurrentRequest();

        $log = new AuditLog();
        $log->setAction($action);
        $log->setEntityType($entityType);
        $log->setEntityId($entityId);
        $log->setDetails($details);

        if ($user instanceof User) {
            $log->setUserId($user->getId());
            $log->setUsername($user->getUsername());
            $log->setAtelierId($user->getAtelierId());
        }

        if ($request) {
            $log->setIpAddress($request->getClientIp());
        }

        $this->em->persist($log);
        $this->em->flush();
    }
}
