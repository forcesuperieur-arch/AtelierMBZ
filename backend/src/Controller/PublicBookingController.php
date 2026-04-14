<?php
namespace App\Controller;

use App\Entity\Client;
use App\Entity\RendezVous;
use App\Entity\Vehicule;
use App\Service\SlotService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Public booking endpoint (no auth required).
 * Used by the public appointment booking page.
 */
#[Route('/api/public')]
class PublicBookingController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private SlotService $slotService,
    ) {}

    /**
     * Get available slots for public booking.
     */
    #[Route('/slots', methods: ['GET'])]
    public function slots(Request $request): JsonResponse
    {
        $dateDebut = $request->query->get('date_debut', (new \DateTime())->format('Y-m-d'));
        $dateFin = $request->query->get('date_fin', (new \DateTime('+14 days'))->format('Y-m-d'));
        $tempsMinutes = (int) $request->query->get('temps_minutes', 60);
        $atelierId = (int) $request->query->get('atelier_id', 1);

        $slots = $this->slotService->getAvailableSlots(
            new \DateTime($dateDebut),
            new \DateTime($dateFin),
            $tempsMinutes,
            $atelierId,
        );

        return $this->json($slots);
    }

    /**
     * Create a public booking.
     */
    #[Route('/booking', methods: ['POST'])]
    public function createBooking(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        // Validate required fields
        $required = ['nom', 'prenom', 'telephone', 'date_rdv', 'heure_rdv', 'type_intervention'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return $this->json(['error' => "Field '$field' is required"], Response::HTTP_BAD_REQUEST);
            }
        }

        $atelierId = (int) ($data['atelier_id'] ?? 1);

        // Find or create client
        $client = $this->em->getRepository(Client::class)->findOneBy([
            'telephone' => $data['telephone'],
            'atelierId' => $atelierId,
        ]);

        if (!$client) {
            $client = new Client();
            $client->setNom($data['nom']);
            $client->setPrenom($data['prenom']);
            $client->setTelephone($data['telephone']);
            $client->setEmail($data['email'] ?? null);
            $client->setAtelierId($atelierId);
            $this->em->persist($client);
        }

        // Find or create vehicle if plaque provided
        $vehicule = null;
        if (!empty($data['plaque'])) {
            $vehicule = $this->em->getRepository(Vehicule::class)->findOneBy([
                'plaque' => strtoupper($data['plaque']),
            ]);

            if (!$vehicule) {
                $vehicule = new Vehicule();
                $vehicule->setPlaque(strtoupper($data['plaque']));
                $vehicule->setMarque($data['marque'] ?? null);
                $vehicule->setModele($data['modele'] ?? null);
                $vehicule->setClient($client);
                $vehicule->setAtelierId($atelierId);
                $this->em->persist($vehicule);
            }
        }

        $rdv = new RendezVous();
        $rdv->setClient($client);
        $rdv->setVehicule($vehicule);
        $rdv->setDateRdv(new \DateTime($data['date_rdv']));
        $rdv->setHeureRdv(new \DateTime($data['heure_rdv']));
        $rdv->setTypeIntervention($data['type_intervention']);
        $rdv->setCommentaire($data['commentaire'] ?? null);
        $rdv->setStatut('en_attente');
        $rdv->setAtelierId($atelierId);

        if (!empty($data['pont_id'])) {
            $rdv->setPontId((int) $data['pont_id']);
        }

        $this->em->persist($rdv);
        $this->em->flush();

        return $this->json([
            'id' => $rdv->getId(),
            'token_suivi' => $rdv->getTokenSuivi(),
            'message' => 'Rendez-vous créé avec succès',
        ], Response::HTTP_CREATED);
    }
}
