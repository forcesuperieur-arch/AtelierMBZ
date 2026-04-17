<?php

namespace App\Command;

use App\Entity\RendezVous;
use App\Service\RendezVousWorkflowService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Workflow\WorkflowInterface;

#[AsCommand(
    name: 'app:check-no-show',
    description: 'Auto-transition confirmed RDVs to no_show if heure_rdv + 30min < now()',
)]
class CheckNoShowCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private WorkflowInterface $rendezVousStateMachine,
        private RendezVousWorkflowService $workflowService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $now = new \DateTime();
        $threshold = (clone $now)->modify('-30 minutes');

        // Find all RDVs in "confirme" where heureRdv + 30min < now
        $qb = $this->em->createQueryBuilder();
        $rdvs = $qb->select('r')
            ->from(RendezVous::class, 'r')
            ->where('r.statut = :statut')
            ->andWhere('r.dateRdv <= :today')
            ->andWhere('r.heureRdv <= :threshold')
            ->setParameter('statut', 'confirme')
            ->setParameter('today', $now->format('Y-m-d'))
            ->setParameter('threshold', $threshold->format('H:i:s'))
            ->getQuery()
            ->getResult();

        $count = 0;
        foreach ($rdvs as $rdv) {
            if ($this->rendezVousStateMachine->can($rdv, 'declarer_no_show')) {
                $annulation = $this->workflowService->recordCancellation(
                    $rdv,
                    'no_show',
                    'automatique',
                    'Auto-détecté par le système (heure RDV + 30min dépassée)',
                );
                $this->em->persist($annulation);

                $this->rendezVousStateMachine->apply($rdv, 'declarer_no_show');
                $count++;
            }
        }

        if ($count > 0) {
            $this->em->flush();
        }

        $io->success(sprintf('%d RDV(s) marqué(s) no-show.', $count));

        return Command::SUCCESS;
    }
}
