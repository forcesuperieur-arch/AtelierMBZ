<?php

namespace App\Command;

use App\Entity\Notification;
use App\Entity\VOPurchase;
use App\Service\AuditService;
use App\Service\MercureNotifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:check-da-siv-expiry',
    description: 'Daily CRON: alert J+10 for pending DA SIV and expire at J+15',
)]
class CheckDaSivExpiryCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private MercureNotifier $mercureNotifier,
        private AuditService $auditService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $now = new \DateTimeImmutable('today');

        $threshold10 = $now->modify('-10 days');
        $threshold15 = $now->modify('-15 days');

        // Load all VOPurchase with a non-terminal sivStatus
        $purchases = $this->em->getRepository(VOPurchase::class)
            ->createQueryBuilder('v')
            ->where('v.sivStatus NOT IN (:done)')
            ->setParameter('done', [VOPurchase::SIV_STATUS_ENREGISTREE, VOPurchase::SIV_STATUS_EXPIREE])
            ->getQuery()
            ->getResult();

        $expired = 0;
        $alerted = 0;

        foreach ($purchases as $purchase) {
            /** @var VOPurchase $purchase */
            $createdAt = new \DateTimeImmutable($purchase->getCreatedAt()->format('Y-m-d'));
            $atelierId = $purchase->getAtelierId() ?? 0;

            // [I1] J+15 — pass to expiree
            if ($createdAt <= $threshold15) {
                $purchase->setSivStatus(VOPurchase::SIV_STATUS_EXPIREE);
                $this->em->persist($purchase);

                $this->auditService->log(
                    'da_siv_expired',
                    'VOPurchase',
                    $purchase->getId(),
                    json_encode([
                        'sivStatus' => 'expiree',
                        'createdAt' => $purchase->getCreatedAt()->format('Y-m-d'),
                    ], JSON_UNESCAPED_UNICODE),
                );

                $this->sendInAppNotification(
                    $atelierId,
                    $purchase,
                    'critical',
                    'DA SIV expirée',
                    sprintf(
                        'La DA SIV pour le véhicule #%d est expirée (J+15 dépassé). Ce véhicule est invendable tant que la DA n\'est pas réenregistrée.',
                        $purchase->getId(),
                    ),
                );

                $expired++;
                continue;
            }

            // [I9] J+10 — alert if still pending
            if ($createdAt <= $threshold10) {
                $this->sendInAppNotification(
                    $atelierId,
                    $purchase,
                    'warning',
                    'DA SIV non enregistrée — J+10',
                    sprintf(
                        'La DA SIV pour le véhicule #%d n\'est pas encore enregistrée (J+%d). Délai légal : 15 jours. Il reste %d jour(s).',
                        $purchase->getId(),
                        $now->diff($createdAt)->days,
                        15 - $now->diff($createdAt)->days,
                    ),
                );

                $alerted++;
            }
        }

        $this->em->flush();

        $io->success(sprintf('DA SIV — Expirées: %d, Alertes J+10: %d', $expired, $alerted));

        return Command::SUCCESS;
    }

    private function sendInAppNotification(
        int $atelierId,
        VOPurchase $purchase,
        string $severity,
        string $title,
        string $message,
    ): void {
        $notif = new Notification();
        $notif->setAtelierId($atelierId);
        $notif->setType('da_siv_alert');
        $notif->setSeverity($severity);
        $notif->setTitle($title);
        $notif->setMessage($message);
        $notif->setRelatedEntityType('VOPurchase');
        $notif->setRelatedEntityId($purchase->getId());
        $notif->setTargetRoles(['ROLE_VO_MANAGER', 'ROLE_ADMIN']);
        $notif->setTargetRole('ROLE_VO_MANAGER');

        $this->em->persist($notif);

        try {
            $this->mercureNotifier->publishToAtelier($atelierId, $notif);
        } catch (\Throwable) {
            // Mercure failure is non-blocking — notif is persisted in DB
        }
    }
}
