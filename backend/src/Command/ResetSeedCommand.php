<?php

namespace App\Command;

use App\Entity\Client;
use App\Entity\Mecanicien;
use App\Entity\OrdreReparation;
use App\Entity\Pont;
use App\Entity\RendezVous;
use App\Entity\User;
use App\Entity\Vehicule;
use App\Entity\VOCounter;
use App\Entity\VODepotVente;
use App\Entity\VOLivrePolice;
use App\Entity\VOPurchase;
use Doctrine\ORM\EntityManagerInterface;
use Faker\Factory as FakerFactory;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:reset-seed',
    description: 'Vide les données opérationnelles et reseed avec Faker (clients, RDV, VO). Garde config, clauses, templates, catalogue moto.',
)]
class ResetSeedCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $hasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('force', null, InputOption::VALUE_NONE, 'Exécuter sans confirmation (pour CI/scripts)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (!$input->getOption('force')) {
            $io->warning('Cette commande supprime TOUTES les données opérationnelles (clients, RDV, VO, OR, etc.).');
            if (!$io->confirm('Continuer ?', false)) {
                $io->comment('Annulé.');
                return Command::SUCCESS;
            }
        }

        $faker = FakerFactory::create('fr_FR');
        $conn = $this->em->getConnection();

        // ─── 1. TRUNCATE opérationnel (CASCADE) ───────────────────────────────
        $io->section('Nettoyage des données opérationnelles…');
        $conn->executeStatement('
            TRUNCATE TABLE
                notification_escalations,
                notification_logs,
                rappels_email,
                audit_logs,
                revoked_tokens,
                messenger_messages,
                photos_intervention,
                essai_routier,
                rapport_intervention,
                pieces_utilisees,
                demandes_travaux_supp,
                annulation_rdv,
                lignes_devis,
                devis,
                lignes_facture,
                paiements,
                factures,
                ordres_reparation,
                rendez_vous,
                vo_remise_en_etat_pieces,
                vo_remise_en_etat_lignes,
                vo_remises_en_etat,
                vo_livre_police,
                vo_factures,
                vo_documents,
                vo_depot_ventes,
                vo_purchases,
                vo_counters,
                vehicules,
                clients,
                absences,
                ponts,
                grille_tarifaire,
                forfaits_mo,
                commande_piece,
                lignes_commande_fournisseur,
                commandes_fournisseur,
                fournisseurs,
                notifications
            CASCADE
        ');
        $io->success('Tables opérationnelles vidées.');

        // ─── 2. Supprimer utilisateurs non-admin ────────────────────────────
        $io->section('Nettoyage des comptes mécaniciens…');
        $conn->executeStatement("DELETE FROM mecaniciens WHERE atelier_id = 1");
        $conn->executeStatement("DELETE FROM user_atelier_roles WHERE atelier_id = 1 AND user_id IN (SELECT id FROM users WHERE role != 'super_admin' AND role != 'admin' AND id != 1)");
        $conn->executeStatement("DELETE FROM users WHERE role = 'mecanicien' OR email LIKE 'meca%@atelier.local'");
        $io->success('Utilisateurs mécaniciens supprimés.');

        $this->em->clear();

        // ─── 3. Recrée mécaniciens + ponts ──────────────────────────────────
        $io->section('Création mécaniciens + ponts…');
        [$meca1, $meca2, $meca3] = $this->seedMecaniciens($io);
        [$pont1, $pont2] = $this->seedPonts($io);
        $this->em->flush();
        $io->success('Mécaniciens et ponts créés.');

        // ─── 4. Clients + Véhicules ──────────────────────────────────────────
        $io->section('Création clients et véhicules (Faker)…');
        $clients = $this->seedClients($faker);
        $vehicules = $this->seedVehicules($faker, $clients);
        $this->em->flush();
        $io->success(count($clients) . ' clients, ' . count($vehicules) . ' véhicules créés.');

        // ─── 5. RDV + OR ─────────────────────────────────────────────────────
        $io->section('Création RDV et OR (scénarios réalistes)…');
        $rdvList = $this->seedRdv($faker, $clients, $vehicules, [$meca1, $meca2, $meca3], [$pont1, $pont2]);
        $this->em->flush();
        $io->success(count($rdvList) . ' RDV créés.');

        // ─── 6. VO ───────────────────────────────────────────────────────────
        $io->section('Création dossiers VO (achats + dépôts)…');
        [$purchases, $lpCount] = $this->seedVO($faker, $clients, $vehicules);
        $this->em->flush();
        $io->success(count($purchases) . ' achats VO, ' . $lpCount . ' entrées LP, 1 dépôt-vente créés.');

        $io->success('Reset + seed terminé. Base prête pour une démo complète.');
        return Command::SUCCESS;
    }

    // ═══════════════════════════════════════════
    // MÉCANICIENS
    // ═══════════════════════════════════════════

    /** @return Mecanicien[] */
    private function seedMecaniciens(SymfonyStyle $io): array
    {
        $mecaData = [
            ['Dupont', 'Marc', 'Moteur, Injection', '#3B82F6', 'meca1', 'marc.dupont@atelier.local'],
            ['Martin', 'Lucas', 'Électricité, Diagnostic', '#EF4444', 'meca2', 'lucas.martin@atelier.local'],
            ['Bernard', 'Thomas', 'Freins, Suspension', '#10B981', 'meca3', 'thomas.bernard@atelier.local'],
        ];

        $mecas = [];
        foreach ($mecaData as [$nom, $prenom, $specialites, $couleur, $username, $email]) {
            $user = new User();
            $user->setUsername($username);
            $user->setEmail($email);
            $user->setRole('mecanicien');
            $user->setAtelierId(1);
            $user->setHashedPassword($this->hasher->hashPassword($user, 'meca123'));
            $this->em->persist($user);
            $this->em->flush(); // flush pour avoir l'ID

            $m = new Mecanicien();
            $m->setAtelierId(1);
            $m->setNom($nom);
            $m->setPrenom($prenom);
            $m->setSpecialites($specialites);
            $m->setCouleur($couleur);
            $m->setUserId($user->getId());
            $this->em->persist($m);
            $mecas[] = $m;
        }

        $io->writeln('  → 3 mécaniciens (meca1/meca2/meca3, mdp: meca123)');
        return $mecas;
    }

    // ═══════════════════════════════════════════
    // PONTS
    // ═══════════════════════════════════════════

    /** @return Pont[] */
    private function seedPonts(SymfonyStyle $io): array
    {
        $pontsData = [
            ['Pont A - Atelier', 'moto', 500, 0],
            ['Pont B - Atelier', 'moto', 500, 1],
        ];

        $ponts = [];
        foreach ($pontsData as [$nom, $type, $cap, $ordre]) {
            $p = new Pont();
            $p->setAtelierId(1);
            $p->setNom($nom);
            $p->setTypePont($type);
            $p->setCapaciteKg($cap);
            $p->setOrdreAffichage($ordre);
            $this->em->persist($p);
            $ponts[] = $p;
        }

        $io->writeln('  → 2 ponts créés.');
        return $ponts;
    }

    // ═══════════════════════════════════════════
    // CLIENTS
    // ═══════════════════════════════════════════

    /** @return Client[] */
    private function seedClients(\Faker\Generator $faker): array
    {
        $clients = [];
        for ($i = 0; $i < 15; $i++) {
            $c = new Client();
            $c->setAtelierId(1);
            $c->setNom($faker->lastName());
            $c->setPrenom($faker->firstName());
            $c->setTelephone($faker->regexify('0[67][0-9]{8}'));
            $c->setEmail($faker->unique()->safeEmail());
            $c->setAdresse($faker->streetAddress() . ', ' . $faker->postcode() . ' ' . $faker->city());
            $this->em->persist($c);
            $clients[] = $c;
        }
        return $clients;
    }

    // ═══════════════════════════════════════════
    // VÉHICULES
    // ═══════════════════════════════════════════

    private static array $MOTOS = [
        ['Honda', 'CB 500 F', '500cc', 'roadster'],
        ['Yamaha', 'MT-07', '700cc', 'roadster'],
        ['Kawasaki', 'Z900', '900cc', 'roadster'],
        ['BMW', 'R 1250 GS', '1250cc', 'trail'],
        ['Ducati', 'Monster 937', '937cc', 'roadster'],
        ['KTM', '790 Adventure', '790cc', 'trail'],
        ['Suzuki', 'GSX-S750', '750cc', 'roadster'],
        ['Honda', 'Africa Twin', '1100cc', 'trail'],
        ['Yamaha', 'Tracer 9', '890cc', 'touring'],
        ['Triumph', 'Tiger 900', '900cc', 'trail'],
        ['Honda', 'PCX 125', '125cc', 'scooter'],
        ['Yamaha', 'TMAX 560', '560cc', 'scooter'],
    ];

    /** @return Vehicule[] */
    private function seedVehicules(\Faker\Generator $faker, array $clients): array
    {
        $vehicules = [];
        foreach ($clients as $client) {
            $count = $faker->randomElement([1, 1, 1, 2]); // 75% ont 1 moto, 25% en ont 2
            for ($i = 0; $i < $count; $i++) {
                $moto = $faker->randomElement(self::$MOTOS);
                $annee = $faker->numberBetween(2015, 2024);
                $plaque = strtoupper($faker->bothify('??-###-??'));

                $v = new Vehicule();
                $v->setAtelierId(1);
                $v->setClient($client);
                $v->setPlaque($plaque);
                $v->setMarque($moto[0]);
                $v->setModele($moto[1]);
                $v->setCylindree($moto[2]);
                $v->setTypeMoto($moto[3]);
                $v->setAnnee($annee);
                $this->em->persist($v);
                $vehicules[] = $v;
            }
        }
        return $vehicules;
    }

    // ═══════════════════════════════════════════
    // RDV + OR
    // ═══════════════════════════════════════════

    private static array $TYPES_INTERVENTION = [
        'Révision complète',
        'Vidange + filtres',
        'Remplacement plaquettes',
        'Diagnostic électronique',
        'Kit chaîne',
        'Pneus avant + arrière',
        'Révision intermédiaire',
        'Contrôle technique préparatoire',
        'Batterie et charge',
        'Hivernage',
    ];

    /** @return RendezVous[] */
    private function seedRdv(\Faker\Generator $faker, array $clients, array $vehicules, array $mecas, array $ponts): array
    {
        $rdvList = [];
        $today = new \DateTime('today');
        $orCounter = 1;

        $scenarios = [
            // [statut, jours_offset, heure, avec_or, or_statut]
            // Passés restitués
            ['restitue', -30, '09:00', true, 'signe'],
            ['restitue', -22, '10:00', true, 'signe'],
            ['restitue', -15, '14:00', true, 'signe'],
            ['restitue', -10, '09:30', true, 'signe'],
            ['restitue', -7,  '11:00', true, 'signe'],
            ['restitue', -5,  '08:30', true, 'signe'],
            // Passés terminés (attente facturation)
            ['termine', -3,  '09:00', true, 'signe'],
            ['termine', -2,  '10:30', true, 'signe'],
            ['termine', -1,  '14:00', true, 'signe'],
            // Aujourd'hui
            ['en_cours',          0, '08:00', true, 'signe'],
            ['en_attente_pieces', 0, '09:00', true, 'signe'],
            ['en_pause',          0, '10:00', true, 'signe'],
            ['reception',         0, '11:00', true, 'brouillon'],
            ['no_show',           0, '08:30', false, null],
            // À venir
            ['confirme', +1,  '09:00', false, null],
            ['confirme', +2,  '10:00', false, null],
            ['confirme', +3,  '14:30', false, null],
            ['en_attente', +5, '09:00', false, null],
            ['en_attente', +7, '10:00', false, null],
            ['en_attente', +14, '14:00', false, null],
        ];

        foreach ($scenarios as $idx => [$statut, $offsetJours, $heure, $avecOr, $orStatut]) {
            $rdvDate = (clone $today)->modify("{$offsetJours} days");
            $client = $clients[$idx % count($clients)];
            // Cherche un véhicule du client, ou prend un random
            $clientVehicules = array_filter($vehicules, fn($v) => $v->getClient() === $client);
            $vehicule = count($clientVehicules) > 0
                ? array_values($clientVehicules)[0]
                : $faker->randomElement($vehicules);

            $rdv = new RendezVous();
            $rdv->setAtelierId(1);
            $rdv->setClient($client);
            $rdv->setVehicule($vehicule);
            $rdv->setDateRdv($rdvDate);
            $rdv->setHeureRdv(new \DateTime('2000-01-01 ' . $heure));
            $rdv->setTypeIntervention($faker->randomElement(self::$TYPES_INTERVENTION));
            $rdv->setStatut($statut);
            $rdv->setMecanicien($faker->randomElement($mecas));
            $rdv->setPont($faker->randomElement($ponts));
            $rdv->setKilometrage($faker->numberBetween(5000, 80000));
            $rdv->setTempsEstime($faker->randomElement([45, 60, 90, 120, 180]));

            if ($offsetJours <= 0) {
                $rdv->setCommentaire($faker->optional(0.6)->sentence());
            }

            $this->em->persist($rdv);
            $rdvList[] = $rdv;

            if ($avecOr) {
                $this->em->flush(); // nécessaire pour avoir l'ID du RDV
                $or = new OrdreReparation();
                $or->setRendezVous($rdv);
                $or->setNumeroOr(sprintf('OR-%d-%05d', (int) date('Y'), $orCounter++));
                $or->setStatut($orStatut);
                $or->setKilometrage($rdv->getKilometrage());
                $or->setEtatVehicule('Bon état général.');
                if ($orStatut === 'signe') {
                    $or->setSignatureClient('data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');
                    $or->setSignedAt(new \DateTime());
                    $or->setSnapClientNom($client->getNom());
                    $or->setSnapClientPrenom($client->getPrenom());
                }
                $this->em->persist($or);
            }
        }

        return $rdvList;
    }

    // ═══════════════════════════════════════════
    // VO : ACHATS + DÉPÔTS + LP
    // ═══════════════════════════════════════════

    /** @return array{VOPurchase[], int} */
    private function seedVO(\Faker\Generator $faker, array $clients, array $vehicules): array
    {
        $purchases = [];
        $lpCounter = 1;
        $year = (int) date('Y');

        // Scénarios achats VO
        $voScenarios = [
            // [status, siv_status, avec_lp, vendu]
            [VOPurchase::STATUS_EN_STOCK,  VOPurchase::SIV_STATUS_A_PREPARER,  false, false],
            [VOPurchase::STATUS_EN_STOCK,  VOPurchase::SIV_STATUS_EN_COURS,    true,  false],
            [VOPurchase::STATUS_EN_VENTE,  VOPurchase::SIV_STATUS_ENREGISTREE, true,  false],
            [VOPurchase::STATUS_EN_VENTE,  VOPurchase::SIV_STATUS_ENREGISTREE, true,  false],
            [VOPurchase::STATUS_VENDU,     VOPurchase::SIV_STATUS_ENREGISTREE, true,  true],
        ];

        foreach ($voScenarios as [$status, $sivStatus, $avecLp, $vendu]) {
            $moto = $faker->randomElement(self::$MOTOS);
            $annee = $faker->numberBetween(2016, 2023);
            $seller = $faker->randomElement($clients);

            // Véhicule VO dédié (pas lié à un client — c'est le stock VO)
            $voVehicule = new Vehicule();
            $voVehicule->setAtelierId(1);
            $voVehicule->setPlaque(strtoupper($faker->bothify('??-###-??')));
            $voVehicule->setMarque($moto[0]);
            $voVehicule->setModele($moto[1]);
            $voVehicule->setCylindree($moto[2]);
            $voVehicule->setTypeMoto($moto[3]);
            $voVehicule->setAnnee($annee);
            $this->em->persist($voVehicule);
            $this->em->flush();

            $purchasePrice = $faker->randomElement(['1200.00', '1800.00', '2500.00', '3200.00', '4500.00']);
            $targetPrice = bcadd($purchasePrice, (string) $faker->numberBetween(300, 1500), 2);

            $purchase = new VOPurchase();
            $purchase->setAtelierId(1);
            $purchase->setVehicule($voVehicule);
            $purchase->setSeller($seller);
            $purchase->setPurchasePrice($purchasePrice);
            $purchase->setTargetSalePrice($targetPrice);
            $purchase->setStatus($status);
            $purchase->setSivStatus($sivStatus);
            $purchase->setPurchaseDate(new \DateTime('-' . $faker->numberBetween(5, 60) . ' days'));
            $purchase->setSellerIdType('CNI');
            $purchase->setSellerIdNumber(strtoupper($faker->bothify('??######??')));
            $purchase->setSellerIdDate(new \DateTime('-3 years'));
            $purchase->setNonGageDate(new \DateTime('-' . $faker->numberBetween(1, 10) . ' days'));
            $purchase->setControleTechniqueOk($faker->boolean(70));

            if ($sivStatus === VOPurchase::SIV_STATUS_ENREGISTREE) {
                $purchase->setSivReference('DA-' . $year . '-' . $faker->numerify('#####'));
                $purchase->setSivRecordedAt(new \DateTime('-' . $faker->numberBetween(1, 20) . ' days'));
            }

            if ($vendu) {
                $purchase->setSaleDate(new \DateTime('-' . $faker->numberBetween(1, 10) . ' days'));
            }

            $this->em->persist($purchase);
            $this->em->flush(); // ID nécessaire pour LP

            if ($avecLp) {
                $lp = new VOLivrePolice();
                $lp->setAtelierId(1);
                $lp->setNumeroOrdre($lpCounter++);
                $lp->setType('achat');
                $lp->setDateAcquisition($purchase->getPurchaseDate());
                $lp->setDescriptionBien(sprintf('%s %s %s %d', $moto[0], $moto[1], $moto[2], $annee));
                $lp->setImmatriculation($voVehicule->getPlaque());
                $lp->setVendeurNom($seller->getNom());
                $lp->setVendeurPrenom($seller->getPrenom());
                $lp->setVendeurAdresse($seller->getAdresse() ?? '1 rue de la Paix, 75001 Paris');
                $lp->setVendeurIdType('CNI');
                $lp->setVendeurIdNumber($purchase->getSellerIdNumber());
                $lp->setVendeurIdDate($purchase->getSellerIdDate());
                $lp->setPrixAchat($purchasePrice);
                $lp->setModePaiement($faker->randomElement(['virement', 'cheque', 'especes']));
                $lp->setVoPurchase($purchase);

                if ($vendu) {
                    $lp->setPrixVente($targetPrice);
                    $lp->setDateVente($purchase->getSaleDate());
                    $lp->setModePaiementVente('virement');
                    $buyer = $faker->randomElement($clients);
                    $lp->setAcheteurNom($buyer->getNom());
                    $lp->setAcheteurPrenom($buyer->getPrenom());
                    $lp->setAcheteurAdresse($buyer->getAdresse() ?? '10 avenue Victor Hugo, 69001 Lyon');
                }

                $this->em->persist($lp);
            }

            $purchases[] = $purchase;
        }

        // Initialise le compteur LP pour les prochaines créations
        $lpCounterEntity = new VOCounter();
        $lpCounterEntity->setCounterType('livre_police');
        $lpCounterEntity->setAtelierId(1);
        $lpCounterEntity->setCounterYear($year);
        $lpCounterEntity->setCounterValue($lpCounter - 1);
        $lpCounterEntity->setCreatedAt(new \DateTime());
        $lpCounterEntity->setUpdatedAt(new \DateTime());
        $this->em->persist($lpCounterEntity);

        // Dépôt-vente
        $depot = $this->seedDepotVente($faker, $clients, $vehicules);
        $this->em->persist($depot);

        $lpCount = $lpCounter - 1;
        return [$purchases, $lpCount];
    }

    private function seedDepotVente(\Faker\Generator $faker, array $clients, array $vehicules): VODepotVente
    {
        $moto = $faker->randomElement(self::$MOTOS);
        $deposant = $faker->randomElement($clients);

        $voVehicule = new Vehicule();
        $voVehicule->setAtelierId(1);
        $voVehicule->setPlaque(strtoupper($faker->bothify('??-###-??')));
        $voVehicule->setMarque($moto[0]);
        $voVehicule->setModele($moto[1]);
        $voVehicule->setCylindree($moto[2]);
        $voVehicule->setTypeMoto($moto[3]);
        $voVehicule->setAnnee($faker->numberBetween(2017, 2023));
        $this->em->persist($voVehicule);

        $depot = new VODepotVente();
        $depot->setAtelierId(1);
        $depot->setVehicule($voVehicule);
        $depot->setDeposant($deposant);
        // [SEED-ONLY] Bypass workflow — entity will be persisted then activated via setStatus
        $depot->setStatus('actif');
        $depot->setPrixVenteSouhaite($faker->randomElement(['3500.00', '4200.00', '5800.00']));
        $depot->setCommissionType('pct');
        $depot->setCommissionValeur('12.00');
        $depot->setDateDebut(new \DateTime('-' . $faker->numberBetween(5, 30) . ' days'));
        $depot->setDateFin(new \DateTime('+' . $faker->numberBetween(30, 60) . ' days'));
        $depot->setNotes('Dépôt-vente créé via seed demo.');

        return $depot;
    }
}
