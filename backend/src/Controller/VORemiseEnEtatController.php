<?php

namespace App\Controller;

use App\Entity\Prestation;
use App\Entity\User;
use App\Entity\VODepotVente;
use App\Entity\VOPurchase;
use App\Entity\VORemiseEnEtat;
use App\Entity\VORemiseEnEtatLigne;
use App\Entity\VORemiseEnEtatPiece;
use App\Service\AuditService;
use App\Service\PrestationCatalogService;
use App\Service\VORemiseEnEtatDocumentService;
use App\Service\VORemiseEnEtatService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/vo')]
class VORemiseEnEtatController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private VORemiseEnEtatService $service,
        private VORemiseEnEtatDocumentService $documentService,
        private PrestationCatalogService $catalogService,
        private AuditService $audit,
    ) {}

    #[Route('/purchases/{id}/remises-en-etat', methods: ['GET'])]
    public function listPurchaseCampaigns(int $id): JsonResponse
    {
        $this->assertViewAccess();

        $purchase = $this->em->getRepository(VOPurchase::class)->find($id);
        if (!$purchase) {
            return $this->json(['error' => 'Not found'], 404);
        }

        return $this->json($this->serializeCampaignCollection($purchase));
    }

    #[Route('/purchases/{id}/remises-en-etat', methods: ['POST'])]
    public function createPurchaseCampaign(int $id, Request $request): JsonResponse
    {
        $this->assertEditorAccess();

        $purchase = $this->em->getRepository(VOPurchase::class)->find($id);
        if (!$purchase) {
            return $this->json(['error' => 'Not found'], 404);
        }

        try {
            $campaign = $this->service->createCampaignForRecord($purchase, $this->getCurrentUser(), $this->parseBody($request));
            $this->em->flush();
            $this->audit->log('create_vo_remise_en_etat', 'vo_remise_en_etat', $campaign->getId(), json_encode([
                'sourceType' => 'purchase',
                'sourceId' => $purchase->getId(),
                'campaignIndex' => $campaign->getCampaignIndex(),
                ...$this->buildActorAuditContext(),
            ]));

            return $this->json($this->service->normalizeCampaign($campaign), 201);
        } catch (\InvalidArgumentException $exception) {
            return $this->json(['error' => $exception->getMessage()], 422);
        } catch (\DomainException $exception) {
            return $this->json(['error' => $exception->getMessage()], 409);
        }
    }

    #[Route('/depots/{id}/remises-en-etat', methods: ['GET'])]
    public function listDepotCampaigns(int $id): JsonResponse
    {
        $this->assertViewAccess();

        $depot = $this->em->getRepository(VODepotVente::class)->find($id);
        if (!$depot) {
            return $this->json(['error' => 'Not found'], 404);
        }

        return $this->json($this->serializeCampaignCollection($depot));
    }

    #[Route('/depots/{id}/remises-en-etat', methods: ['POST'])]
    public function createDepotCampaign(int $id, Request $request): JsonResponse
    {
        $this->assertEditorAccess();

        $depot = $this->em->getRepository(VODepotVente::class)->find($id);
        if (!$depot) {
            return $this->json(['error' => 'Not found'], 404);
        }

        try {
            $campaign = $this->service->createCampaignForRecord($depot, $this->getCurrentUser(), $this->parseBody($request));
            $this->em->flush();
            $this->audit->log('create_vo_remise_en_etat', 'vo_remise_en_etat', $campaign->getId(), json_encode([
                'sourceType' => 'depot',
                'sourceId' => $depot->getId(),
                'campaignIndex' => $campaign->getCampaignIndex(),
                ...$this->buildActorAuditContext(),
            ]));

            return $this->json($this->service->normalizeCampaign($campaign), 201);
        } catch (\InvalidArgumentException $exception) {
            return $this->json(['error' => $exception->getMessage()], 422);
        } catch (\DomainException $exception) {
            return $this->json(['error' => $exception->getMessage()], 409);
        }
    }

    #[Route('/remises-en-etat/queue', methods: ['GET'])]
    public function queue(): JsonResponse
    {
        $this->assertViewAccess();

        $qb = $this->em->getRepository(VORemiseEnEtat::class)->createQueryBuilder('r')
            ->where('r.status NOT IN (:finalStatuses)')
            ->setParameter('finalStatuses', VORemiseEnEtat::FINAL_STATUSES)
            ->orderBy('r.priority', 'DESC')
            ->addOrderBy('r.createdAt', 'ASC');

        $items = array_map(
            fn (VORemiseEnEtat $campaign): array => $this->service->normalizeQueueItem($campaign),
            $qb->getQuery()->getResult(),
        );

        return $this->json(['items' => $items, 'total' => count($items)]);
    }

    #[Route('/remises-en-etat/{id}', methods: ['GET'])]
    public function getCampaign(int $id): JsonResponse
    {
        $this->assertViewAccess();

        $campaign = $this->em->getRepository(VORemiseEnEtat::class)->find($id);
        if (!$campaign) {
            return $this->json(['error' => 'Not found'], 404);
        }

        return $this->json($this->service->normalizeCampaign($campaign));
    }

    #[Route('/remises-en-etat/{id}/pdf', methods: ['GET'])]
    public function downloadCampaignPdf(int $id): Response
    {
        $this->assertViewAccess();

        $campaign = $this->em->getRepository(VORemiseEnEtat::class)->find($id);
        if (!$campaign) {
            return $this->json(['error' => 'Not found'], 404);
        }

        $filePath = $this->documentService->generateLivePdf($campaign);

        return new BinaryFileResponse($filePath, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf('inline; filename="remise-en-etat-%d.pdf"', $campaign->getId()),
        ]);
    }

    #[Route('/remises-en-etat/{id}/document', methods: ['GET'])]
    public function downloadArchivedCampaignDocument(int $id): Response
    {
        $this->assertViewAccess();

        $campaign = $this->em->getRepository(VORemiseEnEtat::class)->find($id);
        if (!$campaign) {
            return $this->json(['error' => 'Not found'], 404);
        }

        $document = $this->documentService->getArchivedDocument($campaign);
        if (!$document) {
            return $this->json(['error' => 'Document archivé introuvable'], 404);
        }

        $projectDir = (string) $this->getParameter('kernel.project_dir');
        $uploadsDir = realpath($projectDir . '/public/uploads/vo');
        $filePath = realpath($projectDir . '/public' . $document->getFilePath());

        if (!$uploadsDir || !$filePath || !str_starts_with($filePath, $uploadsDir)) {
            return $this->json(['error' => 'Document introuvable'], 404);
        }

        return new BinaryFileResponse($filePath, 200, [
            'Content-Type' => $document->getMimeType(),
            'Content-Disposition' => 'inline; filename="' . $document->getOriginalFilename() . '"',
        ]);
    }

    #[Route('/remises-en-etat/{id}/sign', methods: ['POST'])]
    public function signCampaignDocument(int $id, Request $request): JsonResponse
    {
        $this->assertEditorAccess();

        $campaign = $this->em->getRepository(VORemiseEnEtat::class)->find($id);
        if (!$campaign) {
            return $this->json(['error' => 'Not found'], 404);
        }

        $data = $this->parseBody($request);
        $signature = trim((string) ($data['signature'] ?? ''));
        if ($signature === '' || !str_starts_with($signature, 'data:image/')) {
            return $this->json(['error' => 'Signature invalide'], 422);
        }

        try {
            $document = $this->documentService->signCampaignDocument(
                campaign: $campaign,
                signatureData: $signature,
                user: $this->getCurrentUser(),
                signedIp: $request->getClientIp(),
                signedUserAgent: $request->headers->get('User-Agent'),
            );
        } catch (\DomainException $exception) {
            return $this->json(['error' => $exception->getMessage()], 409);
        }

        $this->audit->log('sign_vo_remise_en_etat_document', 'vo_remise_en_etat', $campaign->getId(), json_encode([
            'documentId' => $document->getId(),
            'signedHash' => $campaign->getSignedHash(),
            ...$this->buildActorAuditContext(),
        ]));

        return $this->json($this->service->normalizeCampaign($campaign));
    }

    #[Route('/remises-en-etat/{id}', methods: ['PATCH'])]
    public function updateCampaign(int $id, Request $request): JsonResponse
    {
        $this->assertEditorAccess();

        $campaign = $this->em->getRepository(VORemiseEnEtat::class)->find($id);
        if (!$campaign) {
            return $this->json(['error' => 'Not found'], 404);
        }

        $data = $this->parseBody($request);
        $oldStatus = $campaign->getStatus();

        if ($campaign->isClosed() && isset($data['status']) && !in_array((string) $data['status'], VORemiseEnEtat::FINAL_STATUSES, true)) {
            return $this->json(['error' => 'Une campagne cloturee ou annulee ne peut pas etre reouverte. Creez une nouvelle campagne.'], 409);
        }

        try {
            if (isset($data['titre'])) $campaign->setTitre($this->requiredString($data['titre'], 'Titre requis'));
            if (isset($data['priority'])) $campaign->setPriority((string) $data['priority']);
            if (array_key_exists('diagnosticNotes', $data)) $campaign->setDiagnosticNotes($this->nullableString($data['diagnosticNotes']));
            if (array_key_exists('workshopNotes', $data)) $campaign->setWorkshopNotes($this->nullableString($data['workshopNotes']));
            if (array_key_exists('businessNotes', $data)) $campaign->setBusinessNotes($this->nullableString($data['businessNotes']));
            if (array_key_exists('plannedFor', $data)) $campaign->setPlannedFor(!empty($data['plannedFor']) ? new \DateTime((string) $data['plannedFor']) : null);

            if (isset($data['status'])) {
                $newStatus = (string) $data['status'];
                $this->assertStatusTransitionAccess($newStatus);
                $campaign->setStatus($newStatus);

                if ($newStatus === VORemiseEnEtat::STATUS_VALIDEE && $oldStatus !== VORemiseEnEtat::STATUS_VALIDEE) {
                    $campaign->setValidatedAt(new \DateTime());
                    $campaign->setValidatedBy($this->getCurrentUser());
                }
                if ($newStatus === VORemiseEnEtat::STATUS_EN_COURS && $campaign->getStartedAt() === null) {
                    $campaign->setStartedAt(new \DateTime());
                }
                if ($newStatus === VORemiseEnEtat::STATUS_TERMINEE && $campaign->getCompletedAt() === null) {
                    $campaign->setCompletedAt(new \DateTime());
                }
                if ($newStatus === VORemiseEnEtat::STATUS_CLOTUREE) {
                    if ($campaign->getCompletedAt() === null) {
                        $campaign->setCompletedAt(new \DateTime());
                    }
                    if ($campaign->getClosedAt() === null) {
                        $campaign->setClosedAt(new \DateTime());
                    }
                }
            }
        } catch (\InvalidArgumentException $exception) {
            return $this->json(['error' => $exception->getMessage()], 422);
        }

        $this->em->flush();

        if ($campaign->getStatus() === VORemiseEnEtat::STATUS_CLOTUREE) {
            $this->documentService->archiveFallbackDocumentIfMissing($campaign, $this->getCurrentUser());
        }

        $this->audit->log('update_vo_remise_en_etat', 'vo_remise_en_etat', $campaign->getId(), json_encode([
            'oldStatus' => $oldStatus,
            'newStatus' => $campaign->getStatus(),
            'priority' => $campaign->getPriority(),
            ...$this->buildActorAuditContext(),
        ]));

        return $this->json($this->service->normalizeCampaign($campaign));
    }

    #[Route('/remises-en-etat/{id}/prestations-applicables', methods: ['GET'])]
    public function applicablePrestations(int $id): JsonResponse
    {
        $this->assertViewAccess();

        $campaign = $this->em->getRepository(VORemiseEnEtat::class)->find($id);
        if (!$campaign) {
            return $this->json(['error' => 'Not found'], 404);
        }

        $vehicle = $campaign->getSourceVehicule();
        if (!$vehicle) {
            return $this->json(['items' => []]);
        }

        $items = array_map(function (array $entry): array {
            /** @var Prestation $prestation */
            $prestation = $entry['prestation'];

            return [
                'prestationId' => $prestation->getId(),
                'code' => $prestation->getCode(),
                'nom' => $prestation->getNom(),
                'prixHt' => $entry['prix_ht'],
                'prixTtc' => $entry['prix_ttc'],
                'tempsMinutes' => $entry['temps_minutes'],
                'mode' => $entry['mode'],
                'source' => $entry['source'],
            ];
        }, $this->catalogService->getApplicablePrestations($vehicle));

        return $this->json(['items' => $items]);
    }

    #[Route('/remises-en-etat/{id}/lignes', methods: ['POST'])]
    public function addLine(int $id, Request $request): JsonResponse
    {
        $this->assertEditorAccess();

        $campaign = $this->em->getRepository(VORemiseEnEtat::class)->find($id);
        if (!$campaign) {
            return $this->json(['error' => 'Not found'], 404);
        }

        $vehicle = $campaign->getSourceVehicule();
        if (!$vehicle) {
            return $this->json(['error' => 'Vehicule du dossier introuvable'], 422);
        }

        $data = $this->parseBody($request);
        $prestation = $this->em->getRepository(Prestation::class)->find((int) ($data['prestationId'] ?? 0));
        if (!$prestation) {
            return $this->json(['error' => 'Prestation introuvable'], 404);
        }

        $quantity = max(1, (int) ($data['quantity'] ?? 1));
        $pricing = $this->catalogService->calculatePrice($prestation, $vehicle, $campaign->getAtelierId());

        $line = new VORemiseEnEtatLigne();
        $line->setRemiseEnEtat($campaign);
        $line->setPrestation($prestation);
        $line->setLibelle($prestation->getNom());
        $line->setQuantity($quantity);
        $line->setEstimatedUnitHt((string) $pricing['prix_ht']);
        $line->setEstimatedMinutes(((int) $pricing['temps_minutes']) * $quantity);
        $line->setNotes($this->nullableString($data['notes'] ?? null));
        $line->setSortOrder((int) ($data['sortOrder'] ?? count($campaign->getLignes()) + 1));

        $campaign->addLigne($line);
        $this->em->persist($line);
        $this->em->flush();
        $this->audit->log('add_vo_remise_en_etat_ligne', 'vo_remise_en_etat', $campaign->getId(), json_encode([
            'lineId' => $line->getId(),
            'prestationId' => $prestation->getId(),
            'quantity' => $line->getQuantity(),
            ...$this->buildActorAuditContext(),
        ]));

        return $this->json($this->service->normalizeCampaign($campaign), 201);
    }

    #[Route('/remises-en-etat/lignes/{id}', methods: ['PATCH'])]
    public function updateLine(int $id, Request $request): JsonResponse
    {
        $this->assertEditorAccess();

        $line = $this->em->getRepository(VORemiseEnEtatLigne::class)->find($id);
        if (!$line) {
            return $this->json(['error' => 'Not found'], 404);
        }

        $data = $this->parseBody($request);
        if (isset($data['quantity'])) $line->setQuantity((int) $data['quantity']);
        if (isset($data['estimatedUnitHt'])) $line->setEstimatedUnitHt($this->decimalString($data['estimatedUnitHt']));
        if (array_key_exists('actualTotalHt', $data)) $line->setActualTotalHt(!empty($data['actualTotalHt']) ? $this->decimalString($data['actualTotalHt']) : null);
        if (array_key_exists('actualMinutes', $data)) $line->setActualMinutes($data['actualMinutes'] !== null && $data['actualMinutes'] !== '' ? (int) $data['actualMinutes'] : null);
        if (isset($data['status'])) $line->setStatus((string) $data['status']);
        if (array_key_exists('notes', $data)) $line->setNotes($this->nullableString($data['notes']));
        if (isset($data['sortOrder'])) $line->setSortOrder((int) $data['sortOrder']);

        $this->em->flush();
        $this->audit->log('update_vo_remise_en_etat_ligne', 'vo_remise_en_etat', $line->getRemiseEnEtat()->getId(), json_encode([
            'lineId' => $line->getId(),
            'status' => $line->getStatus(),
            'quantity' => $line->getQuantity(),
            ...$this->buildActorAuditContext(),
        ]));

        return $this->json($this->service->normalizeCampaign($line->getRemiseEnEtat()));
    }

    #[Route('/remises-en-etat/lignes/{id}', methods: ['DELETE'])]
    public function deleteLine(int $id): JsonResponse
    {
        $this->assertEditorAccess();

        $line = $this->em->getRepository(VORemiseEnEtatLigne::class)->find($id);
        if (!$line) {
            return $this->json(['error' => 'Not found'], 404);
        }

        $campaign = $line->getRemiseEnEtat();
        $lineId = $line->getId();
        $this->em->remove($line);
        $this->em->flush();
        $this->audit->log('delete_vo_remise_en_etat_ligne', 'vo_remise_en_etat', $campaign->getId(), json_encode([
            'lineId' => $lineId,
            ...$this->buildActorAuditContext(),
        ]));

        return $this->json($this->service->normalizeCampaign($campaign));
    }

    #[Route('/remises-en-etat/{id}/pieces', methods: ['POST'])]
    public function addPiece(int $id, Request $request): JsonResponse
    {
        $this->assertEditorAccess();

        $campaign = $this->em->getRepository(VORemiseEnEtat::class)->find($id);
        if (!$campaign) {
            return $this->json(['error' => 'Not found'], 404);
        }

        $data = $this->parseBody($request);
        try {
            $piece = new VORemiseEnEtatPiece();
            $piece->setRemiseEnEtat($campaign);
            $piece->setLibelle($this->requiredString($data['libelle'] ?? null, 'Libelle requis'));
            $piece->setReference($this->nullableString($data['reference'] ?? null));
            $piece->setQuantity((int) ($data['quantity'] ?? 1));
            $piece->setSupplier($this->nullableString($data['supplier'] ?? null));
            $piece->setEstimatedUnitCostHt($this->decimalString($data['estimatedUnitCostHt'] ?? '0.00'));
            $piece->setStatus((string) ($data['status'] ?? VORemiseEnEtatPiece::STATUS_A_COMMANDER));
            $piece->setNotes($this->nullableString($data['notes'] ?? null));
        } catch (\InvalidArgumentException $exception) {
            return $this->json(['error' => $exception->getMessage()], 422);
        }

        $campaign->addPiece($piece);
        $this->em->persist($piece);
        $this->em->flush();
        $this->audit->log('add_vo_remise_en_etat_piece', 'vo_remise_en_etat', $campaign->getId(), json_encode([
            'pieceId' => $piece->getId(),
            'status' => $piece->getStatus(),
            'quantity' => $piece->getQuantity(),
            ...$this->buildActorAuditContext(),
        ]));

        return $this->json($this->service->normalizeCampaign($campaign), 201);
    }

    #[Route('/remises-en-etat/pieces/{id}', methods: ['PATCH'])]
    public function updatePiece(int $id, Request $request): JsonResponse
    {
        $this->assertEditorAccess();

        $piece = $this->em->getRepository(VORemiseEnEtatPiece::class)->find($id);
        if (!$piece) {
            return $this->json(['error' => 'Not found'], 404);
        }

        $data = $this->parseBody($request);
        try {
            if (isset($data['libelle'])) $piece->setLibelle($this->requiredString($data['libelle'], 'Libelle requis'));
            if (array_key_exists('reference', $data)) $piece->setReference($this->nullableString($data['reference']));
            if (isset($data['quantity'])) $piece->setQuantity((int) $data['quantity']);
            if (array_key_exists('supplier', $data)) $piece->setSupplier($this->nullableString($data['supplier']));
            if (isset($data['estimatedUnitCostHt'])) $piece->setEstimatedUnitCostHt($this->decimalString($data['estimatedUnitCostHt']));
            if (array_key_exists('actualTotalCostHt', $data)) $piece->setActualTotalCostHt(!empty($data['actualTotalCostHt']) ? $this->decimalString($data['actualTotalCostHt']) : null);
            if (isset($data['status'])) $piece->setStatus((string) $data['status']);
            if (array_key_exists('notes', $data)) $piece->setNotes($this->nullableString($data['notes']));
        } catch (\InvalidArgumentException $exception) {
            return $this->json(['error' => $exception->getMessage()], 422);
        }

        $this->em->flush();
        $this->audit->log('update_vo_remise_en_etat_piece', 'vo_remise_en_etat', $piece->getRemiseEnEtat()->getId(), json_encode([
            'pieceId' => $piece->getId(),
            'status' => $piece->getStatus(),
            'quantity' => $piece->getQuantity(),
            ...$this->buildActorAuditContext(),
        ]));

        return $this->json($this->service->normalizeCampaign($piece->getRemiseEnEtat()));
    }

    #[Route('/remises-en-etat/pieces/{id}', methods: ['DELETE'])]
    public function deletePiece(int $id): JsonResponse
    {
        $this->assertEditorAccess();

        $piece = $this->em->getRepository(VORemiseEnEtatPiece::class)->find($id);
        if (!$piece) {
            return $this->json(['error' => 'Not found'], 404);
        }

        $campaign = $piece->getRemiseEnEtat();
        $pieceId = $piece->getId();
        $this->em->remove($piece);
        $this->em->flush();
        $this->audit->log('delete_vo_remise_en_etat_piece', 'vo_remise_en_etat', $campaign->getId(), json_encode([
            'pieceId' => $pieceId,
            ...$this->buildActorAuditContext(),
        ]));

        return $this->json($this->service->normalizeCampaign($campaign));
    }

    private function serializeCampaignCollection(VOPurchase|VODepotVente $record): array
    {
        $items = array_map(
            fn (VORemiseEnEtat $campaign): array => $this->service->normalizeCampaign($campaign),
            $this->service->getCampaignsForRecord($record),
        );
        $active = $this->service->getActiveCampaignForRecord($record);

        return [
            'items' => $items,
            'activeCampaignId' => $active?->getId(),
            'canCreate' => !($active instanceof VORemiseEnEtat),
        ];
    }

    private function assertViewAccess(): void
    {
        if (!$this->isGranted('ROLE_VO_MANAGER') && !$this->isGranted('ROLE_RECEPTIONNAIRE') && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException();
        }
    }

    private function assertEditorAccess(): void
    {
        if (!$this->isGranted('ROLE_VO_MANAGER') && !$this->isGranted('ROLE_RECEPTIONNAIRE') && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException();
        }
    }

    private function assertStatusTransitionAccess(string $status): void
    {
        if (!in_array($status, [
            VORemiseEnEtat::STATUS_VALIDEE,
            VORemiseEnEtat::STATUS_PIECES_A_COMMANDER,
            VORemiseEnEtat::STATUS_EN_ATTENTE_PIECES,
            VORemiseEnEtat::STATUS_PLANIFIEE_ATELIER,
            VORemiseEnEtat::STATUS_EN_COURS,
            VORemiseEnEtat::STATUS_TERMINEE,
            VORemiseEnEtat::STATUS_CLOTUREE,
        ], true)) {
            return;
        }

        if ($status === VORemiseEnEtat::STATUS_CLOTUREE) {
            if (!$this->canCloseCampaign()) {
                throw $this->createAccessDeniedException('Cloture atelier requise pour ce changement de statut.');
            }

            return;
        }

        if (!$this->isGranted('ROLE_RECEPTIONNAIRE') && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('Validation atelier requise pour ce changement de statut.');
        }
    }

    private function canCloseCampaign(): bool
    {
        if ($this->isGranted('ROLE_SUPER_ADMIN')) {
            return true;
        }

        $roleMetierCode = $this->getCurrentUser()?->getRoleMetier()?->getCode();

        if ($roleMetierCode === 'responsable_magasin') {
            return false;
        }

        if (in_array($roleMetierCode, ['responsable_atelier', 'receptionniste'], true)) {
            return true;
        }

        if ($this->isGranted('ROLE_RECEPTIONNAIRE')) {
            return true;
        }

        return $this->isGranted('ROLE_ADMIN') && $roleMetierCode === null;
    }

    private function parseBody(Request $request): array
    {
        $content = trim((string) $request->getContent());
        if ($content === '') {
            return [];
        }

        return json_decode($content, true) ?? [];
    }

    private function requiredString(mixed $value, string $message): string
    {
        $normalized = $this->nullableString($value);
        if ($normalized === null) {
            throw new \InvalidArgumentException($message);
        }

        return $normalized;
    }

    private function nullableString(mixed $value): ?string
    {
        $normalized = trim((string) $value);
        return $normalized !== '' ? $normalized : null;
    }

    private function decimalString(mixed $value): string
    {
        $normalized = str_replace(',', '.', trim((string) $value));
        if ($normalized === '' || !is_numeric($normalized)) {
            return '0.00';
        }

        return number_format((float) $normalized, 2, '.', '');
    }

    private function getCurrentUser(): ?User
    {
        $user = $this->getUser();
        return $user instanceof User ? $user : null;
    }

    private function buildActorAuditContext(): array
    {
        $user = $this->getCurrentUser();

        return [
            'actorUserId' => $user?->getId(),
            'actorLegacyRole' => $user?->getRole(),
            'actorRoleMetier' => $user?->getRoleMetier()?->getCode(),
        ];
    }
}