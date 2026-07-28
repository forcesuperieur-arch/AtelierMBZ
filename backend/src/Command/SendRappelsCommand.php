<?php
namespace App\Command;

use App\Entity\RendezVous;
use App\Message\SendRappelMessage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(name: 'app:send-rappels', description: 'Send scheduled reminders (J-1, J-3) for upcoming RDVs')]
class SendRappelsCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private MessageBusInterface $bus,
        private \App\Service\ReglesAtelier $regles,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $sent = 0;

        // Deux rappels client, dont les délais sont réglables en administration
        // (par défaut J-3 puis J-1). Le rappel le plus proche du RDV utilise le
        // gabarit `rappel_j1`, le plus anticipé `rappel_j3`.
        $joursRappel = $this->regles->rappelsRdvJours();
        $jourProche = $joursRappel[0];
        $jourAnticipe = $joursRappel[1] ?? null;

        $j3 = $jourAnticipe !== null
            ? (new \DateTime())->modify(sprintf('+%d days', $jourAnticipe))->format('Y-m-d')
            : null;
        $rdvsJ3 = $j3 === null ? [] : $this->em->getRepository(RendezVous::class)
            ->createQueryBuilder('r')
            ->where('r.dateRdv = :date')
            ->andWhere('r.statut = :statut')
            ->setParameter('date', $j3)
            ->setParameter('statut', 'confirme')
            ->getQuery()->getResult();

        foreach ($rdvsJ3 as $rdv) {
            $this->bus->dispatch(new SendRappelMessage($rdv->getId(), 'rappel_j3'));
            $sent++;
        }

        $j1 = (new \DateTime())->modify(sprintf('+%d days', $jourProche))->format('Y-m-d');
        $rdvsJ1 = $this->em->getRepository(RendezVous::class)
            ->createQueryBuilder('r')
            ->where('r.dateRdv = :date')
            ->andWhere('r.statut = :statut')
            ->setParameter('date', $j1)
            ->setParameter('statut', 'confirme')
            ->getQuery()->getResult();

        foreach ($rdvsJ1 as $rdv) {
            $this->bus->dispatch(new SendRappelMessage($rdv->getId(), 'rappel_j1'));
            $sent++;
        }

        $io->success("$sent reminder(s) dispatched.");
        return Command::SUCCESS;
    }
}
