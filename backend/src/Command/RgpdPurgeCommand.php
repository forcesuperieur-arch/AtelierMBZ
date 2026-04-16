<?php
namespace App\Command;

use App\Entity\AuditLog;
use App\Entity\Client;
use App\Entity\Devis;
use App\Entity\Facture;
use App\Entity\RappelEmail;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:rgpd-purge', description: 'RGPD automated data retention purge')]
class RgpdPurgeCommand extends Command
{
    public function __construct(private EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('execute', null, InputOption::VALUE_NONE, 'Actually execute the purge (default is dry-run)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = !$input->getOption('execute');

        if ($dryRun) {
            $io->warning('Mode DRY-RUN — aucune modification ne sera appliquée. Ajoutez --execute pour appliquer.');
        }

        $now = new \DateTime();
        $stats = ['clients_anonymized' => 0, 'audit_ips_cleared' => 0, 'rappels_deleted' => 0, 'devis_deleted' => 0];

        // 1. Anonymize clients inactive > 3 years with no invoice < 10 years
        $threeYearsAgo = (clone $now)->modify('-3 years');
        $tenYearsAgo = (clone $now)->modify('-10 years');

        $candidates = $this->em->getRepository(Client::class)->createQueryBuilder('c')
            ->where('c.isAnonymized = false')
            ->andWhere('(c.lastActivityAt IS NULL AND c.createdAt < :threeYears) OR (c.lastActivityAt IS NOT NULL AND c.lastActivityAt < :threeYears)')
            ->setParameter('threeYears', $threeYearsAgo)
            ->getQuery()->getResult();

        foreach ($candidates as $client) {
            // Check no recent invoices (< 10 years)
            $recentInvoices = (int) $this->em->getRepository(Facture::class)->createQueryBuilder('f')
                ->select('COUNT(f.id)')
                ->where('f.client = :client')
                ->andWhere('f.createdAt > :tenYears')
                ->setParameter('client', $client)
                ->setParameter('tenYears', $tenYearsAgo)
                ->getQuery()->getSingleScalarResult();

            if ($recentInvoices > 0) {
                $io->text(sprintf('  ⏭ Client #%d (%s %s) — factures récentes, reporté', $client->getId(), $client->getPrenom(), $client->getNom()));
                continue;
            }

            $io->text(sprintf('  🗑 Client #%d (%s %s) — à anonymiser', $client->getId(), $client->getPrenom(), $client->getNom()));
            $stats['clients_anonymized']++;

            if (!$dryRun) {
                $client->setNom('ANONYME');
                $client->setPrenom('ANONYME');
                $client->setEmail(null);
                $client->setTelephone('0000000000');
                $client->setAdresse(null);
                $client->setNotes(null);
                $client->setConsentDate(null);
                $client->setConsentSource(null);
                $client->setIsAnonymized(true);

                foreach ($client->getVehicules() as $vehicule) {
                    $vehicule->setPlaque('XX-000-XX');
                    $vehicule->setClient(null);
                }
            }
        }

        // 2. Anonymize IP addresses in audit logs > 6 months
        $sixMonthsAgo = (clone $now)->modify('-6 months');
        $auditCount = (int) $this->em->getRepository(AuditLog::class)->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.ipAddress IS NOT NULL')
            ->andWhere('a.createdAt < :cutoff')
            ->setParameter('cutoff', $sixMonthsAgo)
            ->getQuery()->getSingleScalarResult();

        $stats['audit_ips_cleared'] = $auditCount;
        $io->text(sprintf('  🔒 %d entrées audit_log avec IP à anonymiser', $auditCount));

        if (!$dryRun && $auditCount > 0) {
            $this->em->createQueryBuilder()
                ->update(AuditLog::class, 'a')
                ->set('a.ipAddress', ':null')
                ->where('a.ipAddress IS NOT NULL')
                ->andWhere('a.createdAt < :cutoff')
                ->setParameter('null', null)
                ->setParameter('cutoff', $sixMonthsAgo)
                ->getQuery()->execute();
        }

        // 3. Delete reminder emails > 1 year
        $oneYearAgo = (clone $now)->modify('-1 year');
        $rappelCount = (int) $this->em->getRepository(RappelEmail::class)->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.createdAt < :cutoff')
            ->setParameter('cutoff', $oneYearAgo)
            ->getQuery()->getSingleScalarResult();

        $stats['rappels_deleted'] = $rappelCount;
        $io->text(sprintf('  📧 %d rappels email à supprimer (> 1 an)', $rappelCount));

        if (!$dryRun && $rappelCount > 0) {
            $this->em->createQueryBuilder()
                ->delete(RappelEmail::class, 'r')
                ->where('r.createdAt < :cutoff')
                ->setParameter('cutoff', $oneYearAgo)
                ->getQuery()->execute();
        }

        // 4. Delete refused/expired devis > 1 year
        $devisCount = (int) $this->em->getRepository(Devis::class)->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->where('d.statut IN (:statuts)')
            ->andWhere('d.createdAt < :cutoff')
            ->setParameter('statuts', ['refuse', 'expire'])
            ->setParameter('cutoff', $oneYearAgo)
            ->getQuery()->getSingleScalarResult();

        $stats['devis_deleted'] = $devisCount;
        $io->text(sprintf('  📄 %d devis refusés/expirés à supprimer (> 1 an)', $devisCount));

        if (!$dryRun && $devisCount > 0) {
            // Delete lines first
            $devisIds = $this->em->getRepository(Devis::class)->createQueryBuilder('d')
                ->select('d.id')
                ->where('d.statut IN (:statuts)')
                ->andWhere('d.createdAt < :cutoff')
                ->setParameter('statuts', ['refuse', 'expire'])
                ->setParameter('cutoff', $oneYearAgo)
                ->getQuery()->getSingleColumnResult();

            if (!empty($devisIds)) {
                $this->em->createQuery('DELETE FROM App\Entity\LigneDevis l WHERE l.devis IN (:ids)')
                    ->setParameter('ids', $devisIds)
                    ->execute();

                $this->em->createQuery('DELETE FROM App\Entity\Devis d WHERE d.id IN (:ids)')
                    ->setParameter('ids', $devisIds)
                    ->execute();
            }
        }

        if (!$dryRun) {
            $this->em->flush();
        }

        $io->success(sprintf(
            '%s — Clients: %d | IPs audit: %d | Rappels: %d | Devis: %d',
            $dryRun ? 'DRY-RUN' : 'EXÉCUTÉ',
            $stats['clients_anonymized'],
            $stats['audit_ips_cleared'],
            $stats['rappels_deleted'],
            $stats['devis_deleted'],
        ));

        return Command::SUCCESS;
    }
}
