<?php

namespace App\Controller;

use App\Entity\OrdreReparation;
use App\Service\PdfService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/ordres-reparation')]
#[IsGranted('ROLE_USER')]
class OrdreReparationPdfController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private PdfService $pdfService,
    ) {}

    #[Route('/{id}/pdf', methods: ['GET'])]
    public function downloadPdf(int $id): BinaryFileResponse|JsonResponse
    {
        $ordre = $this->em->getRepository(OrdreReparation::class)->find($id);
        if (!$ordre) {
            return $this->json(['error' => 'Ordre de réparation introuvable'], Response::HTTP_NOT_FOUND);
        }

        // Only allow download for finalized ORs
        if ($ordre->getStatut() !== 'termine' || $ordre->getSignedHash() === null) {
            return $this->json(
                ['error' => 'Le PDF n\'est disponible qu\'une fois l\'ordre de réparation finalisé (statut terminé).'],
                Response::HTTP_FORBIDDEN
            );
        }

        $filePath = $this->pdfService->getOrPdfPath($ordre);
        if (!is_file($filePath)) {
            // Fallback: regenerate if file is missing (should not happen)
            $filePath = $this->pdfService->generateOrPdf($ordre);
        }

        return $this->file($filePath, 'OR-' . $ordre->getNumeroOr() . '.pdf');
    }
}
