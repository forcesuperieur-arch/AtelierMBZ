<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
class RendezVousFacturationCompatController extends FacturationController
{
    #[Route('/rendez-vous/{rdvId}/preview-facture', methods: ['GET'])]
    public function previewFactureCompat(int $rdvId): JsonResponse
    {
        return $this->previewFacture($rdvId);
    }

    #[Route('/rendez-vous/{rdvId}/facturer', methods: ['POST'])]
    public function facturerRendezVousCompat(int $rdvId, Request $request): JsonResponse
    {
        return $this->facturerRendezVous($rdvId, $request);
    }
}
