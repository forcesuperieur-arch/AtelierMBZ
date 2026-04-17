<?php
namespace App\Controller;

use App\Entity\RendezVous;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Public tracking endpoint (no auth required).
 * Client can check appointment status via a unique token.
 */
#[Route('/api/public/suivi')]
class SuiviController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private RateLimiterFactory $publicSuiviLimiter,
    ) {}

    #[Route('/{token}', methods: ['GET'])]
    public function suivi(string $token, Request $request): JsonResponse
    {
        $limiter = $this->publicSuiviLimiter->create($request->getClientIp());
        if (!$limiter->consume()->isAccepted()) {
            return $this->json(['error' => 'Too many requests'], Response::HTTP_TOO_MANY_REQUESTS);
        }

        $rdv = $this->em->getRepository(RendezVous::class)->findOneBy(['tokenSuivi' => $token]);

        if (!$rdv) {
            return $this->json(['error' => 'Invalid tracking token'], Response::HTTP_NOT_FOUND);
        }

        // RGPD: Token expires 30 days after RDV creation
        $expiry = (clone $rdv->getCreatedAt())->modify('+30 days');
        if (new \DateTime() > $expiry) {
            return $this->json(['error' => 'Tracking token expired'], Response::HTTP_GONE);
        }

        $vehicule = $rdv->getVehicule();

        // RGPD: Only expose non-identifying vehicle info, no client PII
        return $this->json([
            'statut' => $rdv->getStatut(),
            'date_rdv' => $rdv->getDateRdv()->format('Y-m-d'),
            'heure_rdv' => $rdv->getHeureRdv()->format('H:i'),
            'type_intervention' => $rdv->getTypeIntervention(),
            'vehicule' => $vehicule ? [
                'marque' => $vehicule->getMarque(),
                'modele' => $vehicule->getModele(),
            ] : null,
            'rapport' => $rdv->getRapportTechnicien() ? [
                'statut' => $rdv->getRapportTechnicien()->getStatut(),
            ] : null,
        ]);
    }
}
