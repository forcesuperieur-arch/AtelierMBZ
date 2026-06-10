<?php

namespace App\Command;

use App\Service\AnalyticsSyncService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:analytics:rebuild',
    description: 'Rebuild analytics tables for an atelier',
)]
class AnalyticsRebuildCommand extends Command
{
    public function __construct(private AnalyticsSyncService $analyticsSync)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('atelier_id', InputArgument::REQUIRED, 'Atelier ID to rebuild');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $atelierId = (int) $input->getArgument('atelier_id');
        $output->writeln("Rebuilding analytics for atelier {$atelierId}...");
        $this->analyticsSync->rebuildAll($atelierId);
        $output->writeln('Done.');
        return Command::SUCCESS;
    }
}
