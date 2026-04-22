<?php

namespace App\Command;

use App\Entity\Notification;
use App\Entity\NotificationEscalation;
use App\Entity\User;
use App\Entity\UserAtelierRole;
use App\Service\MercureNotifier;
use App\Service\NotificationDispatcher;
use App\Service\NotificationMessage;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:process-notification-escalations',
    description: 'Process pending notification escalations (push, SMS, email)',
)]
class ProcessNotificationEscalationsCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private MercureNotifier $mercureNotifier,
        private NotificationDispatcher $notificationDispatcher,
        private LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $now = new \DateTime();

        $escalations = $this->em->getRepository(NotificationEscalation::class)
            ->createQueryBuilder('e')
            ->join('e.notification', 'n')
            ->where('e.scheduledAt <= :now')
            ->andWhere('e.executedAt IS NULL')
            ->setParameter('now', $now)
            ->orderBy('e.scheduledAt', 'ASC')
            ->setMaxResults(100)
            ->getQuery()
            ->getResult();

        $processed = 0;
        $skipped = 0;

        foreach ($escalations as $escalation) {
            /** @var NotificationEscalation $escalation */
            $notif = $escalation->getNotification();

            // If the parent notification is already acknowledged, skip
            if ($notif->getAcknowledgedAt() !== null) {
                $escalation->setExecutedAt($now);
                $escalation->setResult('skipped');
                $escalation->setSkipReason('acknowledged_before');
                $skipped++;
                continue;
            }

            // Dispatch based on channel
            switch ($escalation->getChannel()) {
                case 'push':
                    $this->handlePush($escalation, $notif);
                    break;
                case 'sms':
                    $this->handleSms($escalation, $notif);
                    break;
                case 'email':
                    $this->handleEmail($escalation, $notif);
                    break;
                default:
                    $escalation->setResult('failed');
                    $escalation->setSkipReason('unknown_channel');
            }

            $escalation->setExecutedAt($now);
            $processed++;
        }

        $this->em->flush();

        if ($processed > 0 || $skipped > 0) {
            $this->logger->info("Escalations processed: {$processed}, skipped: {$skipped}");
            $output->writeln("Processed: {$processed}, Skipped: {$skipped}");
        }

        return Command::SUCCESS;
    }

    private function handlePush(NotificationEscalation $escalation, Notification $notif): void
    {
        try {
            $atelierId = $notif->getAtelierId() ?? 0;
            $this->mercureNotifier->publishToAtelier($atelierId, $notif);
            $escalation->setResult('success');
            $escalation->setSkipReason(null);
        } catch (\Throwable $e) {
            $this->logger->error('Push escalation failed', [
                'escalationId' => $escalation->getId(),
                'error' => $e->getMessage(),
            ]);
            $escalation->setResult('failed');
            $escalation->setSkipReason($this->truncateReason($e->getMessage()));
        }
    }

    private function handleSms(NotificationEscalation $escalation, Notification $notif): void
    {
        $body = trim(($notif->getTitle() ? $notif->getTitle() . "\n" : '') . $notif->getMessage());
        $targetInfo = trim((string) ($escalation->getTargetInfo() ?? ''));

        // If targetInfo is a role pattern, resolve to user emails (no phone available for internal users)
        if (str_contains($targetInfo, 'ROLE_')) {
            $this->dispatchToRoleEmails($escalation, $notif, $body, null);
            return;
        }

        $this->dispatchDirectChannel($escalation, $notif, 'sms', $body, null);
    }

    private function handleEmail(NotificationEscalation $escalation, Notification $notif): void
    {
        $targetInfo = trim((string) ($escalation->getTargetInfo() ?? ''));

        // If targetInfo is a role pattern, resolve to user emails
        if (str_contains($targetInfo, 'ROLE_')) {
            $this->dispatchToRoleEmails($escalation, $notif, $notif->getMessage(), $notif->getTitle());
            return;
        }

        $this->dispatchDirectChannel($escalation, $notif, 'email', $notif->getMessage(), $notif->getTitle());
    }

    /**
     * Resolve ROLE_* targets to actual user emails and dispatch to each.
     * Used when escalation targets an internal role instead of a direct address.
     */
    private function dispatchToRoleEmails(
        NotificationEscalation $escalation,
        Notification $notif,
        string $body,
        ?string $subject,
    ): void {
        $targetInfo = trim((string) ($escalation->getTargetInfo() ?? ''));
        $atelierId = (int) ($notif->getAtelierId() ?? 0);

        // Extract role names from comma-separated string (e.g. "ROLE_RECEPTIONNAIRE,ROLE_ADMIN")
        $roles = array_filter(
            array_map('trim', explode(',', $targetInfo)),
            static fn(string $r): bool => str_starts_with($r, 'ROLE_'),
        );

        if (empty($roles)) {
            $escalation->setResult('failed');
            $escalation->setSkipReason('no_valid_role');
            return;
        }

        // Find all UserAtelierRole entries for these roles in this atelier
        $userAtelierRoles = $this->em->getRepository(UserAtelierRole::class)
            ->createQueryBuilder('uar')
            ->where('uar.role IN (:roles)')
            ->andWhere('uar.atelierId = :atelierId')
            ->setParameter('roles', array_values($roles))
            ->setParameter('atelierId', $atelierId)
            ->getQuery()
            ->getResult();

        $emails = [];
        foreach ($userAtelierRoles as $uar) {
            $user = $this->em->getRepository(User::class)->find($uar->getUserId());
            if ($user && $user->getEmail()) {
                $emails[] = $user->getEmail();
            }
        }

        $emails = array_unique($emails);

        if (empty($emails)) {
            $escalation->setResult('failed');
            $escalation->setSkipReason('no_recipient_found_for_role');
            return;
        }

        $lastResult = null;
        foreach ($emails as $email) {
            try {
                $result = $this->notificationDispatcher->send(new NotificationMessage(
                    'email',
                    $atelierId,
                    $email,
                    $body,
                    $subject,
                    $notif->getType(),
                    $notif->getRelatedEntityType(),
                    $notif->getRelatedEntityId(),
                ));
                $lastResult = $result;
            } catch (\Throwable $e) {
                $this->logger->error('Role escalation email failed', [
                    'escalationId' => $escalation->getId(),
                    'email' => $email,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($lastResult?->isSuccess()) {
            $escalation->setResult('success');
            $escalation->setSkipReason(null);
        } else {
            $escalation->setResult('partial');
            $escalation->setSkipReason('sent_to_' . count($emails) . '_recipients');
        }
    }

    private function dispatchDirectChannel(
        NotificationEscalation $escalation,
        Notification $notif,
        string $channel,
        string $body,
        ?string $subject,
    ): void {
        $recipient = trim((string) ($escalation->getTargetInfo() ?? ''));
        if ($recipient === '') {
            $escalation->setResult('failed');
            $escalation->setSkipReason('missing_target');
            return;
        }

        try {
            $result = $this->notificationDispatcher->send(new NotificationMessage(
                $channel,
                (int) ($notif->getAtelierId() ?? 0),
                $recipient,
                $body,
                $subject,
                $notif->getType(),
                $notif->getRelatedEntityType(),
                $notif->getRelatedEntityId(),
            ));

            if ($result->isSuccess()) {
                $escalation->setResult('success');
                $escalation->setSkipReason(null);
                return;
            }

            $escalation->setResult('failed');
            $escalation->setSkipReason($this->truncateReason($result->getErrorMessage() ?? 'dispatch_failed'));
        } catch (\Throwable $e) {
            $this->logger->error('Channel escalation failed', [
                'escalationId' => $escalation->getId(),
                'channel' => $channel,
                'error' => $e->getMessage(),
            ]);
            $escalation->setResult('failed');
            $escalation->setSkipReason($this->truncateReason($e->getMessage()));
        }
    }

    private function truncateReason(string $reason): string
    {
        return mb_substr($reason, 0, 100);
    }
}
