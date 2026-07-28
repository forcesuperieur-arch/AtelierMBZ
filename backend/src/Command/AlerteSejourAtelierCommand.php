<?php

namespace App\Command;

use App\Entity\Notification;
use App\Service\MercureNotifier;
use App\Service\SejourAtelierService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

/**
 * CRON quotidien : alerte sur les motos immobilisées à l'atelier depuis plus de
 * 72 heures OUVRÉES (jours de fermeture non comptés).
 *
 * Deux canaux (choix cmoreau) : notification cloche du staff (1 par moto, sans
 * spam quotidien) + e-mail récapitulatif à l'atelier. Le badge planning/dashboard,
 * lui, lit l'état en direct via l'API (pas besoin de cette commande).
 */
#[AsCommand(
    name: 'app:alerte-sejour-atelier',
    description: 'Alerte (cloche + e-mail) sur les motos en atelier depuis plus de 72h ouvrées',
)]
class AlerteSejourAtelierCommand extends Command
{
    /** @deprecated Valeur historique : le délai est réglable en administration. */
    private const RENOTIFY_AFTER_HOURS = 24;

    public function __construct(
        private EntityManagerInterface $em,
        private SejourAtelierService $sejour,
        private MercureNotifier $mercureNotifier,
        private MailerInterface $mailer,
        private LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('seuil', null, InputOption::VALUE_REQUIRED, 'Seuil en heures ouvrées (défaut : réglage back-office de chaque atelier)')
            ->addOption('atelier', null, InputOption::VALUE_REQUIRED, 'Limiter à un atelier')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Affiche sans notifier ni envoyer d\'e-mail');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Sans --seuil, chaque atelier applique le seuil réglé en administration.
        $seuilImpose = $input->getOption('seuil') !== null ? max(1, (int) $input->getOption('seuil')) : null;
        $atelierOption = $input->getOption('atelier');
        $atelierId = $atelierOption !== null ? (int) $atelierOption : null;
        $dryRun = (bool) $input->getOption('dry-run');

        // En CLI le TenantFilter n'est pas actif : on voit tous les ateliers.
        $motos = $this->sejour->motosEnDepassement($seuilImpose, $atelierId);

        // Ateliers ayant coupé l'alerte automatique en back-office : l'onglet de suivi
        // reste consultable, mais on ne notifie ni n'envoie d'e-mail pour eux.
        $ignorees = 0;
        $motos = array_values(array_filter($motos, function (array $moto) use (&$ignorees) {
            if ($this->sejour->alerteActivePourAtelier($moto['atelier_id'])) {
                return true;
            }
            ++$ignorees;

            return false;
        }));
        if ($ignorees > 0) {
            $io->note(sprintf('%d moto(s) ignorée(s) : alerte désactivée en administration.', $ignorees));
        }

        if (!$motos) {
            $io->success('Aucune moto à signaler.');

            return Command::SUCCESS;
        }

        $io->section(sprintf('%d moto(s) au-delà du seuil', count($motos)));
        $io->table(
            ['RDV', 'Atelier', 'Statut', 'Reçue le', 'H. ouvrées', 'Client', 'Plaque'],
            array_map(static fn (array $m) => [
                $m['rdv_id'],
                $m['atelier_id'] ?? '-',
                $m['statut'],
                substr((string) $m['recu_le'], 0, 16),
                $m['heures_ouvrees'],
                $m['client_nom'] ?? '-',
                $m['plaque'] ?? '-',
            ], $motos),
        );

        if ($dryRun) {
            $io->note('Dry-run : aucune notification ni e-mail envoyé.');

            return Command::SUCCESS;
        }

        $notifiees = 0;
        foreach ($motos as $moto) {
            if ($this->creerNotification($moto, (int) $moto['seuil_heures'])) {
                ++$notifiees;
            }
        }
        $this->em->flush();

        // Récapitulatif e-mail, un par atelier.
        $parAtelier = [];
        foreach ($motos as $moto) {
            $parAtelier[$moto['atelier_id'] ?? 0][] = $moto;
        }
        $mailsEnvoyes = 0;
        foreach ($parAtelier as $atelier => $liste) {
            if ($this->envoyerRecap((int) $atelier, $liste, (int) $liste[0]['seuil_heures'])) {
                ++$mailsEnvoyes;
            }
        }

        $io->success(sprintf(
            '%d notification(s) créée(s) (%d déjà notifiée(s) récemment), %d e-mail(s) récapitulatif(s).',
            $notifiees,
            count($motos) - $notifiees,
            $mailsEnvoyes,
        ));

