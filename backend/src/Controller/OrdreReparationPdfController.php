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

        // Disponible une fois l'OR scellé à la restitution (empreinte finale).
        if ($ordre->getFinalHash() === null) {
            return $this->json(
                ['error' => 'Le PDF n\'est disponible qu\'une fois l\'ordre de réparation signé à la restitution.'],
                Response::HTTP_FORBIDDEN
            );
        }

        // On ne sert QUE l'archive immuable — jamais un re-rendu depuis l'entité
        // vivante (les notes mécano restent modifiables après signature et
        // différeraient du document scellé).
        $filePath = $this->pdfService->getArchivedOrPdfPath($ordre);
        if ($filePath === null) {
            return $this->json(
                ['error' => 'Document archivé momentanément indisponible. Contactez le support.'],
                Response::HTTP_NOT_FOUND
            );
        }

        return $this->file($filePath, 'OR-' . $ordre->getNumeroOr() . '.pdf');
    }
}
