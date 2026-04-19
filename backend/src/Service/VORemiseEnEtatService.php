<?php

namespace App\Service;

use App\Entity\Prestation;
use App\Entity\User;
use App\Entity\VODepotVente;
use App\Entity\VOPurchase;
use App\Entity\VORemiseEnEtat;
use App\Entity\VORemiseEnEtatLigne;
use App\Entity\VORemiseEnEtatPiece;
use App\Entity\Vehicule;
use Doctrine\ORM\EntityManagerInterface;

class VORemiseEnEtatService
{
    public function __construct(
        private EntityManagerInterface $em,
        private ?VORemiseEnEtatDocumentService $documentService = null,
    ) {}

    /**
     * @return list<VORemiseEnEtat>
     */
    public function getCampaignsForRecord(VOPurchase|VODepotVente $record): array
    {
        return $this->em->getRepository(VORemiseEnEtat::class)->findBy(
            $record instanceof VOPurchase ? ['voPurchase' => $record] : ['voDepotVente' => $record],
            ['createdAt' => 'DESC'],
        );
    }

    public function getActiveCampaignForRecord(VOPurchase|VODepotVente $record): ?VORemiseEnEtat
    {
        $qb = $this->em->getRepository(VORemiseEnEtat::class)->createQueryBuilder('r')
            ->where($record instanceof VOPurchase ? 'r.voPurchase = :record' : 'r.voDepotVente = :record')
            ->andWhere('r.status NOT IN (:finalStatuses)')
            ->setParameter('record', $record)
            ->setParameter('finalStatuses', VORemiseEnEtat::FINAL_STATUSES)
            ->orderBy('r.createdAt', 'DESC')
            ->setMaxResults(1);

        return $qb->getQuery()->getOneOrNullResult();
    }

    public function hasBlockingActiveCampaign(VOPurchase|VODepotVente $record): bool
    {
        return $this->getActiveCampaignForRecord($record) instanceof VORemiseEnEtat;
    }

    public function createCampaignForRecord(VOPurchase|VODepotVente $record, ?User $requestedBy, array $payload = []): VORemiseEnEtat
    {
        if ($this->hasBlockingActiveCampaign($record)) {
            throw new \DomainException('Une campagne active de remise en etat VO existe deja pour ce dossier.');
        }

        $count = $this->em->getRepository(VORemiseEnEtat::class)->count(
            $record instanceof VOPurchase ? ['voPurchase' => $record] : ['voDepotVente' => $record],
        );
        $campaignIndex = $count + 1;

        $campaign = new VORemiseEnEtat();
        $campaign->setAtelierId($record->getAtelierId());
        $campaign->setRequestedBy($requestedBy);
        $campaign->setCampaignIndex($campaignIndex);
        $campaign->setTitre($this->stringOrDefault($payload['titre'] ?? null, sprintf('Remise en etat VO #%d', $campaignIndex)));
        $campaign->setPriority($this->stringOrDefault($payload['priority'] ?? null, VORemiseEnEtat::PRIORITY_NORMALE));
        $campaign->setDiagnosticNotes($this->nullableString($payload['diagnosticNotes'] ?? null));
        $campaign->setWorkshopNotes($this->nullableString($payload['workshopNotes'] ?? null));
        $campaign->setBusinessNotes($this->nullableString($payload['businessNotes'] ?? null));

        if ($record instanceof VOPurchase) {
            $campaign->setVoPurchase($record);
        } else {
            $campaign->setVoDepotVente($record);
        }

        $this->em->persist($campaign);

        return $campaign;
    }

    public function normalizeCampaign(VORemiseEnEtat $campaign): array
    {
        $vehicle = $campaign->getSourceVehicule();
        $estimatedTotal = $campaign->getEstimatedTotalCost();
        $actualTotal = $campaign->getActualTotalCost();

        $lines = iterator_to_array($campaign->getLignes());
        usort($lines, static fn (VORemiseEnEtatLigne $left, VORemiseEnEtatLigne $right): int => [$left->getSortOrder(), $left->getId() ?? 0] <=> [$right->getSortOrder(), $right->getId() ?? 0]);

        $pieces = iterator_to_array($campaign->getPieces());
        usort($pieces, static fn (VORemiseEnEtatPiece $left, VORemiseEnEtatPiece $right): int => ($left->getId() ?? 0) <=> ($right->getId() ?? 0));

        return [
            'id' => $campaign->getId(),
            'atelierId' => $campaign->getAtelierId(),
            'sourceType' => $campaign->getSourceType(),
            'sourceId' => $campaign->getSourceId(),
            'campaignIndex' => $campaign->getCampaignIndex(),
            'titre' => $campaign->getTitre(),
            'status' => $campaign->getStatus(),
            'priority' => $campaign->getPriority(),
            'diagnosticNotes' => $campaign->getDiagnosticNotes(),
            'workshopNotes' => $campaign->getWorkshopNotes(),
            'businessNotes' => $campaign->getBusinessNotes(),
            'requestedBy' => $this->normalizeUserLite($campaign->getRequestedBy()),
            'validatedBy' => $this->normalizeUserLite($campaign->getValidatedBy()),
            'requestedAt' => $campaign->getRequestedAt()->format(DATE_ATOM),
            'validatedAt' => $campaign->getValidatedAt()?->format(DATE_ATOM),
            'plannedFor' => $campaign->getPlannedFor()?->format(DATE_ATOM),
            'startedAt' => $campaign->getStartedAt()?->format(DATE_ATOM),
            'completedAt' => $campaign->getCompletedAt()?->format(DATE_ATOM),
            'closedAt' => $campaign->getClosedAt()?->format(DATE_ATOM),
            'createdAt' => $campaign->getCreatedAt()->format(DATE_ATOM),
            'updatedAt' => $campaign->getUpdatedAt()->format(DATE_ATOM),
            'vehicle' => $this->normalizeVehicleLite($vehicle),
            'document' => $this->normalizeDocumentState($campaign),
            'isClosed' => $campaign->isClosed(),
            'isBlockingSale' => $campaign->isBlockingSale(),
            'pendingPiecesCount' => $campaign->getPendingPiecesCount(),
            'costSummary' => [
                'estimatedMoCost' => $campaign->getEstimatedMoCost(),
                'estimatedPartsCost' => $campaign->getEstimatedPartsCost(),
                'estimatedTotalCost' => $estimatedTotal,
                'actualMoCost' => $campaign->getActualMoCost(),
                'actualPartsCost' => $campaign->getActualPartsCost(),
                'actualTotalCost' => $actualTotal,
                'varianceTotal' => bcsub($actualTotal, $estimatedTotal, 2),
            ],
            'lignes' => array_map(fn (VORemiseEnEtatLigne $line): array => $this->normalizeLine($line), $lines),
            'pieces' => array_map(fn (VORemiseEnEtatPiece $piece): array => $this->normalizePiece($piece), $pieces),
        ];
    }

