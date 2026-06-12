<?php

namespace App\Command;

use App\Entity\ConfigAtelier;
use App\Entity\DemandeTravauxSupp;
use App\Service\NotificationDispatcher;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:relance-demandes-travaux',
    description: 'Relance les clients sans décision sur des travaux supplémentaires (H+4, une seule fois)',
)]
class RelanceDemandesTravauxCommand extends Command
{
    /** Pas de relance la nuit : fenêtre d'envoi en heures locales atelier */
    private const HEURE_MIN = 8;
    private const HEURE_MAX = 19;

    /** Délai sans décision avant relance */
    private const DELAI_HEURES = 4;

    public function __construct(
        private EntityManagerInterface $em,
        private NotificationDispatcher $dispatcher,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $heure = (int) (new \DateTime())->format('G');
        if ($heure < self::HEURE_MIN || $heure >= self::HEURE_MAX) {
            $io->writeln('Hors fenêtre d\'envoi (8h-19h) — aucune relance.');
            return Command::SUCCESS;
        }

        $seuil = (new \DateTime())->modify(sprintf('-%d hours', self::DELAI_HEURES));

        $demandes = $this->em->createQueryBuilder()
            ->select('d')
            ->from(DemandeTravauxSupp::class, 'd')
            ->where('d.statut = :statut')
            ->andWhere('d.sentAt IS NOT NULL')
            ->andWhere('d.sentAt <= :seuil')
            ->andWhere('d.relanceAt IS NULL')
            ->setParameter('statut', DemandeTravauxSupp::STATUT_EN_ATTENTE_DECISION_CLIENT)
            ->setParameter('seuil', $seuil)
            ->getQuery()
            ->getResult();

        $count = 0;
        foreach ($demandes as $demande) {
            // Marquée AVANT l'envoi : un provider en échec ne doit pas
            // re-relancer le même client à chaque passage du cron.
            $demande->setRelanceAt(new \DateTime());

            $rdv = $demande->getRendezVous();
            $client = $rdv->getClient();
            $atId = $rdv->getAtelierId() ?? 0;

            if (!$client || !$client->getEmail()) {
                continue;
            }

            // Interrupteur atelier (transparence par défaut)
            $config = $this->em->getRepository(ConfigAtelier::class)->findOneBy(['atelierId' => $atId]);
            if ($config && !$config->isNotificationEtapeEnabled('demande_relance')) {
                continue;
            }

            $vehicule = $rdv->getVehicule();
            $baseUrl = rtrim($_ENV['PUBLIC_URL'] ?? '', '/');

            $this->dispatcher->sendFromTemplate(
                'demande_relance',
                'email',
                $atId,
                $client->getEmail(),
                [
                    'client_prenom' => $client->getPrenom(),
                    'vehicule' => $vehicule
                        ? trim(($vehicule->getMarque() ?? '') . ' ' . ($vehicule->getModele() ?? ''))
                        : 'votre moto',
                    'lien' => $baseUrl . '/public/demande/' . $demande->getTokenValidation(),
                ],
                'DemandeTravauxSupp',
                $demande->getId(),
            );

            $count++;
        }

        $this->em->flush();

        $io->success(sprintf('%d relance(s) envoyée(s) sur %d demande(s) en attente.', $count, count($demandes)));

        return Command::SUCCESS;
    }
}