        return Command::SUCCESS;
    }

    /**
     * Crée la notification cloche si la moto n'a pas déjà été signalée récemment.
     *
     * @param array<string, mixed> $moto
     */
    private function creerNotification(array $moto, int $seuil): bool
    {
        if ($this->dejaNotifiee((int) $moto['rdv_id'], $moto['atelier_id'] !== null ? (int) $moto['atelier_id'] : null)) {
            return false;
        }

        $notif = new Notification();
        $notif->setAtelierId($moto['atelier_id'] !== null ? (int) $moto['atelier_id'] : null);
        $notif->setType('sejour_atelier_depasse');
        $notif->setSeverity('warning');
        $notif->setTitle('Moto en atelier depuis trop longtemps');
        $notif->setMessage(sprintf(
            '%s (%s) — %s — sur place depuis %s h ouvrées (seuil %dh), statut « %s »',
            $moto['vehicule'] ?: 'Moto',
            $moto['plaque'] ?? '?',
            $moto['client_nom'] ?? 'Client inconnu',
            $moto['heures_ouvrees'],
            $seuil,
            $moto['statut'],
        ));
        $notif->setRelatedEntityType('RendezVous');
        $notif->setRelatedEntityId((int) $moto['rdv_id']);
        $notif->setActionUrl('/planning?rdv=' . $moto['rdv_id']);
        $notif->setTargetRoles(['ROLE_ADMIN', 'ROLE_RECEPTIONNAIRE']);
        $notif->setTargetRole('ROLE_RECEPTIONNAIRE');
        $notif->setPriority('high');

        $this->em->persist($notif);

        try {
            if ($moto['atelier_id'] !== null) {
                $this->mercureNotifier->publishToAtelier((int) $moto['atelier_id'], $notif);
            }
        } catch (\Throwable $e) {
            // Push temps réel non bloquant : la cloche récupérera la notif au polling.
        }

        return true;
    }

    /** Une notification récente (< 24h) pour ce RDV suffit : on ne re-notifie pas. */
    private function dejaNotifiee(int $rdvId, ?int $atelierId = null): bool
    {
        $heures = $this->sejour->rappelAlerteHeures($atelierId);
        $depuis = (new \DateTimeImmutable())->modify('-' . $heures . ' hours');

        $count = (int) $this->em->createQueryBuilder()
            ->select('COUNT(n.id)')
            ->from(Notification::class, 'n')
            ->where('n.type = :type')
            ->andWhere('n.relatedEntityType = :entite')
            ->andWhere('n.relatedEntityId = :rdv')
            ->andWhere('(n.createdAt >= :depuis OR n.acknowledgedAt IS NULL)')
            ->setParameter('type', 'sejour_atelier_depasse')
            ->setParameter('entite', 'RendezVous')
            ->setParameter('rdv', $rdvId)
            ->setParameter('depuis', $depuis)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    /**
     * E-mail récapitulatif à l'adresse d'exploitation (ADMIN_EMAIL).
     *
     * @param list<array<string, mixed>> $motos
     */
    private function envoyerRecap(int $atelierId, array $motos, int $seuil): bool
    {
        $to = trim((string) ($_ENV['ADMIN_EMAIL'] ?? ''));
        if ($to === '' || $to === 'CHANGEME') {
            $this->logger->warning('app:alerte-sejour-atelier : ADMIN_EMAIL non configuré, e-mail récapitulatif ignoré.');

            return false;
        }

        $from = trim((string) ($_ENV['MAILER_FROM'] ?? '')) ?: 'noreply@paddock.fr';

        $lignes = array_map(static fn (array $m) => sprintf(
            "- %s (%s) — %s — %s h ouvrées — statut %s — mécanicien : %s",
            $m['vehicule'] ?: 'Moto',
            $m['plaque'] ?? '?',
            $m['client_nom'] ?? 'Client inconnu',
            $m['heures_ouvrees'],
            $m['statut'],
            $m['mecanicien'] ?? 'non affecté',
        ), $motos);

        $body = sprintf(
            "%d moto(s) sont à l'atelier depuis plus de %d heures ouvrées :\n\n%s\n\nLes jours de fermeture (week-end, fériés, fermetures exceptionnelles) ne sont pas comptés.",
            count($motos),
            $seuil,
            implode("\n", $lignes),
        );

        try {
            $this->mailer->send(
                (new Email())
                    ->from($from)
                    ->to($to)
                    ->subject(sprintf('[Paddock] %d moto(s) en atelier depuis plus de %dh ouvrées', count($motos), $seuil))
                    ->text($body)
            );

            return true;
        } catch (\Throwable $e) {
            $this->logger->error('app:alerte-sejour-atelier : envoi du récapitulatif impossible: {err}', ['err' => $e->getMessage()]);

            return false;
        }
    }
}
