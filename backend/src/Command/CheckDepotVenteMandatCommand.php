<?php

namespace App\Command;

use App\Entity\Notification;
use App\Entity\User;
use App\Entity\UserAtelierRole;
use App\Entity\VODepotVente;
use App\Service\MercureNotifier;
use App\Service\NotificationDispatcher;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:check-depot-vente-mandat',
    description: 'Daily CRON: alert J-7 before depot-vente mandate expiry',
)]
class CheckDepotVenteMandatCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private MercureNotifier $mercureNotifier,
        private NotificationDispatcher $notificationDispatcher,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $today = new \DateTimeImmutable('today');
        $alertDate = $today->modify('+7 days');

        // Load all active depot-vente mandates not yet expired
        $depots = $this->em->getRepository(VODepotVente::class)
            ->createQueryBuilder('d')
            ->where('d.status = :status')
            ->setParameter('status', 'actif')
            ->getQuery()
            ->getResult();

        $alerted = 0;

        foreach ($depots as $depot) {
            /** @var VODepotVente $depot */
            if ($depot->isMandatExpire()) {
                continue;
            }

            // Compute expiry date: dateDebut + dureeMandat days
            $expiryDate = new \DateTimeImmutable(
                (clone $depot->getDateDebut())->modify("+{$depot->getDureeMandat()} days")->format('Y-m-d'),
            );

            // Alert only if expiry is exactly 7 days from today
            if ($expiryDate->format('Y-m-d') !== $alertDate->format('Y-m-d')) {
                continue;
            }

            $atelierId = $depot->getAtelierId() ?? 0;
            $vehicleInfo = $depot->getVehicule()?->getPlaque() ?? sprintf('#%d', $depot->getId());

            $notif = new Notification();
            $notif->setAtelierId($atelierId);
            $notif->setType('mandat_depot_vente_expiry');
            $notif->setSeverity('warning');
            $notif->setTitle('Mandat dépôt-vente — expiration dans 7 jours');
            $notif->setMessage(sprintf(
                'Le mandat dépôt-vente pour le véhicule %s expire le %s (dans 7 jours). Pensez à le renouveler ou à clôturer le dossier.',
                $vehicleInfo,
                $expiryDate->format('d/m/Y'),
            ));
            $notif->setRelatedEntityType('VODepotVente');
            $notif->setRelatedEntityId($depot->getId());
            $notif->setTargetRoles(['ROLE_VO_MANAGER', 'ROLE_ADMIN']);
            $notif->setTargetRole('ROLE_VO_MANAGER');

            $this->em->persist($notif);

            try {
                $this->mercureNotifier->publishToAtelier($atelierId, $notif);
            } catch (\Throwable) {
                // Non-blocking
            }

            // Also send email to VO managers (best-effort, one per recipient)
            $voEmails = $this->getVoManagerEmails($atelierId);
            foreach ($voEmails as $email) {
                try {
                    $this->notificationDispatcher->sendFromTemplate(
                        'mandat_depot_vente_j7',
                        'email',
                        $atelierId,
                        $email,
                        [
                            'vehicule'    => $vehicleInfo,
                            'date_expiry' => $expiryDate->format('d/m/Y'),
                            'depot_id'    => (string) $depot->getId(),
                        ],
                        'VODepotVente',
                        $depot->getId(),
                    );
                } catch (\Throwable) {
                    // Non-blocking
                }
            }

            $alerted++;
        }

        $this->em->flush();

        $io->success(sprintf('Mandats dépôt-vente — Alertes J-7 envoyées: %d', $alerted));

        return Command::SUCCESS;
    }

    /** @return string[] */
    private function getVoManagerEmails(int $atelierId): array
    {
        $uars = $this->em->getRepository(UserAtelierRole::class)
            ->createQueryBuilder('uar')
            ->where('uar.role IN (:roles)')
            ->andWhere('uar.atelierId = :atelierId')
            ->setParameter('roles', ['ROLE_VO_MANAGER', 'ROLE_ADMIN'])
            ->setParameter('atelierId', $atelierId)
            ->getQuery()
            ->getResult();

        $emails = [];
        foreach ($uars as $uar) {
            $user = $this->em->getRepository(User::class)->find($uar->getUserId());
            if ($user && $user->getEmail()) {
                $emails[] = $user->getEmail();
            }
        }

        return array_unique($emails);
    }
}
