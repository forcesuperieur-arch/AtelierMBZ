<?php

namespace App\Command;

use App\Entity\OrdreReparation;
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
        private NotificationDispatcher $dispatcher,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $targetDate = (new \DateTime())->modify('+30 days')->format('Y-m-d');
        $statuts = ['intervention_signee', 'signe', 'execute', 'termine'];

        // Rappel J-30 par date (comportement historique, inchangé).
        $parDate = $this->em->createQueryBuilder()
            ->select('o')
            ->from(OrdreReparation::class, 'o')
            ->where('o.prochaineRevisionDate = :date')
            ->andWhere('o.statut IN (:statuts)')
            ->setParameter('date', $targetDate)
            ->setParameter('statuts', $statuts)
            ->getQuery()
            ->getResult();

        // Rappel par kilométrage : le client a déclaré avoir atteint (ou dépassé)
        // le seuil de son OR le plus récent. Anti-doublon par `vidangeNotifieeAt`
        // (contrairement au rappel par date, la comparaison n'est pas bornée à un
        // seul jour : sans ce verrou, elle enverrait le rappel à chaque exécution).
        $parKm = $this->em->createQueryBuilder()
            ->select('o')
            ->from(OrdreReparation::class, 'o')
            ->join('o.rendezVous', 'r')
            ->join('r.vehicule', 'v')
            ->where('o.prochaineRevisionKm IS NOT NULL')
            ->andWhere('o.vidangeNotifieeAt IS NULL')
            ->andWhere('o.statut IN (:statuts)')
            ->andWhere('v.kilometrage IS NOT NULL')
            ->andWhere('v.kilometrage >= o.prochaineRevisionKm')
            ->setParameter('statuts', $statuts)
            ->getQuery()
            ->getResult();

        $count = 0;
        foreach ([...$parDate, ...$parKm] as $ordre) {
            $rdv = $ordre->getRendezVous();
            $client = $rdv?->getClient();

            if (!$client) {
                continue;
            }

            $ordre->setVidangeNotifieeAt(new \DateTimeImmutable());

            $atId = $ordre->getAtelierId() ?? 0;
            $vehicule = $rdv->getVehicule();
            $vars = [
                'client_nom'    => $client->getNom(),
                'client_prenom' => $client->getPrenom(),
                'date_revision' => $ordre->getProchaineRevisionDate()?->format('d/m/Y') ?? '',
                'vehicule'      => $vehicule
                    ? trim(($vehicule->getMarque() ?? '') . ' ' . ($vehicule->getModele() ?? ''))
                    : 'votre moto',
            ];

            // Email
            if ($client->getEmail()) {
                $this->dispatcher->sendFromTemplate(
                    'rappel_revision',
                    'email',
                    $atId,
                    $client->getEmail(),
                    $vars,
                    'OrdreReparation',
                    $ordre->getId(),
                );
            }

            // SMS
            if ($client->getTelephone()) {
                $this->dispatcher->sendFromTemplate(
                    'rappel_revision',
                    'sms',
                    $atId,
                    $client->getTelephone(),
                    $vars,
                    'OrdreReparation',
                    $ordre->getId(),
                );
            }

            $count++;
        }

        $this->em->flush();

        $io->success(sprintf('%d rappel(s) de révision envoyé(s).', $count));

        return Command::SUCCESS;
    }
}
