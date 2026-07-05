<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Client;
use App\Entity\EtatDesLieux;
use App\Entity\Notification;
use App\Entity\OrdreReparation;
use App\Entity\PhotoIntervention;
use App\Entity\RendezVous;
use App\Service\AuditService;
use App\Service\MercureNotifier;
use App\Service\OrdreReparationPolicy;
use App\Service\PublicTokenPolicy;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Public appointment tracking endpoint (no auth required).
 * Finds an active appointment by client email + phone.
 */
#[Route('/api/public')]
class PublicSuiviController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private RateLimiterFactory $publicBookingLimiter,
        private OrdreReparationPolicy $ordreReparationPolicy,
        private AuditService $auditService,
        private MercureNotifier $mercureNotifier,
        private PublicTokenPolicy $publicTokenPolicy,
    ) {}

    #[Route('/suivi', methods: ['POST'])]
    public function suivi(Request $request): JsonResponse
    {
        $limiter = $this->publicBookingLimiter->create($request->getClientIp());
        if (!$limiter->consume()->isAccepted()) {
            return $this->json(['error' => 'Too many requests'], Response::HTTP_TOO_MANY_REQUESTS);
        }

        $data = json_decode($request->getContent(), true);
        $email = strtolower(trim($data['email'] ?? ''));
        $telephone = preg_replace('/[\s\-\.]+/', '', $data['telephone'] ?? '');

        if (empty($email) || empty($telephone)) {
            return $this->json(['error' => 'email and telephone are required'], Response::HTTP_BAD_REQUEST);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->json(['error' => 'Invalid email format'], Response::HTTP_BAD_REQUEST);
        }

        // Find client by email and phone
        $client = $this->em->getRepository(Client::class)
            ->createQueryBuilder('c')
            ->where('LOWER(c.email) = :email')
            ->andWhere('c.telephone = :telephone')
            ->setParameter('email', $email)
            ->setParameter('telephone', $telephone)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$client) {
            return $this->json(['error' => 'Aucun rendez-vous trouvé pour ces coordonnées.'], Response::HTTP_NOT_FOUND);
        }

        // Find active RDV for this client
        $rdv = $this->em->getRepository(RendezVous::class)
            ->createQueryBuilder('r')
            ->where('r.client = :client')
            ->andWhere('r.statut NOT IN (:excluded)')
            ->setParameter('client', $client)
            ->setParameter('excluded', ['termine', 'annule'])
            ->orderBy('r.dateRdv', 'DESC')
            ->addOrderBy('r.heureRdv', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$rdv) {
            return $this->json(['error' => 'Aucun rendez-vous actif trouvé pour ce client.'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->buildSuiviPayload($rdv, $client));
    }

    /**
     * Suivi direct par token (lien « Suivez l'avancement » des emails).
     */
    #[Route('/suivi/token/{token}', methods: ['GET'])]
    public function suiviByToken(string $token, Request $request): JsonResponse
    {
        $limiter = $this->publicBookingLimiter->create($request->getClientIp());
        if (!$limiter->consume()->isAccepted()) {
            return $this->json(['error' => 'Too many requests'], Response::HTTP_TOO_MANY_REQUESTS);
        }

        $rdv = strlen($token) >= 16
            ? $this->em->getRepository(RendezVous::class)->findOneBy(['tokenSuivi' => $token])
            : null;

        if (!$rdv || !$rdv->getClient()) {
            return $this->json(['error' => 'Lien de suivi invalide.'], Response::HTTP_NOT_FOUND);
        }

        if ($this->isTokenExpired($rdv)) {
            return $this->json(['error' => 'Ce lien de suivi a expiré.'], Response::HTTP_GONE);
        }

        return $this->json($this->buildSuiviPayload($rdv, $rdv->getClient()));
    }

    private function buildSuiviPayload(RendezVous $rdv, Client $client): array
    {
        return [
            'rdv' => [
                'id' => $rdv->getId(),
                'date' => $rdv->getDateRdv()?->format('Y-m-d'),
                'heure' => $rdv->getHeureRdv()?->format('H:i'),
                'statut' => $rdv->getStatut(),
                'type_intervention' => $rdv->getTypeIntervention(),
                'temps_estime' => $rdv->getTempsEstime(),
                'pont' => $rdv->getPont()?->getNom(),
                'mecanicien' => $rdv->getMecanicien()
                    ? $rdv->getMecanicien()->getPrenom() . ' ' . $rdv->getMecanicien()->getNom()
                    : null,
            ],
            'client' => [
                'nom' => $client->getNom(),
                'prenom' => $client->getPrenom(),
                'telephone' => $client->getTelephone(),
                'email' => $client->getEmail(),
            ],
            'etat_des_lieux' => $this->buildEtatDesLieuxPayload($rdv),
        ];
    }

    /**
     * Lot B — état des lieux d'entrée exposé au public tokenisé, visible dès
     * signature (gate signedHash, même règle que le portail client). Les
     * photos viennent EXCLUSIVEMENT du snapshot figé à la signature : une
     * photo checkin/reception ajoutée ou retypée après signature n'apparaît
     * jamais dans le document public (invariant « document gelé »).
     */
    private function buildEtatDesLieuxPayload(RendezVous $rdv): ?array
    {
        $etatDesLieux = $this->em->getRepository(EtatDesLieux::class)->findOneBy(['rendezVous' => $rdv]);
        if (!$etatDesLieux || !$etatDesLieux->isSigned()) {
            return null;
        }

        $niveauCarburant = $etatDesLieux->getNiveauCarburant();

        return [
            'signe' => true,
            'signed_at' => $etatDesLieux->getSignedAt()?->format('c'),
            'kilometrage' => $etatDesLieux->getKilometrage(),
            'niveau_carburant' => $niveauCarburant,
            'niveau_carburant_label' => $niveauCarburant !== null
                ? (EtatDesLieux::NIVEAUX_CARBURANT_LABELS[$niveauCarburant] ?? $niveauCarburant)
                : null,
            'observations' => $etatDesLieux->getObservations(),
            'photos' => $this->collectSignedSnapshotPhotoUrls($rdv, $etatDesLieux),
        ];
    }

    /**
     * URLs publiques des photos de l'EDL signé : intersection entre les
     * filenames figés dans signedSnapshot['photos'] et les photos du RDV
     * (pour ne servir que des fichiers existants et appartenant au RDV).
     * Ordre = celui du snapshot (déterministe, figé à la signature).
     */
    private function collectSignedSnapshotPhotoUrls(RendezVous $rdv, EtatDesLieux $etatDesLieux): array
    {
        $snapshotPhotos = $etatDesLieux->getSignedSnapshot()['photos'] ?? [];
        if (!is_array($snapshotPhotos) || $snapshotPhotos === []) {
            return [];
        }

        $photosByFilename = [];
        foreach ($rdv->getPhotosIntervention() as $photo) {
            $photosByFilename[$photo->getFilename()] = $photo;
        }

        $urls = [];
        foreach ($snapshotPhotos as $snapshotPhoto) {
            $filename = is_array($snapshotPhoto) ? ($snapshotPhoto['filename'] ?? null) : null;
            if (is_string($filename) && isset($photosByFilename[$filename])) {
                $urls[] = $this->buildPublicPhotoUrl($rdv, $photosByFilename[$filename]);
            }
        }

        return $urls;
    }

    /** @param string[] $types */
    private function collectPublicPhotoUrls(RendezVous $rdv, array $types): array
    {
        $urls = [];
        foreach ($rdv->getPhotosIntervention() as $photo) {
            if (in_array($photo->getType(), $types, true)) {
                $urls[] = $this->buildPublicPhotoUrl($rdv, $photo);
            }
        }

        return $urls;
    }

    /** Même pattern que CompanionController::buildPublicPhotoUrl (PublicPhotoController). */
    private function buildPublicPhotoUrl(RendezVous $rdv, PhotoIntervention $photo): string
    {
        return '/api/public/photos/' . $rdv->getTokenSuivi() . '/' . $photo->getFilename();
    }

    /**
     * Règle d'expiration mutualisée (PublicTokenPolicy) : token valide tant
     * que le RDV vit, puis dateRdv + 30 jours après clôture. Même règle que
     * PublicPhotoController::serve — ne jamais les faire diverger.
     */
    private function isTokenExpired(RendezVous $rdv): bool
    {
        return $this->publicTokenPolicy->isTokenExpired($rdv);
    }

    /**
     * OR principal du RDV, sélectionné de façon DÉTERMINISTE : discriminant
     * typeOr='initial' (les OR complémentaires créés à l'acceptation d'une
     * demande de travaux supp sont typés 'complementaire' —
     * DemandeTravauxSuppDecisionService::createOrComplementaire), tri id ASC.
     * Fallback plus ancien OR du RDV pour les données historiques sans typeOr.
     * Remplace le ->first() arbitraire sur une collection sans OrderBy.
     */
    private function findOrdrePrincipal(RendezVous $rdv): ?OrdreReparation
    {
        $repo = $this->em->getRepository(OrdreReparation::class);

        return $repo->findOneBy(['rendezVous' => $rdv, 'typeOr' => 'initial'], ['id' => 'ASC'])
            ?? $repo->findOneBy(['rendezVous' => $rdv], ['id' => 'ASC']);
    }

    /**
     * Public endpoint to retrieve restitution data for a given tracking token.
     * Returns RDV + OR info so the client can review and sign before pickup.
     */
    #[Route('/restitution/{token}', methods: ['GET'])]
    public function restitutionData(string $token, Request $request): JsonResponse
    {
        $limiter = $this->publicBookingLimiter->create($request->getClientIp());
        if (!$limiter->consume()->isAccepted()) {
            return $this->json(['error' => 'Too many requests'], Response::HTTP_TOO_MANY_REQUESTS);
        }

        $rdv = strlen($token) >= 16
            ? $this->em->getRepository(RendezVous::class)->findOneBy(['tokenSuivi' => $token])
            : null;

        if (!$rdv) {
            return $this->json(['error' => 'Lien de restitution invalide.'], Response::HTTP_NOT_FOUND);
        }

        if ($this->isTokenExpired($rdv)) {
            return $this->json(['error' => 'Ce lien de restitution a expiré.'], Response::HTTP_GONE);
        }

        $ordre = $this->findOrdrePrincipal($rdv);

        // Only allow restitution when work is done (mechanic signed)
        if (!$ordre || !$this->ordreReparationPolicy->canSignRestitution($ordre)) {
            return $this->json([
                'error' => 'Véhicule non prêt pour restitution.',
                'code' => 'NOT_READY',
            ], Response::HTTP_BAD_REQUEST);
        }

        return $this->json([
            'rdv' => [
                'id' => $rdv->getId(),
                'date' => $rdv->getDateRdv()?->format('Y-m-d'),
                'heure' => $rdv->getHeureRdv()?->format('H:i'),
                'statut' => $rdv->getStatut(),
                'type_intervention' => $rdv->getTypeIntervention(),
                'pont' => $rdv->getPont()?->getNom(),
                'mecanicien' => $rdv->getMecanicien()
                    ? $rdv->getMecanicien()->getPrenom() . ' ' . $rdv->getMecanicien()->getNom()
                    : null,
            ],
            'client' => [
                'nom' => $rdv->getClient()?->getNom(),
                'prenom' => $rdv->getClient()?->getPrenom(),
                'telephone' => $rdv->getClient()?->getTelephone(),
            ],
            'vehicule' => [
                'marque' => $rdv->getVehicule()?->getMarque(),
                'modele' => $rdv->getVehicule()?->getModele(),
                'plaque' => $rdv->getVehicule()?->getPlaque(),
            ],
            'ordre' => [
                'id' => $ordre->getId(),
                'travaux_realises' => $ordre->getTravauxRealises(),
                'alertes' => $ordre->getAlertes(),
                'recommandations' => $ordre->getRecommandations(),
                'garantie' => $ordre->getGarantie(),
                'kilometrage_restitution' => $ordre->getKilometrageRestitution(),
                'prochaine_revision_km' => $ordre->getProchaineRevisionKm(),
                'prochaine_revision_date' => $ordre->getProchaineRevisionDate()?->format('Y-m-d'),
                'signature_mecanicien' => $ordre->getSignatureMecanicien() !== null,
                'signature_client_restitution' => $ordre->getSignatureClientRestitution() !== null,
            ],
            // Lot B — comparatif avant/après à la restitution : état des lieux
            // d'entrée (photos checkin/reception) + photos de sortie tokenisées.
            'etat_des_lieux' => $this->buildEtatDesLieuxPayload($rdv),
            'photos_restitution' => $this->collectPublicPhotoUrls($rdv, ['restitution']),
        ]);
    }

    /**
     * Public endpoint to sign the restitution (client signature at pickup).
     */
    #[Route('/restitution/{token}/sign', methods: ['POST'])]
    public function signRestitution(string $token, Request $request): JsonResponse
    {
        $limiter = $this->publicBookingLimiter->create($request->getClientIp());
        if (!$limiter->consume()->isAccepted()) {
            return $this->json(['error' => 'Too many requests'], Response::HTTP_TOO_MANY_REQUESTS);
        }

        $rdv = strlen($token) >= 16
            ? $this->em->getRepository(RendezVous::class)->findOneBy(['tokenSuivi' => $token])
            : null;

        if (!$rdv) {
            return $this->json(['error' => 'Lien de restitution invalide.'], Response::HTTP_NOT_FOUND);
        }

        if ($this->isTokenExpired($rdv)) {
            return $this->json(['error' => 'Ce lien de restitution a expiré.'], Response::HTTP_GONE);
        }

        $ordre = $this->findOrdrePrincipal($rdv);

        if (!$ordre || !$this->ordreReparationPolicy->canSignRestitution($ordre)) {
            return $this->json([
                'error' => 'Restitution non autorisée.',
                'code' => 'NOT_READY',
            ], Response::HTTP_BAD_REQUEST);
        }

        $data = json_decode($request->getContent(), true);
        $signature = trim((string) ($data['signature'] ?? ''));

        if (empty($signature) || !str_starts_with($signature, 'data:image')) {
            return $this->json(['error' => 'Signature invalide.'], Response::HTTP_BAD_REQUEST);
        }

        $this->ordreReparationPolicy->signRestitution($ordre, $signature);

        // Lot B — litige à la restitution : champs sur le RDV (pas sur l'OR gelé).
        // Commentaire stocké brut (pas de HTML), tronqué à 2000 caractères.
        $litigeSignale = filter_var($data['litige_signale'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $litigeNotif = null;
        if ($litigeSignale) {
            $litigeCommentaire = mb_substr(trim((string) ($data['litige_commentaire'] ?? '')), 0, 2000);
            $rdv->setLitigeSignale(true);
            $rdv->setLitigeCommentaire($litigeCommentaire !== '' ? $litigeCommentaire : null);

            $litigeNotif = $this->buildLitigeNotification($rdv);
            if ($litigeNotif) {
                $this->em->persist($litigeNotif);
            }
        }

        $this->em->flush();

        if ($litigeSignale) {
            $this->auditService->log(
                'litige_restitution_signale',
                'RendezVous',
                $rdv->getId(),
                sprintf('Litige signalé à la restitution — RDV #%d', $rdv->getId()),
            );
        }

        // Publication après flush : le payload Mercure embarque l'id de la
        // notification (pattern DemandeTravauxSuppDecisionService).
        if ($litigeNotif) {
            try {
                $this->mercureNotifier->publishToAtelier($litigeNotif->getAtelierId(), $litigeNotif);
            } catch (\Throwable) {
                // Mercure indisponible : la notification reste visible dans la cloche
            }
        }

        return $this->json([
            'success' => true,
            'message' => 'Restitution signée avec succès.',
            'statut' => $ordre->getStatut(),
        ]);
    }

    /**
     * Le staff doit savoir SANS DÉLAI qu'un litige a été signalé à la
     * restitution : cloche sévérité haute (pop-in immédiat côté front).
     */
    private function buildLitigeNotification(RendezVous $rdv): ?Notification
    {
        $atelierId = $rdv->getAtelierId();
        if (!$atelierId) {
            return null;
        }

        $client = $rdv->getClient();

        $notif = new Notification();
        $notif->setAtelierId($atelierId);
        $notif->setType('litige_restitution');
        $notif->setSeverity('warning');
        $notif->setTitle('Litige signalé à la restitution');
        $notif->setMessage(sprintf(
            'Litige signalé à la restitution — RDV #%d, client %s %s (RDV du %s)',
            $rdv->getId(),
            $client?->getPrenom() ?? 'Le',
            $client?->getNom() ?? 'client',
            $rdv->getDateRdv()?->format('d/m/Y') ?? '?',
        ));
        $notif->setRelatedEntityType('RendezVous');
        $notif->setRelatedEntityId($rdv->getId());
        $notif->setTargetRoles(['ROLE_RECEPTIONNAIRE', 'ROLE_ADMIN', 'ROLE_MECANICIEN']);
        $notif->setPriority('high');

        return $notif;
    }
}
