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
    ) {}

    public function archiveCompanionDocumentIfReady(VOPurchase|VODepotVente $record, ?User $user = null): bool
    {
        if ($record->getVehicule() === null || $this->workflowService->getParty($record) === null) {
            return false;
        }

        if ($record instanceof VOPurchase) {
            $pdfPath = $this->pdfService->generatePvRachatPdf($record);
            $this->documentService->archiveGeneratedPdf(
                $pdfPath,
                VODocument::TYPE_PV_RACHAT,
                $record,
                null,
                $user,
                sprintf('pv-rachat-%d.pdf', $record->getId()),
            );

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
}