<?php

namespace App\Service;

use App\Entity\CommandeFournisseur;
use App\Entity\LigneCommandeFournisseur;
use App\Entity\MouvementStock;
use App\Entity\PieceDetachee;
use App\Entity\RendezVous;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Gestion centralisée des mouvements de stock.
 * Chaque modification de quantité passe par ici pour créer un MouvementStock immuable.
 */
class StockMovementService
{
    public function __construct(
        private EntityManagerInterface $em,
        private AuditService $auditService,
    ) {}

    /**
     * Crée un mouvement de stock et met à jour la quantité de la pièce.
     */
    public function recordMovement(
        PieceDetachee $piece,
        string $type,
        int $quantite,
        ?string $prixUnitaireHt = null,
        ?string $motif = null,
        ?CommandeFournisseur $commande = null,
        ?RendezVous $rdv = null,
        ?User $utilisateur = null,
        ?int $atelierId = null,
    ): MouvementStock {
        if (!in_array($type, MouvementStock::TYPES, true)) {
            throw new \InvalidArgumentException('Type de mouvement invalide');
        }

        if ($quantite <= 0) {
            throw new \InvalidArgumentException('La quantité doit être strictement positive');
        }

        $mouvement = new MouvementStock();
        $mouvement->setPiece($piece);
        $mouvement->setType($type);
        $mouvement->setQuantite($quantite);
        $mouvement->setPrixUnitaireHt($prixUnitaireHt);
        $mouvement->setMotif($motif);
        $mouvement->setCommandeFournisseur($commande);
        $mouvement->setRendezVous($rdv);
        $mouvement->setUtilisateur($utilisateur);
        $mouvement->setAtelierId($atelierId ?? $piece->getAtelierId());

        $this->em->persist($mouvement);

        // Mise à jour du stock selon le type
        $currentQty = $piece->getQuantiteStock();
        $newQty = match ($type) {
            MouvementStock::TYPE_ENTREE, MouvementStock::TYPE_RECEPTION => $currentQty + $quantite,
            MouvementStock::TYPE_SORTIE, MouvementStock::TYPE_COMMANDE => $currentQty - $quantite,
            MouvementStock::TYPE_AJUSTEMENT => $quantite, // la quantité représente le nouveau stock
            default => $currentQty,
        };

        $piece->setQuantiteStock(max(0, $newQty));
        $this->em->persist($piece);

        $this->auditService->log(
            'stock_movement',
            'MouvementStock',
            $mouvement->getId(),
            json_encode([
                'piece_id' => $piece->getId(),
                'type' => $type,
                'quantite' => $quantite,
                'stock_before' => $currentQty,
                'stock_after' => $piece->getQuantiteStock(),
                'motif' => $motif,
            ], JSON_UNESCAPED_UNICODE)
        );

        return $mouvement;
    }

    /**
     * Réception d'une commande fournisseur — crée un mouvement par ligne reçue.
     */
    public function receiveCommande(CommandeFournisseur $commande, array $lignesRecues, ?User $user = null): void
    {
        if ($commande->getStatut() === 'recue') {
            throw new \LogicException('Cette commande a déjà été réceptionnée.');
        }

        foreach ($lignesRecues as $ligneData) {
            $ligneId = $ligneData['ligne_id'] ?? null;
            $qtyRecue = (int) ($ligneData['quantite_recue'] ?? 0);

            if (!$ligneId || $qtyRecue <= 0) {
                continue;
            }

            $ligne = $this->em->getRepository(LigneCommandeFournisseur::class)->find($ligneId);
            if (!$ligne || $ligne->getCommande()->getId() !== $commande->getId()) {
                continue;
            }

            $piece = $ligne->getPiece();
            $oldRecue = $ligne->getQuantiteRecue();
            $ligne->setQuantiteRecue($oldRecue + $qtyRecue);
            $this->em->persist($ligne);

            $this->recordMovement(
                $piece,
                MouvementStock::TYPE_RECEPTION,
                $qtyRecue,
                (string) $ligne->getPrixUnitaireHt(),
                sprintf('Réception commande %s', $commande->getNumeroCommande()),
                $commande,
                null,
                $user,
                $commande->getAtelierId(),
            );
        }

        $commande->setStatut('recue');
        $commande->setDateReception(new \DateTime());
        $this->em->persist($commande);
        $this->em->flush();
    }

    /**
     * Consommation de pièces pour un RDV / ordre de réparation.
     */
    public function consumeForRdv(PieceDetachee $piece, int $quantite, RendezVous $rdv, ?User $user = null): void
    {
        if ($piece->getQuantiteStock() < $quantite) {
            throw new \LogicException(sprintf('Stock insuffisant pour la pièce %s (dispo: %d, demandé: %d)', $piece->getNom(), $piece->getQuantiteStock(), $quantite));
        }

        $this->recordMovement(
            $piece,
            MouvementStock::TYPE_SORTIE,
            $quantite,
            (string) $piece->getPrixVenteHt(),
            sprintf('Consommation RDV #%d', $rdv->getId()),
            null,
            $rdv,
            $user,
            $rdv->getAtelierId(),
        );
    }

    /**
     * Ajustement manuel du stock (inventaire, casse, perte, correction).
     */
    public function adjustStock(PieceDetachee $piece, int $newQuantity, string $reason, ?User $user = null): void
    {
        $diff = $newQuantity - $piece->getQuantiteStock();

        $mouvement = new MouvementStock();
        $mouvement->setPiece($piece);
        $mouvement->setType(MouvementStock::TYPE_AJUSTEMENT);
        $mouvement->setQuantite(abs($diff));
        $mouvement->setMotif($reason);
        $mouvement->setUtilisateur($user);
        $mouvement->setAtelierId($piece->getAtelierId());

        $this->em->persist($mouvement);

        $piece->setQuantiteStock($newQuantity);
        $this->em->persist($piece);

        $this->auditService->log(
            'stock_adjust',
            'MouvementStock',
            $mouvement->getId(),
            json_encode([
                'piece_id' => $piece->getId(),
                'new_quantity' => $newQuantity,
                'diff' => $diff,
                'reason' => $reason,
            ], JSON_UNESCAPED_UNICODE)
        );
    }
}
