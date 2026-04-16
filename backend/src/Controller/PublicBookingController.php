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
        $required = ['nom', 'prenom', 'telephone', 'email', 'date_rdv', 'heure_rdv', 'type_intervention'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return $this->json(['error' => "Field '$field' is required"], Response::HTTP_BAD_REQUEST);
            }
        }

        // RGPD: Validate email format
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return $this->json(['error' => 'Invalid email format'], Response::HTTP_BAD_REQUEST);
        }

        // RGPD: Validate phone (basic: 10-15 digits)
        $phone = preg_replace('/[\s\-\.]+/', '', $data['telephone']);
        if (!preg_match('/^\+?[0-9]{10,15}$/', $phone)) {
            return $this->json(['error' => 'Invalid phone format'], Response::HTTP_BAD_REQUEST);
        }

        $atelierId = (int) ($data['atelier_id'] ?? 1);
        $tempsEstime = max(15, (int) ($data['duree_estimee'] ?? 60));
        $targetDate = new \DateTime($data['date_rdv']);
        $availableSlots = $this->slotService->getSlotsForDay($targetDate, $tempsEstime, $atelierId);
        $matchingSlots = array_values(array_filter($availableSlots, static fn(array $slot) => ($slot['heure'] ?? null) === ($data['heure_rdv'] ?? null)));

        if (empty($matchingSlots)) {
            return $this->json([
                'error' => 'Le créneau sélectionné n’est plus disponible. Merci d’en choisir un autre.',
            ], Response::HTTP_CONFLICT);
        }

        $selectedSlot = null;
        if (!empty($data['pont_id'])) {
            foreach ($matchingSlots as $slot) {
                if ((int) ($slot['pont_id'] ?? 0) === (int) $data['pont_id']) {
                    $selectedSlot = $slot;
                    break;
                }
            }
        }
        $selectedSlot ??= $matchingSlots[0];

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
            // RGPD: Record consent from public booking form
            $client->setConsentDate(new \DateTime());
            $client->setConsentSource('public_booking');
            $this->em->persist($client);
        }
        // RGPD: Update last activity
        $client->touchActivity();

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
        $rdv->setTempsEstime($tempsEstime);
        $rdv->setPrixEstime(isset($data['prix_estime']) ? (string) $data['prix_estime'] : null);
        $rdv->setStatut('en_attente');
        $rdv->setAtelierId($atelierId);

        if (!empty($selectedSlot['pont_id'])) {
            $pont = $this->em->getRepository(\App\Entity\Pont::class)->find((int) $selectedSlot['pont_id']);
            if ($pont) {
                $rdv->setPont($pont);
                if ($pont->getMecanicien()) {
                    $rdv->setMecanicien($pont->getMecanicien());
                }
            }
        }

        $this->em->persist($rdv);
        $this->em->flush();

        return $this->json([
            'id' => $rdv->getId(),
            'token_suivi' => $rdv->getTokenSuivi(),
            'message' => 'Demande de créneau enregistrée. Une confirmation vous sera envoyée par email.',
            'date' => $rdv->getDateRdv()->format('Y-m-d'),
            'heure' => $rdv->getHeureRdv()->format('H:i'),
            'heure_fin' => $selectedSlot['heure_fin'] ?? null,
            'pause_appliquee' => (bool) ($selectedSlot['pause_appliquee'] ?? false),
        ], Response::HTTP_CREATED);
    }
}
