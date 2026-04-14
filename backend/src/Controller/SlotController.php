<?php
namespace App\Controller;

use App\Service\SlotService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/slots')]
class SlotController extends AbstractController
{
    public function __construct(private SlotService $slotService) {}

    /**
     * Get available slots for a date range.
     * Query params: date_debut, date_fin, temps_minutes (default 60)
     */
    #[Route('', methods: ['GET'])]
    public function getSlots(Request $request): JsonResponse
    {
        $dateDebut = $request->query->get('date_debut', (new \DateTime())->format('Y-m-d'));
        $dateFin = $request->query->get('date_fin', (new \DateTime('+7 days'))->format('Y-m-d'));
        $tempsMinutes = (int) $request->query->get('temps_minutes', 60);

        $user = $this->getUser();
        $atelierId = $user?->getAtelierId();

        $slots = $this->slotService->getAvailableSlots(
            new \DateTime($dateDebut),
            new \DateTime($dateFin),
            $tempsMinutes,
            $atelierId,
        );

        return $this->json($slots);
    }
}
