<?php
namespace App\Command;

use App\Entity\Devis;
use App\Entity\Facture;
use App\Entity\OrdreReparation;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:backfill-snapshots', description: 'Backfill snapshot fields on existing Facture, Devis, and OrdreReparation')]
class BackfillSnapshotsCommand extends Command
{
    public function __construct(private EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $stats = ['factures' => 0, 'devis' => 0, 'or' => 0];

        // Backfill Factures
        $factures = $this->em->getRepository(Facture::class)->createQueryBuilder('f')
            ->where('f.snapClientNom IS NULL')
            ->andWhere('f.client IS NOT NULL')
            ->getQuery()->getResult();

        foreach ($factures as $facture) {
            $client = $facture->getClient();
            if (!$client) continue;
            $facture->setSnapClientNom($client->getNom());
            $facture->setSnapClientPrenom($client->getPrenom());
            $facture->setSnapClientEmail($client->getEmail());
            $facture->setSnapClientTelephone($client->getTelephone());
            $facture->setSnapClientAdresse($client->getAdresse());

            $vehicule = $facture->getVehicule();
            if ($vehicule) {
                $facture->setSnapVehiculePlaque($vehicule->getPlaque());
                $facture->setSnapVehiculeMarque($vehicule->getMarque());
                $facture->setSnapVehiculeModele($vehicule->getModele());
            }
            $stats['factures']++;
        }

        // Backfill Devis
        $devisList = $this->em->getRepository(Devis::class)->createQueryBuilder('d')
            ->where('d.snapClientNom IS NULL')
            ->andWhere('d.client IS NOT NULL')
            ->getQuery()->getResult();

        foreach ($devisList as $devis) {
            $client = $devis->getClient();
            if (!$client) continue;
            $devis->setSnapClientNom($client->getNom());
            $devis->setSnapClientPrenom($client->getPrenom());
            $devis->setSnapClientEmail($client->getEmail());
            $devis->setSnapClientTelephone($client->getTelephone());

            $vehicule = $devis->getVehicule();
            if ($vehicule) {
                $devis->setSnapVehiculePlaque($vehicule->getPlaque());
                $devis->setSnapVehiculeMarque($vehicule->getMarque());
                $devis->setSnapVehiculeModele($vehicule->getModele());
            }
            $stats['devis']++;
        }

        // Backfill OrdreReparation
        $ors = $this->em->getRepository(OrdreReparation::class)->createQueryBuilder('o')
            ->where('o.snapClientNom IS NULL')
            ->andWhere('o.rendezVous IS NOT NULL')
            ->getQuery()->getResult();

        foreach ($ors as $or) {
            $rdv = $or->getRendezVous();
            if (!$rdv) continue;
            $client = $rdv->getClient();
            if ($client) {
                $or->setSnapClientNom($client->getNom());
                $or->setSnapClientPrenom($client->getPrenom());
            }
            $vehicule = $rdv->getVehicule();
            if ($vehicule) {
                $or->setSnapVehiculePlaque($vehicule->getPlaque());
                $or->setSnapVehiculeMarque($vehicule->getMarque());
                $or->setSnapVehiculeModele($vehicule->getModele());
            }
            $stats['or']++;
        }

        $this->em->flush();

        $io->success(sprintf(
            'Backfill terminé — Factures: %d | Devis: %d | OR: %d',
            $stats['factures'], $stats['devis'], $stats['or']
        ));

        return Command::SUCCESS;
    }
}
