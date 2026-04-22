<?php

namespace App\Service;

use App\Entity\User;
use App\Entity\VODepotVente;
use App\Entity\VODocument;
use App\Entity\VOPurchase;

class VOGeneratedDocumentService
{
    public function __construct(
        private PdfService $pdfService,
        private VODocumentService $documentService,
        private VOCompanionWorkflowService $workflowService,
        private AuditService $auditService,
    ) {}

    public function archiveCompanionDocumentIfReady(VOPurchase|VODepotVente $record, ?User $user = null, bool $prepareSiv = false): bool
    {
        if ($record->getVehicule() === null || $this->workflowService->getParty($record) === null) {
            return false;
        }

        if ($record instanceof VOPurchase) {
            $cerfaPath = $this->pdfService->generateCerfaCessionAchatPdf($record);
            $this->documentService->archiveGeneratedPdf(
                $cerfaPath,
                VODocument::TYPE_CERFA_CESSION_ACHAT,
                $record,
                null,
                $user,
                sprintf('cerfa-cession-achat-%d.pdf', $record->getId()),
            );

            $pdfPath = $this->pdfService->generatePvRachatPdf($record);
            $this->documentService->archiveGeneratedPdf(
                $pdfPath,
                VODocument::TYPE_PV_RACHAT,
                $record,
                null,
                $user,
                sprintf('pv-rachat-%d.pdf', $record->getId()),
            );

            if ($this->canPreparePurchaseSiv($record)) {
                $this->archivePurchaseSivPreparation($record, $user, $prepareSiv);
            }

            return true;
        }

        $pdfPath = $this->pdfService->generateContratDepotVentePdf($record);
        $this->documentService->archiveGeneratedPdf(
            $pdfPath,
            VODocument::TYPE_CONTRAT_DEPOT_VENTE,
            null,
            $record,
            $user,
            sprintf('contrat-depot-%d.pdf', $record->getId()),
        );

        return true;
    }

    public function archivePurchaseSivPreparation(VOPurchase $purchase, ?User $user = null, bool $markAsInProgress = false): bool
    {
        if ($purchase->getVehicule() === null || $purchase->getSeller() === null) {
            return false;
        }

        if ($markAsInProgress && in_array($purchase->getSivStatus(), [
            VOPurchase::SIV_STATUS_A_PREPARER,
            VOPurchase::SIV_STATUS_REJETEE,
            VOPurchase::SIV_STATUS_EXPIREE,
        ], true)) {
            $purchase->setSivStatus(VOPurchase::SIV_STATUS_EN_COURS);

            $this->auditService->log(
                'siv_transition',
                'VOPurchase',
                $purchase->getId(),
                sprintf('SIV status → %s (purchase #%d)', VOPurchase::SIV_STATUS_EN_COURS, $purchase->getId()),
            );
        }

        $blockers = array_values(array_filter(
            $this->documentService->getPurchaseSaleBlockers($purchase),
            static fn (string $message): bool => $message !== 'DA SIV non enregistrée.',
        ));

        $pdfPath = $this->pdfService->generateDaSivPreparationPdf($purchase, $blockers);
        $this->documentService->archiveGeneratedPdf(
            $pdfPath,
            VODocument::TYPE_DA_SIV,
            $purchase,
            null,
            $user,
            sprintf('da-siv-%d.pdf', $purchase->getId()),
        );

        return true;
    }

    private function canPreparePurchaseSiv(VOPurchase $purchase): bool
    {
        $missingDocuments = array_values(array_diff(
            $this->documentService->getMissingDocuments($purchase),
            [VODocument::TYPE_PV_RACHAT, VODocument::TYPE_CERFA_CESSION_ACHAT],
        ));

        return $missingDocuments === [];
    }
}