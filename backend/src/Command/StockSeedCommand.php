<?php

namespace App\Command;

use App\Entity\CommandeFournisseur;
use App\Entity\Fournisseur;
use App\Entity\LigneCommandeFournisseur;
use App\Entity\MouvementStock;
use App\Entity\PieceDetachee;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:stock:seed', description: 'Seed demo stock data (fournisseurs, pieces, commandes, mouvements)')]
class StockSeedCommand extends Command
{
    public function __construct(private EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $atelierId = 1;

        // Fournisseurs
        $fournisseursData = [
            ['nom' => 'Motopro', 'contact' => 'Jean Martin', 'telephone' => '0145123412', 'email' => 'contact@motopro.fr', 'delaiLivraisonJours' => 2],
            ['nom' => 'BikeParts Distribution', 'contact' => 'Sophie Durand', 'telephone' => '0145987654', 'email' => 'commandes@bikeparts.fr', 'delaiLivraisonJours' => 3],
            ['nom' => 'Moto Racing Service', 'contact' => 'Pierre Lemoine', 'telephone' => '0145235689', 'email' => 'pro@motoracing.fr', 'delaiLivraisonJours' => 5],
        ];

        $fournisseurs = [];
        foreach ($fournisseursData as $data) {
            $f = new Fournisseur();
            $f->setNom($data['nom']);
            $f->setContact($data['contact']);
            $f->setTelephone($data['telephone']);
            $f->setEmail($data['email']);
            $f->setDelaiLivraisonJours($data['delaiLivraisonJours']);
            $f->setAtelierId($atelierId);
            $f->setIsActive(1);
            $this->em->persist($f);
            $fournisseurs[] = $f;
        }
        $this->em->flush();

        // Pièces
        $piecesData = [
            ['reference' => 'FIL-HUI-10W40', 'nom' => 'Filtre à huile 10W40', 'categorie' => 'Moteur', 'quantiteStock' => 12, 'quantiteMinimale' => 5, 'prixAchatHt' => '8.50', 'prixVenteHt' => '14.90', 'fournisseur' => 0],
            ['reference' => 'CHA-DID-520', 'nom' => 'Chaîne de transmission DID 520', 'categorie' => 'Transmission', 'quantiteStock' => 3, 'quantiteMinimale' => 2, 'prixAchatHt' => '45.00', 'prixVenteHt' => '79.00', 'fournisseur' => 1],
            ['reference' => 'PLA-BRE-AV', 'nom' => 'Plaquettes de frein avant', 'categorie' => 'Freinage', 'quantiteStock' => 8, 'quantiteMinimale' => 4, 'prixAchatHt' => '18.00', 'prixVenteHt' => '32.50', 'fournisseur' => 0],
            ['reference' => 'PNE-MIC-PR2', 'nom' => 'Pneu Michelin Power Road 2', 'categorie' => 'Pneumatique', 'quantiteStock' => 6, 'quantiteMinimale' => 4, 'prixAchatHt' => '85.00', 'prixVenteHt' => '129.00', 'fournisseur' => 1],
            ['reference' => 'BAT-YU-12V', 'nom' => 'Batterie YUASA 12V 8.6Ah', 'categorie' => 'Électricité', 'quantiteStock' => 2, 'quantiteMinimale' => 3, 'prixAchatHt' => '55.00', 'prixVenteHt' => '89.00', 'fournisseur' => 2],
            ['reference' => 'CAB-EMB-RACING', 'nom' => 'Câble d\'embrayage racing', 'categorie' => 'Transmission', 'quantiteStock' => 15, 'quantiteMinimale' => 5, 'prixAchatHt' => '12.00', 'prixVenteHt' => '22.00', 'fournisseur' => 0],
            ['reference' => 'DIS-FRE-AV-310', 'nom' => 'Disque de frein avant 310mm', 'categorie' => 'Freinage', 'quantiteStock' => 4, 'quantiteMinimale' => 2, 'prixAchatHt' => '65.00', 'prixVenteHt' => '110.00', 'fournisseur' => 2],
            ['reference' => 'KIT-CHA-ORING', 'nom' => 'Kit chaîne O-ring renforcé', 'categorie' => 'Transmission', 'quantiteStock' => 0, 'quantiteMinimale' => 2, 'prixAchatHt' => '38.00', 'prixVenteHt' => '65.00', 'fournisseur' => 1],
        ];

        $pieces = [];
        foreach ($piecesData as $data) {
            $p = new PieceDetachee();
            $p->setReference($data['reference']);
            $p->setNom($data['nom']);
            $p->setCategorie($data['categorie']);
            $p->setQuantiteStock($data['quantiteStock']);
            $p->setQuantiteMinimale($data['quantiteMinimale']);
            $p->setPrixAchatHt($data['prixAchatHt']);
            $p->setPrixVenteHt($data['prixVenteHt']);
            $p->setAtelierId($atelierId);
            $p->setIsActive(1);
            if (isset($fournisseurs[$data['fournisseur']])) {
                $p->setFournisseur($fournisseurs[$data['fournisseur']]);
            }
            $this->em->persist($p);
            $pieces[] = $p;
        }
        $this->em->flush();

        // Mouvements historiques
        $mouvements = [
            ['piece' => 0, 'type' => MouvementStock::TYPE_ENTREE, 'quantite' => 20, 'motif' => 'Stock initial'],
            ['piece' => 0, 'type' => MouvementStock::TYPE_SORTIE, 'quantite' => 8, 'motif' => 'Consommation OR #1234'],
            ['piece' => 1, 'type' => MouvementStock::TYPE_ENTREE, 'quantite' => 5, 'motif' => 'Stock initial'],
            ['piece' => 1, 'type' => MouvementStock::TYPE_SORTIE, 'quantite' => 2, 'motif' => 'Consommation OR #1235'],
            ['piece' => 4, 'type' => MouvementStock::TYPE_ENTREE, 'quantite' => 6, 'motif' => 'Stock initial'],
            ['piece' => 4, 'type' => MouvementStock::TYPE_SORTIE, 'quantite' => 4, 'motif' => 'Consommation OR #1236'],
            ['piece' => 7, 'type' => MouvementStock::TYPE_ENTREE, 'quantite' => 3, 'motif' => 'Stock initial'],
            ['piece' => 7, 'type' => MouvementStock::TYPE_SORTIE, 'quantite' => 3, 'motif' => 'Consommation OR #1237'],
            ['piece' => 3, 'type' => MouvementStock::TYPE_ENTREE, 'quantite' => 10, 'motif' => 'Stock initial'],
            ['piece' => 3, 'type' => MouvementStock::TYPE_SORTIE, 'quantite' => 4, 'motif' => 'Consommation OR #1238'],
        ];

        foreach ($mouvements as $m) {
            $mv = new MouvementStock();
            $mv->setPiece($pieces[$m['piece']]);
            $mv->setType($m['type']);
            $mv->setQuantite($m['quantite']);
            $mv->setMotif($m['motif']);
            $mv->setAtelierId($atelierId);
            $this->em->persist($mv);
        }
        $this->em->flush();

        // Commandes fournisseur en attente
        $commande = new CommandeFournisseur();
        $commande->setNumeroCommande('CF-20260605-SEED01');
        $commande->setFournisseur($fournisseurs[0]);
        $commande->setAtelierId($atelierId);
        $commande->setStatut('en_attente');
        $commande->setTotalHt('93.50');
        $commande->setTotalTtc('112.20');
        $this->em->persist($commande);

        $l1 = new LigneCommandeFournisseur();
        $l1->setCommande($commande);
        $l1->setPiece($pieces[4]);
        $l1->setQuantiteDemandee(3);
        $l1->setPrixUnitaireHt('55.00');
        $l1->setAtelierId($atelierId);
        $this->em->persist($l1);

        $l2 = new LigneCommandeFournisseur();
        $l2->setCommande($commande);
        $l2->setPiece($pieces[7]);
        $l2->setQuantiteDemandee(5);
        $l2->setPrixUnitaireHt('38.00');
        $l2->setAtelierId($atelierId);
        $this->em->persist($l2);

        $this->em->flush();

        $output->writeln(sprintf('<info>Seeded %d fournisseurs, %d pieces, %d mouvements, %d commande</info>',
            count($fournisseurs), count($pieces), count($mouvements), 1));

        return Command::SUCCESS;
    }
}
