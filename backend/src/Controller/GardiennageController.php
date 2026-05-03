<?php

namespace App\Controller;

use App\Entity\CommandePiece;
use App\Entity\OrdreReparation;
use App\Entity\PieceDetachee;
use App\Entity\PieceUtilisee;
use App\Entity\RendezVous;
use App\Entity\User;
use App\Service\AuditService;
use App\Service\GardiennageService;
use App\Service\StockMovementService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Workflow\WorkflowInterface;

use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class GardiennageController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private GardiennageService $gardiennageService,
        private AuditService $audit,
        private WorkflowInterface $rendezVousStateMachine,
        private StockMovementService $stockMovementService,
    ) {}

    // ── Gardiennage ──

    #[Route('/api/rdv/{id}/declencher-gardiennage', methods: ['POST'])]
    public function declencherGardiennage(int $id, Request $request): JsonResponse
    {
        $rdv = $this->em->getRepository(RendezVous::class)->find($id);
        if (!$rdv) {
            return $this->json(['error' => 'RDV not found'], Response::HTTP_NOT_FOUND);
        }

        if (!$this->gardiennageService->peutDeclencher($rdv)) {
            return $this->json(['error' => 'Gardiennage non autorisé pour ce RDV'], Response::HTTP_CONFLICT);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $motif = $data['motif'] ?? 'Non-récupération du véhicule';
        $user = $this->getUser();

        $this->gardiennageService->declencher($rdv, $user?->getId() ?? 0, $motif);

        // Apply workflow transition if available
        if ($this->rendezVousStateMachine->can($rdv, 'passer_gardiennage')) {
            $this->rendezVousStateMachine->apply($rdv, 'passer_gardiennage');
            $this->em->flush();
        }

        $this->audit->log('declencher_gardiennage', 'rendez_vous', $rdv->getId(), json_encode(['motif' => $motif]));

        return $this->json([
            'id' => $rdv->getId(),
            'statut' => $rdv->getStatut(),
            'gardiennageDebutAt' => $rdv->getGardiennageDebutAt()->format('c'),
            'gardiennageMotif' => $rdv->getGardiennageMotif(),
        ]);
    }

    #[Route('/api/rdv/{id}/gardiennage-montant', methods: ['GET'])]
    public function gardiennageMontant(int $id): JsonResponse
    {
        $rdv = $this->em->getRepository(RendezVous::class)->find($id);
        if (!$rdv) {
            return $this->json(['error' => 'RDV not found'], Response::HTTP_NOT_FOUND);
        }

        if (!$rdv->getGardiennageDebutAt()) {
            return $this->json(['montant' => '0.00', 'jours' => 0]);
        }

        $montant = $this->gardiennageService->calculerMontant($rdv, new \DateTime());

        return $this->json([
            'montant' => $montant,
            'debut' => $rdv->getGardiennageDebutAt()->format('c'),
            'motif' => $rdv->getGardiennageMotif(),
        ]);
    }

    // ── Commandes pièces ──

    #[Route('/api/rdv/{id}/commandes-pieces', methods: ['GET'])]
    public function listCommandes(int $id): JsonResponse
    {
        $rdv = $this->em->getRepository(RendezVous::class)->find($id);
        if (!$rdv) {
            return $this->json(['error' => 'RDV not found'], Response::HTTP_NOT_FOUND);
        }

        $commandes = $this->em->getRepository(CommandePiece::class)->findBy(
            ['rendezVous' => $rdv],
            ['dateCommande' => 'DESC'],
        );

        return $this->json(array_map(fn($c) => $this->serializeCommande($c), $commandes));
    }

    #[Route('/api/rdv/{id}/commandes-pieces', methods: ['POST'])]
    public function createCommande(int $id, Request $request): JsonResponse
    {
        $rdv = $this->em->getRepository(RendezVous::class)->find($id);
        if (!$rdv) {
            return $this->json(['error' => 'RDV not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true) ?? [];

        if (empty($data['reference']) || empty($data['designation'])) {
            return $this->json(['error' => 'reference et designation requis'], Response::HTTP_BAD_REQUEST);
        }

        $commande = new CommandePiece();
        $commande->setAtelierId($rdv->getAtelierId());
        $commande->setRendezVous($rdv);

        if (!empty($data['ordreReparationId'])) {
            $or = $this->em->getRepository(OrdreReparation::class)->find($data['ordreReparationId']);
            if ($or) $commande->setOrdreReparation($or);
        }

        $commande->setReference($data['reference']);
        $commande->setDesignation($data['designation']);
        if (isset($data['quantite'])) $commande->setQuantite((int) $data['quantite']);
        if (isset($data['fournisseur'])) $commande->setFournisseur($data['fournisseur']);
        if (isset($data['numeroCommandeFournisseur'])) $commande->setNumeroCommandeFournisseur($data['numeroCommandeFournisseur']);
        if (isset($data['prixAchat'])) $commande->setPrixAchat($data['prixAchat']);
        if (isset($data['prixVente'])) $commande->setPrixVente($data['prixVente']);
        if (isset($data['dateLivraisonEstimee'])) $commande->setDateLivraisonEstimee(new \DateTime($data['dateLivraisonEstimee']));
        if (isset($data['notes'])) $commande->setNotes($data['notes']);

        $this->em->persist($commande);

        // Auto-transition to en_attente_pieces if possible
        if ($this->rendezVousStateMachine->can($rdv, 'attendre_pieces')) {
            $this->rendezVousStateMachine->apply($rdv, 'attendre_pieces');
        }

        $this->em->flush();

        $this->audit->log('create_commande_piece', 'commande_piece', $commande->getId());

        return $this->json($this->serializeCommande($commande), Response::HTTP_CREATED);
    }

    #[Route('/api/commandes-pieces/{id}', methods: ['PUT'])]
    public function updateCommande(int $id, Request $request): JsonResponse
    {
        $commande = $this->em->getRepository(CommandePiece::class)->find($id);
        if (!$commande) {
            return $this->json(['error' => 'Commande not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true) ?? [];

        if (isset($data['reference'])) $commande->setReference($data['reference']);
        if (isset($data['designation'])) $commande->setDesignation($data['designation']);
        if (isset($data['quantite'])) $commande->setQuantite((int) $data['quantite']);
        if (isset($data['fournisseur'])) $commande->setFournisseur($data['fournisseur']);
        if (isset($data['numeroCommandeFournisseur'])) $commande->setNumeroCommandeFournisseur($data['numeroCommandeFournisseur']);
        if (isset($data['prixAchat'])) $commande->setPrixAchat($data['prixAchat']);
        if (isset($data['prixVente'])) $commande->setPrixVente($data['prixVente']);
        if (isset($data['dateLivraisonEstimee'])) $commande->setDateLivraisonEstimee(new \DateTime($data['dateLivraisonEstimee']));
        if (isset($data['dateLivraisonReelle'])) $commande->setDateLivraisonReelle(new \DateTime($data['dateLivraisonReelle']));
        if (isset($data['notes'])) $commande->setNotes($data['notes']);
        if (isset($data['statut']) && in_array($data['statut'], CommandePiece::STATUTS, true)) {
            $commande->setStatut($data['statut']);
        }

        $this->em->flush();

        return $this->json($this->serializeCommande($commande));
    }

    #[Route('/api/commandes-pieces/{id}/recue', methods: ['POST'])]
    public function marquerRecue(int $id): JsonResponse
    {
        $commande = $this->em->getRepository(CommandePiece::class)->find($id);
        if (!$commande) {
            return $this->json(['error' => 'Commande not found'], Response::HTTP_NOT_FOUND);
        }

        $commande->setStatut('recue');
        $commande->setDateLivraisonReelle(new \DateTime());

        // Auto-réception stock si une PieceDetachee correspondante existe
        $piece = $this->em->getRepository(PieceDetachee::class)->createQueryBuilder('p')
            ->where('p.reference = :ref OR p.referenceFournisseur = :ref')
            ->andWhere('p.isActive = 1')
            ->setParameter('ref', $commande->getReference())
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($piece) {
            $user = $this->getUser() instanceof User ? $this->getUser() : null;
            $this->stockMovementService->recordMovement(
                $piece,
                \App\Entity\MouvementStock::TYPE_RECEPTION,
                $commande->getQuantite(),
                $commande->getPrixAchat(),
                sprintf('Réception commande pièce OR #%d — %s', $commande->getId(), $commande->getDesignation()),
                null,
                $commande->getRendezVous(),
                $user,
                $commande->getAtelierId(),
            );
        }

        // Check if all commandes for this RDV are received
        $rdv = $commande->getRendezVous();
        $pending = $this->em->getRepository(CommandePiece::class)->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.rendezVous = :rdv')
            ->andWhere('c.statut NOT IN (:done)')
            ->setParameter('rdv', $rdv)
            ->setParameter('done', ['recue', 'installee', 'annulee', 'retour_fournisseur'])
            ->getQuery()
            ->getSingleScalarResult();

        // If this was the last pending piece and current count will be 0 after this flush
        // (we check count-1 because current piece hasn't been flushed yet)
        $this->em->flush();

        $allReceived = ((int)$pending === 0);

        $this->audit->log('commande_piece_recue', 'commande_piece', $commande->getId());

        return $this->json([
            ...$this->serializeCommande($commande),
            'allReceived' => $allReceived,
            'canReprendre' => $allReceived && $this->rendezVousStateMachine->can($rdv, 'reprendre_apres_pieces'),
        ]);
    }

    #[Route('/api/commandes-pieces/{id}/installer', methods: ['POST'])]
    public function installerCommande(int $id): JsonResponse
    {
        $commande = $this->em->getRepository(CommandePiece::class)->find($id);
        if (!$commande) {
            return $this->json(['error' => 'Commande not found'], Response::HTTP_NOT_FOUND);
        }

        if ($commande->getStatut() !== 'recue') {
            return $this->json(['error' => 'La pièce doit être reçue avant installation'], Response::HTTP_CONFLICT);
        }

        $rdv = $commande->getRendezVous();
        $user = $this->getUser() instanceof User ? $this->getUser() : null;

        // Chercher une pièce en stock correspondant à la référence
        $piece = $this->em->getRepository(PieceDetachee::class)->createQueryBuilder('p')
            ->where('p.reference = :ref OR p.referenceFournisseur = :ref')
            ->andWhere('p.isActive = 1')
            ->setParameter('ref', $commande->getReference())
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($piece) {
            try {
                $this->stockMovementService->consumeForRdv($piece, $commande->getQuantite(), $rdv, $user);
            } catch (\LogicException $e) {
                return $this->json(['error' => $e->getMessage()], Response::HTTP_CONFLICT);
            }

            // Créer le lien PieceUtilisee
            $utilisee = new PieceUtilisee();
            $utilisee->setRendezVous($rdv);
            $utilisee->setPiece($piece);
            $utilisee->setQuantite($commande->getQuantite());
            $utilisee->setPrixVenteUnitaire($commande->getPrixVente());
            $this->em->persist($utilisee);
        }

        $commande->setStatut('installee');
        $this->em->persist($commande);
        $this->em->flush();

        $this->audit->log('commande_piece_installee', 'commande_piece', $commande->getId(), json_encode([
            'reference' => $commande->getReference(),
            'quantite' => $commande->getQuantite(),
            'piece_id' => $piece?->getId(),
        ]));

        return $this->json($this->serializeCommande($commande));
    }

    private function serializeCommande(CommandePiece $c): array
    {
        return [
            'id' => $c->getId(),
            'rdv_id' => $c->getRendezVous()->getId(),
            'or_id' => $c->getOrdreReparation()?->getId(),
            'reference' => $c->getReference(),
            'designation' => $c->getDesignation(),
            'quantite' => $c->getQuantite(),
            'fournisseur' => $c->getFournisseur(),
            'numeroCommandeFournisseur' => $c->getNumeroCommandeFournisseur(),
            'prixAchat' => $c->getPrixAchat(),
            'prixVente' => $c->getPrixVente(),
            'dateCommande' => $c->getDateCommande()->format('c'),
            'dateLivraisonEstimee' => $c->getDateLivraisonEstimee()?->format('Y-m-d'),
            'dateLivraisonReelle' => $c->getDateLivraisonReelle()?->format('Y-m-d'),
            'statut' => $c->getStatut(),
            'notes' => $c->getNotes(),
        ];
    }
}
