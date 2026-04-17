<?php

namespace App\Command;

use App\Entity\RapportIntervention;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:rappel-prochaine-revision',
    description: 'Remind clients 30 days before their next scheduled revision date',
)]
class RappelProchaineRevisionCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $targetDate = (new \DateTime())->modify('+30 days')->format('Y-m-d');

        $rapports = $this->em->createQueryBuilder()
            ->select('r')
            ->from(RapportIntervention::class, 'r')
            ->where('r.prochaineRevisionDate = :date')
            ->andWhere('r.statut IN (:statuts)')
            ->setParameter('date', $targetDate)
            ->setParameter('statuts', ['signe', 'rectifie'])
            ->getQuery()
            ->getResult();

        $count = 0;
        foreach ($rapports as $rapport) {
            $rdv = $rapport->getRendezVous();
            $client = $rdv->getClient();

            if ($client && $client->getEmail()) {
                // TODO: integrate with NotificationDispatcher when LOT 11 providers are configured
                $io->note(sprintf(
                    'Rappel: Client %s (%s) — révision prévue le %s',
                    $client->getNom(),
                    $client->getEmail(),
                    $rapport->getProchaineRevisionDate()->format('d/m/Y'),
                ));
                $count++;
            }
        }

        $io->success(sprintf('%d rappel(s) de révision identifié(s).', $count));

        return Command::SUCCESS;
    }
}
