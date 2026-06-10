<?php

namespace App\Service;

use App\Entity\Notification;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

class MercureNotifier
{
    public function __construct(
        private HubInterface $hub,
        private LoggerInterface $logger,
    ) {}

    public function publishToAtelier(int $atelierId, Notification $notif): void
    {
        try {
            $update = new Update(
                "atelier/{$atelierId}/notifications",
                json_encode([
                    'id' => $notif->getId(),
                    'type' => $notif->getType(),
                    'severity' => $notif->getSeverity(),
                    'title' => $notif->getTitle(),
                    'message' => $notif->getMessage(),
                    'actionUrl' => $notif->getActionUrl(),
                    'relatedEntityType' => $notif->getRelatedEntityType(),
                    'relatedEntityId' => $notif->getRelatedEntityId(),
                    'createdAt' => $notif->getCreatedAt()->format(\DATE_ATOM),
                ]),
            );

            $this->hub->publish($update);
            $this->logger->info('Mercure notification published', [
                'atelierId' => $atelierId,
                'notifId' => $notif->getId(),
                'type' => $notif->getType(),
            ]);
        } catch (\Throwable $e) {
            $this->logger->warning('Mercure publish failed (non-blocking): {error}', [
                'error' => $e->getMessage(),
                'atelierId' => $atelierId,
                'notifId' => $notif->getId(),
            ]);
        }
    }

    public function publishAcknowledged(int $atelierId, int $notifId, int $userId): void
    {
        try {
            $update = new Update(
                "atelier/{$atelierId}/notifications/acknowledged",
                json_encode([
                    'notificationId' => $notifId,
                    'acknowledgedBy' => $userId,
                    'acknowledgedAt' => (new \DateTimeImmutable())->format(\DATE_ATOM),
                ]),
            );

            $this->hub->publish($update);
        } catch (\Throwable $e) {
            $this->logger->warning('Mercure acknowledge publish failed (non-blocking): {error}', [
                'error' => $e->getMessage(),
                'atelierId' => $atelierId,
                'notifId' => $notifId,
            ]);
        }
    }
}
