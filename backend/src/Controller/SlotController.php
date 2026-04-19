<?php
namespace App\Controller;

use App\Service\CurrentAtelierResolver;
use App\Service\SlotService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class SlotController extends AbstractController
{
    public function __construct(
        private SlotService $slotService,
        private CurrentAtelierResolver $currentAtelierResolver,
    ) {}

    /**
     * Get available slots for a date range.
     * Query params: date_debut, date_fin, temps_minutes (default 60)
     */
    #[Route('/api/slots', methods: ['GET'])]
    public function getSlots(Request $request): JsonResponse
    {
        $dateDebut = $request->query->get('date_debut', (new \DateTime())->format('Y-m-d'));
        $dateFin = $request->query->get('date_fin', (new \DateTime('+7 days'))->format('Y-m-d'));
        $tempsMinutes = (int) $request->query->get('temps_minutes', 60);

        $atelierId = $this->currentAtelierResolver->resolveAtelierId();

        $slots = $this->slotService->getAvailableSlots(
            new \DateTime($dateDebut),
            new \DateTime($dateFin),
            $tempsMinutes,
            $atelierId,
        );

        return $this->json($slots);
    }

    #[Route('/api/creneaux/disponibles', methods: ['GET'])]
    public function getLegacySlots(Request $request): JsonResponse
    {
        $date = $request->query->get('date_str', (new \DateTime())->format('Y-m-d'));
        $tempsMinutes = (int) $request->query->get('duree_minutes', 60);

        $atelierId = $this->currentAtelierResolver->resolveAtelierId();

        $slots = $this->slotService->getAvailableSlots(
            new \DateTime($date),
            new \DateTime($date),
            $tempsMinutes,
            $atelierId,
        );

        $daySlots = $slots[$date] ?? [];
        $legacySlots = [];
        $seen = [];

        foreach ($daySlots as $slot) {
            $heure = (string) ($slot['heure'] ?? '');
            if ($heure === '' || isset($seen[$heure])) {
                continue;
            }

            $seen[$heure] = true;
            $legacySlots[] = [
                'heure' => $heure,
                'disponible' => true,
                'pont_id' => $slot['pont_id'] ?? null,
                'pont_nom' => $slot['pont_nom'] ?? null,
                'mecanicien_id' => $slot['mecanicien_id'] ?? null,
            ];
        }

        return $this->json($legacySlots);
    }
}
