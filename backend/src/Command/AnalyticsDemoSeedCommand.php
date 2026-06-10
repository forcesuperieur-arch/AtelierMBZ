<?php

namespace App\Command;

use App\Entity\Devis;
use App\Entity\Facture;
use App\Entity\LigneFacture;
use App\Entity\OrdreReparation;
use App\Entity\RendezVous;
use App\Service\AnalyticsSyncService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:analytics:demo-seed', description: 'Seed realistic demo data for analytics')]
class AnalyticsDemoSeedCommand extends Command
{
    private array $typesIntervention = [
        'Révision' => ['min' => 60, 'max' => 120, 'prix' => [80, 150]],
        'Entretien' => ['min' => 30, 'max' => 90, 'prix' => [50, 120]],
        'Pneumatiques' => ['min' => 45, 'max' => 75, 'prix' => [120, 300]],
        'Freinage' => ['min' => 60, 'max' => 150, 'prix' => [150, 400]],
        'Diagnostic' => ['min' => 30, 'max' => 60, 'prix' => [40, 80]],
        'Carburant' => ['min' => 45, 'max' => 120, 'prix' => [100, 250]],
        'Électricité' => ['min' => 60, 'max' => 180, 'prix' => [120, 350]],
        'Révision complète' => ['min' => 120, 'max' => 300, 'prix' => [200, 600]],
    ];