    public function normalizeQueueItem(VORemiseEnEtat $campaign): array
    {
        $payload = $this->normalizeCampaign($campaign);
        $payload['sourceLabel'] = $campaign->getSourceType() === 'purchase' ? 'Rachat' : 'Depot-vente';
        $payload['dossierPath'] = sprintf('/vo/%s/%d', $campaign->getSourceType() === 'purchase' ? 'rachats' : 'depots', $campaign->getSourceId());

        return $payload;
    }

    private function normalizeLine(VORemiseEnEtatLigne $line): array
    {
        return [
            'id' => $line->getId(),
            'prestation' => $line->getPrestation() ? [
                'id' => $line->getPrestation()?->getId(),
                'code' => $line->getPrestation()?->getCode(),
                'nom' => $line->getPrestation()?->getNom(),
            ] : null,
            'libelle' => $line->getLibelle(),
            'quantity' => $line->getQuantity(),
            'estimatedUnitHt' => $line->getEstimatedUnitHt(),
            'estimatedTotalHt' => $line->getEstimatedTotalHt(),
            'actualTotalHt' => $line->getActualTotalHt(),
            'estimatedMinutes' => $line->getEstimatedMinutes(),
            'actualMinutes' => $line->getActualMinutes(),
            'status' => $line->getStatus(),
            'notes' => $line->getNotes(),
            'sortOrder' => $line->getSortOrder(),
            'createdAt' => $line->getCreatedAt()->format(DATE_ATOM),
            'updatedAt' => $line->getUpdatedAt()->format(DATE_ATOM),
        ];
    }

    private function normalizePiece(VORemiseEnEtatPiece $piece): array
    {
        return [
            'id' => $piece->getId(),
            'libelle' => $piece->getLibelle(),
            'reference' => $piece->getReference(),
            'quantity' => $piece->getQuantity(),
            'supplier' => $piece->getSupplier(),
            'estimatedUnitCostHt' => $piece->getEstimatedUnitCostHt(),
            'estimatedTotalCostHt' => $piece->getEstimatedTotalCostHt(),
            'actualTotalCostHt' => $piece->getActualTotalCostHt(),
            'status' => $piece->getStatus(),
            'notes' => $piece->getNotes(),
            'createdAt' => $piece->getCreatedAt()->format(DATE_ATOM),
            'updatedAt' => $piece->getUpdatedAt()->format(DATE_ATOM),
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
            'prenom' => $user->getPrenom(),
            'nom' => $user->getNom(),
        ];
    }

    private function normalizeVehicleLite(?Vehicule $vehicle): ?array
    {
        if (!$vehicle instanceof Vehicule) {
            return null;
        }

        return [
            'id' => $vehicle->getId(),
            'plaque' => $vehicle->getPlaque(),
            'marque' => $vehicle->getMarque(),
            'modele' => $vehicle->getModele(),
            'vin' => $vehicle->getVin(),
            'typeMoto' => $vehicle->getTypeMoto(),
            'cylindree' => $vehicle->getCylindree(),
            'categorieId' => $vehicle->getCategorie()?->getId(),
            'categorieNom' => $vehicle->getCategorie()?->getNom(),
        ];
    }

    private function normalizeDocumentState(VORemiseEnEtat $campaign): array
    {
        if ($this->documentService instanceof VORemiseEnEtatDocumentService) {
            return $this->documentService->normalizeDocumentState($campaign);
        }

        return [
            'canSign' => !$campaign->isClosed() && !$campaign->hasSignedDocument(),
            'signed' => $campaign->hasSignedDocument(),
            'signedAt' => $campaign->getSignedAt()?->format(DATE_ATOM),
            'signedBy' => $this->normalizeUserLite($campaign->getSignedBy()),
            'signedHash' => $campaign->getSignedHash(),
            'currentHash' => null,
            'outdatedSinceSignature' => false,
            'livePdfUrl' => $campaign->getId() ? sprintf('/api/vo/remises-en-etat/%d/pdf', $campaign->getId()) : null,
            'archivedDocument' => null,
        ];
    }

    private function nullableString(mixed $value): ?string
    {
        $normalized = trim((string) $value);
        return $normalized !== '' ? $normalized : null;
    }

    private function stringOrDefault(mixed $value, string $default): string
    {
        $normalized = $this->nullableString($value);
        return $normalized ?? $default;
    }
}