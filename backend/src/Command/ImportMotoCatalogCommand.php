<?php

namespace App\Command;

use App\Service\MotoCatalogImporter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:motos:import-catalogue', description: 'Importe le catalogue moto pour l’autocomplétion')]
class ImportMotoCatalogCommand extends Command
{
    public function __construct(private readonly MotoCatalogImporter $importer)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $result = $this->importer->importFromDefaultFile();

        $io->success(sprintf(
            'Import catalogue terminé : %d lignes préparées, %d modèles créés, %d modèles mis à jour, %d fiches techniques créées, %d fiches techniques mises à jour.',
            $result['rows'] ?? 0,
            $result['created'] ?? 0,
            $result['updated'] ?? 0,
            $result['specs_created'] ?? 0,
            $result['specs_updated'] ?? 0,
        ));

        return Command::SUCCESS;
    }
}
