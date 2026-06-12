<?php
namespace App\Controller;

use App\Entity\Atelier;
use App\Entity\Client;
use App\Entity\ConfigAtelier;
use App\Entity\RendezVous;
use App\Entity\Vehicule;
use App\Service\SlotService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Service\NotificationDispatcher;
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
        private NotificationDispatcher $notificationDispatcher,
    ) {}

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

        $bookingEnabled = $this->isPublicBookingEnabled($atelierId);

        $slots = $this->slotService->getAvailableSlots(
            new \DateTime($dateDebut),
            new \DateTime($dateFin),
            $tempsMinutes,
            $atelierId,
        );

        return $this->json([
            'bookingEnabled' => $bookingEnabled,
            'slots' => $slots,
        ]);
    }

    /**
     * Get prestation catalog for public booking.
     */
    #[Route('/prestations', methods: ['GET'])]
    public function prestations(Request $request): JsonResponse
    {
        $limiter = $this->publicBookingLimiter->create($request->getClientIp());
        if (!$limiter->consume()->isAccepted()) {
            return $this->json(['error' => 'Too many requests'], Response::HTTP_TOO_MANY_REQUESTS);
        }

        $atelierId = $request->query->getInt('atelier_id');
        if (!$atelierId) {
            return $this->json(['error' => 'atelier_id is required'], Response::HTTP_BAD_REQUEST);
        }

        $prestations = $this->em->getRepository(\App\Entity\Prestation::class)
            ->createQueryBuilder('p')
            ->where('p.atelierId = :atelier')
            ->andWhere('p.isActive = 1')
            ->setParameter('atelier', $atelierId)
            ->orderBy('p.categorie', 'ASC')
            ->addOrderBy('p.nom', 'ASC')
            ->getQuery()
            ->getResult();

        return $this->json(array_map(function ($p) {
            return [
                'id' => $p->getId(),
                'code' => $p->getCode(),
                'nom' => $p->getNom(),
                'description' => $p->getDescription(),
                'categorie' => $p->getCategorie(),
                'type_vehicule' => $p->getTypeVehicule(),
                'cylindree_min' => $p->getCylindreeMin(),
                'cylindree_max' => $p->getCylindreeMax(),
                'type_tarif' => $p->getTypeTarif(),
                'is_active' => $p->getIsActive(),
                'prix_base_ht' => (float) $p->getPrixBaseHt(),
                'prix_base_ttc' => (float) $p->getPrixBaseTtc(),
                'temps_estime_minutes' => $p->getTempsEstimeMinutes(),
            ];
        }, $prestations));
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

        // Le module peut être désactivé : l'UI masque le formulaire mais l'API
        // doit refuser aussi (sinon réservations fantômes par POST direct).
        if (!$this->isPublicBookingEnabled($atelierId)) {
            return $this->json([
                'error' => 'La réservation en ligne n\'est pas disponible pour cet atelier.',
            ], Response::HTTP_FORBIDDEN);
        }

        $tempsEstime = max(15, (int) ($data['duree_estimee'] ?? 60));
        $targetDate = new \DateTime($data['date_rdv']);

        // Verrou transactionnel anti-course : deux réservations simultanées sur
        // le même atelier/jour sont sérialisées — la seconde revérifie la
        // disponibilité APRÈS l'insert de la première (sinon double résa pont).
        $connection = $this->em->getConnection();
        $connection->beginTransaction();
        try {
            $connection->executeStatement(
                'SELECT pg_advisory_xact_lock(hashtext(:lockKey))',
                ['lockKey' => sprintf('public-booking:%d:%s', $atelierId, $targetDate->format('Y-m-d'))]
            );

            $response = $this->doCreateBooking($data, $atelierId, $tempsEstime, $targetDate, $request);
            $connection->commit();

            return $response;
        } catch (\Throwable $e) {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }
            throw $e;
        }
    }

    private function doCreateBooking(array $data, int $atelierId, int $tempsEstime, \DateTime $targetDate, Request $request): JsonResponse
    {
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

        $isNewClient = false;
        if (!$client) {
            $isNewClient = true;
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
        // SÉCURITÉ : ne jamais rattacher l'email soumis à une fiche client
        // existante — quiconque connaît le téléphone pourrait détourner le
        // compte (l'email reçoit le lien d'activation de l'espace client).
        // RGPD: Update last activity
        $client->touchActivity();

        // Find or create vehicle if plaque provided
        $vehicule = null;
        if (!empty($data['plaque'])) {
            $vehicule = $this->em->getRepository(Vehicule::class)->findOneBy([
                'plaque' => strtoupper($data['plaque']),
            ]);

            // La fiche n'est réutilisée que si elle appartient au même client
            // (même téléphone) — sinon nouvelle fiche locale à cet atelier,
            // pour ne pas polluer l'historique d'un autre propriétaire.
            if ($vehicule && $vehicule->getClient()?->getTelephone() !== $client->getTelephone()) {
                $vehicule = null;
            }

            if (!$vehicule) {
                $vehicule = new Vehicule();
                $vehicule->setPlaque(strtoupper($data['plaque']));
                $vehicule->setMarque($data['marque'] ?? null);
                $vehicule->setModele($data['modele'] ?? null);
                if (!empty($data['annee'])) {
                    $vehicule->setAnnee((int) $data['annee']);
                }
                if (!empty($data['cylindree'])) {
                    $vehicule->setCylindree((string) $data['cylindree']);
                }
                if (!empty($data['type_moto'])) {
                    $vehicule->setTypeMoto((string) $data['type_moto']);
                }
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

        // Accusé de réception + invitation espace client (best-effort : ne bloque jamais la réservation)
        try {
            $this->sendBookingAcknowledgement($rdv, $client, $isNewClient, $request);
        } catch (\Throwable) {
            // l'email échoue silencieusement, le RDV est créé
        }

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

    private function sendBookingAcknowledgement(RendezVous $rdv, Client $client, bool $isNewClient, Request $request): void
    {
        $to = $client->getEmail();
        $atelierId = $rdv->getAtelierId();
        if (!$to || !$atelierId) {
            return;
        }

        $baseUrl = rtrim($_ENV['PUBLIC_URL'] ?? $request->getSchemeAndHttpHost(), '/');
        $atelier = $this->em->getRepository(Atelier::class)->find($atelierId);
        $atelierNom = $atelier?->getNom() ?? 'votre atelier';
        $suiviUrl = $baseUrl . '/public/suivi?token=' . $rdv->getTokenSuivi();

        // Invitation espace client : UNIQUEMENT pour un compte créé par ce
        // booking. Pour un client existant, jamais de lien d'activation (risque
        // de détournement) ni d'écrasement d'un resetToken encore valide.
        $activationBloc = '';
        if ($isNewClient && !$client->getPassword()) {
            $token = bin2hex(random_bytes(32));
            $client->setResetToken($token);
            $client->setResetTokenExpiresAt(new \DateTime('+7 days'));
            $this->em->flush();

            $activationUrl = $baseUrl . '/client/reset-password?token=' . $token;
            $activationBloc = sprintf(
                '<hr><p><strong>Créez votre espace client</strong><br>' .
                'Retrouvez vos rendez-vous, vos motos et votre historique d\'entretien en ligne. ' .
                'Activez votre espace en définissant votre mot de passe (lien valable 7 jours) :<br>' .
                '<a href="%s">%s</a></p>',
                htmlspecialchars($activationUrl),
                htmlspecialchars($activationUrl)
            );
        }

        $this->notificationDispatcher->sendFromTemplate(
            'booking_accuse',
            'email',
            $atelierId,
            $to,
            [
                'client_prenom' => htmlspecialchars($client->getPrenom() ?? ''),
                'atelier_nom' => htmlspecialchars($atelierNom),
                'date_rdv' => $rdv->getDateRdv()->format('d/m/Y'),
                'heure_rdv' => $rdv->getHeureRdv()->format('H\hi'),
                'type_intervention' => htmlspecialchars($rdv->getTypeIntervention() ?? ''),
                'suivi_url' => htmlspecialchars($suiviUrl),
                'activation_bloc' => $activationBloc,
            ],
            'RendezVous',
            $rdv->getId(),
        );
    }

    /**
     * List active ateliers for public booking selection.
     */
    #[Route('/ateliers', methods: ['GET'])]
    public function ateliers(Request $request): JsonResponse
    {
        $limiter = $this->publicBookingLimiter->create($request->getClientIp() ?? 'default');
        if (!$limiter->consume()->isAccepted()) {
            return $this->json(['error' => 'Too many requests'], Response::HTTP_TOO_MANY_REQUESTS);
        }

        $ateliers = $this->em->getRepository(Atelier::class)
            ->createQueryBuilder('a')
            ->where('a.actif = true')
            ->orderBy('a.nom', 'ASC')
            ->getQuery()
            ->getResult();

        return $this->json(array_map(static fn (Atelier $a): array => [
            'id' => (int) $a->getId(),
            'nom' => $a->getNom(),
            'adresse' => $a->getAdresse(),
            'ville' => $a->getVille(),
        ], $ateliers));
    }

    /**
     * Public vehicle lookup by plate/VIN (no sensitive client data).
     */
    #[Route('/vehicule-lookup/{query}', methods: ['GET'])]
    public function vehiculeLookup(string $query, Request $request): JsonResponse
    {
        $limiter = $this->publicBookingLimiter->create($request->getClientIp() ?? 'default');
        if (!$limiter->consume()->isAccepted()) {
            return $this->json(['error' => 'Too many requests'], Response::HTTP_TOO_MANY_REQUESTS);
        }

        $normalized = $this->normalizeVehicleQuery($query);
        if ($normalized === '') {
            return $this->json(['found' => false, 'query' => $query]);
        }

        // Correspondance exacte de plaque uniquement : la recherche floue
        // (marque/modèle) permettait d'énumérer les plaques des clients.
        $vehicule = $this->findByNormalizedPlate($normalized, $query);

        if (!$vehicule) {
            return $this->json(['found' => false, 'query' => $query]);
        }

        return $this->json([
            'found' => true,
            'plaque' => $vehicule->getPlaque(),
            'marque' => $vehicule->getMarque(),
            'modele' => $vehicule->getModele(),
            'annee' => $vehicule->getAnnee(),
            'cylindree' => $vehicule->getCylindree(),
            'type_moto' => $vehicule->getTypeMoto(),
        ]);
    }

    private function normalizeVehicleQuery(string $query): string
    {
        return mb_strtoupper((string) preg_replace('/[^A-Z0-9]/i', '', $query));
    }

    private function findByNormalizedPlate(string $normalized, string $query): ?Vehicule
    {
        $variants = $this->buildPlateVariants($normalized, $query);

        $candidates = $this->em->getRepository(Vehicule::class)
            ->createQueryBuilder('v')
            ->where('UPPER(v.plaque) IN (:variants)')
            ->setParameter('variants', $variants)
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();

        foreach ($candidates as $candidate) {
            if (!$candidate instanceof Vehicule) {
                continue;
            }
            if ($this->normalizeVehicleQuery((string) $candidate->getPlaque()) === $normalized) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function buildPlateVariants(string $normalized, string $query): array
    {
        $variants = [
            mb_strtoupper(trim($query)),
            $normalized,
        ];

        if (strlen($normalized) === 7) {
            $variants[] = sprintf('%s-%s-%s', substr($normalized, 0, 2), substr($normalized, 2, 3), substr($normalized, 5, 2));
            $variants[] = sprintf('%s %s %s', substr($normalized, 0, 2), substr($normalized, 2, 3), substr($normalized, 5, 2));
        }

        return array_values(array_unique(array_filter($variants)));
    }

    private function isPublicBookingEnabled(int $atelierId): bool
    {
        $config = $this->em->getRepository(ConfigAtelier::class)
            ->createQueryBuilder('c')
            ->where('c.atelierId = :atelier')
            ->setParameter('atelier', $atelierId)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$config) {
            return false;
        }

        $modules = $config->getFeatureModules();
        return !empty($modules['public_booking']);
    }
}
