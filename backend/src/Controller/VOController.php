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
use App\Service\VOCompanionWorkflowService;
use App\Service\VODocumentService;
use App\Service\VOLivrePoliceService;
use App\Service\VOMarginService;
use App\Service\VONumberingService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
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
        private VOCompanionWorkflowService $companionWorkflowService,
        private SerializerInterface $serializer,
        private VONumberingService $numberingService,
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

    #[Route('/purchases/{id}/full', methods: ['GET'])]
    public function getPurchaseFull(int $id): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_VO_MANAGER');

        $purchase = $this->em->getRepository(VOPurchase::class)->find($id);
        if (!$purchase) {
            return $this->json(['error' => 'Not found'], 404);
        }

        $missingDocuments = $this->documentService->getMissingDocuments($purchase);
        $confirmationMissingDocuments = array_values(array_diff($missingDocuments, [VODocument::TYPE_PV_RACHAT]));
        $tokenUpdated = $this->companionWorkflowService->ensureToken($purchase);
        $documents = $this->em->getRepository(VODocument::class)->findBy(['voPurchase' => $purchase], ['uploadedAt' => 'DESC']);
        $livrePolice = $this->em->getRepository(VOLivrePolice::class)->findOneBy(['voPurchase' => $purchase]);
        $companionSteps = $this->companionWorkflowService->buildSteps($purchase, $documents);

        if ($tokenUpdated) {
            $this->em->flush();
        }

        $data = $this->serializer->normalize($purchase, null, ['groups' => 'vo:read']);
        $data['margin'] = $purchase->getMargin();
        $data['totalFre'] = $purchase->getTotalFre();
        $data['missingDocuments'] = $missingDocuments;
        $data['confirmationMissingDocuments'] = $confirmationMissingDocuments;
        $data['confirmationMissingCompanionSteps'] = $this->extractIncompleteCompanionSteps($companionSteps);
        $data['canConfirm'] = count($confirmationMissingDocuments) === 0
            && $data['confirmationMissingCompanionSteps'] === []
            && $purchase->getStatus() === 'brouillon';
        $data['canSell'] = in_array($purchase->getStatus(), ['en_stock', 'en_vente', 'reserve'], true);
        $data['documents'] = $this->serializer->normalize($documents, null, ['groups' => 'vodoc:read']);
        $data['generatedDocuments'] = $this->companionWorkflowService->getGeneratedDocuments($purchase);
        $data['companion'] = $this->buildCompanionData($purchase, $documents, $companionSteps);
        $data['livrePolice'] = $livrePolice
            ? $this->serializer->normalize($livrePolice, null, ['groups' => 'livrepolice:read'])
            : null;
        $data['marginCalculation'] = null;

        if (bccomp($purchase->getPurchasePrice(), '0', 2) > 0 && bccomp($purchase->getTargetSalePrice(), '0', 2) > 0) {
            $data['marginCalculation'] = $purchase->getRegimeTva() === 'marge'
                ? $this->marginService->calculateMarginVat($purchase->getPurchasePrice(), $purchase->getTargetSalePrice())
                : $this->marginService->calculateNormalVat($purchase->getTargetSalePrice());
        }

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
        $purchase->setAtelierId($this->resolveAtelierId());
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

        if (!empty($body['vehiculeId'])) {
            $vehicule = $this->em->getRepository(Vehicule::class)->find((int) $body['vehiculeId']);
            if ($vehicule instanceof Vehicule) {
                $purchase->setVehicule($vehicule);
            }
        }
        if (!empty($body['sellerId'])) {
            $seller = $this->em->getRepository(Client::class)->find((int) $body['sellerId']);
            if ($seller instanceof Client) {
                $purchase->setSeller($seller);
            }
        }
        if (array_key_exists('expertId', $body)) {
            $expert = !empty($body['expertId']) ? $this->em->getRepository(User::class)->find((int) $body['expertId']) : null;
            $purchase->setExpert($expert instanceof User ? $expert : null);
        }

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

        $missingDocuments = array_values(array_diff(
            $this->documentService->getMissingDocuments($purchase),
            [VODocument::TYPE_PV_RACHAT],
        ));

        if ($missingDocuments !== []) {
            return $this->json([
                'error' => 'Des documents obligatoires sont manquants avant confirmation.',
                'missingDocuments' => $missingDocuments,
            ], 422);
        }

        try {
            $payload = $this->inTransaction(function () use ($purchase) {
                $entry = $this->livrePoliceService->createEntryForPurchase($purchase);
                $purchase->setStatus('en_stock');
                $this->em->flush();

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
                return [
                    'purchase' => $this->serializer->normalize($purchase, null, ['groups' => 'vo:read']),
                    'livrePoliceId' => $entry->getId(),
                    'livrePoliceNumero' => $entry->getNumeroOrdre(),
                    'pdfGenerated' => true,
                    'pdfError' => null,
                ];
            });

            return $this->json($payload);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            return $this->json([
                'error' => 'La confirmation a échoué pendant la génération du PDF obligatoire.',
                'details' => $e->getMessage(),
            ], 500);
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

    #[Route('/depots/{id}/full', methods: ['GET'])]
    public function getDepotFull(int $id): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_VO_MANAGER');

        $depot = $this->em->getRepository(VODepotVente::class)->find($id);
        if (!$depot) {
            return $this->json(['error' => 'Not found'], 404);
        }

        $tokenUpdated = $this->companionWorkflowService->ensureToken($depot);
        $documents = $this->em->getRepository(VODocument::class)->findBy(['voDepotVente' => $depot], ['uploadedAt' => 'DESC']);
        $livrePolice = $this->em->getRepository(VOLivrePolice::class)->findOneBy(['voDepotVente' => $depot]);
        $missingDocuments = $this->documentService->getMissingDocumentsDepot($depot);
        $companionSteps = $this->companionWorkflowService->buildSteps($depot, $documents);

        if ($tokenUpdated) {
            $this->em->flush();
        }

        $data = $this->serializer->normalize($depot, null, ['groups' => 'vo:read']);
        $data['commissionAmount'] = $depot->getCommissionAmount();
        $data['commissionVat'] = $depot->getCommissionVatAmount();
        $data['commissionTtc'] = $depot->getCommissionTtc();
        $data['deposantNet'] = $depot->getDeposantNet();
        $data['mandatExpire'] = $depot->isMandatExpire();
        $data['joursRestants'] = $depot->getJoursRestantsMandat();
        $data['missingDocuments'] = $missingDocuments;
        $data['canSell'] = $depot->getStatus() === 'actif' && $companionSteps['allComplete'];
        $data['documents'] = $this->serializer->normalize($documents, null, ['groups' => 'vodoc:read']);
        $data['generatedDocuments'] = $this->companionWorkflowService->getGeneratedDocuments($depot);
        $data['companion'] = $this->buildCompanionData($depot, $documents, $companionSteps);
        $data['livrePolice'] = $livrePolice
            ? $this->serializer->normalize($livrePolice, null, ['groups' => 'livrepolice:read'])
            : null;

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
        $depot->setAtelierId($this->resolveAtelierId());
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

        try {
            $payload = $this->inTransaction(function () use ($depot) {
                $this->em->persist($depot);
                $this->livrePoliceService->createEntryForDepotVente($depot);
                $this->em->flush();

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

                return $this->serializer->normalize($depot, null, ['groups' => 'vo:read']);
            });

            return $this->json($payload, 201);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            return $this->json([
                'error' => 'La création du dépôt-vente a échoué pendant la génération du PDF obligatoire.',
                'details' => $e->getMessage(),
            ], 500);
        }
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

        if (!empty($body['vehiculeId'])) {
            $vehicule = $this->em->getRepository(Vehicule::class)->find((int) $body['vehiculeId']);
            if ($vehicule instanceof Vehicule) {
                $depot->setVehicule($vehicule);
            }
        }
        if (!empty($body['deposantId'])) {
            $deposant = $this->em->getRepository(Client::class)->find((int) $body['deposantId']);
            if ($deposant instanceof Client) {
                $depot->setDeposant($deposant);
            }
        }
        if (array_key_exists('gestionnaireId', $body)) {
            $gestionnaire = !empty($body['gestionnaireId']) ? $this->em->getRepository(User::class)->find((int) $body['gestionnaireId']) : null;
            $depot->setGestionnaire($gestionnaire instanceof User ? $gestionnaire : null);
        }

        if (isset($body['prixVenteSouhaite'])) $depot->setPrixVenteSouhaite((string) $body['prixVenteSouhaite']);
        if (isset($body['commissionType'])) $depot->setCommissionType($body['commissionType']);
        if (isset($body['commissionValeur'])) $depot->setCommissionValeur((string) $body['commissionValeur']);
        if (isset($body['dureeMandat'])) $depot->setDureeMandat($body['dureeMandat']);
        if (isset($body['status'])) $depot->setStatus($body['status']);
        if (isset($body['conditionsRestitution'])) $depot->setConditionsRestitution($body['conditionsRestitution']);
        if (isset($body['assuranceInfo'])) $depot->setAssuranceInfo($body['assuranceInfo']);
        if (isset($body['notes'])) $depot->setNotes($body['notes']);
        if (!empty($body['dateDebut'])) $depot->setDateDebut(new \DateTime($body['dateDebut']));
        if (!empty($body['dateFin'])) $depot->setDateFin(new \DateTime($body['dateFin']));
        if (isset($body['prixVenteEffectif'])) $depot->setPrixVenteEffectif((string) $body['prixVenteEffectif']);
        if (isset($body['deposantIdType'])) $depot->setDeposantIdType($body['deposantIdType']);
        if (isset($body['deposantIdNumber'])) $depot->setDeposantIdNumber($body['deposantIdNumber']);
        if (!empty($body['deposantIdDate'])) $depot->setDeposantIdDate(new \DateTime($body['deposantIdDate']));

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

    #[Route('/documents/{id}/download', methods: ['GET'])]
    public function downloadDocument(int $id): Response
    {
        $this->denyAccessUnlessGranted('ROLE_VO_MANAGER');
        $atelierId = $this->getUser()?->getAtelierId();

        $document = $this->em->getRepository(VODocument::class)->find($id);
        if (!$document) {
            return $this->json(['error' => 'Not found'], 404);
        }
        if ($atelierId && $document->getAtelierId() !== $atelierId) {
            return $this->json(['error' => 'Not found'], 404);
        }

        $projectDir = (string) $this->getParameter('kernel.project_dir');
        $relativePath = $document->getFilePath();
        if (!str_starts_with($relativePath, '/uploads/vo/')) {
            return $this->json(['error' => 'Document path is not allowed'], 404);
        }

        $uploadsDir = realpath($projectDir . '/public/uploads/vo');
        if ($uploadsDir === false) {
            return $this->json(['error' => 'Document storage unavailable'], 404);
        }

        $resolvedPath = realpath($projectDir . '/public' . $relativePath);
        if ($resolvedPath === false || !str_starts_with($resolvedPath, $uploadsDir . '/') || !is_file($resolvedPath)) {
            return $this->json(['error' => 'File not found'], 404);
        }

        $response = new BinaryFileResponse($resolvedPath);
        $response->headers->set('Cache-Control', 'private, max-age=3600');
        $response->headers->set('Content-Type', $document->getMimeType() ?: 'application/octet-stream');
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, $document->getOriginalFilename());

        return $response;
    }

    #[Route('/purchases/{id}/vehicule', methods: ['PUT'])]
    public function updatePurchaseVehicule(int $id, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_VO_MANAGER');
        $atelierId = $this->getUser()?->getAtelierId();

        $purchase = $this->em->getRepository(VOPurchase::class)->find($id);
        if (!$purchase || !$purchase->getVehicule()) {
            return $this->json(['error' => 'Not found'], 404);
        }
        if ($atelierId && $purchase->getAtelierId() !== $atelierId) {
            return $this->json(['error' => 'Not found'], 404);
        }

        $this->applyVehiculePayload($purchase->getVehicule(), $request->toArray());
        $this->em->flush();

        return $this->json([
            'success' => true,
            'vehicule' => $this->serializer->normalize($purchase->getVehicule(), null, ['groups' => 'vehicule:read']),
        ]);
    }

    #[Route('/depots/{id}/vehicule', methods: ['PUT'])]
    public function updateDepotVehicule(int $id, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_VO_MANAGER');
        $atelierId = $this->getUser()?->getAtelierId();

        $depot = $this->em->getRepository(VODepotVente::class)->find($id);
        if (!$depot || !$depot->getVehicule()) {
            return $this->json(['error' => 'Not found'], 404);
        }
        if ($atelierId && $depot->getAtelierId() !== $atelierId) {
            return $this->json(['error' => 'Not found'], 404);
        }

        $this->applyVehiculePayload($depot->getVehicule(), $request->toArray());
        $this->em->flush();

        return $this->json([
            'success' => true,
            'vehicule' => $this->serializer->normalize($depot->getVehicule(), null, ['groups' => 'vehicule:read']),
        ]);
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
        try {
            $payload = $this->inTransaction(function () use ($purchase, $buyer, $salePrice, $vatCalc, $body) {
                $numero = $this->numberingService->nextFactureNumber($this->resolveAtelierId($purchase->getAtelierId()));

                $facture = new VOFacture();
                $facture->setAtelierId($this->resolveAtelierId($purchase->getAtelierId()));
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

                $vehicule = $purchase->getVehicule();
                $facture->setImmatriculation($vehicule->getPlaque());
                $facture->setVinFacture($vehicule->getVin());
                $facture->setKilometrage($vehicule->getMileage());
                $facture->setDatePremiereMiseEnCirculationFacture($vehicule->getDatePremiereMiseEnCirculation());
                $facture->setNotes($body['notes'] ?? null);

                $this->em->persist($facture);

                $purchase->setStatus('vendu');
                $purchase->setSaleDate(new \DateTime());

                $lpEntry = $this->em->getRepository(VOLivrePolice::class)->findOneBy(['voPurchase' => $purchase]);
                if ($lpEntry) {
                    $this->livrePoliceService->recordSale($lpEntry, $buyer, $salePrice);
                }

                $this->em->flush();

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

                return [
                    'facture' => $this->serializer->normalize($facture, null, ['groups' => 'vofacture:read']),
                    'invoiceNumber' => $numero,
                    'pdfGenerated' => true,
                    'pdfError' => null,
                ];
            });

            return $this->json($payload, 201);
        } catch (\Throwable $e) {
            return $this->json([
                'error' => 'La vente a échoué pendant la génération du PDF obligatoire de facture.',
                'details' => $e->getMessage(),
            ], 500);
        }
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
        try {
            $payload = $this->inTransaction(function () use ($depot, $buyer, $salePrice, $body, $commCalc) {
                $numero = $this->numberingService->nextFactureNumber($this->resolveAtelierId($depot->getAtelierId()));

                $facture = new VOFacture();
                $facture->setAtelierId($this->resolveAtelierId($depot->getAtelierId()));
                $facture->setNumeroFacture($numero);
                $facture->setVoDepotVente($depot);
                $facture->setClient($buyer);
                $facture->setVehicule($depot->getVehicule());
                $facture->setRegimeTva('normal');
                $facture->setMentionTvaMarge(false);
                $facture->setTotalHt($salePrice);
                $facture->setTotalTva('0.00');
                $facture->setTotalTtc($salePrice);

                $vehicule = $depot->getVehicule();
                $facture->setImmatriculation($vehicule->getPlaque());
                $facture->setVinFacture($vehicule->getVin());
                $facture->setKilometrage($vehicule->getMileage());
                $facture->setDatePremiereMiseEnCirculationFacture($vehicule->getDatePremiereMiseEnCirculation());
                $facture->setNotes($body['notes'] ?? null);

                $this->em->persist($facture);

                $depot->setStatus('vendu');
                $depot->setPrixVenteEffectif($salePrice);
                $depot->setDateFin(new \DateTime());

                $lpEntry = $this->em->getRepository(VOLivrePolice::class)->findOneBy(['voDepotVente' => $depot]);
                if ($lpEntry) {
                    $this->livrePoliceService->recordSale($lpEntry, $buyer, $salePrice);
                }

                $this->em->flush();

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

                return [
                    'facture' => $this->serializer->normalize($facture, null, ['groups' => 'vofacture:read']),
                    'invoiceNumber' => $numero,
                    'commission' => $commCalc,
                    'pdfGenerated' => true,
                    'pdfError' => null,
                ];
            });

            return $this->json($payload, 201);
        } catch (\Throwable $e) {
            return $this->json([
                'error' => 'La vente du dépôt a échoué pendant la génération du PDF obligatoire de facture.',
                'details' => $e->getMessage(),
            ], 500);
        }
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

    #[Route('/margin/simulate', methods: ['POST'])]
    public function simulateMargin(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_VO_MANAGER');
        $body = $request->toArray();

        $purchasePrice = (string) ($body['purchasePrice'] ?? '0');
        $salePrice = (string) ($body['salePrice'] ?? '0');
        $regime = (string) ($body['regime'] ?? 'marge');
        $freItems = is_array($body['freItems'] ?? null) ? $body['freItems'] : [];

        $totalFre = '0.00';
        foreach ($freItems as $fre) {
            $amount = is_array($fre) ? (string) ($fre['amount'] ?? '0') : '0';
            $totalFre = bcadd($totalFre, $amount, 2);
        }

        $totalCost = bcadd($purchasePrice, $totalFre, 2);
        $vatCalc = $regime === 'marge'
            ? $this->marginService->calculateMarginVat($purchasePrice, $salePrice)
            : $this->marginService->calculateNormalVat($salePrice);
        $netMargin = bcsub($salePrice, $totalCost, 2);
        $marginPct = bccomp($totalCost, '0', 2) > 0
            ? bcdiv(bcmul($netMargin, '100', 4), $totalCost, 2)
            : '0.00';

        return $this->json([
            'purchase_price' => $purchasePrice,
            'total_fre' => $totalFre,
            'total_cost' => $totalCost,
            'sale_price' => $salePrice,
            'net_margin' => $netMargin,
            'margin_pct' => $marginPct,
            'vat_detail' => $vatCalc,
            'is_profitable' => bccomp($netMargin, '0', 2) > 0,
        ]);
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

    #[Route('/stock', methods: ['GET'])]
    public function stock(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_VO_MANAGER');

        $search = trim((string) $request->query->get('q', ''));
        $limit = max(0, min(100, $request->query->getInt('limit', 0)));

        return $this->json($this->buildStockPayload($this->resolveAtelierId(), $search, $limit));
    }

    // ═══════════════════════════════════════════
    // DASHBOARD / STATS
    // ═══════════════════════════════════════════

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

    #[Route('/depots/{id}/restituer', methods: ['POST'])]
    public function restituerDepot(int $id, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_VO_MANAGER');
        $depot = $this->em->getRepository(VODepotVente::class)->find($id);

        if (!$depot) {
            return $this->json(['error' => 'Not found'], 404);
        }

        if ($depot->getStatus() !== 'actif') {
            return $this->json(['error' => 'Seul un dépôt actif peut être restitué'], 400);
        }

        $body = $request->toArray();
        $depot->setStatus('restitue');
        $depot->setDateFin(new \DateTime());

        if (!empty($body['notes'])) {
            $existingNotes = trim((string) $depot->getNotes());
            $restitutionNote = '[RESTITUTION] ' . trim((string) $body['notes']);
            $depot->setNotes($existingNotes !== '' ? $existingNotes . "\n" . $restitutionNote : $restitutionNote);
        }

        $this->em->flush();

        return $this->json($this->serializer->normalize($depot, null, ['groups' => 'vo:read']));
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
        $items = [];

        foreach ($purchases as $purchase) {
            $vehicule = $purchase->getVehicule();
            $missingDocuments = $this->documentService->getMissingDocuments($purchase, true);
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
                'can_sell' => empty($missingDocuments) || in_array($purchase->getStatus(), ['en_vente', 'reserve'], true),
                'created_at' => $purchase->getCreatedAt()->format('Y-m-d'),
            ];
        }

        foreach ($depots as $depot) {
            $vehicule = $depot->getVehicule();
            $missingDocuments = $this->documentService->getMissingDocumentsDepot($depot);

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
                'can_sell' => $depot->getStatus() === 'actif',
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

    private function buildCompanionData(VOPurchase|VODepotVente $record, array $documents, array $steps): array
    {
        return [
            'mode' => $this->companionWorkflowService->getMode($record),
            'partyRole' => $this->companionWorkflowService->getPartyRoleLabel($record),
            'publicPath' => $record->getCompanionPublicPath(),
            'expiresAt' => $record->getCompanionTokenExpiresAt()?->format(DATE_ATOM),
            'signedAt' => $record->getCompanionSignedAt()?->format(DATE_ATOM),
            'steps' => $steps,
            'generatedDocuments' => $this->companionWorkflowService->getGeneratedDocuments($record),
            'documentsCount' => count($documents),
        ];
    }

    private function extractIncompleteCompanionSteps(array $steps): array
    {
        $labels = [];

        foreach (['seller', 'vehicle', 'documents', 'signature'] as $key) {
            if (($steps[$key]['completed'] ?? false) === false) {
                $labels[] = (string) ($steps[$key]['label'] ?? $key);
            }
        }

        return $labels;
    }

    private function inTransaction(callable $operation): mixed
    {
        $connection = $this->em->getConnection();
        $connection->beginTransaction();

        try {
            $result = $operation();
            $connection->commit();

            return $result;
        } catch (\Throwable $throwable) {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }
            $this->em->clear();

            throw $throwable;
        }
    }

    private function applyVehiculePayload(Vehicule $vehicule, array $data): void
    {
        if (isset($data['marque'])) {
            $vehicule->setMarque($this->nullableString($data['marque']));
        }
        if (isset($data['modele'])) {
            $vehicule->setModele($this->nullableString($data['modele']));
        }
        if (isset($data['vin'])) {
            $vin = $this->nullableString($data['vin']);
            $vehicule->setVin($vin ? strtoupper(substr($vin, 0, 17)) : null);
        }
        if (isset($data['annee'])) {
            $annee = (int) $data['annee'];
            $vehicule->setAnnee($annee > 0 ? $annee : null);
        }
        if (isset($data['cylindree'])) {
            $vehicule->setCylindree($this->nullableString($data['cylindree']));
        }
        if (isset($data['type_moto'])) {
            $vehicule->setTypeMoto($this->nullableString($data['type_moto']));
        }
        if (isset($data['plaque'])) {
            $plaque = $this->nullableString($data['plaque']);
            if ($plaque !== null) {
                $vehicule->setPlaque($plaque);
            }
        }
    }

    private function nullableString(mixed $value): ?string
    {
        $normalized = trim((string) ($value ?? ''));

        return $normalized !== '' ? $normalized : null;
    }

    private function resolveAtelierId(?int $atelierId = null): int
    {
        return $atelierId ?? $this->getUser()?->getAtelierId() ?? 0;
    }
}
