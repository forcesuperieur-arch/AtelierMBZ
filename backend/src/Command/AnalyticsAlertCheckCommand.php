<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\AnalyticsAlertRule;
use App\Entity\Notification;
use App\Service\MercureNotifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:analytics:check-alerts',
    description: 'Check analytics alert thresholds and create notifications if triggered',
)]
class AnalyticsAlertCheckCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private MercureNotifier $notifier,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $conn = $this->em->getConnection();
        $repo = $this->em->getRepository(AnalyticsAlertRule::class);
        $rules = $repo->findBy(['isActive' => true]);

        $now = new \DateTimeImmutable();
        $triggered = 0;

        foreach ($rules as $rule) {
            $atelierId = $rule->getAtelierId();

            // Check cooldown
            $lastTriggered = $rule->getLastTriggeredAt();
            if ($lastTriggered) {
                $cooldownEnd = (new \DateTimeImmutable())->setTimestamp($lastTriggered->getTimestamp())
                    ->modify("+{$rule->getCooldownMinutes()} minutes");
                if ($cooldownEnd > $now) {
                    continue;
                }
            }

            $metric = $rule->getMetric();
            $operator = $rule->getOperator();
            $threshold = (float) $rule->getThresholdValue();

            $currentValue = $this->resolveMetricValue($conn, $atelierId, $metric);

            if ($currentValue === null) {
                continue;
            }

            $isTriggered = match ($operator) {
                '>' => $currentValue > $threshold,
                '<' => $currentValue < $threshold,
                '>=' => $currentValue >= $threshold,
                '<=' => $currentValue <= $threshold,
                '==' => abs($currentValue - $threshold) < 0.001,
                default => false,
            };

            if (!$isTriggered) {
                continue;
            }

            // Create notification
            $notif = new Notification();
            $notif->setAtelierId($atelierId);
            $notif->setType('analytics_alert');
            $notif->setSeverity($rule->getSeverity());
            $notif->setTitle($rule->getName());
            $notif->setMessage(sprintf(
                '%s : valeur actuelle %.2f (seuil %s %.2f)',
                $rule->getName(),
                $currentValue,
                $operator,
                $threshold
            ));
            $notif->setActionUrl('/');
            $this->em->persist($notif);
            $this->em->flush();

            // Publish via Mercure (graceful fallback if not configured)
            try {
                $this->notifier->publishToAtelier($atelierId, $notif);
            } catch (\Throwable $e) {
                $output->writeln("[WARN] Mercure publish failed: {$e->getMessage()}");
            }

            // Update rule last triggered
            $rule->setLastTriggeredAt($now);
            $this->em->flush();

            $triggered++;
            $output->writeln("[TRIGGERED] Atelier {$atelierId} | {$rule->getName()} | {$currentValue} {$operator} {$threshold}");
        }

        $output->writeln("Checked " . count($rules) . " rules, triggered {$triggered} alerts.");
        return Command::SUCCESS;
    }

    private function resolveMetricValue(\Doctrine\DBAL\Connection $conn, int $atelierId, string $metric): ?float
    {
        $today = date('Y-m-d');

        $sqlMap = [
            'occupation_ponts_pct' => "SELECT occupation_ponts_pct FROM analytics_daily_snapshots WHERE atelier_id = :a AND snapshot_date = :d ORDER BY snapshot_date DESC LIMIT 1",
            'nb_retards_depassement' => "SELECT nb_retards_depassement FROM analytics_daily_snapshots WHERE atelier_id = :a AND snapshot_date = :d ORDER BY snapshot_date DESC LIMIT 1",
            'nb_attente_restitution' => "SELECT nb_attente_restitution FROM analytics_daily_snapshots WHERE atelier_id = :a AND snapshot_date = :d ORDER BY snapshot_date DESC LIMIT 1",
            'rendement_global' => "SELECT CASE WHEN charge_planifiee_minutes > 0 THEN ROUND(charge_effective_minutes * 100.0 / charge_planifiee_minutes, 2) ELSE 0 END FROM analytics_daily_snapshots WHERE atelier_id = :a AND snapshot_date = :d ORDER BY snapshot_date DESC LIMIT 1",
            'ca_du_jour_ht' => "SELECT ca_du_jour_ht FROM analytics_daily_snapshots WHERE atelier_id = :a AND snapshot_date = :d ORDER BY snapshot_date DESC LIMIT 1",
            'ca_mo_ht' => "SELECT ca_mo_ht FROM analytics_daily_snapshots WHERE atelier_id = :a AND snapshot_date = :d ORDER BY snapshot_date DESC LIMIT 1",
            'ca_pieces_ht' => "SELECT ca_pieces_ht FROM analytics_daily_snapshots WHERE atelier_id = :a AND snapshot_date = :d ORDER BY snapshot_date DESC LIMIT 1",
            'nb_or_ouverts' => "SELECT nb_or_ouverts FROM analytics_daily_snapshots WHERE atelier_id = :a AND snapshot_date = :d ORDER BY snapshot_date DESC LIMIT 1",
            'panier_moyen' => "SELECT panier_moyen FROM analytics_daily_snapshots WHERE atelier_id = :a AND snapshot_date = :d ORDER BY snapshot_date DESC LIMIT 1",
        ];

        $sql = $sqlMap[$metric] ?? null;
        if (!$sql) {
            return null;
        }

        $result = $conn->fetchOne($sql, ['a' => $atelierId, 'd' => $today]);
        return $result !== false ? (float) $result : null;
    }
}
