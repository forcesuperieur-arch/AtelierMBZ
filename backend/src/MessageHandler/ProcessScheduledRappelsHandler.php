<?php
namespace App\MessageHandler;

use App\Entity\RendezVous;
use App\Message\ProcessScheduledRappels;
use App\Message\SendRappelMessage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
class ProcessScheduledRappelsHandler
{
    public function __construct(
        private EntityManagerInterface $em,
        private MessageBusInterface $bus,
    ) {}

    public function __invoke(ProcessScheduledRappels $message): void
    {
        // J-3 reminders
        $j3 = (new \DateTime())->modify('+3 days')->format('Y-m-d');
        $rdvsJ3 = $this->em->getRepository(RendezVous::class)
            ->createQueryBuilder('r')
            ->where('r.dateRdv = :date')
            ->andWhere('r.statut = :statut')
            ->setParameter('date', $j3)
            ->setParameter('statut', 'confirme')
            ->getQuery()->getResult();

        foreach ($rdvsJ3 as $rdv) {
            $this->bus->dispatch(new SendRappelMessage($rdv->getId(), 'rappel_j3'));
        }

        // J-1 reminders
        $j1 = (new \DateTime())->modify('+1 day')->format('Y-m-d');
        $rdvsJ1 = $this->em->getRepository(RendezVous::class)
            ->createQueryBuilder('r')
            ->where('r.dateRdv = :date')
            ->andWhere('r.statut = :statut')
            ->setParameter('date', $j1)
            ->setParameter('statut', 'confirme')
            ->getQuery()->getResult();

        foreach ($rdvsJ1 as $rdv) {
            $this->bus->dispatch(new SendRappelMessage($rdv->getId(), 'rappel_j1'));
        }
    }
}
