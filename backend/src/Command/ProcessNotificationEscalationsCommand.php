<?php

namespace App\Command;

use App\Entity\NotificationEscalation;
use App\Service\MercureNotifier;
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
                    $this->handleSms($escalation);
                    break;
                case 'email':
                    $this->handleEmail($escalation);
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

    private function handlePush(NotificationEscalation $escalation, $notif): void
    {
        try {
            $atelierId = $notif->getAtelierId() ?? 0;
            $this->mercureNotifier->publishToAtelier($atelierId, $notif);
            $escalation->setResult('success');
        } catch (\Throwable $e) {
            $this->logger->error('Push escalation failed', [
                'escalationId' => $escalation->getId(),
                'error' => $e->getMessage(),
            ]);
            $escalation->setResult('failed');
            $escalation->setSkipReason($e->getMessage());
        }
    }

    private function handleSms(NotificationEscalation $escalation): void
    {
        // SMS integration placeholder — log for now
        $this->logger->info('SMS escalation dispatched (placeholder)', [
            'escalationId' => $escalation->getId(),
            'target' => $escalation->getTargetInfo(),
        ]);
        $escalation->setResult('success');
    }

    private function handleEmail(NotificationEscalation $escalation): void
    {
        // Email escalation placeholder — log for now
        $this->logger->info('Email escalation dispatched (placeholder)', [
            'escalationId' => $escalation->getId(),
            'target' => $escalation->getTargetInfo(),
        ]);
        $escalation->setResult('success');
    }
}
