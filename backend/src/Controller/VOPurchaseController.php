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
use App\Infrastructure\EntityNormalizer;
use App\Infrastructure\InputNormalizer;
use App\Service\AuditService;
use App\Service\CurrentAtelierResolver;
use App\Service\PdfService;
use App\Service\VOCompanionWorkflowService;
use App\Service\VODocumentService;
use App\Service\VOGeneratedDocumentService;
use App\Service\VOLivrePoliceService;
use App\Service\VOMarginService;
use App\Service\VONumberingService;
use App\Service\VORemiseEnEtatService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Workflow\WorkflowInterface;

#[Route('/api/vo')]
class VOPurchaseController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private PdfService $pdfService,
        private VOMarginService $marginService,
        private VOLivrePoliceService $livrePoliceService,
        private VODocumentService $documentService,
        private VOCompanionWorkflowService $companionWorkflowService,
        private VOGeneratedDocumentService $generatedDocumentService,
        private SerializerInterface&NormalizerInterface $serializer,
        private VONumberingService $numberingService,
        private VORemiseEnEtatService $remiseEnEtatService,
        private AuditService $audit,
        private CurrentAtelierResolver $currentAtelierResolver,
        private InputNormalizer $inputNormalizer,
        private EntityNormalizer $entityNormalizer,
        #[Target('vo_purchase')]
        private WorkflowInterface $voPurchaseWorkflow,
    ) {}

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
        $data['saleVerdict'] = $this->documentService->buildPurchaseSaleVerdict($purchase);

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
        $tokenUpdated = $this->companionWorkflowService->ensureToken($purchase);
        $documents = array_values(array_filter(
            $this->em->getRepository(VODocument::class)->findBy(['voPurchase' => $purchase], ['uploadedAt' => 'DESC']),
            static fn (VODocument $d): bool => VODocument::RETENTION_YEARS[$d->getType()] > 0,
        ));
        $livrePolice = $this->em->getRepository(VOLivrePolice::class)->findOneBy(['voPurchase' => $purchase]);
        $companionSteps = $this->companionWorkflowService->buildSteps($purchase, $documents);
        $incompleteCompanionSteps = $this->extractIncompleteCompanionSteps($companionSteps);
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
        $data['canConfirm'] = count($confirmationMissingDocuments) === 0
            && $incompleteCompanionSteps === []
            && $purchase->getStatus() === 'brouillon';
        $data['legalChecklist'] = $legalChecklist;
        $data['siv'] = $this->documentService->getPurchaseSivSummary($purchase);
        $data['documents'] = $this->serializer->normalize($documents, null, ['groups' => 'vodoc:read']);
        $data['generatedDocuments'] = $this->companionWorkflowService->getGeneratedDocuments($purchase);
        $data['companion'] = $this->buildCompanionData($purchase, $documents, $companionSteps);
        $data['remisesEnEtat'] = array_map(fn ($campaign): array => $this->remiseEnEtatService->normalizeCampaign($campaign), $campaigns);
        $data['activeRemiseEnEtat'] = $activeCampaign ? $this->remiseEnEtatService->normalizeCampaign($activeCampaign) : null;
        $data['canCreateRemiseEnEtat'] = !($activeCampaign instanceof \App\Entity\VORemiseEnEtat);
        $data['refurbishmentBlockingSale'] = $activeCampaign?->isBlockingSale() ?? false;
        $extraSaleBlockers = [];
        if ($activeCampaign instanceof \App\Entity\VORemiseEnEtat) {
            $extraSaleBlockers[] = sprintf('Remise en etat VO "%s" non cloturee.', $activeCampaign->getTitre());
        }
        $saleVerdict = $this->documentService->buildPurchaseSaleVerdict($purchase, $extraSaleBlockers);
        $data['saleVerdict'] = $saleVerdict;
        $data['canSell'] = in_array($purchase->getStatus(), ['en_stock', 'en_vente', 'reserve'], true)
            && $saleVerdict['status'] === 'vendable';
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

    #[Route('/purchases/{id}/confirm', methods: ['POST'])]
    public function confirmPurchase(int $id, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_VO_MANAGER');
        $purchase = $this->em->getRepository(VOPurchase::class)->find($id);
        if (!$purchase) {
            return $this->json(['error' => 'Not found'], 404);
        }

        $body = $request->toArray();
        $modePaiement = $body['modePaiement'] ?? null;
        if (!$modePaiement) {
            return $this->json(['error' => 'Le mode de paiement est obligatoire pour enregistrer le rachat au Livre de Police.', 'field' => 'modePaiement'], 422);
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
            $payload = $this->inTransaction(function () use ($purchase, $modePaiement, $body) {
                $entry = $this->livrePoliceService->createEntryForPurchase(
                    $purchase,
                    $modePaiement,
                    $body['numeroCheque'] ?? null,
                    $body['nomBanque'] ?? null,
                );
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

        if ($transition === 'mettre_en_vente') {
            $verdict = $this->documentService->buildPurchaseSaleVerdict($purchase);
            if ($verdict['status'] !== 'vendable') {
                return $this->json([
                    'error' => 'Le véhicule ne peut pas être mis en vente.',
                    'saleVerdict' => $verdict,
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
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
                'error' => 'La vente est bloquée tant que le dossier légal / SIV n\'est pas régularisé.',
                'saleBlockers' => $saleBlockers,
            ], 422);
        }

        $body = $request->toArray();

        $buyer = $this->em->getRepository(Client::class)->find($body['buyerId'] ?? 0);
        if (!$buyer) {
            return $this->json(['error' => 'Acheteur requis'], 400);
        }

        $salePrice = (string) ($body['salePrice'] ?? $purchase->getTargetSalePrice());

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
                    $this->livrePoliceService->recordSale(
                        $lpEntry,
                        $buyer,
                        $salePrice,
                        $body['modePaiementVente'] ?? 'cb',
                        null,
                        $body['numeroChequeVente'] ?? null,
                        $body['nomBanqueVente'] ?? null,
                    );
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

    #[Route('/factures', methods: ['GET'])]
    public function listFactures(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_VO_MANAGER');

        $qb = $this->em->getRepository(VOFacture::class)->createQueryBuilder('f')
            ->orderBy('f.createdAt', 'DESC');

        $data = $this->serializer->normalize($qb->getQuery()->getResult(), null, ['groups' => 'vofacture:read']);

        return $this->json($data);
    }

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

    #[Route('/purchases/{id}/cerfa-cession-achat/pdf', methods: ['GET'])]
    public function downloadPurchaseCerfaCessionPdf(int $id): Response
    {
        $this->denyAccessUnlessGranted('ROLE_VO_MANAGER');
        $purchase = $this->em->getRepository(VOPurchase::class)->find($id);
        if (!$purchase) {
            return $this->json(['error' => 'Not found'], 404);
        }

        $filePath = $this->pdfService->generateCerfaCessionAchatPdf($purchase);

        return new BinaryFileResponse($filePath, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="cerfa-cession-achat-' . $purchase->getId() . '.pdf"',
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

    private function normalizePurchaseBase(VOPurchase $purchase): array
    {
        return [
            'id' => $purchase->getId(),
            'atelierId' => $purchase->getAtelierId(),
            'vehicule' => $this->entityNormalizer->normalizeVehiculeLite($purchase->getVehicule()),
            'seller' => $this->entityNormalizer->normalizeClientLite($purchase->getSeller()),
            'expert' => $this->entityNormalizer->normalizeUserLite($purchase->getExpert()),
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

    private function applyPurchaseSivPayload(VOPurchase $purchase, array $data): void
    {
        if (isset($data['sivStatus'])) {
            $purchase->setSivStatus((string) $data['sivStatus']);
        }
        if (array_key_exists('sivReference', $data)) {
            $purchase->setSivReference($this->inputNormalizer->nullableString($data['sivReference']));
        }
        if (array_key_exists('sivNotes', $data)) {
            $purchase->setSivNotes($this->inputNormalizer->nullableString($data['sivNotes']));
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
            $vehicule->setMarque($this->inputNormalizer->nullableString($data['marque']));
        }
        if (isset($data['modele'])) {
            $vehicule->setModele($this->inputNormalizer->nullableString($data['modele']));
        }
        if (isset($data['vin'])) {
            $vin = $this->inputNormalizer->nullableString($data['vin']);
            $vehicule->setVin($vin ? strtoupper(substr($vin, 0, 17)) : null);
        }
        if (isset($data['annee'])) {
            $annee = (int) $data['annee'];
            $vehicule->setAnnee($annee > 0 ? $annee : null);
        }
        if (isset($data['cylindree'])) {
            $vehicule->setCylindree($this->inputNormalizer->nullableString($data['cylindree']));
        }
        if (isset($data['type_moto'])) {
            $vehicule->setTypeMoto($this->inputNormalizer->nullableString($data['type_moto']));
        }
        if (isset($data['plaque'])) {
            $plaque = $this->inputNormalizer->nullableString($data['plaque']);
            if ($plaque !== null) {
                $vehicule->setPlaque($plaque);
            }
        }
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