    public function __construct(
        private EntityManagerInterface $em,
        private AnalyticsSyncService $sync,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('atelier_id', InputArgument::REQUIRED, 'Atelier ID');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $atelierId = (int) $input->getArgument('atelier_id');

        $rdvs = $this->em->getRepository(RendezVous::class)
            ->findBy(['atelierId' => $atelierId], ['dateRdv' => 'ASC']);

        if (empty($rdvs)) {
            $io->warning('Aucun RDV trouvé pour cet atelier.');
            return Command::SUCCESS;
        }

        $io->info(sprintf('Traitement de %d RDVs...', count($rdvs)));

        $statusFlow = ['en_attente', 'confirme', 'reception', 'en_cours', 'termine', 'restitue', 'facture', 'paye'];
        $segments = ['nouveau' => 30, 'fidèle' => 25, 'occasionnel' => 25, 'premium' => 15, 'dormant' => 5];

        foreach ($rdvs as $idx => $rdv) {
            $dayOffset = (int) $rdv->getDateRdv()->diff(new \DateTime())->days;
            $progress = min(1.0, max(0.0, 1.0 - ($dayOffset / 60)));
            $rand = mt_rand() / mt_getrandmax();
            $statusIdx = (int) floor(($rand * 0.7 + $progress * 0.3) * count($statusFlow));
            $statusIdx = min(count($statusFlow) - 1, max(0, $statusIdx));
            $status = $statusFlow[$statusIdx];

            $typeKeys = array_keys($this->typesIntervention);
            $type = $typeKeys[array_rand($typeKeys)];
            $cfg = $this->typesIntervention[$type];

            $rdv->setTypeIntervention($type);
            $rdv->setStatut($status);
            $rdv->setTempsEstime(mt_rand($cfg['min'], $cfg['max']));

            if (in_array($status, ['termine', 'restitue', 'facture', 'paye'], true)) {
                $ratio = 0.8 + (mt_rand() / mt_getrandmax()) * 0.4;
                $rdv->setTempsEffectifMinutes((int) round($rdv->getTempsEstime() * $ratio));
                $rdv->setHeureDebutTravail((clone $rdv->getDateRdv())->setTime(8 + mt_rand(0, 4), mt_rand(0, 59)));
                $rdv->setHeureFinTravail((clone $rdv->getHeureDebutTravail())->modify("+{$rdv->getTempsEffectifMinutes()} minutes"));
            }

            $client = $rdv->getClient();
            if ($client) {
                $client->setSegment($this->weightedRandom($segments));
            }

            // OR
            $hasOr = count($rdv->getOrdresReparation()) > 0;
            if (!$hasOr && in_array($status, ['en_cours', 'termine', 'restitue', 'facture', 'paye'], true)) {
                $or = new OrdreReparation();
                $or->setRendezVous($rdv);
                $or->setStatut(match ($status) {
                    'en_cours' => 'en_cours',
                    'termine', 'restitue' => 'termine',
                    'facture', 'paye' => 'execute',
                    default => 'en_cours',
                });
                $or->setTravaux("$type effectué");
                $or->setNumeroOr('OR-' . str_pad((string) mt_rand(1, 9999), 4, '0', STR_PAD_LEFT));
                $or->setTypeOr('standard');
                $this->em->persist($or);
            }

            foreach ($rdv->getOrdresReparation() as $or) {
                if (in_array($status, ['termine', 'restitue', 'facture', 'paye'], true)) {
                    $or->setStatut(match ($status) {
                        'termine', 'restitue' => 'termine',
                        'facture', 'paye' => 'execute',
                        default => 'en_cours',
                    });
                }
            }

            // Facture
            if (in_array($status, ['facture', 'paye'], true)) {
                $existingFacture = $this->em->getRepository(Facture::class)->findOneBy(['rendezVous' => $rdv]);
                if (!$existingFacture) {
                    $basePrix = mt_rand($cfg['prix'][0], $cfg['prix'][1]);
                    $moPrix = (int) round($basePrix * 0.4);
                    $piecesPrix = (int) round($basePrix * 0.6);
                    $ttc = (int) round($basePrix * 1.2);

                    $facture = new Facture();
                    $facture->setRendezVous($rdv);
                    $facture->setClient($rdv->getClient());
                    $facture->setAtelierId($atelierId);
                    $facture->setNumeroFacture('F-' . date('Ymd') . '-' . str_pad((string) mt_rand(1, 9999), 4, '0', STR_PAD_LEFT));
                    $facture->setTotalHt((string) $basePrix);
                    $facture->setTotalTtc((string) $ttc);
                    $facture->setTotalMoHt((string) $moPrix);
                    $facture->setTotalPiecesHt((string) $piecesPrix);
                    $facture->setStatut($status === 'paye' ? 'payee' : 'emise');
                    $this->em->persist($facture);

                    $ligneMO = new LigneFacture();
                    $ligneMO->setTypeLigne('mo');
                    $ligneMO->setDesignation("Main d'œuvre - $type");
                    $ligneMO->setQuantite(1);
                    $ligneMO->setPrixUnitaireHt((string) $moPrix);
                    $ligneMO->setTotalLigneHt((string) $moPrix);
                    $ligneMO->setTotalLigneTtc((string) round($moPrix * 1.2));
                    $ligneMO->setFacture($facture);
                    $this->em->persist($ligneMO);

                    $lignePiece = new LigneFacture();
                    $lignePiece->setTypeLigne('pieces');
                    $lignePiece->setDesignation("Pièces - $type");
                    $lignePiece->setQuantite(1);
                    $lignePiece->setPrixUnitaireHt((string) $piecesPrix);
                    $lignePiece->setTotalLigneHt((string) $piecesPrix);
                    $lignePiece->setTotalLigneTtc((string) round($piecesPrix * 1.2));
                    $lignePiece->setFacture($facture);
                    $this->em->persist($lignePiece);
                }
            }

            // Devis — skip (entity has no setter for rendezVousId)

            if ($idx % 10 === 0) {
                $this->em->flush();
            }
        }

        $this->em->flush();
        $io->success('Données sources enrichies.');

        $io->info('Rebuild des tables analytics...');
        $this->sync->rebuildAll($atelierId);
        $io->success('Analytics rebuild terminé !');

        return Command::SUCCESS;
    }

    private function weightedRandom(array $weights): string
    {
        $total = array_sum($weights);
        $rand = mt_rand(1, $total);
        $cumulative = 0;
        foreach ($weights as $key => $weight) {
            $cumulative += $weight;
            if ($rand <= $cumulative) return $key;
        }
        return array_key_first($weights);
    }
}
