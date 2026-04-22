<?php

namespace App\Command;

use App\Entity\ConfigAtelier;
use App\Entity\RendezVous;
use App\Message\SendGardiennageRappelMessage;
use App\Service\AuditService;
use App\Service\JoursOuvresService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: 'app:relance-client-stockage',
    description: 'Daily CRON: send reminders to clients with vehicles in storage, propose gardiennage',
)]
class RelanceClientStockageCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private JoursOuvresService $joursOuvres,
        private MessageBusInterface $bus,
        private AuditService $auditService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // All RDVs in non-recovered statuses
        $rdvs = $this->em->createQueryBuilder()
            ->select('r')
            ->from(RendezVous::class, 'r')
            ->where('r.statut IN (:statuts)')
            ->setParameter('statuts', ['termine', 'en_attente_pieces', 'en_gardiennage'])
            ->getQuery()
            ->getResult();

        $counters = ['j15' => 0, 'j30' => 0, 'j45' => 0, 'j180' => 0];
        $now = new \DateTime();

        foreach ($rdvs as $rdv) {
            $atelierId = $rdv->getAtelierId() ?? 0;
            $config = $this->em->getRepository(ConfigAtelier::class)->findOneBy(['atelierId' => $atelierId]);
            if (!$config) {
                $config = new ConfigAtelier();
            }

            $refDate = $rdv->getHeureFinTravail() ?? \DateTime::createFromInterface($rdv->getDateRdv());
            $joursOuvres = $this->joursOuvres->compterJoursOuvres(
                \DateTime::createFromInterface($refDate),
                $now,
                $atelierId,
            );

            // Seuil J+180 — procédure abandon (checked first: highest threshold wins)
            if ($joursOuvres >= $config->getDelaiProcedureAbandonJoursOuvres()) {
                $this->dispatchRappel($rdv, 'relance_gardiennage_j180', $atelierId, 180);
                $counters['j180']++;
            } elseif ($joursOuvres >= $config->getDelaiProposeGardiennageJoursOuvres()) {
                $this->dispatchRappel($rdv, 'relance_gardiennage_j45', $atelierId, 45);
                $counters['j45']++;
            } elseif ($joursOuvres >= $config->getDelaiRelance2JoursOuvres()) {
                $this->dispatchRappel($rdv, 'relance_gardiennage_j30', $atelierId, 30);
                $counters['j30']++;
            } elseif ($joursOuvres >= $config->getDelaiRelance1JoursOuvres()) {
                $this->dispatchRappel($rdv, 'relance_gardiennage_j15', $atelierId, 15);
                $counters['j15']++;
            }
        }

        $io->success(sprintf(
            'Relances dispatched — J+15: %d, J+30: %d, J+45: %d, J+180: %d',
            $counters['j15'],
            $counters['j30'],
            $counters['j45'],
            $counters['j180'],
        ));

        return Command::SUCCESS;
    }

    private function dispatchRappel(RendezVous $rdv, string $templateCode, int $atelierId, int $seuilJours): void
    {
        $this->bus->dispatch(new SendGardiennageRappelMessage(
            rdvId: $rdv->getId(),
            templateCode: $templateCode,
            atelierId: $atelierId,
            seuilJours: $seuilJours,
        ));
    }
}

