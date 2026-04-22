<?php
namespace App\Controller;

use App\Entity\Atelier;
use App\Entity\Client;
use App\Entity\Prestation;
use App\Entity\RendezVous;
use App\Entity\Vehicule;
use App\Service\AtelierCatalogBootstrapService;
use App\Service\PrestationCatalogService;
use App\Service\SlotService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
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
        private RateLimiterFactory $publicBookingLimiter,
        private PrestationCatalogService $catalogService,
        private AtelierCatalogBootstrapService $atelierCatalogBootstrapService,
    ) {}

    /**
     * List active ateliers that accept public online booking.
     * No auth required — returns only name, slug, ville, telephone.
     */
    #[Route('/ateliers', methods: ['GET'])]
    public function ateliers(): JsonResponse
    {
        $ateliers = $this->em->getRepository(Atelier::class)->findBy(['actif' => true], ['nom' => 'ASC']);

        $result = [];
        foreach ($ateliers as $atelier) {
            // Only expose ateliers with rdv module enabled
            $config = $this->em->getRepository(\App\Entity\ConfigAtelier::class)->findOneBy(['atelierId' => $atelier->getId()]);
            $modules = $config?->getFeatureModules() ?? \App\Entity\ConfigAtelier::defaultFeatureModules();
            if (($modules['rdv'] ?? true) === false) {
                continue;
            }

            $result[] = [
                'id'        => $atelier->getId(),
                'nom'       => $atelier->getNom(),
                'ville'     => $atelier->getVille(),
                'telephone' => $atelier->getTelephone(),
                'email'     => $atelier->getEmail(),
            ];
        }

        return $this->json($result);
    }

    /**
     * Public prestations catalogue for a given atelier.
     * No auth required — returns only active prestations for the selected atelier.
     */
    #[Route('/prestations-catalogue', methods: ['GET'])]
    public function prestationsCatalogue(Request $request): JsonResponse
    {
        $atelierId = $request->query->getInt('atelier_id');
        if (!$atelierId) {
            return $this->json(['error' => 'atelier_id is required'], Response::HTTP_BAD_REQUEST);
        }

        $this->atelierCatalogBootstrapService->ensurePrestationsForAtelier($atelierId);

        $vehicule = (new Vehicule())
            ->setPlaque('PUBLIC-BOOKING')
            ->setAtelierId($atelierId)
            ->setTypeMoto($request->query->get('type_moto') ?: null)
            ->setCylindree($request->query->get('cylindree') ?: null);

        $entries = $this->catalogService->getApplicablePrestations($vehicule);

        $payload = array_map(function (array $entry) use ($atelierId): array {
            /** @var Prestation $prestation */
            $prestation = $entry['prestation'];

            return [
                'id'                   => $prestation->getId(),
                'code'                 => $prestation->getCode(),
                'nom'                  => $prestation->getNom(),
                'description'          => $prestation->getDescription(),
                'categorie'            => $prestation->getCategorie(),
                'type_vehicule'        => $prestation->getTypeVehicule(),
                'cylindree_min'        => $prestation->getCylindreeMin(),
                'cylindree_max'        => $prestation->getCylindreeMax(),
                'type_tarif'           => $prestation->getTypeTarif(),
                'is_active'            => $prestation->getIsActive(),
                'prix_base_ht'         => (float) ($entry['prix_ht'] ?? $prestation->getPrixBaseHt()),
                'prix_base_ttc'        => (float) ($entry['prix_ttc'] ?? $prestation->getPrixBaseTtc()),
                'temps_estime_minutes' => (int) ($entry['temps_minutes'] ?? $prestation->getTempsEstimeMinutes()),
                'price_source'         => $entry['source'] ?? 'unknown',
                'atelier_id'           => $atelierId,
            ];
        }, $entries);

        return $this->json($payload);
    }

    /**
     * Get available slots for public booking.
     */
    #[Route('/slots', methods: ['GET'])]
    public function slots(Request $request): JsonResponse
    {
        $limiter = $this->publicBookingLimiter->create($request->getClientIp());
        if (!$limiter->consume()->isAccepted()) {
            return $this->json(['error' => 'Too many requests'], Response::HTTP_TOO_MANY_REQUESTS);
        }

        $atelierId = $request->query->getInt('atelier_id');
        if (!$atelierId) {
            return $this->json(['error' => 'atelier_id is required'], Response::HTTP_BAD_REQUEST);
        }
        $dateDebut = $request->query->get('date_debut', (new \DateTime())->format('Y-m-d'));
        $dateFin = $request->query->get('date_fin', (new \DateTime('+14 days'))->format('Y-m-d'));
        $tempsMinutes = (int) $request->query->get('temps_minutes', 60);

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
        $limiter = $this->publicBookingLimiter->create($request->getClientIp());
        if (!$limiter->consume()->isAccepted()) {
            return $this->json(['error' => 'Too many requests'], Response::HTTP_TOO_MANY_REQUESTS);
        }

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

        $atelierId = (int) ($data['atelier_id'] ?? 0);
        if (!$atelierId) {
            return $this->json(['error' => 'atelier_id is required'], Response::HTTP_BAD_REQUEST);
        }

        // [I7] Vérifier que le module rdv (et rdv_public si distinct) est activé pour cet atelier
        $configAtelier = $this->em->getRepository(\App\Entity\ConfigAtelier::class)->findOneBy(['atelierId' => $atelierId]);
        $featureModules = $configAtelier?->getFeatureModules() ?? \App\Entity\ConfigAtelier::defaultFeatureModules();
        if (($featureModules['rdv'] ?? true) === false) {
            return $this->json(['error' => 'La prise de rendez-vous en ligne n\'est pas disponible pour cet atelier.'], Response::HTTP_FORBIDDEN);
        }

        $tempsEstime = max(15, (int) ($data['duree_estimee'] ?? 60));
        $targetDate = new \DateTime($data['date_rdv']);
        $availableSlots = $this->slotService->getSlotsForDay($targetDate, $tempsEstime, $atelierId);
        $matchingSlots = array_values(array_filter($availableSlots, static fn(array $slot) => ($slot['heure'] ?? null) === ($data['heure_rdv'] ?? null)));

        if (empty($matchingSlots)) {
            return $this->json([
                'error' => 'Le creneau selectionne n\'est plus disponible. Merci d\'en choisir un autre.',
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
        $rdv->setSource('web'); // [I4] Marqueur RDV public

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
