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
use App\Service\AuditService;
use App\Service\VORemiseEnEtatService;
use App\Service\PdfService;
use App\Service\VOCompanionWorkflowService;
use App\Service\VODocumentService;
use App\Service\VOGeneratedDocumentService;
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
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Workflow\WorkflowInterface;

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
        private VOGeneratedDocumentService $generatedDocumentService,
        private SerializerInterface $serializer,
        private VONumberingService $numberingService,
        private VORemiseEnEtatService $remiseEnEtatService,
        private AuditService $audit,
        #[Target('vo_purchase')]
        private WorkflowInterface $voPurchaseWorkflow,
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

        $data = array_map(fn (VOPurchase $purchase): array => $this->normalizePurchaseBase($purchase), $qb->getQuery()->getResult());

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

        $data = $this->normalizePurchaseBase($purchase);
        $data['margin'] = $purchase->getMargin();
        $data['totalFre'] = $purchase->getTotalFre();
        $data['missingDocuments'] = $this->documentService->getMissingDocuments($purchase);
        $data['siv'] = $this->documentService->getPurchaseSivSummary($purchase);
        $data['dossierStatus'] = $this->documentService->getPurchaseDossierStatus($purchase);

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
        $legalChecklist = $this->documentService->buildPurchaseLegalChecklist($purchase);
        $saleBlockers = $this->documentService->getPurchaseSaleBlockers($purchase);
        $tokenUpdated = $this->companionWorkflowService->ensureToken($purchase);
        $documents = $this->em->getRepository(VODocument::class)->findBy(['voPurchase' => $purchase], ['uploadedAt' => 'DESC']);
        $livrePolice = $this->em->getRepository(VOLivrePolice::class)->findOneBy(['voPurchase' => $purchase]);
        $companionSteps = $this->companionWorkflowService->buildSteps($purchase, $documents);
        $campaigns = $this->remiseEnEtatService->getCampaignsForRecord($purchase);
        $activeCampaign = $this->remiseEnEtatService->getActiveCampaignForRecord($purchase);

        if ($tokenUpdated) {
            $this->em->flush();
        }

        $data = $this->normalizePurchaseBase($purchase);
        $data['margin'] = $purchase->getMargin();
        $data['totalFre'] = $purchase->getTotalFre();
        $data['missingDocuments'] = $missingDocuments;
        $data['confirmationMissingDocuments'] = $confirmationMissingDocuments;
        $data['confirmationMissingCompanionSteps'] = $this->extractIncompleteCompanionSteps($companionSteps);
        $data['canConfirm'] = count($confirmationMissingDocuments) === 0
            && $data['confirmationMissingCompanionSteps'] === []
            && $purchase->getStatus() === 'brouillon';
        $data['legalChecklist'] = $legalChecklist;
        $data['siv'] = $this->documentService->getPurchaseSivSummary($purchase);
        $data['dossierStatus'] = $this->documentService->getPurchaseDossierStatus($purchase);
        $data['canSell'] = in_array($purchase->getStatus(), ['en_stock', 'en_vente', 'reserve'], true)
            && $saleBlockers === []
            && !($activeCampaign?->isBlockingSale() ?? false);
        $data['documents'] = $this->serializer->normalize($documents, null, ['groups' => 'vodoc:read']);
        $data['generatedDocuments'] = $this->companionWorkflowService->getGeneratedDocuments($purchase);
        $data['companion'] = $this->buildCompanionData($purchase, $documents, $companionSteps);
        $data['remisesEnEtat'] = array_map(fn ($campaign): array => $this->remiseEnEtatService->normalizeCampaign($campaign), $campaigns);
        $data['activeRemiseEnEtat'] = $activeCampaign ? $this->remiseEnEtatService->normalizeCampaign($activeCampaign) : null;
        $data['canCreateRemiseEnEtat'] = !($activeCampaign instanceof \App\Entity\VORemiseEnEtat);
        $data['refurbishmentBlockingSale'] = $activeCampaign?->isBlockingSale() ?? false;
        if ($activeCampaign instanceof \App\Entity\VORemiseEnEtat) {
            $saleBlockers[] = sprintf('Remise en etat VO "%s" non cloturee.', $activeCampaign->getTitre());
        }
        $data['saleBlockers'] = array_values(array_unique($saleBlockers));
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

        if (($vehicule === null || $seller === null) && ($body['status'] ?? 'brouillon') !== 'brouillon') {
            return $this->json(['error' => 'Vehicule and seller are required'], 400);
        }

        $purchase = new VOPurchase();
        $purchase->setAtelierId($this->resolveAtelierId());
        if ($vehicule instanceof Vehicule) {
            $purchase->setVehicule($vehicule);
        }
        if ($seller instanceof Client) {
            $purchase->setSeller($seller);
        }
        $purchase->setPurchasePrice((string) ($body['purchasePrice'] ?? '0'));
        $purchase->setTargetSalePrice((string) ($body['targetSalePrice'] ?? '0'));
        $purchase->setRepairEstimates($body['repairEstimates'] ?? null);
        $purchase->setRegimeTva($body['regimeTva'] ?? 'marge');
        $purchase->setNotes($body['notes'] ?? null);
        $this->applyPurchaseSivPayload($purchase, $body);

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

        $this->audit->log('create_vo_purchase', 'vo_purchase', $purchase->getId(), json_encode([
            'status' => $purchase->getStatus(),
            'vehiculeId' => $purchase->getVehicule()?->getId(),
            'sellerId' => $purchase->getSeller()?->getId(),
        ]));

        return $this->json(
            $this->normalizePurchaseBase($purchase),
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
        $hadIncompleteSignedDraft = $purchase->hasCompanionSignature()
            && ($purchase->getSeller() === null || $purchase->getVehicule() === null);

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
        if (isset($body['regimeTva'])) $purchase->setRegimeTva($body['regimeTva']);
        if (isset($body['notes'])) $purchase->setNotes($body['notes']);
        if (isset($body['controleTechniqueOk'])) $purchase->setControleTechniqueOk($body['controleTechniqueOk']);
        $this->applyPurchaseSivPayload($purchase, $body);
        if (!empty($body['purchaseDate'])) $purchase->setPurchaseDate(new \DateTime($body['purchaseDate']));
        if (!empty($body['saleDate'])) $purchase->setSaleDate(new \DateTime($body['saleDate']));
        if (!empty($body['nonGageDate'])) $purchase->setNonGageDate(new \DateTime($body['nonGageDate']));
        if (isset($body['sellerIdType'])) $purchase->setSellerIdType($body['sellerIdType']);
        if (isset($body['sellerIdNumber'])) $purchase->setSellerIdNumber($body['sellerIdNumber']);
        if (!empty($body['sellerIdDate'])) $purchase->setSellerIdDate(new \DateTime($body['sellerIdDate']));

        $this->em->flush();

        if ($hadIncompleteSignedDraft && $purchase->getSeller() && $purchase->getVehicule()) {
            $this->generatedDocumentService->archiveCompanionDocumentIfReady(
                $purchase,
                $this->getUser() instanceof User ? $this->getUser() : null,
            );
            $this->em->flush();
        }

        $this->audit->log('update_vo_purchase', 'vo_purchase', $purchase->getId(), json_encode([
            'fields' => array_keys(array_filter($body, static fn ($v) => $v !== null)),
        ]));

        return $this->json($this->normalizePurchaseBase($purchase));
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

        if (!$this->voPurchaseWorkflow->can($purchase, 'confirmer')) {
            return $this->json(['error' => 'Transition "confirmer" non autorisée depuis le statut actuel'], 400);
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
                $this->voPurchaseWorkflow->apply($purchase, 'confirmer');

                $this->generatedDocumentService->archiveCompanionDocumentIfReady(
                    $purchase,
                    $this->getUser() instanceof User ? $this->getUser() : null,
                    true,
                );
                $this->em->flush();
                $this->audit->log('confirm_vo_purchase', 'vo_purchase', $purchase->getId(), json_encode([
                    'livrePoliceId' => $entry->getId(),
                    'livrePoliceNumero' => $entry->getNumeroOrdre(),
                ]));

                return [
                    'purchase' => $this->normalizePurchaseBase($purchase),
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

    /**
     * Generic workflow transition for VOPurchase.
     * Allowed transitions: mettre_en_vente, retirer_de_la_vente, reserver, liberer.
     * confirmer and vendre have dedicated endpoints with side-effects.
     */
    #[Route('/purchases/{id}/transition', methods: ['POST'])]
    public function transitionPurchase(int $id, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_VO_MANAGER');
        $purchase = $this->em->getRepository(VOPurchase::class)->find($id);
        if (!$purchase) {
            return $this->json(['error' => 'Not found'], 404);
        }

        $body = $request->toArray();
        $transition = $body['transition'] ?? null;

        $allowedTransitions = ['mettre_en_vente', 'retirer_de_la_vente', 'reserver', 'liberer'];
        if (!in_array($transition, $allowedTransitions, true)) {
            return $this->json(['error' => sprintf('Transition invalide. Autorisées : %s', implode(', ', $allowedTransitions))], 400);
        }

        if (!$this->voPurchaseWorkflow->can($purchase, $transition)) {
            return $this->json(['error' => sprintf('Transition "%s" non autorisée depuis le statut "%s"', $transition, $purchase->getStatus())], 409);
        }

        $this->voPurchaseWorkflow->apply($purchase, $transition);
        $this->em->flush();

        $this->audit->log('vo_purchase_transition', 'vo_purchase', $purchase->getId(), json_encode([
            'transition' => $transition,
            'new_status' => $purchase->getStatus(),
        ]));

        return $this->json([
            'id' => $purchase->getId(),
            'status' => $purchase->getStatus(),
            'transition' => $transition,
        ]);
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

        $data = array_map(fn (VODepotVente $depot): array => $this->normalizeDepotBase($depot), $qb->getQuery()->getResult());

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

        $data = $this->normalizeDepotBase($depot);
        $data['commissionAmount'] = $depot->getCommissionAmount();
        $data['deposantNet'] = $depot->getDeposantNet();
        $data['mandatExpire'] = $depot->isMandatExpire();
        $data['missingDocuments'] = $this->documentService->getMissingDocumentsDepot($depot);
        $data['legalChecklist'] = $this->documentService->buildDepotLegalChecklist($depot);
        $data['dossierStatus'] = $this->documentService->getDepotDossierStatus($depot);

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
        $legalChecklist = $this->documentService->buildDepotLegalChecklist($depot);
        $saleBlockers = $this->documentService->getDepotSaleBlockers($depot);
        $companionSteps = $this->companionWorkflowService->buildSteps($depot, $documents);
        $campaigns = $this->remiseEnEtatService->getCampaignsForRecord($depot);
        $activeCampaign = $this->remiseEnEtatService->getActiveCampaignForRecord($depot);

        if ($tokenUpdated) {
            $this->em->flush();
        }

        $data = $this->normalizeDepotBase($depot);
        $data['commissionAmount'] = $depot->getCommissionAmount();
        $data['commissionVat'] = $depot->getCommissionVatAmount();
        $data['commissionTtc'] = $depot->getCommissionTtc();
        $data['deposantNet'] = $depot->getDeposantNet();
        $data['mandatExpire'] = $depot->isMandatExpire();
        $data['joursRestants'] = $depot->getJoursRestantsMandat();
        $data['missingDocuments'] = $missingDocuments;
        $data['legalChecklist'] = $legalChecklist;
        $data['dossierStatus'] = $this->documentService->getDepotDossierStatus($depot);
        $data['canSell'] = $depot->getStatus() === 'actif'
            && $companionSteps['allComplete']
            && $saleBlockers === []
            && !($activeCampaign?->isBlockingSale() ?? false);
        $data['documents'] = $this->serializer->normalize($documents, null, ['groups' => 'vodoc:read']);
        $data['generatedDocuments'] = $this->companionWorkflowService->getGeneratedDocuments($depot);
        $data['companion'] = $this->buildCompanionData($depot, $documents, $companionSteps);
        $data['remisesEnEtat'] = array_map(fn ($campaign): array => $this->remiseEnEtatService->normalizeCampaign($campaign), $campaigns);
        $data['activeRemiseEnEtat'] = $activeCampaign ? $this->remiseEnEtatService->normalizeCampaign($activeCampaign) : null;
        $data['canCreateRemiseEnEtat'] = !($activeCampaign instanceof \App\Entity\VORemiseEnEtat);
        $data['refurbishmentBlockingSale'] = $activeCampaign?->isBlockingSale() ?? false;
        if ($activeCampaign instanceof \App\Entity\VORemiseEnEtat) {
            $saleBlockers[] = sprintf('Remise en etat VO "%s" non cloturee.', $activeCampaign->getTitre());
        }
        $data['saleBlockers'] = array_values(array_unique($saleBlockers));
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
        $status = (string) ($body['status'] ?? 'actif');

        if (($vehicule === null || $deposant === null) && $status !== 'brouillon') {
            return $this->json(['error' => 'Vehicule and deposant are required'], 400);
        }

        $depot = new VODepotVente();
        $depot->setAtelierId($this->resolveAtelierId());
        if ($vehicule instanceof Vehicule) {
            $depot->setVehicule($vehicule);
        }
        if ($deposant instanceof Client) {
            $depot->setDeposant($deposant);
        }
        $depot->setPrixVenteSouhaite((string) ($body['prixVenteSouhaite'] ?? '0'));
        $depot->setCommissionType($body['commissionType'] ?? 'pourcentage');
        $depot->setCommissionValeur((string) ($body['commissionValeur'] ?? '0'));
        $depot->setDureeMandat($body['dureeMandat'] ?? 90);
        $depot->setStatus($status);
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
                $this->em->flush();

                if ($depot->getStatus() !== 'brouillon') {
                    $this->activateDepotRecord($depot);
                    $this->em->flush();
                }

                return $this->normalizeDepotBase($depot);
            });

            $this->audit->log('create_vo_depot', 'vo_depot', $depot->getId(), json_encode([
                'status' => $depot->getStatus(),
                'vehiculeId' => $depot->getVehicule()?->getId(),
                'deposantId' => $depot->getDeposant()?->getId(),
            ]));

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
        $wasDraft = $depot->getStatus() === 'brouillon';

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

        $shouldFinalizeDraft = (bool) ($body['finalizeCompanionDraft'] ?? false)
            || ($wasDraft && $depot->getStatus() !== 'brouillon');

        if (!$shouldFinalizeDraft) {
            $this->em->flush();
            $this->audit->log('update_vo_depot', 'vo_depot', $depot->getId(), json_encode([
                'fields' => array_keys(array_filter($body, static fn ($v) => $v !== null)),
            ]));

            return $this->json($this->normalizeDepotBase($depot));
        }

        try {
            $payload = $this->inTransaction(function () use ($depot) {
                if ($depot->getStatus() === 'brouillon') {
                    $depot->setStatus('actif');
                }

                $this->activateDepotRecord($depot);
                $this->em->flush();

                return $this->normalizeDepotBase($depot);
            });

            $this->audit->log('finalize_vo_depot', 'vo_depot', $depot->getId(), json_encode([
                'status' => $depot->getStatus(),
            ]));

            return $this->json($payload);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            return $this->json([
                'error' => 'La finalisation du brouillon dépôt-vente a échoué.',
                'details' => $e->getMessage(),
            ], 500);
        }
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

            $this->audit->log('upload_vo_document', 'vo_document', $doc->getId(), json_encode([
                'type' => $type,
                'purchaseId' => $purchase?->getId(),
                'depotId' => $depot?->getId(),
            ]));

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
        if ($atelierId !== null && $document->getAtelierId() !== $atelierId) {
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
        if ($atelierId !== null && $purchase->getAtelierId() !== $atelierId) {
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
        if ($atelierId !== null && $depot->getAtelierId() !== $atelierId) {
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

        if (!$this->voPurchaseWorkflow->can($purchase, 'vendre')) {
            return $this->json(['error' => 'Transition "vendre" non autorisée depuis le statut actuel'], 400);
        }

        if ($this->remiseEnEtatService->hasBlockingActiveCampaign($purchase)) {
            return $this->json(['error' => 'La remise en etat VO active doit etre cloturee avant la vente.'], 422);
        }

        $saleBlockers = $this->documentService->getPurchaseSaleBlockers($purchase);
        if ($saleBlockers !== []) {
            return $this->json([
                'error' => 'La vente est bloquée tant que le dossier légal / SIV n’est pas régularisé.',
                'saleBlockers' => $saleBlockers,
            ], 422);
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

                $purchase->setSaleDate(new \DateTime());
                $this->voPurchaseWorkflow->apply($purchase, 'vendre');

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

                $mandatPath = $this->pdfService->generateMandatImmatriculationPdf($purchase, $buyer);
                $this->documentService->archiveGeneratedPdf(
                    $mandatPath,
                    VODocument::TYPE_MANDAT_IMMATRICULATION,
                    $purchase,
                    null,
                    $this->getUser(),
                    sprintf('mandat-immat-%d.pdf', $purchase->getId()),
                );
                $this->em->flush();

                $this->audit->log('sell_vo_purchase', 'vo_purchase', $purchase->getId(), json_encode([
                    'factureId' => $facture->getId(),
                    'invoiceNumber' => $numero,
                    'buyerId' => $buyer->getId(),
                    'salePrice' => $salePrice,
                    'regimeTva' => $purchase->getRegimeTva(),
                ]));

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

        if ($this->remiseEnEtatService->hasBlockingActiveCampaign($depot)) {
            return $this->json(['error' => 'La remise en etat VO active doit etre cloturee avant la vente.'], 422);
        }

        $saleBlockers = $this->documentService->getDepotSaleBlockers($depot);
        if ($saleBlockers !== []) {
            return $this->json([
                'error' => 'La vente est bloquée tant que le dossier légal / mandat n’est pas régularisé.',
                'saleBlockers' => $saleBlockers,
            ], 422);
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

                $mandatPath = $this->pdfService->generateMandatImmatriculationPdf($depot, $buyer);
                $this->documentService->archiveGeneratedPdf(
                    $mandatPath,
                    VODocument::TYPE_MANDAT_IMMATRICULATION,
                    null,
                    $depot,
                    $this->getUser(),
                    sprintf('mandat-immat-depot-%d.pdf', $depot->getId()),
                );
                $this->em->flush();

                $this->audit->log('sell_vo_depot', 'vo_depot', $depot->getId(), json_encode([
                    'factureId' => $facture->getId(),
                    'invoiceNumber' => $numero,
                    'buyerId' => $buyer->getId(),
                    'salePrice' => $salePrice,
                ]));

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

    #[Route('/purchases/{id}/siv/prepare', methods: ['POST'])]
    public function preparePurchaseSiv(int $id): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_VO_MANAGER');
        $purchase = $this->em->getRepository(VOPurchase::class)->find($id);
        if (!$purchase) {
            return $this->json(['error' => 'Not found'], 404);
        }

        if (!$purchase->getSeller() || !$purchase->getVehicule()) {
            return $this->json(['error' => 'Le dossier doit au minimum contenir un vendeur et un véhicule avant préparation SIV.'], 422);
        }

        if (in_array($purchase->getSivStatus(), [VOPurchase::SIV_STATUS_A_PREPARER, VOPurchase::SIV_STATUS_REJETEE, VOPurchase::SIV_STATUS_EXPIREE], true)) {
            $purchase->setSivStatus(VOPurchase::SIV_STATUS_EN_COURS);
        }

        $blockers = $this->documentService->getPurchaseSaleBlockers($purchase);

        $this->generatedDocumentService->archivePurchaseSivPreparation(
            $purchase,
            $this->getUser() instanceof User ? $this->getUser() : null,
            true,
        );
        $document = $this->em->getRepository(VODocument::class)->findOneBy([
            'voPurchase' => $purchase,
            'type' => VODocument::TYPE_DA_SIV,
        ]);
        $this->em->flush();

        $this->audit->log('prepare_siv_vo_purchase', 'vo_purchase', $purchase->getId(), json_encode([
            'sivStatus' => $purchase->getSivStatus(),
            'blockers' => $blockers,
        ]));

        return $this->json([
            'success' => true,
            'pdfGenerated' => true,
            'pdfUrl' => sprintf('/api/vo/purchases/%d/da-siv/pdf', $purchase->getId()),
            'document' => $this->serializer->normalize($document, null, ['groups' => 'vodoc:read']),
            'siv' => $this->documentService->getPurchaseSivSummary($purchase),
            'blockers' => $blockers,
            'ready' => $blockers === [],
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

    #[Route('/purchases/{id}/da-siv/pdf', methods: ['GET'])]
    public function downloadDaSivPdf(int $id): Response
    {
        $this->denyAccessUnlessGranted('ROLE_VO_MANAGER');
        $purchase = $this->em->getRepository(VOPurchase::class)->find($id);
        if (!$purchase) {
            return $this->json(['error' => 'Not found'], 404);
        }

        $blockers = $this->documentService->getPurchaseSaleBlockers($purchase);
        $filePath = $this->pdfService->generateDaSivPreparationPdf($purchase, $blockers);

        return new BinaryFileResponse($filePath, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="da-siv-' . $purchase->getId() . '.pdf"',
        ]);
    }

    #[Route('/purchases/{id}/mandat-immat/pdf', methods: ['GET'])]
    public function downloadPurchaseMandatImmatPdf(int $id, Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_VO_MANAGER');
        $purchase = $this->em->getRepository(VOPurchase::class)->find($id);
        if (!$purchase) {
            return $this->json(['error' => 'Not found'], 404);
        }

        $buyer = $request->query->getInt('buyerId') > 0
            ? $this->em->getRepository(Client::class)->find($request->query->getInt('buyerId'))
            : null;
        $filePath = $this->pdfService->generateMandatImmatriculationPdf($purchase, $buyer instanceof Client ? $buyer : null);

        return new BinaryFileResponse($filePath, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="mandat-immat-' . $purchase->getId() . '.pdf"',
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

    #[Route('/depots/{id}/mandat-immat/pdf', methods: ['GET'])]
    public function downloadDepotMandatImmatPdf(int $id, Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_VO_MANAGER');
        $depot = $this->em->getRepository(VODepotVente::class)->find($id);
        if (!$depot) {
            return $this->json(['error' => 'Not found'], 404);
        }

        $buyer = $request->query->getInt('buyerId') > 0
            ? $this->em->getRepository(Client::class)->find($request->query->getInt('buyerId'))
            : null;
        $filePath = $this->pdfService->generateMandatImmatriculationPdf($depot, $buyer instanceof Client ? $buyer : null);

        return new BinaryFileResponse($filePath, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="mandat-immat-depot-' . $depot->getId() . '.pdf"',
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

        $this->audit->log('restituer_vo_depot', 'vo_depot', $depot->getId(), json_encode([
            'notes' => !empty($body['notes']) ? substr((string) $body['notes'], 0, 120) : null,
        ]));

        $this->em->flush();

        return $this->json($this->normalizeDepotBase($depot));
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

    private function normalizeClientLite(?Client $client): ?array
    {
        if (!$client instanceof Client) {
            return null;
        }

        return [
            'id' => $client->getId(),
            'atelierId' => $client->getAtelierId(),
            'nom' => $client->getNom(),
            'prenom' => $client->getPrenom(),
            'telephone' => $client->getTelephone(),
            'email' => $client->getEmail(),
            'adresse' => $client->getAdresse(),
        ];
    }

    private function normalizeUserLite(?User $user): ?array
    {
        if (!$user instanceof User) {
            return null;
        }

        return [
            'id' => $user->getId(),
            'username' => $user->getUsername(),
            'nom' => method_exists($user, 'getNom') ? $user->getNom() : null,
            'prenom' => method_exists($user, 'getPrenom') ? $user->getPrenom() : null,
        ];
    }

    private function normalizeVehiculeLite(?Vehicule $vehicule): ?array
    {
        if (!$vehicule instanceof Vehicule) {
            return null;
        }

        return [
            'id' => $vehicule->getId(),
            'atelierId' => $vehicule->getAtelierId(),
            'plaque' => $vehicule->getPlaque(),
            'marque' => $vehicule->getMarque(),
            'modele' => $vehicule->getModele(),
            'annee' => $vehicule->getAnnee(),
            'cylindree' => $vehicule->getCylindree(),
            'typeMoto' => $vehicule->getTypeMoto(),
            'mileage' => $vehicule->getMileage(),
            'vin' => $vehicule->getVin(),
            'couleur' => $vehicule->getCouleur(),
            'datePremiereMiseEnCirculation' => $vehicule->getDatePremiereMiseEnCirculation()?->format('Y-m-d'),
        ];
    }

    private function normalizePurchaseBase(VOPurchase $purchase): array
    {
        return [
            'id' => $purchase->getId(),
            'atelierId' => $purchase->getAtelierId(),
            'vehicule' => $this->normalizeVehiculeLite($purchase->getVehicule()),
            'seller' => $this->normalizeClientLite($purchase->getSeller()),
            'expert' => $this->normalizeUserLite($purchase->getExpert()),
            'purchasePrice' => $purchase->getPurchasePrice(),
            'targetSalePrice' => $purchase->getTargetSalePrice(),
            'repairEstimates' => $purchase->getRepairEstimates(),
            'status' => $purchase->getStatus(),
            'purchaseDate' => $purchase->getPurchaseDate()?->format('Y-m-d'),
            'saleDate' => $purchase->getSaleDate()?->format('Y-m-d'),
            'notes' => $purchase->getNotes(),
            'sellerIdType' => $purchase->getSellerIdType(),
            'sellerIdNumber' => $purchase->getSellerIdNumber(),
            'sellerIdDate' => $purchase->getSellerIdDate()?->format('Y-m-d'),
            'nonGageDate' => $purchase->getNonGageDate()?->format('Y-m-d'),
            'controleTechniqueOk' => $purchase->getControleTechniqueOk(),
            'regimeTva' => $purchase->getRegimeTva(),
            'sivStatus' => $purchase->getSivStatus(),
            'sivReference' => $purchase->getSivReference(),
            'sivRecordedAt' => $purchase->getSivRecordedAt()?->format(DATE_ATOM),
            'sivNotes' => $purchase->getSivNotes(),
            'createdAt' => $purchase->getCreatedAt()->format(DATE_ATOM),
            'updatedAt' => $purchase->getUpdatedAt()->format(DATE_ATOM),
        ];
    }

    private function normalizeDepotBase(VODepotVente $depot): array
    {
        return [
            'id' => $depot->getId(),
            'atelierId' => $depot->getAtelierId(),
            'vehicule' => $this->normalizeVehiculeLite($depot->getVehicule()),
            'deposant' => $this->normalizeClientLite($depot->getDeposant()),
            'gestionnaire' => $this->normalizeUserLite($depot->getGestionnaire()),
            'prixVenteSouhaite' => $depot->getPrixVenteSouhaite(),
            'commissionType' => $depot->getCommissionType(),
            'commissionValeur' => $depot->getCommissionValeur(),
            'dateDebut' => $depot->getDateDebut()->format('Y-m-d'),
            'dateFin' => $depot->getDateFin()?->format('Y-m-d'),
            'dureeMandat' => $depot->getDureeMandat(),
            'status' => $depot->getStatus(),
            'conditionsRestitution' => $depot->getConditionsRestitution(),
            'assuranceInfo' => $depot->getAssuranceInfo(),
            'notes' => $depot->getNotes(),
            'deposantIdType' => $depot->getDeposantIdType(),
            'deposantIdNumber' => $depot->getDeposantIdNumber(),
            'deposantIdDate' => $depot->getDeposantIdDate()?->format('Y-m-d'),
            'prixVenteEffectif' => $depot->getPrixVenteEffectif(),
            'createdAt' => $depot->getCreatedAt()->format(DATE_ATOM),
            'updatedAt' => $depot->getUpdatedAt()->format(DATE_ATOM),
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

    private function activateDepotRecord(VODepotVente $depot): void
    {
        if (!$depot->getVehicule() || !$depot->getDeposant()) {
            throw new \InvalidArgumentException('Vehicule et deposant requis pour finaliser le dépôt-vente.');
        }

        $existingEntry = $this->em->getRepository(VOLivrePolice::class)->findOneBy(['voDepotVente' => $depot]);
        if (!$existingEntry instanceof VOLivrePolice) {
            $this->livrePoliceService->createEntryForDepotVente($depot);
            $this->em->flush();
        }

        $this->generatedDocumentService->archiveCompanionDocumentIfReady(
            $depot,
            $this->getUser() instanceof User ? $this->getUser() : null,
        );
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

    private function applyPurchaseSivPayload(VOPurchase $purchase, array $data): void
    {
        if (isset($data['sivStatus'])) {
            $purchase->setSivStatus((string) $data['sivStatus']);
        }
        if (array_key_exists('sivReference', $data)) {
            $purchase->setSivReference($this->nullableString($data['sivReference']));
        }
        if (array_key_exists('sivNotes', $data)) {
            $purchase->setSivNotes($this->nullableString($data['sivNotes']));
        }
        if (array_key_exists('sivRecordedAt', $data)) {
            $purchase->setSivRecordedAt(!empty($data['sivRecordedAt']) ? new \DateTime((string) $data['sivRecordedAt']) : null);
        }

        if ($purchase->isSivRegistered() && $purchase->getSivRecordedAt() === null) {
            $purchase->setSivRecordedAt(new \DateTime());
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
