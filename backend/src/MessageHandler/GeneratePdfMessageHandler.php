<?php

namespace App\MessageHandler;

use App\Entity\Devis;
use App\Entity\Facture;
use App\Entity\OrdreReparation;
use App\Entity\RapportIntervention;
use App\Message\GeneratePdfMessage;
use App\Service\PdfService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * [SPRINT-4] I14 — Pre-generates PDF files asynchronously so GET /pdf endpoints serve cached files.
 *
 * Supported types: 'or', 'rapport', 'devis', 'facture'
 */
#[AsMessageHandler]
class GeneratePdfMessageHandler
{
    public function __construct(
        private PdfService $pdfService,
        private EntityManagerInterface $em,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(GeneratePdfMessage $message): void
    {
        try {
            match ($message->type) {
                'or'      => $this->generateOr($message->entityId),
                'rapport' => $this->generateRapport($message->entityId),
                'devis'   => $this->generateDevis($message->entityId),
                'facture' => $this->generateFacture($message->entityId),
                default   => $this->logger->warning('GeneratePdfMessageHandler: unknown type', ['type' => $message->type]),
            };
        } catch (\Throwable $e) {
            $this->logger->error('GeneratePdfMessageHandler: error', [
                'type' => $message->type,
                'entityId' => $message->entityId,
                'error' => $e->getMessage(),
            ]);
            // Do not rethrow — non-blocking, the sync endpoint will re-generate on demand
        }
    }

    private function generateOr(int $id): void
    {
        $or = $this->em->getRepository(OrdreReparation::class)->find($id);
        if ($or) {
            $this->pdfService->generateOrPdf($or);
        }
    }

    private function generateRapport(int $id): void
    {
        $rapport = $this->em->getRepository(RapportIntervention::class)->find($id);
        if ($rapport) {
            $this->pdfService->generateRapportPdf($rapport);
        }
    }

    private function generateDevis(int $id): void
    {
        $devis = $this->em->getRepository(Devis::class)->find($id);
        if ($devis) {
            $this->pdfService->generateDevisPdf($devis);
        }
    }

    private function generateFacture(int $id): void
    {
        $facture = $this->em->getRepository(Facture::class)->find($id);
        if ($facture) {
            $this->pdfService->generateFacturePdf($facture);
        }
    }
}
