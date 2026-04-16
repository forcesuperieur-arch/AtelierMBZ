<?php
namespace App\Command;

use App\Entity\CategorieMoto;
use App\Entity\Client;
use App\Entity\ConfigAtelier;
use App\Entity\EmailTemplate;
use App\Entity\HoraireAtelier;
use App\Entity\Mecanicien;
use App\Entity\PieceDetachee;
use App\Entity\Pont;
use App\Entity\Prestation;
use App\Entity\RolePermission;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'app:seed', description: 'Seed reference data (roles, categories, horaires, config)')]
class SeedCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $hasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('demo', null, InputOption::VALUE_NONE, 'Also seed demo data (clients, vehicules, mecaniciens, ponts, pieces, email templates)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $this->seedRoles($io);
        $this->seedCategories($io);
        $this->seedHoraires($io);
        $this->seedConfig($io);
        $this->seedEmailTemplates($io);
        $this->seedPrestations($io);

        if ($input->getOption('demo')) {
            $this->seedMecaniciens($io);
            $this->seedPonts($io);
            $this->seedClients($io);
            $this->seedPieces($io);
        }

        $this->em->flush();
        $io->success('Seed data inserted.');

        return Command::SUCCESS;
    }

    private function seedRoles(SymfonyStyle $io): void
    {
        $roles = [
            ['super_admin', 'Super Admin', 'Accès total à tous les ateliers', '["*"]', '["*"]', 1],
            ['admin', 'Administrateur', 'Gestion complète de l\'atelier', '["dashboard","rdv","planning","clients","workshop","or","devis","facturation","stock","motos","admin","mecanicien","absences","suivi"]', '["rdv.create","rdv.edit","rdv.delete","client.create","client.edit","client.delete","facturation.create","facturation.edit","stock.create","stock.edit","stock.delete","admin.users","admin.config"]', 1],
            ['receptionnaire', 'Réceptionnaire', 'Gestion RDV, clients, facturation', '["dashboard","rdv","planning","clients","workshop","or","devis","facturation","suivi"]', '["rdv.create","rdv.edit","client.create","client.edit","facturation.create","facturation.edit"]', 1],
            ['mecanicien', 'Mécanicien', 'Espace technicien, rapports', '["mecanicien","suivi"]', '["rdv.edit","or.edit"]', 1],
            ['comptable', 'Comptable', 'Facturation et statistiques', '["dashboard","facturation","stock"]', '["facturation.create","facturation.edit","stock.edit"]', 1],
        ];

        foreach ($roles as [$role, $label, $desc, $sections, $perms, $system]) {
            $existing = $this->em->getRepository(RolePermission::class)->find($role);
            if ($existing) continue;

            $rp = new RolePermission();
            $rp->setRole($role);
            $rp->setLabel($label);
            $rp->setDescription($desc);
            $rp->setSectionsJson($sections);
            $rp->setPermissionsJson($perms);
            $rp->setIsSystem($system);
            $this->em->persist($rp);
        }
        $io->info('Roles seeded.');
    }

    private function seedCategories(SymfonyStyle $io): void
    {
        $cats = ['Roadster', 'Sportive', 'Trail', 'Touring', 'Custom', 'Scooter', 'Enduro', 'Supermotard', 'Vintage', 'Électrique'];

        foreach ($cats as $nom) {
            $existing = $this->em->getRepository(CategorieMoto::class)->findOneBy(['nom' => $nom]);
            if ($existing) continue;

            $cat = new CategorieMoto();
            $cat->setNom($nom);
            $this->em->persist($cat);
        }
        $io->info('Moto categories seeded.');
    }

    private function seedHoraires(SymfonyStyle $io): void
    {
        $count = $this->em->getRepository(HoraireAtelier::class)->count([]);
        if ($count > 0) return;

        // Monday to Friday open, Saturday morning, Sunday closed
        $horaires = [
            [0, '08:00', '18:00', '12:00', '13:30', 1],
            [1, '08:00', '18:00', '12:00', '13:30', 1],
            [2, '08:00', '18:00', '12:00', '13:30', 1],
            [3, '08:00', '18:00', '12:00', '13:30', 1],
            [4, '08:00', '18:00', '12:00', '13:30', 1],
            [5, '09:00', '13:00', null, null, 1],
            [6, null, null, null, null, 0],
        ];

        foreach ($horaires as [$jour, $ouv, $ferm, $pd, $pf, $ouvert]) {
            $h = new HoraireAtelier();
            $h->setAtelierId(1);
            $h->setJourSemaine($jour);
            $h->setHeureOuverture($ouv);
            $h->setHeureFermeture($ferm);
            $h->setPauseDebut($pd);
            $h->setPauseFin($pf);
            $h->setIsOuvert($ouvert);
            $this->em->persist($h);
        }
        $io->info('Workshop hours seeded.');
    }

    private function seedConfig(SymfonyStyle $io): void
    {
        $existing = $this->em->getRepository(ConfigAtelier::class)->findOneBy(['atelierId' => 1]);
        if ($existing) return;

        $config = new ConfigAtelier();
        $config->setAtelierId(1);
        $this->em->persist($config);
        $io->info('Default ConfigAtelier seeded.');
    }

    private function seedEmailTemplates(SymfonyStyle $io): void
    {
        $templates = [
            ['confirmation', 'Confirmation RDV', 'Confirmation de votre rendez-vous du {{date_rdv}}', '<p>Bonjour {{client_prenom}} {{client_nom}},</p><p>Votre rendez-vous du <strong>{{date_rdv}}</strong> à <strong>{{heure_rdv}}</strong> pour <em>{{type_intervention}}</em> est confirmé.</p><p>Suivez l\'avancement : <a href="{{url_suivi}}">Suivi en ligne</a></p>'],
            ['rappel_j3', 'Rappel J-3', 'Rappel : votre rendez-vous dans 3 jours', '<p>Bonjour {{client_prenom}},</p><p>Rappel : votre rendez-vous est prévu dans 3 jours, le <strong>{{date_rdv}}</strong> à <strong>{{heure_rdv}}</strong>.</p>'],
            ['rappel_j1', 'Rappel J-1', 'Rappel : votre rendez-vous demain', '<p>Bonjour {{client_prenom}},</p><p>Rappel : votre rendez-vous est <strong>demain</strong> le {{date_rdv}} à {{heure_rdv}}.</p>'],
            ['travaux_termines', 'Travaux terminés', 'Votre moto est prête !', '<p>Bonjour {{client_prenom}},</p><p>Les travaux sur votre véhicule sont terminés. Vous pouvez venir le récupérer.</p>'],
        ];

        foreach ($templates as [$code, $nom, $sujet, $corps]) {
            $existing = $this->em->getRepository(EmailTemplate::class)->findOneBy(['code' => $code]);
            if ($existing) continue;

            $t = new EmailTemplate();
            $t->setAtelierId(1);
            $t->setCode($code);
            $t->setNom($nom);
            $t->setSujet($sujet);
            $t->setCorpsHtml($corps);
            $t->setVariablesDisponibles('["client_nom","client_prenom","date_rdv","heure_rdv","type_intervention","token_suivi","url_suivi"]');
            $this->em->persist($t);
        }
        $io->info('Email templates seeded.');
    }

    private function seedMecaniciens(SymfonyStyle $io): void
    {
        if ($this->em->getRepository(Mecanicien::class)->count([]) > 0) return;

        $mecas = [
            ['Dupont', 'Marc', 'Moteur, Injection', '#3B82F6'],
            ['Martin', 'Lucas', 'Électricité, Diagnostic', '#EF4444'],
            ['Bernard', 'Thomas', 'Freins, Suspension', '#10B981'],
        ];

        foreach ($mecas as $i => [$nom, $prenom, $specialites, $couleur]) {
            // Create user for mecanicien
            $user = new User();
            $user->setUsername('meca' . ($i + 1));
            $user->setEmail("meca" . ($i + 1) . "@atelier.local");
            $user->setRole('mecanicien');
            $user->setAtelierId(1);
            $user->setHashedPassword($this->hasher->hashPassword($user, 'meca123'));
            $this->em->persist($user);
            $this->em->flush();

            $m = new Mecanicien();
            $m->setAtelierId(1);
            $m->setNom($nom);
            $m->setPrenom($prenom);
            $m->setSpecialites($specialites);
            $m->setCouleur($couleur);
            $m->setUserId($user->getId());
            $this->em->persist($m);
        }
        $io->info('Demo mechanics seeded.');
    }

    private function seedPonts(SymfonyStyle $io): void
    {
        if ($this->em->getRepository(Pont::class)->count([]) > 0) return;

        $ponts = [
            ['Pont 1 - Moto', 'moto', 500, 0],
            ['Pont 2 - Moto', 'moto', 500, 1],
            ['Pont 3 - Quad', 'quad', 800, 2],
        ];

        foreach ($ponts as [$nom, $type, $cap, $ordre]) {
            $p = new Pont();
            $p->setAtelierId(1);
            $p->setNom($nom);
            $p->setTypePont($type);
            $p->setCapaciteKg($cap);
            $p->setOrdreAffichage($ordre);
            $this->em->persist($p);
        }
        $io->info('Demo ponts seeded.');
    }

    private function seedClients(SymfonyStyle $io): void
    {
        if ($this->em->getRepository(Client::class)->count([]) > 0) return;

        $clients = [
            ['Moreau', 'Jean', '0601020304', 'jean.moreau@email.fr'],
            ['Petit', 'Sophie', '0611223344', 'sophie.petit@email.fr'],
            ['Robert', 'Pierre', '0622334455', 'pierre.robert@email.fr'],
            ['Durand', 'Marie', '0633445566', 'marie.durand@email.fr'],
            ['Leroy', 'Antoine', '0644556677', 'antoine.leroy@email.fr'],
        ];

        foreach ($clients as [$nom, $prenom, $tel, $email]) {
            $c = new Client();
            $c->setAtelierId(1);
            $c->setNom($nom);
            $c->setPrenom($prenom);
            $c->setTelephone($tel);
            $c->setEmail($email);
            $this->em->persist($c);
        }
        $io->info('Demo clients seeded.');
    }

    private function seedPrestations(SymfonyStyle $io): void
    {
        if ($this->em->getRepository(Prestation::class)->count([]) > 0) return;

        $prestations = [
            ['DIAG-45', 'Diagnostic / recherche de panne', 'diagnostic', '49.17', '59.00', 45, 'Lecture défauts, contrôle visuel et premier diagnostic.', 'tous'],
            ['VID-SCOOT', 'Forfait vidange scooter', 'entretien', '57.50', '69.00', 45, 'Vidange moteur avec contrôle des niveaux et serrages.', 'scooter'],
            ['VID-MOTO', 'Forfait vidange moto', 'entretien', '74.17', '89.00', 60, 'Vidange standard et contrôle sécurité atelier.', 'moto'],
            ['REV-INT', 'Révision intermédiaire', 'entretien', '107.50', '129.00', 90, 'Contrôle des points de sécurité et entretien courant.', 'tous'],
            ['REV-CPL', 'Révision complète', 'entretien', '182.50', '219.00', 180, 'Révision atelier complète avec essai et vérifications.', 'tous'],
            ['PNEU-AV', 'Forfait pneu avant', 'pneumatique', '29.17', '35.00', 30, 'Montage et équilibrage du pneu avant.', 'moto'],
            ['PNEU-AR', 'Forfait pneu arrière', 'pneumatique', '32.50', '39.00', 35, 'Montage et équilibrage du pneu arrière.', 'moto'],
            ['PNEU-SET', 'Forfait train de pneus', 'pneumatique', '62.50', '75.00', 75, 'Montage et équilibrage avant + arrière.', 'moto'],
            ['FREIN-AV', 'Forfait plaquettes avant', 'freinage', '57.50', '69.00', 60, 'Remplacement plaquettes avant et contrôle du circuit.', 'tous'],
            ['FREIN-AR', 'Forfait plaquettes arrière', 'freinage', '49.17', '59.00', 45, 'Remplacement plaquettes arrière et contrôle.', 'tous'],
            ['KIT-CHAINE', 'Forfait kit chaîne', 'transmission', '99.17', '119.00', 75, 'Pose du kit chaîne avec réglage tension et alignement.', 'moto'],
            ['BATTERIE', 'Forfait batterie / charge', 'electricite', '40.83', '49.00', 30, 'Contrôle charge, tension et remplacement simple.', 'tous'],
            ['HIVER', 'Forfait hivernage / remise en route', 'saisonnier', '65.83', '79.00', 60, 'Contrôle complet après immobilisation et remise en route.', 'tous'],
        ];

        foreach ($prestations as [$code, $nom, $cat, $prixHt, $prixTtc, $temps, $description, $typeVehicule]) {
            $p = new Prestation();
            $p->setAtelierId(1);
            $p->setCode($code);
            $p->setNom($nom);
            $p->setDescription($description);
            $p->setCategorie($cat);
            $p->setPrixBaseHt($prixHt);
            $p->setPrixBaseTtc($prixTtc);
            $p->setTempsEstimeMinutes($temps);
            $p->setTypeTarif('forfait');
            $p->setTypeVehicule($typeVehicule);
            $p->setIsForfait(1);
            $this->em->persist($p);
        }
        $io->info('Default forfait prestations seeded.');
    }

    private function seedPieces(SymfonyStyle $io): void
    {
        if ($this->em->getRepository(PieceDetachee::class)->count([]) > 0) return;

        $pieces = [
            ['FIL-HUI-001', 'Filtre à huile universel', 'filtre', 15, 5, '4.50', '8.90'],
            ['FIL-AIR-001', 'Filtre à air sport', 'filtre', 8, 3, '12.00', '24.00'],
            ['PLQ-AV-001', 'Plaquettes frein avant Brembo', 'freinage', 20, 5, '18.00', '35.00'],
            ['PLQ-AR-001', 'Plaquettes frein arrière', 'freinage', 15, 5, '14.00', '28.00'],
            ['HUI-10W40', 'Huile moteur 10W40 1L', 'lubrifiant', 30, 10, '8.00', '14.50'],
            ['HUI-5W40', 'Huile moteur 5W40 synthèse 1L', 'lubrifiant', 25, 10, '12.00', '19.90'],
            ['BOU-NGK', 'Bougie NGK standard', 'allumage', 40, 10, '3.50', '7.90'],
            ['KIT-CHA-520', 'Kit chaîne 520', 'transmission', 6, 2, '55.00', '95.00'],
            ['PNEU-120', 'Pneu 120/70 ZR17 avant', 'pneumatique', 4, 2, '65.00', '110.00'],
            ['PNEU-180', 'Pneu 180/55 ZR17 arrière', 'pneumatique', 4, 2, '80.00', '135.00'],
        ];

        foreach ($pieces as [$ref, $nom, $cat, $stock, $min, $prixAchat, $prixVente]) {
            $p = new PieceDetachee();
            $p->setAtelierId(1);
            $p->setReference($ref);
            $p->setNom($nom);
            $p->setCategorie($cat);
            $p->setQuantiteStock($stock);
            $p->setQuantiteMinimale($min);
            $p->setPrixAchatHt($prixAchat);
            $p->setPrixVenteHt($prixVente);
            $this->em->persist($p);
        }
        $io->info('Demo pieces seeded.');
    }
}
