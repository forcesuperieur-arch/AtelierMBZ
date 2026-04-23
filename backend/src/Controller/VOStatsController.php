<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\VODepotVente;
use App\Entity\VOPurchase;
use App\Infrastructure\EntityNormalizer;
use App\Service\CurrentAtelierResolver;
use App\Service\VODocumentService;
use App\Service\VORemiseEnEtatService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/vo')]
class VOStatsController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private VODocumentService $documentService,
        private VORemiseEnEtatService $remiseEnEtatService,
        private CurrentAtelierResolver $currentAtelierResolver,
        private EntityNormalizer $entityNormalizer,
    ) {}

    #[Route('/stock', methods: ['GET'])]
    public function stock(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_VO_MANAGER');

        $search = trim((string) $request->query->get('q', ''));
        $limit = max(0, min(100, $request->query->getInt('limit', 0)));

        return $this->json($this->buildStockPayload($this->resolveAtelierId(), $search, $limit));
    }

    #[Route('/stats', methods: ['GET'])]
    public function stats(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_VO_MANAGER');
        $atelierId = $this->resolveAtelierId();

        $repo = $this->em->getRepository(VOPurchase::class);

        $buildQb = function (string $alias) use ($repo, $atelierId) {
            $qb = $repo->createQueryBuilder($alias);
            if ($atelierId) {
                $qb->andWhere("{$alias}.atelierId = :aid")->setParameter('aid', $atelierId);
            }
            return $qb;
        };

        $enStock = (int) $buildQb('p')->select('COUNT(p)')
            ->andWhere('p.status IN (:s)')->setParameter('s', ['en_stock', 'en_vente', 'reserve'])
            ->getQuery()->getSingleScalarResult();

        $vendus = (int) $buildQb('p')->select('COUNT(p)')
            ->andWhere('p.status = :s')->setParameter('s', 'vendu')
            ->andWhere('p.saleDate >= :startOfMonth')->setParameter('startOfMonth', new \DateTimeImmutable('first day of this month'))
            ->getQuery()->getSingleScalarResult();

        $depotsQb = $this->em->getRepository(VODepotVente::class)->createQueryBuilder('d')
            ->select('COUNT(d)')
            ->where('d.status = :s')->setParameter('s', 'actif')
            ->andWhere('d.atelierId = :depotAid')->setParameter('depotAid', $atelierId);

        $depotsActifs = (int) $depotsQb->getQuery()->getSingleScalarResult();

        $alerts = $this->documentService->getAlerts($atelierId);
        $stock = $this->buildStockPayload($atelierId, '', 5);
        $mandatsExpirant = count(array_filter(
            $stock['items'],
            static fn (array $item): bool => ($item['source'] ?? null) === 'depot'
                && (int) ($item['jours_restants'] ?? 999) <= 7
                && !(bool) ($item['mandat_expire'] ?? false),
        ));

        return $this->json([
            'en_stock' => $enStock,
            'vendus' => $vendus,
            'depots_actifs' => $depotsActifs,
            'alerts_count' => count($alerts),
            'stock_total' => $stock['total'],
            'stock_items' => $stock['items'],
            'mandats_expirant_7j' => $mandatsExpirant,
        ]);
    }

    private function buildStockPayload(int $atelierId, string $search = '', int $limit = 0): array
    {
        $qbPurchases = $this->em->getRepository(VOPurchase::class)->createQueryBuilder('p')
            ->leftJoin('p.vehicule', 'v')->addSelect('v')
            ->leftJoin('p.seller', 's')->addSelect('s')
            ->where('p.status IN (:statuses)')
            ->andWhere('p.atelierId = :atelierId')
            ->setParameter('statuses', ['en_stock', 'en_vente', 'reserve'])
            ->setParameter('atelierId', $atelierId)
            ->orderBy('p.createdAt', 'ASC');

        if ($search !== '') {
            $qbPurchases
                ->andWhere('v.plaque LIKE :purchaseQuery OR v.marque LIKE :purchaseQuery OR v.modele LIKE :purchaseQuery')
                ->setParameter('purchaseQuery', '%' . $search . '%');
        }

        if ($limit > 0) {
            $qbPurchases->setMaxResults($limit);
        }

        $purchases = $qbPurchases->getQuery()->getResult();

        $qbDepots = $this->em->getRepository(VODepotVente::class)->createQueryBuilder('d')
            ->leftJoin('d.vehicule', 'v')->addSelect('v')
            ->leftJoin('d.deposant', 'c')->addSelect('c')
            ->where('d.status = :status')
            ->andWhere('d.atelierId = :atelierId')
            ->setParameter('status', 'actif')
            ->setParameter('atelierId', $atelierId)
            ->orderBy('d.dateDebut', 'ASC');

        if ($search !== '') {
            $qbDepots
                ->andWhere('v.plaque LIKE :depotQuery OR v.marque LIKE :depotQuery OR v.modele LIKE :depotQuery')
                ->setParameter('depotQuery', '%' . $search . '%');
        }

        if ($limit > 0) {
            $qbDepots->setMaxResults($limit);
        }

        $depots = $qbDepots->getQuery()->getResult();

        $purchaseIds = array_map(static fn ($p) => $p->getId(), $purchases);
        $depotIds = array_map(static fn ($d) => $d->getId(), $depots);
        $batchDocs = $this->documentService->batchDocumentTypes($purchaseIds, $depotIds);

        $items = [];

        foreach ($purchases as $purchase) {
            $vehicule = $purchase->getVehicule();
            $presentTypes = $batchDocs['purchases'][$purchase->getId()] ?? [];
            $missingDocuments = $this->documentService->getMissingDocumentsFromTypes($presentTypes, true);
            $activeCampaign = $this->remiseEnEtatService->getActiveCampaignForRecord($purchase);
            $joursStock = $purchase->getPurchaseDate()
                ? (new \DateTime('today'))->diff($purchase->getPurchaseDate())->days
                : null;

            $items[] = [
                'id' => $purchase->getId(),
                'source' => 'purchase',
                'status' => $purchase->getStatus(),
                'plaque' => $vehicule->getPlaque(),
                'marque' => $vehicule->getMarque(),
                'modele' => $vehicule->getModele(),
                'annee' => $vehicule->getAnnee(),
                'km' => $vehicule->getMileage(),
                'couleur' => $vehicule->getCouleur(),
                'prix_achat' => $purchase->getPurchasePrice(),
                'prix_vente' => $purchase->getTargetSalePrice(),
                'marge' => $purchase->getMargin(),
                'total_fre' => $purchase->getTotalFre(),
                'regime_tva' => $purchase->getRegimeTva(),
                'jours_stock' => $joursStock,
                'missing_docs' => $missingDocuments,
                'refurbishment_status' => $activeCampaign?->getStatus(),
                'refurbishment_blocking_sale' => $activeCampaign?->isBlockingSale() ?? false,
                'can_sell' => (empty($missingDocuments) || in_array($purchase->getStatus(), ['en_vente', 'reserve'], true))
                    && !($activeCampaign?->isBlockingSale() ?? false),
                'created_at' => $purchase->getCreatedAt()->format('Y-m-d'),
            ];
        }

        foreach ($depots as $depot) {
            $vehicule = $depot->getVehicule();
            $presentTypes = $batchDocs['depots'][$depot->getId()] ?? [];
            $missingDocuments = $this->documentService->getMissingDepotDocumentsFromTypes($presentTypes);
            $activeCampaign = $this->remiseEnEtatService->getActiveCampaignForRecord($depot);

            $items[] = [
                'id' => $depot->getId(),
                'source' => 'depot',
                'status' => $depot->getStatus(),
                'plaque' => $vehicule->getPlaque(),
                'marque' => $vehicule->getMarque(),
                'modele' => $vehicule->getModele(),
                'annee' => $vehicule->getAnnee(),
                'km' => $vehicule->getMileage(),
                'couleur' => $vehicule->getCouleur(),
                'prix_vente' => $depot->getPrixVenteSouhaite(),
                'commission_ht' => $depot->getCommissionAmount(),
                'commission_ttc' => $depot->getCommissionTtc(),
                'deposant_net' => $depot->getDeposantNet(),
                'jours_restants' => $depot->getJoursRestantsMandat(),
                'mandat_expire' => $depot->isMandatExpire(),
                'missing_docs' => $missingDocuments,
                'refurbishment_status' => $activeCampaign?->getStatus(),
                'refurbishment_blocking_sale' => $activeCampaign?->isBlockingSale() ?? false,
                'can_sell' => $depot->getStatus() === 'actif' && !($activeCampaign?->isBlockingSale() ?? false),
                'created_at' => $depot->getDateDebut()->format('Y-m-d'),
            ];
        }

        usort($items, static fn (array $left, array $right): int => strcmp((string) $left['created_at'], (string) $right['created_at']));

        if ($limit > 0) {
            $items = array_slice($items, 0, $limit);
        }

        return [
            'items' => $items,
            'total_purchases' => count($purchases),
            'total_depots' => count($depots),
            'total' => count($items),
        ];
    }

    private function getAuthenticatedUser(): ?User
    {
        $user = $this->getUser();

        return $user instanceof User ? $user : null;
    }

    private function resolveAtelierId(?int $atelierId = null): int
    {
        return $atelierId ?? $this->getAuthenticatedUser()?->getAtelierId() ?? 0;
    }
}
