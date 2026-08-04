<?php

namespace App\Controller;

use App\Entity\Vehicule;
use App\Service\AuditService;
use App\Service\CurrentAtelierResolver;
use App\Service\HistoriqueEntretienService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class HistoriqueEntretienController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private HistoriqueEntretienService $historiqueService,
        private AuditService $audit,
        private CurrentAtelierResolver $atelierResolver,
    ) {}

    // Ouvert par le nouveau consommateur "Historique de cette moto" côté
    // mécanicien : la route n'avait jamais de vérification de tenant, elle ne
    // servait qu'à un usage staff interne non encore branché.
    private function assertVehiculeAccessible(Vehicule $vehicule): void
    {
        $atelierId = $this->atelierResolver->resolveAtelierId();
        if ($atelierId !== null && $vehicule->getAtelierId() !== $atelierId) {
            throw $this->createAccessDeniedException();
        }
    }

    #[Route('/api/vehicules/{id}/historique-entretien', methods: ['GET'])]
    public function historique(int $id): JsonResponse
    {
        $vehicule = $this->em->getRepository(Vehicule::class)->find($id);
        if (!$vehicule) {
            return $this->json(['error' => 'Véhicule non trouvé'], Response::HTTP_NOT_FOUND);
        }
        $this->assertVehiculeAccessible($vehicule);

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
        $this->assertVehiculeAccessible($vehicule);

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
