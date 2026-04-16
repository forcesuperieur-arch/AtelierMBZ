<?php
namespace App\MessageHandler;

use App\Entity\Devis;
use App\Entity\Facture;
use App\Entity\OrdreReparation;
use App\Entity\VODepotVente;
use App\Entity\VOFacture;
use App\Entity\VOLivrePolice;
use App\Entity\VOPurchase;
use App\Message\GeneratePdfMessage;
use App\Service\PdfService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class GeneratePdfHandler
{
    public function __construct(
        private EntityManagerInterface $em,
        private PdfService $pdfService,
    ) {}

    public function __invoke(GeneratePdfMessage $message): void
    {
        match ($message->type) {
            'or' => $this->generateOr($message->entityId),
            'facture' => $this->generateFacture($message->entityId),
            'devis' => $this->generateDevis($message->entityId),
            'vo_facture' => $this->generateVoFacture($message->entityId),
            'pv_rachat' => $this->generatePvRachat($message->entityId),
            'contrat_depot' => $this->generateContratDepot($message->entityId),
            default => throw new \InvalidArgumentException("Unknown PDF type: {$message->type}"),
        };
    }

    private function generateOr(int $id): void
    {
        $or = $this->em->getRepository(OrdreReparation::class)->find($id);
        if ($or) {
            $this->pdfService->generateOrPdf($or);
        }
    }

    private function generateFacture(int $id): void
    {
        $facture = $this->em->getRepository(Facture::class)->find($id);
        if ($facture) {
            $this->pdfService->generateFacturePdf($facture);
        }
    }

    private function generateDevis(int $id): void
    {
        $devis = $this->em->getRepository(Devis::class)->find($id);
        if ($devis) {
            $this->pdfService->generateDevisPdf($devis);
        }
    }

    private function generateVoFacture(int $id): void
    {
        $facture = $this->em->getRepository(VOFacture::class)->find($id);
        if ($facture) {
            $this->pdfService->generateVoFacturePdf($facture);
        }
    }

    private function generatePvRachat(int $id): void
    {
        $purchase = $this->em->getRepository(VOPurchase::class)->find($id);
        if ($purchase) {
            $this->pdfService->generatePvRachatPdf($purchase);
        }
    }

    private function generateContratDepot(int $id): void
    {
        $depot = $this->em->getRepository(VODepotVente::class)->find($id);
        if ($depot) {
            $this->pdfService->generateContratDepotVentePdf($depot);
        }
    }
}
