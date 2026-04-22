<?php

namespace App\Command;

use App\Entity\RapportIntervention;
use App\Service\NotificationDispatcher;
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
        private NotificationDispatcher $notificationDispatcher,
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
            $client = $rdv?->getClient();
            $vehicule = $rdv?->getVehicule();

            if (!$client || !$client->getEmail()) {
                continue;
            }

            $atelierId = $rapport->getAtelierId() ?? 0;
            $variables = [
                'client_prenom' => $client->getPrenom() ?? $client->getNom(),
                'marque'        => $vehicule?->getMarque() ?? '',
                'modele'        => $vehicule?->getModele() ?? '',
                'date_revision' => $rapport->getProchaineRevisionDate()->format('d/m/Y'),
                'atelier_nom'   => '',
            ];

            // [SPRINT-4] I2 — dispatch email via NotificationDispatcher
            $this->notificationDispatcher->sendFromTemplate(
                'rappel_prochaine_revision',
                'email',
                $atelierId,
                $client->getEmail(),
                $variables,
                'RapportIntervention',
                $rapport->getId(),
            );

            $count++;
        }

        $io->success(sprintf('%d rappel(s) de révision envoyé(s).', $count));

        return Command::SUCCESS;
    }
}
