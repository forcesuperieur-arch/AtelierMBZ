<?php
namespace App\Controller;

use App\Entity\RendezVous;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Public tracking endpoint (no auth required).
 * Client can check appointment status via a unique token.
 */
#[Route('/api/public/suivi')]
class SuiviController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em) {}

    #[Route('/{token}', methods: ['GET'])]
    public function suivi(string $token): JsonResponse
    {
        $rdv = $this->em->getRepository(RendezVous::class)->findOneBy(['tokenSuivi' => $token]);

        if (!$rdv) {
            return $this->json(['error' => 'Invalid tracking token'], Response::HTTP_NOT_FOUND);
        }

        $client = $rdv->getClient();
        $vehicule = $rdv->getVehicule();

        return $this->json([
            'statut' => $rdv->getStatut(),
            'date_rdv' => $rdv->getDateRdv()->format('Y-m-d'),
            'heure_rdv' => $rdv->getHeureRdv()->format('H:i'),
            'type_intervention' => $rdv->getTypeIntervention(),
            'client_prenom' => $client->getPrenom(),
            'vehicule' => $vehicule ? [
                'marque' => $vehicule->getMarque(),
                'modele' => $vehicule->getModele(),
                'plaque' => $vehicule->getPlaque(),
            ] : null,
            'rapport' => $rdv->getRapportTechnicien() ? [
                'statut' => $rdv->getRapportTechnicien()->getStatut(),
                'recommandations' => $rdv->getRapportTechnicien()->getRecommandations(),
            ] : null,
        ]);
    }
}
