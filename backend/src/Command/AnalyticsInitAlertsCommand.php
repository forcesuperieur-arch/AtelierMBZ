<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\AnalyticsAlertRule;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:analytics:init-alerts',
    description: 'Initialize default analytics alert rules for an atelier',
)]
class AnalyticsInitAlertsCommand extends Command
{
    public function __construct(private EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('atelier_id', InputArgument::REQUIRED, 'Atelier ID');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $atelierId = (int) $input->getArgument('atelier_id');
        $repo = $this->em->getRepository(AnalyticsAlertRule::class);
        $existing = $repo->findBy(['atelierId' => $atelierId]);
        if (count($existing) > 0) {
            $output->writeln("Alert rules already exist for atelier {$atelierId}. Skipping.");
            return Command::SUCCESS;
        }

        $defaults = [
            ['name' => 'Occupation ponts trop faible', 'metric' => 'occupation_ponts_pct', 'operator' => '<', 'thresholdValue' => '30', 'severity' => 'warning', 'cooldownMinutes' => 120],
            ['name' => 'Trop de retards dépassement', 'metric' => 'nb_retards_depassement', 'operator' => '>', 'thresholdValue' => '3', 'severity' => 'warning', 'cooldownMinutes' => 60],
            ['name' => 'Rendement global insuffisant', 'metric' => 'rendement_global', 'operator' => '<', 'thresholdValue' => '70', 'severity' => 'critical', 'cooldownMinutes' => 240],
            ['name' => 'CA du jour faible', 'metric' => 'ca_du_jour_ht', 'operator' => '<', 'thresholdValue' => '500', 'severity' => 'warning', 'cooldownMinutes' => 360],
            ['name' => 'Attente restitution élevée', 'metric' => 'nb_attente_restitution', 'operator' => '>', 'thresholdValue' => '5', 'severity' => 'warning', 'cooldownMinutes' => 120],
        ];

        foreach ($defaults as $d) {
            $rule = new AnalyticsAlertRule();
            $rule->setAtelierId($atelierId);
            $rule->setName($d['name']);
            $rule->setMetric($d['metric']);
            $rule->setOperator($d['operator']);
            $rule->setThresholdValue($d['thresholdValue']);
            $rule->setSeverity($d['severity']);
            $rule->setCooldownMinutes($d['cooldownMinutes']);
            $this->em->persist($rule);
        }
        $this->em->flush();

        $output->writeln("Initialized " . count($defaults) . " alert rules for atelier {$atelierId}.");
        return Command::SUCCESS;
    }
}
