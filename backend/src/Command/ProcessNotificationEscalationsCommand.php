<?php

namespace App\Command;

use App\Entity\Notification;
use App\Entity\NotificationEscalation;
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
        $this->dispatchDirectChannel($escalation, $notif, 'sms', $body, null);
    }

    private function handleEmail(NotificationEscalation $escalation, Notification $notif): void
    {
        $this->dispatchDirectChannel($escalation, $notif, 'email', $notif->getMessage(), $notif->getTitle());
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
