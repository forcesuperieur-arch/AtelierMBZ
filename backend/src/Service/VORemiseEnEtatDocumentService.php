<?php

namespace App\Service;

use App\Entity\User;
use App\Entity\VODocument;
use App\Entity\VORemiseEnEtat;
use App\Entity\VORemiseEnEtatLigne;
use App\Entity\VORemiseEnEtatPiece;
use Doctrine\ORM\EntityManagerInterface;

class VORemiseEnEtatDocumentService
{
    public function __construct(
        private EntityManagerInterface $em,
        private PdfService $pdfService,
        private VODocumentService $documentService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function normalizeDocumentState(VORemiseEnEtat $campaign): array
    {
        $currentSnapshot = $this->buildCurrentSnapshot($campaign);
        $currentHash = $this->computeHash($currentSnapshot);
        $signed = $campaign->hasSignedDocument();
        $signedHash = $campaign->getSignedHash();
        $archivedDocument = $this->getArchivedDocument($campaign);

        return [
            'canSign' => !$campaign->isClosed() && !$signed,
            'signed' => $signed,
            'signedAt' => $campaign->getSignedAt()?->format(DATE_ATOM),
            'signedBy' => $this->normalizeUser($campaign->getSignedBy()),
            'signedHash' => $signedHash,
            'currentHash' => $currentHash,
            'outdatedSinceSignature' => $signed && $signedHash !== null && $signedHash !== $currentHash,
            'livePdfUrl' => sprintf('/api/vo/remises-en-etat/%d/pdf', $campaign->getId()),
            'archivedDocument' => $archivedDocument ? [
                'id' => $archivedDocument->getId(),
                'type' => $archivedDocument->getType(),
                'originalFilename' => $archivedDocument->getOriginalFilename(),
                'uploadedAt' => $archivedDocument->getUploadedAt()->format(DATE_ATOM),
                'url' => sprintf('/api/vo/remises-en-etat/%d/document', $campaign->getId()),
                'downloadPath' => $archivedDocument->getDownloadPath(),
            ] : null,
        ];
    }

    public function generateLivePdf(VORemiseEnEtat $campaign): string
    {
        return $this->pdfService->generateVoRemiseEnEtatPdf($this->buildPdfPayload($campaign, false), $campaign->getAtelierId());
    }

    public function signCampaignDocument(
        VORemiseEnEtat $campaign,
        string $signatureData,
        ?User $user = null,
        ?string $signedIp = null,
        ?string $signedUserAgent = null,
    ): VODocument {
        if ($campaign->isClosed()) {
            throw new \DomainException('Une campagne cloturee ou annulee ne peut plus etre signee.');
        }

        if ($campaign->hasSignedDocument()) {
            $existingDocument = $this->getArchivedDocument($campaign);
            if ($existingDocument instanceof VODocument) {
                throw new \DomainException('Le document de remise en etat est deja signe.');
            }

            $document = $this->archiveDocument($campaign, true, $user);
            $this->em->flush();

            return $document;
        }

        $now = new \DateTime();
        $snapshot = $this->buildCurrentSnapshot($campaign);

        $campaign
            ->setSignatureData($signatureData)
            ->setSignedSnapshot($snapshot)
            ->setSignedHash($this->computeHash($snapshot))
            ->setSignedBy($user)
            ->setSignedAt($now)
            ->setSignedIp($signedIp)
            ->setSignedUserAgent($signedUserAgent);

        if (in_array($campaign->getStatus(), [
            VORemiseEnEtat::STATUS_A_CHIFFRER,
            VORemiseEnEtat::STATUS_A_VALIDER,
        ], true)) {
            $campaign->setStatus(VORemiseEnEtat::STATUS_VALIDEE);
        }

        if ($campaign->getValidatedAt() === null) {
            $campaign->setValidatedAt($now);
        }

        if ($campaign->getValidatedBy() === null) {
            $campaign->setValidatedBy($user);
        }

        $this->em->flush();

        $document = $this->archiveDocument($campaign, true, $user);
        $this->em->flush();

        return $document;
    }

    public function archiveFallbackDocumentIfMissing(VORemiseEnEtat $campaign, ?User $user = null): ?VODocument
    {
        if ($this->getArchivedDocument($campaign) instanceof VODocument) {
            return null;
        }

        $document = $this->archiveDocument($campaign, $campaign->hasSignedDocument(), $user);
        $this->em->flush();

        return $document;
    }

    public function getArchivedDocument(VORemiseEnEtat $campaign): ?VODocument
    {
        return $this->em->getRepository(VODocument::class)->findOneBy([
            'type' => VODocument::TYPE_REMISE_EN_ETAT,
            'voRemiseEnEtat' => $campaign,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function buildCurrentSnapshot(VORemiseEnEtat $campaign): array
    {
        $vehicle = $campaign->getSourceVehicule();
        $counterparty = $campaign->getVoPurchase()?->getSeller() ?? $campaign->getVoDepotVente()?->getDeposant();

        $lines = iterator_to_array($campaign->getLignes());
        usort($lines, static fn (VORemiseEnEtatLigne $left, VORemiseEnEtatLigne $right): int => [$left->getSortOrder(), $left->getId() ?? 0] <=> [$right->getSortOrder(), $right->getId() ?? 0]);

        $pieces = iterator_to_array($campaign->getPieces());
        usort($pieces, static fn (VORemiseEnEtatPiece $left, VORemiseEnEtatPiece $right): int => ($left->getId() ?? 0) <=> ($right->getId() ?? 0));

        return [
            'campaign' => [
                'id' => $campaign->getId(),
                'index' => $campaign->getCampaignIndex(),
                'label' => sprintf('REVO-%d-%d', $campaign->getSourceId() ?? 0, $campaign->getCampaignIndex()),
                'title' => $campaign->getTitre(),
                'status' => $campaign->getStatus(),
                'priority' => $campaign->getPriority(),
                'sourceType' => $campaign->getSourceType(),
                'sourceId' => $campaign->getSourceId(),
                'requestedAt' => $campaign->getRequestedAt()->format(DATE_ATOM),
                'validatedAt' => $campaign->getValidatedAt()?->format(DATE_ATOM),
                'plannedFor' => $campaign->getPlannedFor()?->format(DATE_ATOM),
                'startedAt' => $campaign->getStartedAt()?->format(DATE_ATOM),
                'completedAt' => $campaign->getCompletedAt()?->format(DATE_ATOM),
                'closedAt' => $campaign->getClosedAt()?->format(DATE_ATOM),
                'requestedBy' => $this->normalizeUser($campaign->getRequestedBy()),
                'validatedBy' => $this->normalizeUser($campaign->getValidatedBy()),
            ],
            'record' => [
                'typeLabel' => $campaign->getSourceType() === 'purchase' ? 'Rachat' : 'Depot-vente',
                'reference' => sprintf('%s #%d', $campaign->getSourceType() === 'purchase' ? 'Rachat' : 'Depot-vente', $campaign->getSourceId() ?? 0),
                'counterparty' => [
                    'prenom' => $counterparty?->getPrenom(),
                    'nom' => $counterparty?->getNom(),
                    'telephone' => $counterparty?->getTelephone(),
                    'email' => $counterparty?->getEmail(),
                ],
            ],
            'vehicle' => [
                'plaque' => $vehicle?->getPlaque(),
                'marque' => $vehicle?->getMarque(),
                'modele' => $vehicle?->getModele(),
                'vin' => $vehicle?->getVin(),
                'annee' => $vehicle?->getAnnee(),
                'mileage' => $vehicle?->getMileage(),
                'cylindree' => $vehicle?->getCylindree(),
                'categorieNom' => $vehicle?->getCategorie()?->getNom(),
                'typeMoto' => $vehicle?->getTypeMoto(),
            ],
            'notes' => [
                'diagnostic' => $campaign->getDiagnosticNotes(),
                'workshop' => $campaign->getWorkshopNotes(),
                'business' => $campaign->getBusinessNotes(),
            ],
            'summary' => [
                'estimatedMoCost' => $campaign->getEstimatedMoCost(),
                'estimatedPartsCost' => $campaign->getEstimatedPartsCost(),
                'estimatedTotalCost' => $campaign->getEstimatedTotalCost(),
                'actualMoCost' => $campaign->getActualMoCost(),
                'actualPartsCost' => $campaign->getActualPartsCost(),
                'actualTotalCost' => $campaign->getActualTotalCost(),
                'pendingPiecesCount' => $campaign->getPendingPiecesCount(),
            ],
            'lines' => array_map(function (VORemiseEnEtatLigne $line): array {
                return [
                    'id' => $line->getId(),
                    'prestationCode' => $line->getPrestation()?->getCode(),
                    'libelle' => $line->getLibelle(),
                    'quantity' => $line->getQuantity(),
                    'estimatedUnitHt' => $this->normalizeDecimal($line->getEstimatedUnitHt()),
                    'estimatedTotalHt' => $this->normalizeDecimal($line->getEstimatedTotalHt()),
                    'actualTotalHt' => $line->getActualTotalHt() !== null ? $this->normalizeDecimal($line->getActualTotalHt()) : null,
                    'estimatedMinutes' => $line->getEstimatedMinutes(),
                    'actualMinutes' => $line->getActualMinutes(),
                    'status' => $line->getStatus(),
                    'notes' => $line->getNotes(),
                    'sortOrder' => $line->getSortOrder(),
                ];
            }, $lines),
            'pieces' => array_map(function (VORemiseEnEtatPiece $piece): array {
                return [
                    'id' => $piece->getId(),
                    'libelle' => $piece->getLibelle(),
                    'reference' => $piece->getReference(),
                    'quantity' => $piece->getQuantity(),
                    'supplier' => $piece->getSupplier(),
                    'estimatedUnitCostHt' => $this->normalizeDecimal($piece->getEstimatedUnitCostHt()),
                    'estimatedTotalCostHt' => $this->normalizeDecimal($piece->getEstimatedTotalCostHt()),
                    'actualTotalCostHt' => $piece->getActualTotalCostHt() !== null ? $this->normalizeDecimal($piece->getActualTotalCostHt()) : null,
                    'status' => $piece->getStatus(),
                    'notes' => $piece->getNotes(),
                ];
            }, $pieces),
        ];
    }

    /**
     * @param array<string, mixed> $snapshot
     */
    public function computeHash(array $snapshot): string
    {
        $normalized = $this->sortSnapshotRecursively($snapshot);

        return hash('sha256', json_encode($normalized, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION));
    }

    private function archiveDocument(VORemiseEnEtat $campaign, bool $preferSignedSnapshot, ?User $user = null): VODocument
    {
        $payload = $this->buildPdfPayload($campaign, $preferSignedSnapshot);
        $pdfPath = $this->pdfService->generateVoRemiseEnEtatPdf($payload, $campaign->getAtelierId());

        return $this->documentService->archiveGeneratedPdf(
            generatedFilePath: $pdfPath,
            type: VODocument::TYPE_REMISE_EN_ETAT,
            purchase: $campaign->getVoPurchase(),
            depot: $campaign->getVoDepotVente(),
            user: $user,
            originalFilename: $this->buildPdfFilename($campaign, (bool) $payload['signed']),
            campaign: $campaign,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPdfPayload(VORemiseEnEtat $campaign, bool $preferSignedSnapshot): array
    {
        $signed = $preferSignedSnapshot && $campaign->hasSignedDocument();
        $snapshot = $signed ? ($campaign->getSignedSnapshot() ?? $this->buildCurrentSnapshot($campaign)) : $this->buildCurrentSnapshot($campaign);
        $hash = $signed ? $campaign->getSignedHash() : $this->computeHash($snapshot);

        return [
            'reference' => sprintf('REVO-%d-%d', $campaign->getSourceId() ?? 0, $campaign->getCampaignIndex()),
            'mode' => $signed ? 'signed' : 'fallback',
            'signed' => $signed,
            'generatedAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
            'hash' => $hash,
            'signatureData' => $signed ? $campaign->getSignatureData() : null,
            'signature' => [
                'signedAt' => $signed ? $campaign->getSignedAt()?->format(DATE_ATOM) : null,
                'signedBy' => $signed ? $this->normalizeUser($campaign->getSignedBy()) : null,
                'ip' => $signed ? $campaign->getSignedIp() : null,
            ],
            'snapshot' => $snapshot,
        ];
    }

    private function buildPdfFilename(VORemiseEnEtat $campaign, bool $signed): string
    {
        return sprintf(
            'remise-en-etat-%s-%d-campagne-%d-%s.pdf',
            $campaign->getSourceType(),
            $campaign->getSourceId() ?? 0,
            $campaign->getCampaignIndex(),
            $signed ? 'signee' : 'fallback'
        );
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function sortSnapshotRecursively(array $payload): array
    {
        if (!array_is_list($payload)) {
            ksort($payload);
        }

        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $payload[$key] = $this->sortSnapshotRecursively($value);
            }
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function normalizeUser(?User $user): ?array
    {
        if (!$user instanceof User) {
            return null;
        }

        return [
            'id' => $user->getId(),
            'username' => $user->getUsername(),
            'prenom' => $user->getPrenom(),
            'nom' => $user->getNom(),
        ];
    }

    private function normalizeDecimal(string $value): string
    {
        $normalized = str_replace(',', '.', trim($value));
        if ($normalized === '' || !preg_match('/^-?\d+(?:\.\d+)?$/', $normalized)) {
            return '0.00';
        }

        if (!str_contains($normalized, '.')) {
            return $normalized . '.00';
        }

        [$whole, $fraction] = explode('.', $normalized, 2);

        return $whole . '.' . str_pad(substr($fraction, 0, 2), 2, '0');
    }
}