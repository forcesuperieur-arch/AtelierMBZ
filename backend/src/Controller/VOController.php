<?php

namespace App\Controller;

use App\Entity\Client;
use App\Entity\User;
use App\Entity\Vehicule;
use App\Entity\VODepotVente;
use App\Entity\VODocument;
use App\Entity\VOFacture;
use App\Entity\VOLivrePolice;
use App\Entity\VOPurchase;
use App\Service\PdfService;
use App\Service\VODocumentService;
use App\Service\VOLivrePoliceService;
use App\Service\VOMarginService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api/vo')]
class VOController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private PdfService $pdfService,
        private VOMarginService $marginService,
        private VOLivrePoliceService $livrePoliceService,
        private VODocumentService $documentService,
        private SerializerInterface $serializer,
    ) {}

    // ═══════════════════════════════════════════
    // PURCHASES (RACHATS)
    // ═══════════════════════════════════════════

    #[Route('/purchases', methods: ['GET'])]
    public function listPurchases(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_VO_MANAGER');

        $qb = $this->em->getRepository(VOPurchase::class)->createQueryBuilder('p')
            ->leftJoin('p.vehicule', 'v')->addSelect('v')
            ->leftJoin('p.seller', 's')->addSelect('s')
            ->orderBy('p.createdAt', 'DESC');

        $data = $this->serializer->normalize($qb->getQuery()->getResult(), null, ['groups' => 'vo:read']);

        return $this->json($data);
    }

    #[Route('/purchases/{id}', methods: ['GET'])]
    public function getPurchase(int $id): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_VO_MANAGER');
        $purchase = $this->em->getRepository(VOPurchase::class)->find($id);
        if (!$purchase) {
            return $this->json(['error' => 'Purchase not found'], 404);
        }

        $data = $this->serializer->normalize($purchase, null, ['groups' => 'vo:read']);
        $data['margin'] = $purchase->getMargin();
        $data['totalFre'] = $purchase->getTotalFre();
        $data['missingDocuments'] = $this->documentService->getMissingDocuments($purchase);

        return $this->json($data);
    }

    #[Route('/purchases', methods: ['POST'])]
    public function createPurchase(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_VO_MANAGER');
        $body = $request->toArray();

        $vehicule = $this->em->getRepository(Vehicule::class)->find($body['vehiculeId'] ?? 0);
        $seller = $this->em->getRepository(Client::class)->find($body['sellerId'] ?? 0);

        if (!$vehicule || !$seller) {
            return $this->json(['error' => 'Vehicule and seller are required'], 400);
        }

        $purchase = new VOPurchase();
        $purchase->setVehicule($vehicule);
        $purchase->setSeller($seller);
        $purchase->setPurchasePrice((string) ($body['purchasePrice'] ?? '0'));
        $purchase->setTargetSalePrice((string) ($body['targetSalePrice'] ?? '0'));
        $purchase->setRepairEstimates($body['repairEstimates'] ?? null);
        $purchase->setStatus($body['status'] ?? 'brouillon');
        $purchase->setRegimeTva($body['regimeTva'] ?? 'marge');
        $purchase->setNotes($body['notes'] ?? null);

        if (!empty($body['purchaseDate'])) {
            $purchase->setPurchaseDate(new \DateTime($body['purchaseDate']));
        }
        if (!empty($body['sellerIdType'])) {
            $purchase->setSellerIdType($body['sellerIdType']);
            $purchase->setSellerIdNumber($body['sellerIdNumber'] ?? null);
            if (!empty($body['sellerIdDate'])) {
                $purchase->setSellerIdDate(new \DateTime($body['sellerIdDate']));
            }
        }
        if (!empty($body['nonGageDate'])) {
            $purchase->setNonGageDate(new \DateTime($body['nonGageDate']));
        }
        $purchase->setControleTechniqueOk($body['controleTechniqueOk'] ?? false);

        if (!empty($body['expertId'])) {
            $expert = $this->em->getRepository(User::class)->find($body['expertId']);
            $purchase->setExpert($expert);
        }

        $this->em->persist($purchase);
        $this->em->flush();

        return $this->json(
            $this->serializer->normalize($purchase, null, ['groups' => 'vo:read']),
            201,
        );
    }

    #[Route('/purchases/{id}', methods: ['PATCH'])]
    public function updatePurchase(int $id, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_VO_MANAGER');
        $purchase = $this->em->getRepository(VOPurchase::class)->find($id);
        if (!$purchase) {
            return $this->json(['error' => 'Not found'], 404);
        }

        $body = $request->toArray();

        if (isset($body['purchasePrice'])) $purchase->setPurchasePrice((string) $body['purchasePrice']);
        if (isset($body['targetSalePrice'])) $purchase->setTargetSalePrice((string) $body['targetSalePrice']);
        if (isset($body['repairEstimates'])) $purchase->setRepairEstimates($body['repairEstimates']);
        if (isset($body['status'])) $purchase->setStatus($body['status']);
        if (isset($body['regimeTva'])) $purchase->setRegimeTva($body['regimeTva']);
        if (isset($body['notes'])) $purchase->setNotes($body['notes']);
        if (isset($body['controleTechniqueOk'])) $purchase->setControleTechniqueOk($body['controleTechniqueOk']);
        if (!empty($body['purchaseDate'])) $purchase->setPurchaseDate(new \DateTime($body['purchaseDate']));
        if (!empty($body['saleDate'])) $purchase->setSaleDate(new \DateTime($body['saleDate']));
        if (!empty($body['nonGageDate'])) $purchase->setNonGageDate(new \DateTime($body['nonGageDate']));
        if (isset($body['sellerIdType'])) $purchase->setSellerIdType($body['sellerIdType']);
        if (isset($body['sellerIdNumber'])) $purchase->setSellerIdNumber($body['sellerIdNumber']);
        if (!empty($body['sellerIdDate'])) $purchase->setSellerIdDate(new \DateTime($body['sellerIdDate']));

        $this->em->flush();

        return $this->json($this->serializer->normalize($purchase, null, ['groups' => 'vo:read']));
    }

    /**
     * Confirm purchase → register in Livre de Police + set status to en_stock.
     */
    #[Route('/purchases/{id}/confirm', methods: ['POST'])]
    public function confirmPurchase(int $id): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_VO_MANAGER');
        $purchase = $this->em->getRepository(VOPurchase::class)->find($id);
        if (!$purchase) {
            return $this->json(['error' => 'Not found'], 404);
        }

        if ($purchase->getStatus() !== 'brouillon') {
            return $this->json(['error' => 'Seuls les rachats en brouillon peuvent être confirmés'], 400);
        }

        try {
            $entry = $this->livrePoliceService->createEntryForPurchase($purchase);
            $purchase->setStatus('en_stock');
            $this->em->flush();

            try {
                $pdfPath = $this->pdfService->generatePvRachatPdf($purchase);
                $this->documentService->archiveGeneratedPdf(
                    $pdfPath,
                    VODocument::TYPE_PV_RACHAT,
                    $purchase,
                    null,
                    $this->getUser(),
                    sprintf('pv-rachat-%d.pdf', $purchase->getId()),
                );
                $this->em->flush();
            } catch (\Throwable) {
            }

            return $this->json([
                'purchase' => $this->serializer->normalize($purchase, null, ['groups' => 'vo:read']),
                'livrePoliceId' => $entry->getId(),
                'livrePoliceNumero' => $entry->getNumeroOrdre(),
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], 422);
        }
    }

    // ═══════════════════════════════════════════
    // DÉPÔT-VENTE
    // ═══════════════════════════════════════════

    #[Route('/depots', methods: ['GET'])]
    public function listDepots(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_VO_MANAGER');

        $qb = $this->em->getRepository(VODepotVente::class)->createQueryBuilder('d')
            ->leftJoin('d.vehicule', 'v')->addSelect('v')
            ->leftJoin('d.deposant', 'c')->addSelect('c')
            ->orderBy('d.createdAt', 'DESC');

        $data = $this->serializer->normalize($qb->getQuery()->getResult(), null, ['groups' => 'vo:read']);

        return $this->json($data);
    }

    #[Route('/depots/{id}', methods: ['GET'])]
    public function getDepot(int $id): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_VO_MANAGER');
        $depot = $this->em->getRepository(VODepotVente::class)->find($id);
        if (!$depot) {
            return $this->json(['error' => 'Not found'], 404);
        }

        $data = $this->serializer->normalize($depot, null, ['groups' => 'vo:read']);
        $data['commissionAmount'] = $depot->getCommissionAmount();
        $data['deposantNet'] = $depot->getDeposantNet();
        $data['mandatExpire'] = $depot->isMandatExpire();
        $data['missingDocuments'] = $this->documentService->getMissingDocumentsDepot($depot);

        return $this->json($data);
    }

    #[Route('/depots', methods: ['POST'])]
    public function createDepot(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_VO_MANAGER');
        $body = $request->toArray();

        $vehicule = $this->em->getRepository(Vehicule::class)->find($body['vehiculeId'] ?? 0);
        $deposant = $this->em->getRepository(Client::class)->find($body['deposantId'] ?? 0);

        if (!$vehicule || !$deposant) {
            return $this->json(['error' => 'Vehicule and deposant are required'], 400);
        }

        $depot = new VODepotVente();
        $depot->setVehicule($vehicule);
        $depot->setDeposant($deposant);
        $depot->setPrixVenteSouhaite((string) ($body['prixVenteSouhaite'] ?? '0'));
        $depot->setCommissionType($body['commissionType'] ?? 'pourcentage');
        $depot->setCommissionValeur((string) ($body['commissionValeur'] ?? '0'));
        $depot->setDureeMandat($body['dureeMandat'] ?? 90);
        $depot->setConditionsRestitution($body['conditionsRestitution'] ?? null);
        $depot->setAssuranceInfo($body['assuranceInfo'] ?? null);
        $depot->setNotes($body['notes'] ?? null);

        if (!empty($body['dateDebut'])) {
            $depot->setDateDebut(new \DateTime($body['dateDebut']));
        }
        if (!empty($body['deposantIdType'])) {
            $depot->setDeposantIdType($body['deposantIdType']);
            $depot->setDeposantIdNumber($body['deposantIdNumber'] ?? null);
            if (!empty($body['deposantIdDate'])) {
                $depot->setDeposantIdDate(new \DateTime($body['deposantIdDate']));
            }
        }

        if (!empty($body['gestionnaireId'])) {
            $gestionnaire = $this->em->getRepository(User::class)->find($body['gestionnaireId']);
            $depot->setGestionnaire($gestionnaire);
        }

        $this->em->persist($depot);

        try {
            $this->livrePoliceService->createEntryForDepotVente($depot);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], 422);
        }

        $this->em->flush();

        try {
            $pdfPath = $this->pdfService->generateContratDepotVentePdf($depot);
            $this->documentService->archiveGeneratedPdf(
                $pdfPath,
                VODocument::TYPE_CONTRAT_DEPOT_VENTE,
                null,
                $depot,
                $this->getUser(),
                sprintf('contrat-depot-%d.pdf', $depot->getId()),
            );
            $this->em->flush();
        } catch (\Throwable) {
        }

        return $this->json(
            $this->serializer->normalize($depot, null, ['groups' => 'vo:read']),
            201,
        );
    }

    #[Route('/depots/{id}', methods: ['PATCH'])]
    public function updateDepot(int $id, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_VO_MANAGER');
        $depot = $this->em->getRepository(VODepotVente::class)->find($id);
        if (!$depot) {
            return $this->json(['error' => 'Not found'], 404);
        }

        $body = $request->toArray();

        if (isset($body['prixVenteSouhaite'])) $depot->setPrixVenteSouhaite((string) $body['prixVenteSouhaite']);
        if (isset($body['commissionType'])) $depot->setCommissionType($body['commissionType']);
        if (isset($body['commissionValeur'])) $depot->setCommissionValeur((string) $body['commissionValeur']);
        if (isset($body['dureeMandat'])) $depot->setDureeMandat($body['dureeMandat']);
        if (isset($body['status'])) $depot->setStatus($body['status']);
        if (isset($body['conditionsRestitution'])) $depot->setConditionsRestitution($body['conditionsRestitution']);
        if (isset($body['assuranceInfo'])) $depot->setAssuranceInfo($body['assuranceInfo']);
        if (isset($body['notes'])) $depot->setNotes($body['notes']);
        if (!empty($body['dateFin'])) $depot->setDateFin(new \DateTime($body['dateFin']));
        if (isset($body['prixVenteEffectif'])) $depot->setPrixVenteEffectif((string) $body['prixVenteEffectif']);

        $this->em->flush();

        return $this->json($this->serializer->normalize($depot, null, ['groups' => 'vo:read']));
    }

    // ═══════════════════════════════════════════
    // LIVRE DE POLICE
    // ═══════════════════════════════════════════

    #[Route('/livre-police', methods: ['GET'])]
    public function listLivrePolice(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_VO_MANAGER');

        $qb = $this->em->getRepository(VOLivrePolice::class)->createQueryBuilder('lp')
            ->orderBy('lp.numeroOrdre', 'ASC');

        $data = $this->serializer->normalize($qb->getQuery()->getResult(), null, ['groups' => 'livrepolice:read']);

        return $this->json($data);
    }

    #[Route('/livre-police/pdf', methods: ['GET'])]
    public function downloadLivrePolicePdf(Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_VO_MANAGER');
        $atelierId = $this->getUser()?->getAtelierId();

        $qb = $this->em->getRepository(VOLivrePolice::class)->createQueryBuilder('lp')
            ->orderBy('lp.numeroOrdre', 'ASC');

        $entries = $qb->getQuery()->getResult();
        $filePath = $this->pdfService->generateLivrePolicePdf($entries, $atelierId);

        return new BinaryFileResponse($filePath, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="livre-police.pdf"',
        ]);
    }

    // ═══════════════════════════════════════════
    // DOCUMENTS
    // ═══════════════════════════════════════════

    #[Route('/documents', methods: ['GET'])]
    public function listDocuments(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_VO_MANAGER');
        $purchaseId = $request->query->getInt('purchaseId');
        $depotId = $request->query->getInt('depotId');

        $qb = $this->em->getRepository(VODocument::class)->createQueryBuilder('d')
            ->orderBy('d.uploadedAt', 'DESC');

        if ($purchaseId) {
            $qb->andWhere('d.voPurchase = :pid')->setParameter('pid', $purchaseId);
        }
        if ($depotId) {
            $qb->andWhere('d.voDepotVente = :did')->setParameter('did', $depotId);
        }

        $data = $this->serializer->normalize($qb->getQuery()->getResult(), null, ['groups' => 'vodoc:read']);

        return $this->json($data);
    }

    #[Route('/documents/upload', methods: ['POST'])]
    public function uploadDocument(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_VO_MANAGER');

        $file = $request->files->get('file');
        $type = $request->request->get('type');

        if (!$file || !$type) {
            return $this->json(['error' => 'File and type are required'], 400);
        }

        $purchase = null;
        $depot = null;
        $purchaseId = $request->request->getInt('purchaseId');
        $depotId = $request->request->getInt('depotId');

        if ($purchaseId) {
            $purchase = $this->em->getRepository(VOPurchase::class)->find($purchaseId);
        }
        if ($depotId) {
            $depot = $this->em->getRepository(VODepotVente::class)->find($depotId);
        }

        $dateExpiration = $request->request->get('dateExpiration')
            ? new \DateTime($request->request->get('dateExpiration'))
            : null;

        try {
            $doc = $this->documentService->upload(
                $file,
                $type,
                $purchase,
                $depot,
                $this->getUser(),
                $dateExpiration,
            );
            $this->em->flush();

            return $this->json(
                $this->serializer->normalize($doc, null, ['groups' => 'vodoc:read']),
                201,
            );
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }

    #[Route('/documents/alerts', methods: ['GET'])]
    public function documentAlerts(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_VO_MANAGER');
        $atelierId = $this->getUser()?->getAtelierId();

        return $this->json($this->documentService->getAlerts($atelierId));
    }

    // ═══════════════════════════════════════════
    // FACTURATION VO
    // ═══════════════════════════════════════════

    #[Route('/factures', methods: ['GET'])]
    public function listFactures(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_VO_MANAGER');

        $qb = $this->em->getRepository(VOFacture::class)->createQueryBuilder('f')
            ->orderBy('f.createdAt', 'DESC');

        $data = $this->serializer->normalize($qb->getQuery()->getResult(), null, ['groups' => 'vofacture:read']);

        return $this->json($data);
    }

    /**
     * Sell a vehicle from a purchase → create VOFacture + record Livre de Police sale.
     */
    #[Route('/purchases/{id}/sell', methods: ['POST'])]
    public function sellPurchase(int $id, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_VO_MANAGER');
        $purchase = $this->em->getRepository(VOPurchase::class)->find($id);
        if (!$purchase) {
            return $this->json(['error' => 'Not found'], 404);
        }

        if (!in_array($purchase->getStatus(), ['en_stock', 'en_vente', 'reserve'])) {
            return $this->json(['error' => 'Le véhicule doit être en stock ou en vente pour être vendu'], 400);
        }

        $body = $request->toArray();

        $buyer = $this->em->getRepository(Client::class)->find($body['buyerId'] ?? 0);
        if (!$buyer) {
            return $this->json(['error' => 'Acheteur requis'], 400);
        }

        $salePrice = (string) ($body['salePrice'] ?? $purchase->getTargetSalePrice());

        // Calculate VAT
        if ($purchase->getRegimeTva() === 'marge') {
            $vatCalc = $this->marginService->calculateMarginVat($purchase->getPurchasePrice(), $salePrice);
        } else {
            $vatCalc = $this->marginService->calculateNormalVat($salePrice);
        }

        // Generate invoice number
        $year = date('Y');
        $maxNum = $this->em->getRepository(VOFacture::class)->createQueryBuilder('f')
            ->select('MAX(f.numeroFacture)')
            ->where('f.numeroFacture LIKE :prefix')
            ->setParameter('prefix', "VOF-{$year}-%")
            ->getQuery()
            ->getSingleScalarResult();

        $seq = $maxNum ? ((int) substr($maxNum, -4)) + 1 : 1;
        $numero = sprintf('VOF-%s-%04d', $year, $seq);

        // Create facture
        $facture = new VOFacture();
        $facture->setNumeroFacture($numero);
        $facture->setVoPurchase($purchase);
        $facture->setClient($buyer);
        $facture->setVehicule($purchase->getVehicule());
        $facture->setRegimeTva($purchase->getRegimeTva());
        $facture->setPrixAchatHt($purchase->getPurchasePrice());
        $facture->setMentionTvaMarge($purchase->getRegimeTva() === 'marge');
        $facture->setTotalHt($vatCalc['sale_price_ht']);
        $facture->setTotalTva($vatCalc['vat']);
        $facture->setTotalTtc($vatCalc['sale_price_ttc']);

        // Vehicle details on facture
        $v = $purchase->getVehicule();
        $facture->setImmatriculation($v->getPlaque());
        $facture->setVinFacture($v->getVin());
        $facture->setKilometrage($v->getMileage());
        $facture->setDatePremiereMiseEnCirculationFacture($v->getDatePremiereMiseEnCirculation());
        $facture->setNotes($body['notes'] ?? null);

        $this->em->persist($facture);

        // Update purchase status
        $purchase->setStatus('vendu');
        $purchase->setSaleDate(new \DateTime());

        // Record sale in Livre de Police
        $lpEntry = $this->em->getRepository(VOLivrePolice::class)->findOneBy(['voPurchase' => $purchase]);
        if ($lpEntry) {
            $this->livrePoliceService->recordSale($lpEntry, $buyer, $salePrice);
        }

        $this->em->flush();

        try {
            $pdfPath = $this->pdfService->generateVoFacturePdf($facture);
            $this->documentService->archiveGeneratedPdf(
                $pdfPath,
                VODocument::TYPE_FACTURE_VO,
                $purchase,
                null,
                $this->getUser(),
                $numero . '.pdf',
            );
            $this->em->flush();
        } catch (\Throwable) {
        }

        return $this->json([
            'facture' => $this->serializer->normalize($facture, null, ['groups' => 'vofacture:read']),
            'invoiceNumber' => $numero,
        ], 201);
    }

    /**
     * Sell a vehicle from dépôt-vente → create VOFacture + record sale.
     */
    #[Route('/depots/{id}/sell', methods: ['POST'])]
    public function sellDepot(int $id, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_VO_MANAGER');
        $depot = $this->em->getRepository(VODepotVente::class)->find($id);
        if (!$depot) {
            return $this->json(['error' => 'Not found'], 404);
        }

        if ($depot->getStatus() !== 'actif') {
            return $this->json(['error' => 'Le dépôt-vente doit être actif pour vendre'], 400);
        }

        $body = $request->toArray();

        $buyer = $this->em->getRepository(Client::class)->find($body['buyerId'] ?? 0);
        if (!$buyer) {
            return $this->json(['error' => 'Acheteur requis'], 400);
        }

        $salePrice = (string) ($body['salePrice'] ?? $depot->getPrixVenteSouhaite());

        // Commission calculation
        $commCalc = $this->marginService->calculateDepotVenteCommission(
            $salePrice,
            $depot->getCommissionType(),
            $depot->getCommissionValeur(),
        );

        // Generate invoice number
        $year = date('Y');
        $maxNum = $this->em->getRepository(VOFacture::class)->createQueryBuilder('f')
            ->select('MAX(f.numeroFacture)')
            ->where('f.numeroFacture LIKE :prefix')
            ->setParameter('prefix', "VOF-{$year}-%")
            ->getQuery()
            ->getSingleScalarResult();

        $seq = $maxNum ? ((int) substr($maxNum, -4)) + 1 : 1;
        $numero = sprintf('VOF-%s-%04d', $year, $seq);

        // Create facture
        $facture = new VOFacture();
        $facture->setNumeroFacture($numero);
        $facture->setVoDepotVente($depot);
        $facture->setClient($buyer);
        $facture->setVehicule($depot->getVehicule());
        $facture->setRegimeTva('normal');
        $facture->setMentionTvaMarge(false);
        $facture->setTotalHt($salePrice);
        $facture->setTotalTva('0.00');
        $facture->setTotalTtc($salePrice);

        $v = $depot->getVehicule();
        $facture->setImmatriculation($v->getPlaque());
        $facture->setVinFacture($v->getVin());
        $facture->setKilometrage($v->getMileage());
        $facture->setDatePremiereMiseEnCirculationFacture($v->getDatePremiereMiseEnCirculation());
        $facture->setNotes($body['notes'] ?? null);

        $this->em->persist($facture);

        // Update depot
        $depot->setStatus('vendu');
        $depot->setPrixVenteEffectif($salePrice);
        $depot->setDateFin(new \DateTime());

        // Record sale in Livre de Police
        $lpEntry = $this->em->getRepository(VOLivrePolice::class)->findOneBy(['voDepotVente' => $depot]);
        if ($lpEntry) {
            $this->livrePoliceService->recordSale($lpEntry, $buyer, $salePrice);
        }

        $this->em->flush();

        try {
            $pdfPath = $this->pdfService->generateVoFacturePdf($facture);
            $this->documentService->archiveGeneratedPdf(
                $pdfPath,
                VODocument::TYPE_FACTURE_VO,
                null,
                $depot,
                $this->getUser(),
                $numero . '.pdf',
            );
            $this->em->flush();
        } catch (\Throwable) {
        }

        return $this->json([
            'facture' => $this->serializer->normalize($facture, null, ['groups' => 'vofacture:read']),
            'invoiceNumber' => $numero,
            'commission' => $commCalc,
        ], 201);
    }

    // ═══════════════════════════════════════════
    // MARGIN CALCULATION
    // ═══════════════════════════════════════════

    #[Route('/margin/calculate', methods: ['POST'])]
    public function calculateMargin(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_VO_MANAGER');
        $body = $request->toArray();

        $regime = $body['regime'] ?? 'marge';
        $purchasePrice = (string) ($body['purchasePrice'] ?? '0');
        $salePrice = (string) ($body['salePrice'] ?? '0');

        if ($regime === 'marge') {
            $result = $this->marginService->calculateMarginVat($purchasePrice, $salePrice);
        } else {
            $result = $this->marginService->calculateNormalVat($salePrice);
        }

        return $this->json($result);
    }

    // ═══════════════════════════════════════════
    // PDF DOWNLOADS
    // ═══════════════════════════════════════════

    #[Route('/factures/{id}/pdf', methods: ['GET'])]
    public function downloadFacturePdf(int $id): Response
    {
        $this->denyAccessUnlessGranted('ROLE_VO_MANAGER');
        $facture = $this->em->getRepository(VOFacture::class)->find($id);
        if (!$facture) {
            return $this->json(['error' => 'Not found'], 404);
        }

        $filePath = $this->pdfService->generateVoFacturePdf($facture);

        return new BinaryFileResponse($filePath, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $facture->getNumeroFacture() . '.pdf"',
        ]);
    }

    #[Route('/purchases/{id}/pv-rachat/pdf', methods: ['GET'])]
    public function downloadPvRachatPdf(int $id): Response
    {
        $this->denyAccessUnlessGranted('ROLE_VO_MANAGER');
        $purchase = $this->em->getRepository(VOPurchase::class)->find($id);
        if (!$purchase) {
            return $this->json(['error' => 'Not found'], 404);
        }

        $filePath = $this->pdfService->generatePvRachatPdf($purchase);

        return new BinaryFileResponse($filePath, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="PV-rachat-' . $purchase->getId() . '.pdf"',
        ]);
    }

    #[Route('/depots/{id}/contrat/pdf', methods: ['GET'])]
    public function downloadContratDepotPdf(int $id): Response
    {
        $this->denyAccessUnlessGranted('ROLE_VO_MANAGER');
        $depot = $this->em->getRepository(VODepotVente::class)->find($id);
        if (!$depot) {
            return $this->json(['error' => 'Not found'], 404);
        }

        $filePath = $this->pdfService->generateContratDepotVentePdf($depot);

        return new BinaryFileResponse($filePath, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="contrat-depot-' . $depot->getId() . '.pdf"',
        ]);
    }

    // ═══════════════════════════════════════════
    // DASHBOARD / STATS
    // ═══════════════════════════════════════════

    #[Route('/stats', methods: ['GET'])]
    public function stats(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_VO_MANAGER');
        $user = $this->getUser();
        $atelierId = $user?->getAtelierId();

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
            ->getQuery()->getSingleScalarResult();

        $depotsActifs = (int) $this->em->getRepository(VODepotVente::class)->createQueryBuilder('d')
            ->select('COUNT(d)')
            ->where('d.status = :s')->setParameter('s', 'actif')
            ->getQuery()->getSingleScalarResult();

        $alerts = $this->documentService->getAlerts($atelierId);

        return $this->json([
            'en_stock' => $enStock,
            'vendus' => $vendus,
            'depots_actifs' => $depotsActifs,
            'alerts_count' => count($alerts),
        ]);
    }
}
