<?php

namespace App\Controller;

use App\Entity\CommandeFournisseur;
use App\Entity\Fournisseur;
use App\Entity\LigneCommandeFournisseur;
use App\Entity\MouvementStock;
use App\Entity\PieceDetachee;
use App\Entity\RendezVous;
use App\Entity\User;
use App\Service\AuditService;
use App\Service\CurrentAtelierResolver;
use App\Service\StockMovementService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api/stock')]
#[IsGranted('ROLE_USER')]
class StockController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private SerializerInterface $serializer,
        private AuditService $audit,
        private CurrentAtelierResolver $atelierResolver,
        private StockMovementService $stockMovementService,
    ) {}

    // ─── Alerts ─────────────────────────────────────────────────────────────

    #[Route('/alertes', methods: ['GET'], priority: 10)]
    public function alertes(): JsonResponse
    {
        $atelierId = $this->resolveAtelierId();

        $qb = $this->em->getRepository(PieceDetachee::class)->createQueryBuilder('p')
            ->where('p.quantiteStock <= p.quantiteMinimale')
            ->andWhere('p.isActive = 1')
            ->orderBy('p.quantiteStock', 'ASC');

        if ($atelierId !== null) {
            $qb->andWhere('p.atelierId = :atelierId')->setParameter('atelierId', $atelierId);
        }

        $pieces = $qb->getQuery()->getResult();
        $data = json_decode($this->serializer->serialize($pieces, 'json', ['groups' => ['piece:read']]), true);
        return $this->json($data);
    }

    // ─── Movements ──────────────────────────────────────────────────────────

    #[Route('/mouvements', methods: ['GET'])]
    public function listMouvements(Request $request): JsonResponse
    {
        $atelierId = $this->resolveAtelierId();
        $pieceId = $request->query->getInt('piece_id');
        $page = max(1, $request->query->getInt('page', 1));
        $limit = min(100, max(1, $request->query->getInt('limit', 50)));

        $qb = $this->em->getRepository(MouvementStock::class)->createQueryBuilder('m')
            ->leftJoin('m.piece', 'piece')
            ->addSelect('piece')
            ->orderBy('m.createdAt', 'DESC');

        if ($atelierId !== null) {
            $qb->andWhere('m.atelierId = :atelierId')->setParameter('atelierId', $atelierId);
        }
        if ($pieceId) {
            $qb->andWhere('m.piece = :pieceId')->setParameter('pieceId', $pieceId);
        }

        $total = (int) (clone $qb)->select('COUNT(m.id)')->getQuery()->getSingleScalarResult();
        $items = $qb
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        $data = json_decode($this->serializer->serialize($items, 'json', ['groups' => ['mouvement:read']]), true);
        return $this->json([
            'member' => $data,
            'totalItems' => $total,
            'page' => $page,
            'itemsPerPage' => $limit,
        ]);
    }

    #[Route('/mouvements', methods: ['POST'])]
    public function createMouvement(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $data = json_decode($request->getContent(), true) ?? [];
        $pieceId = $data['piece_id'] ?? null;
        $type = $data['type'] ?? null;
        $quantite = (int) ($data['quantite'] ?? 0);
        $motif = $data['motif'] ?? null;

        if (!$pieceId || !$type || $quantite <= 0) {
            return $this->json(['error' => 'piece_id, type et quantite requis'], Response::HTTP_BAD_REQUEST);
        }

        $piece = $this->em->getRepository(PieceDetachee::class)->find($pieceId);
        if (!$piece) {
            return $this->json(['error' => 'Pièce introuvable'], Response::HTTP_NOT_FOUND);
        }

        $user = $this->getUser() instanceof User ? $this->getUser() : null;

        try {
            if ($type === MouvementStock::TYPE_AJUSTEMENT) {
                $this->stockMovementService->adjustStock($piece, $quantite, $motif ?? 'Ajustement manuel', $user);
            } else {
                $this->stockMovementService->recordMovement(
                    $piece, $type, $quantite,
                    $data['prix_unitaire_ht'] ?? null,
                    $motif, null, null, $user,
                    $this->resolveAtelierId(),
                );
            }
            $this->em->flush();
        } catch (\LogicException | \InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_CONFLICT);
        }

        return $this->json(['success' => true, 'stock' => $piece->getQuantiteStock()]);
    }

    // ─── Supplier orders (Commandes Fournisseur) ────────────────────────────

    #[Route('/commandes', methods: ['GET'])]
    public function listCommandes(Request $request): JsonResponse
    {
        $atelierId = $this->resolveAtelierId();
        $page = max(1, $request->query->getInt('page', 1));
        $limit = min(100, max(1, $request->query->getInt('limit', 50)));

        $qb = $this->em->getRepository(CommandeFournisseur::class)->createQueryBuilder('c')
            ->leftJoin('c.fournisseur', 'f')->addSelect('f')
            ->leftJoin('c.lignes', 'l')->addSelect('l')
            ->leftJoin('l.piece', 'p')->addSelect('p')
            ->orderBy('c.dateCommande', 'DESC');

        if ($atelierId !== null) {
            $qb->andWhere('c.atelierId = :atelierId')->setParameter('atelierId', $atelierId);
        }
        if ($statut = $request->query->get('statut')) {
            $qb->andWhere('c.statut = :statut')->setParameter('statut', $statut);
        }

        $total = (int) (clone $qb)->select('COUNT(c.id)')->getQuery()->getSingleScalarResult();
        $items = $qb
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        $data = json_decode($this->serializer->serialize($items, 'json', ['groups' => ['commande:read', 'piece:read', 'fournisseur:read']]), true);
        return $this->json([
            'member' => $data,
            'totalItems' => $total,
            'page' => $page,
            'itemsPerPage' => $limit,
        ]);
    }

    #[Route('/commandes', methods: ['POST'])]
    public function createCommande(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $data = json_decode($request->getContent(), true) ?? [];
        $fournisseurId = $data['fournisseur_id'] ?? null;
        $lignes = $data['lignes'] ?? [];

        if (!$fournisseurId || empty($lignes)) {
            return $this->json(['error' => 'fournisseur_id et lignes requis'], Response::HTTP_BAD_REQUEST);
        }

        $fournisseur = $this->em->getRepository(Fournisseur::class)->find($fournisseurId);
        if (!$fournisseur) {
            return $this->json(['error' => 'Fournisseur introuvable'], Response::HTTP_NOT_FOUND);
        }

        $commande = new CommandeFournisseur();
        $commande->setFournisseur($fournisseur);
        $commande->setAtelierId($this->resolveAtelierId());
        $commande->setNumeroCommande($this->generateNumeroCommande());
        $commande->setStatut('en_attente');
        $commande->setDateCommande(new \DateTime());
        $commande->setDatePrevueLivraison(
            !empty($data['date_prevue_livraison']) ? new \DateTime($data['date_prevue_livraison']) : null
        );
        $commande->setNotes($data['notes'] ?? null);

        $totalHt = '0.00';
        foreach ($lignes as $ligneData) {
            $pieceId = $ligneData['piece_id'] ?? null;
            $qty = (int) ($ligneData['quantite'] ?? 0);
            $prix = (string) ($ligneData['prix_unitaire_ht'] ?? '0.00');

            if (!$pieceId || $qty <= 0) {
                continue;
            }

            $piece = $this->em->getRepository(PieceDetachee::class)->find($pieceId);
            if (!$piece) {
                continue;
            }

            $ligne = new LigneCommandeFournisseur();
            $ligne->setPiece($piece);
            $ligne->setCommande($commande);
            $ligne->setQuantiteDemandee($qty);
            $ligne->setPrixUnitaireHt($prix);
            $ligne->setAtelierId($commande->getAtelierId());
            $this->em->persist($ligne);

            $totalHt = bcadd($totalHt, bcmul($prix, (string) $qty, 2), 2);
        }

        if (count($commande->getLignes()) === 0) {
            return $this->json(['error' => 'Aucune ligne valide'], Response::HTTP_BAD_REQUEST);
        }

        $commande->setTotalHt($totalHt);
        $tva = bcmul($totalHt, '0.20', 2);
        $commande->setTotalTtc(bcadd($totalHt, $tva, 2));

        $this->em->persist($commande);
        $this->em->flush();

        $this->audit->log('create_commande_fournisseur', 'CommandeFournisseur', $commande->getId(), json_encode([
            'fournisseur' => $fournisseur->getNom(),
            'total_ht' => $totalHt,
        ]));

        return $this->json([
            'id' => $commande->getId(),
            'numero_commande' => $commande->getNumeroCommande(),
            'statut' => $commande->getStatut(),
        ], Response::HTTP_CREATED);
    }

    #[Route('/commandes/{id}/recevoir', methods: ['POST'])]
    public function receiveCommande(int $id, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $commande = $this->em->getRepository(CommandeFournisseur::class)->find($id);
        if (!$commande) {
            return $this->json(['error' => 'Commande introuvable'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $lignesRecues = $data['lignes'] ?? [];

        $user = $this->getUser() instanceof User ? $this->getUser() : null;

        try {
            $this->stockMovementService->receiveCommande($commande, $lignesRecues, $user);
        } catch (\LogicException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_CONFLICT);
        }

        return $this->json(['success' => true, 'statut' => $commande->getStatut()]);
    }

    // ─── Suppliers ──────────────────────────────────────────────────────────

    #[Route('/fournisseurs', methods: ['GET'])]
    public function listFournisseurs(): JsonResponse
    {
        $atelierId = $this->resolveAtelierId();

        $qb = $this->em->getRepository(Fournisseur::class)->createQueryBuilder('f')
            ->where('f.isActive = 1')
            ->orderBy('f.nom', 'ASC');

        if ($atelierId !== null) {
            $qb->andWhere('f.atelierId = :atelierId OR f.atelierId IS NULL')
                ->setParameter('atelierId', $atelierId);
        }

        $fournisseurs = $qb->getQuery()->getResult();
        $data = json_decode($this->serializer->serialize($fournisseurs, 'json'), true);
        return $this->json($data);
    }

    #[Route('/fournisseurs', methods: ['POST'])]
    public function createFournisseur(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $data = json_decode($request->getContent(), true) ?? [];
        $nom = trim((string) ($data['nom'] ?? ''));
        if ($nom === '') {
            return $this->json(['error' => 'Le nom est obligatoire'], Response::HTTP_BAD_REQUEST);
        }

        $f = new Fournisseur();
        $f->setNom($nom);
        $f->setContact($data['contact'] ?? null);
        $f->setTelephone($data['telephone'] ?? null);
        $f->setEmail($data['email'] ?? null);
        $f->setAdresse($data['adresse'] ?? null);
        $f->setSiret($data['siret'] ?? null);
        $f->setDelaiLivraisonJours((int) ($data['delai_livraison_jours'] ?? 3));
        $f->setNotes($data['notes'] ?? null);
        $f->setAtelierId($this->resolveAtelierId());
        $f->setIsActive(1);

        $this->em->persist($f);
        $this->em->flush();

        return $this->json(['id' => $f->getId(), 'nom' => $f->getNom()], Response::HTTP_CREATED);
    }

    // ─── Helpers ────────────────────────────────────────────────────────────

    private function resolveAtelierId(): ?int
    {
        return $this->atelierResolver->resolveAtelierId();
    }

    private function generateNumeroCommande(): string
    {
        return 'CF-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));
    }
}
