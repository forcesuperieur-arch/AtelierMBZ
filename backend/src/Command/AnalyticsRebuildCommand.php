<?php

namespace App\Command;

use App\Service\AnalyticsSyncService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Reconstruit les tables analytiques d'un atelier — ou de tous.
 *
 * Sert aussi de RÉCONCILIATION nocturne : la table de faits est alimentée par
 * message (un message par RDV modifié), donc elle dérive dès qu'un message est
 * perdu ou qu'un RDV est créé/supprimé par un chemin qui n'en envoie pas
 * (import, seed, SQL direct). Constaté en dev avant la mise en place de cette
 * réconciliation : 46 faits orphelins (RDV disparu) et 31 RDV sans fait.
 * Un plein rebuild remet les deux côtés d'accord sans avoir à traquer chaque
 * chemin d'écriture.
 */
#[AsCommand(
    name: 'app:analytics:rebuild',
    description: 'Reconstruit les tables analytiques (un atelier, ou --all pour tous)',
)]
class AnalyticsRebuildCommand extends Command
{
    public function __construct(
        private AnalyticsSyncService $analyticsSync,
        private EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('atelier_id', InputArgument::OPTIONAL, "Identifiant de l'atelier à reconstruire");
        $this->addOption('all', null, InputOption::VALUE_NONE, 'Reconstruit tous les ateliers ayant des rendez-vous');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $conn = $this->em->getConnection();

        if ($input->getOption('all')) {
            $ateliers = $conn->fetchFirstColumn(
                'SELECT DISTINCT atelier_id FROM rendez_vous WHERE atelier_id IS NOT NULL ORDER BY atelier_id'
            );
        } else {
            $arg = $input->getArgument('atelier_id');
            if ($arg === null) {
                $output->writeln('<error>Précise un atelier_id, ou utilise --all.</error>');

                return Command::INVALID;
            }
            $ateliers = [(int) $arg];
        }

        foreach ($ateliers as $atelierId) {
            $avant = (int) $conn->fetchOne(
                'SELECT COUNT(*) FROM analytics_rdv_facts WHERE atelier_id = :a',
                ['a' => $atelierId]
            );
            $this->analyticsSync->rebuildAll((int) $atelierId);
            $apres = (int) $conn->fetchOne(
                'SELECT COUNT(*) FROM analytics_rdv_facts WHERE atelier_id = :a',
                ['a' => $atelierId]
            );
            $output->writeln(sprintf(
                'Atelier %d : %d faits → %d faits (%+d).',
                $atelierId,
                $avant,
                $apres,
                $apres - $avant
            ));
        }

        // Les faits dont l'atelier n'a plus aucun RDV ne sont repris par aucun
        // rebuild : on les retire explicitement, sinon ils survivent pour toujours.
        $orphelins = $conn->executeStatement(
            'DELETE FROM analytics_rdv_facts f
             WHERE NOT EXISTS (SELECT 1 FROM rendez_vous r WHERE r.id = f.rdv_id)'
        );
        if ($orphelins > 0) {
            $output->writeln(sprintf('%d fait(s) orphelin(s) supprimé(s) (rendez-vous disparu).', $orphelins));
        }

        return Command::SUCCESS;
    }
}
