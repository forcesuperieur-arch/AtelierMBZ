<?php

namespace App\Command;

use App\Service\MotoCatalogImporter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:motos:import-catalogue', description: 'Importe le référentiel motos (marques/modèles/pièces de référence) depuis les exports DC-AFAM')]
class ImportMotoCatalogCommand extends Command
{
    public function __construct(private readonly MotoCatalogImporter $importer)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('all-applications', InputArgument::OPTIONAL, 'Chemin vers all_applications.xlsx (défaut : var/imports/all_applications.xlsx)')
            ->addArgument('part-applications', InputArgument::OPTIONAL, 'Chemin vers part_applications.xlsx, optionnel (défaut : var/imports/part_applications.xlsx)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $all = $input->getArgument('all-applications');
        $part = $input->getArgument('part-applications');

        try {
            $progress = static fn(string $msg) => $io->writeln($msg);
            $result = $all !== null
                ? $this->importer->import($all, $part, $progress)
                : $this->importer->importFromDefaultFiles($progress);
        } catch (\RuntimeException $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }

        $io->success(sprintf(
            'Import terminé : %d catégories, %d modèles, %d fiches techniques (générations).',
            $result['categories'] ?? 0,
            $result['modeles'] ?? 0,
            $result['specs'] ?? 0,
        ));

        if (($result['modeles_ignores_categorie_inconnue'] ?? 0) > 0) {
            $io->warning(sprintf(
                '%d modèle(s) ignoré(s) : catégorie introuvable en base (voir les lignes "!" ci-dessus).',
                $result['modeles_ignores_categorie_inconnue'],
            ));
        }

        return Command::SUCCESS;
    }
}
