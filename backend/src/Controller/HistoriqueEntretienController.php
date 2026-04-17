<?php

namespace App\Controller;

use App\Entity\Vehicule;
use App\Service\AuditService;
use App\Service\HistoriqueEntretienService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HistoriqueEntretienController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private HistoriqueEntretienService $historiqueService,
        private AuditService $audit,
    ) {}

    #[Route('/api/vehicules/{id}/historique-entretien', methods: ['GET'])]
    public function historique(int $id): JsonResponse
    {
        $vehicule = $this->em->getRepository(Vehicule::class)->find($id);
        if (!$vehicule) {
            return $this->json(['error' => 'Véhicule non trouvé'], Response::HTTP_NOT_FOUND);
        }

        $historique = $this->historiqueService->buildHistorique($vehicule);

        return $this->json($historique);
    }

    #[Route('/api/vehicules/{id}/historique-entretien/pdf', methods: ['GET'])]
    public function historiquePdf(int $id, Request $request): Response
    {
        $vehicule = $this->em->getRepository(Vehicule::class)->find($id);
        if (!$vehicule) {
            return $this->json(['error' => 'Véhicule non trouvé'], Response::HTTP_NOT_FOUND);
        }

        $pdfContent = $this->historiqueService->generatePdf($vehicule);

        $user = $this->getUser();
        $this->audit->log('export_historique_entretien', 'Vehicule', $vehicule->getId(), json_encode([
            'user_id' => $user?->getId(),
            'ip' => $request->getClientIp(),
        ]));

        return new Response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf(
                'inline; filename="historique-entretien-%s.pdf"',
                $vehicule->getPlaque(),
            ),
        ]);
    }
}
