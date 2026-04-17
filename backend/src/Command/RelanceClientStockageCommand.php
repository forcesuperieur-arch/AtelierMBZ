<?php

namespace App\Command;

use App\Entity\ConfigAtelier;
use App\Entity\RendezVous;
use App\Service\JoursOuvresService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:relance-client-stockage',
    description: 'Daily CRON: send reminders to clients with vehicles in storage, propose gardiennage',
)]
class RelanceClientStockageCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private JoursOuvresService $joursOuvres,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Get all RDV in termine or en_attente_pieces that haven't been picked up
        $rdvs = $this->em->createQueryBuilder()
            ->select('r')
            ->from(RendezVous::class, 'r')
            ->where('r.statut IN (:statuts)')
            ->setParameter('statuts', ['termine', 'en_attente_pieces'])
            ->getQuery()
            ->getResult();

        $relance1 = 0;
        $relance2 = 0;
        $gardiennage = 0;
        $now = new \DateTime();

        foreach ($rdvs as $rdv) {
            $atelierId = $rdv->getAtelierId() ?? 0;
            $config = $this->em->getRepository(ConfigAtelier::class)->findOneBy(['atelierId' => $atelierId]);
            if (!$config) $config = new ConfigAtelier();

            // Calculate business days since heureFinTravail or dateRdv
            $refDate = $rdv->getHeureFinTravail() ?? \DateTime::createFromInterface($rdv->getDateRdv());
            $joursOuvres = $this->joursOuvres->compterJoursOuvres(
                \DateTime::createFromInterface($refDate),
                $now,
                $atelierId,
            );

            $client = $rdv->getClient();
            $clientName = $client ? $client->getNom() : 'Inconnu';

            // Check thresholds
            if ($joursOuvres >= $config->getDelaiProposeGardiennageJoursOuvres()) {
                $io->warning(sprintf(
                    'RDV #%d (%s) — %d jours ouvrés — PROPOSITION GARDIENNAGE',
                    $rdv->getId(), $clientName, $joursOuvres,
                ));
                $gardiennage++;
            } elseif ($joursOuvres >= $config->getDelaiRelance2JoursOuvres()) {
                $io->note(sprintf(
                    'RDV #%d (%s) — %d jours ouvrés — Relance 2',
                    $rdv->getId(), $clientName, $joursOuvres,
                ));
                $relance2++;
            } elseif ($joursOuvres >= $config->getDelaiRelance1JoursOuvres()) {
                $io->note(sprintf(
                    'RDV #%d (%s) — %d jours ouvrés — Relance 1',
                    $rdv->getId(), $clientName, $joursOuvres,
                ));
                $relance1++;
            }
        }

        $io->success(sprintf(
            'Relances: %d (J+15), %d (J+30), Propositions gardiennage: %d',
            $relance1, $relance2, $gardiennage,
        ));

        return Command::SUCCESS;
    }
}
