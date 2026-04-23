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
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

#[Route('/api/vo')]
class VODepotController extends AbstractController
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
    ) {}

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
        $data['saleVerdict'] = $this->documentService->buildDepotSaleVerdict($depot);

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
        $documents = array_values(array_filter(
            $this->em->getRepository(VODocument::class)->findBy(['voDepotVente' => $depot], ['uploadedAt' => 'DESC']),
            static fn (VODocument $d): bool => VODocument::RETENTION_YEARS[$d->getType()] > 0,
        ));
        $livrePolice = $this->em->getRepository(VOLivrePolice::class)->findOneBy(['voDepotVente' => $depot]);
        $missingDocuments = $this->documentService->getMissingDocumentsDepot($depot);
        $legalChecklist = $this->documentService->buildDepotLegalChecklist($depot);
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
        $data['documents'] = $this->serializer->normalize($documents, null, ['groups' => 'vodoc:read']);
        $data['generatedDocuments'] = $this->companionWorkflowService->getGeneratedDocuments($depot);
        $data['companion'] = $this->buildCompanionData($depot, $documents, $companionSteps);
        $data['remisesEnEtat'] = array_map(fn ($campaign): array => $this->remiseEnEtatService->normalizeCampaign($campaign), $campaigns);
        $data['activeRemiseEnEtat'] = $activeCampaign ? $this->remiseEnEtatService->normalizeCampaign($activeCampaign) : null;
        $data['canCreateRemiseEnEtat'] = !($activeCampaign instanceof \App\Entity\VORemiseEnEtat);
        $data['refurbishmentBlockingSale'] = $activeCampaign?->isBlockingSale() ?? false;
        $extraSaleBlockers = [];
        if ($activeCampaign instanceof \App\Entity\VORemiseEnEtat) {
            $extraSaleBlockers[] = sprintf('Remise en etat VO "%s" non cloturee.', $activeCampaign->getTitre());
        }
        $saleVerdict = $this->documentService->buildDepotSaleVerdict($depot, $extraSaleBlockers, (bool) ($companionSteps['allComplete'] ?? false));
        $data['saleVerdict'] = $saleVerdict;
        $data['canSell'] = $depot->getStatus() === 'actif'
            && $saleVerdict['status'] === 'vendable';
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
        $depot->setDureeMandat($body['dureeMandat'] ?? $this->resolveDefaultMandatDuration());
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
                'error' => 'La vente est bloquée tant que le dossier légal / mandat n\'est pas régularisé.',
                'saleBlockers' => $saleBlockers,
            ], 422);
        }

        $body = $request->toArray();

        $buyer = $this->em->getRepository(Client::class)->find($body['buyerId'] ?? 0);
        if (!$buyer) {
            return $this->json(['error' => 'Acheteur requis'], 400);
        }

        $salePrice = (string) ($body['salePrice'] ?? $depot->getPrixVenteSouhaite());

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

    private function normalizeDepotBase(VODepotVente $depot): array
    {
        return [
            'id' => $depot->getId(),
            'atelierId' => $depot->getAtelierId(),
            'vehicule' => $this->entityNormalizer->normalizeVehiculeLite($depot->getVehicule()),
            'deposant' => $this->entityNormalizer->normalizeClientLite($depot->getDeposant()),
            'gestionnaire' => $this->entityNormalizer->normalizeUserLite($depot->getGestionnaire()),
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

    private function activateDepotRecord(VODepotVente $depot): void
    {
        if (!$depot->getVehicule() || !$depot->getDeposant()) {
            throw new \InvalidArgumentException('Vehicule et deposant requis pour finaliser le dépôt-vente.');
        }

        $existingEntry = $this->em->getRepository(VOLivrePolice::class)->findOneBy(['voDepotVente' => $depot]);
        if (!$existingEntry instanceof VOLivrePolice) {
            $this->livrePoliceService->createEntryForDepotVente($depot, 'depot_vente');
            $this->em->flush();
        }

        $this->generatedDocumentService->archiveCompanionDocumentIfReady(
            $depot,
            $this->getUser() instanceof User ? $this->getUser() : null,
        );
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

    private function resolveDefaultMandatDuration(): int
    {
        $atelierId = $this->resolveAtelierId();
        if ($atelierId <= 0) {
            return 90;
        }
        $config = $this->em->getRepository(\App\Entity\ConfigAtelier::class)->findOneBy(['atelierId' => $atelierId]);

        return $config?->getDureeDefautMandatJours() ?? 90;
    }
}
