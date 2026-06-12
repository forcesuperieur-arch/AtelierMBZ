<?php

namespace App\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Healthcheck du worker Messenger : échoue (exit 1) si des messages sont en
 * file d'échec — branché sur le healthcheck Docker du service worker pour que
 * `docker compose ps` montre un état unhealthy au lieu d'un échec silencieux.
 */
#[AsCommand(name: 'app:worker-health', description: 'Échoue si la file Messenger "failed" n\'est pas vide')]
class WorkerHealthCommand extends Command
{
    public function __construct(private Connection $connection)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $failed = (int) $this->connection->fetchOne(
            "SELECT COUNT(*) FROM messenger_messages WHERE queue_name = 'failed'"
        );

        if ($failed > 0) {
            $output->writeln(sprintf('<error>%d message(s) en échec — voir messenger:failed:show</error>', $failed));

            return Command::FAILURE;
        }

        $output->writeln('<info>File failed vide.</info>');

        return Command::SUCCESS;
    }
}
