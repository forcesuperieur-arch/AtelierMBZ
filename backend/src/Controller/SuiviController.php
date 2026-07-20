<?php
namespace App\Controller;

use App\Entity\OrdreReparation;
use App\Entity\RendezVous;
use App\Service\PublicTokenPolicy;
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
        private PublicTokenPolicy $publicTokenPolicy,
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

        // RGPD : règle d'expiration MUTUALISÉE (PublicTokenPolicy) — ne pas
        // réintroduire de règle locale « createdAt + 30 j » divergente (bug
        // historique : le lien restait ouvert sur un dossier clôturé).
        if ($this->publicTokenPolicy->isTokenExpired($rdv)) {
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
            'or_statut' => $this->em->getRepository(OrdreReparation::class)
                ->findOneBy(['rendezVous' => $rdv], ['id' => 'DESC']) ?->getStatut(),
        ]);
    }
}
