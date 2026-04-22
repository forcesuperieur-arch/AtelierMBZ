<?php
namespace App\Controller;

use App\Entity\ClauseLegale;
use App\Entity\OrdreReparation;
use App\Entity\PhotoIntervention;
use App\Entity\RendezVous;
use App\Service\ClauseLegaleVisibilityService;
use App\Service\OrdreReparationPolicy;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/api/companion')]
class CompanionController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private SluggerInterface $slugger,
        private OrdreReparationPolicy $ordreReparationPolicy,
        private RateLimiterFactory $publicCompanionLimiter,
        private ClauseLegaleVisibilityService $clauseVisibilityService,
    ) {}

    private function ensureRateLimit(Request $request): ?JsonResponse
    {
        $limiter = $this->publicCompanionLimiter->create((string) $request->getClientIp());
        if ($limiter->consume()->isAccepted()) {
            return null;
        }

        return $this->json(['error' => 'Trop de requêtes'], Response::HTTP_TOO_MANY_REQUESTS);
    }

    private function findRdvByToken(string $token): ?RendezVous
    {
        if (strlen($token) < 16) return null;
        return $this->em->getRepository(RendezVous::class)->findOneBy(['tokenSuivi' => $token]);
    }

    private function decodeEtatVehicule(mixed $raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }

        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function buildCheckupState(RendezVous $rdv, ?OrdreReparation $or = null): array
    {
        $state = $this->decodeEtatVehicule($or?->getEtatVehicule() ?: $rdv->getEtatVehicule());
        $checkup = is_array($state['reception_checkup'] ?? null)
            ? $state['reception_checkup']
            : (is_array($state['mechanic_checkup'] ?? null) ? $state['mechanic_checkup'] : []);
        $notes = is_string($state['reception_notes'] ?? null)
            ? $state['reception_notes']
            : (is_string($state['mechanic_notes'] ?? null) ? $state['mechanic_notes'] : '');
        $done = count(array_filter($checkup, fn($value) => in_array($value, ['ok', 'nok'], true)));

        return [
            'checkup' => $checkup,
            'checkup_notes' => $notes,
            'checkup_done' => $done,
        ];
    }

    private function buildPublicPhotoUrl(RendezVous $rdv, PhotoIntervention $photo): string
    {
        return '/api/public/photos/' . $rdv->getTokenSuivi() . '/' . $photo->getFilename();
    }

    private function resolvePhotoExtension($file): string
    {
        $extension = strtolower((string) ($file->guessExtension() ?: pathinfo((string) $file->getClientOriginalName(), PATHINFO_EXTENSION)));

        return match ($extension) {
            'jpeg', 'jpg', 'pjpeg' => 'jpg',
            'png' => 'png',
            'webp' => 'webp',
            'heic' => 'heic',
            'heif' => 'heif',
            default => 'jpg',
        };
    }

    #[Route('/{token}', methods: ['GET'])]
    public function getRdvInfo(string $token, Request $request): JsonResponse
    {
        if ($response = $this->ensureRateLimit($request)) {
            return $response;
        }

        $rdv = $this->findRdvByToken($token);
        if (!$rdv) {
            return $this->json(['error' => 'Lien invalide ou expiré'], Response::HTTP_NOT_FOUND);
        }

        $client = $rdv->getClient();
        $vehicule = $rdv->getVehicule();

        $photos = $this->em->getRepository(PhotoIntervention::class)->findBy(
            ['rendezVous' => $rdv],
            ['createdAt' => 'DESC']
        );

        $or = $this->em->getRepository(OrdreReparation::class)->findOneBy(
            ['rendezVous' => $rdv],
            ['id' => 'DESC']
        );

        $checkupState = $this->buildCheckupState($rdv, $or);

        return $this->json([
            'id' => $rdv->getId(),
            'statut' => $rdv->getStatut(),
            'date_rdv' => $rdv->getDateRdv()->format('Y-m-d'),
            'heure_rdv' => $rdv->getHeureRdv()->format('H:i'),
            'type_intervention' => $rdv->getTypeIntervention(),
            'commentaire' => $rdv->getCommentaire(),
            'kilometrage' => $rdv->getKilometrage(),
            'etat_vehicule' => $rdv->getEtatVehicule(),
            'client' => $client ? [
                'id' => $client->getId(),
                'nom' => $client->getNom(),
                'prenom' => $client->getPrenom(),
            ] : null,
            'vehicule' => $vehicule ? [
                'id' => $vehicule->getId(),
                'plaque' => $vehicule->getPlaque(),
                'marque' => $vehicule->getMarque(),
                'modele' => $vehicule->getModele(),
            ] : null,
            'photos' => array_map(fn(PhotoIntervention $p) => [
                'id' => $p->getId(),
                'filename' => $p->getFilename(),
                'description' => $p->getDescription(),
                'url' => $this->buildPublicPhotoUrl($rdv, $p),
            ], $photos),
            'has_signature' => $or?->isSigned() ?? false,
            'or_status' => $or?->getStatut(),
            'photos_count' => count($photos),
            ...$checkupState,
        ]);
    }

    #[Route('/{token}/status', methods: ['GET'])]
    public function getStatus(string $token, Request $request): JsonResponse
    {
        if ($response = $this->ensureRateLimit($request)) {
            return $response;
        }

        $rdv = $this->findRdvByToken($token);
        if (!$rdv) {
            return $this->json(['error' => 'Lien invalide'], Response::HTTP_NOT_FOUND);
        }

        $photosCount = $this->em->getRepository(PhotoIntervention::class)->count(['rendezVous' => $rdv]);

        $or = $this->em->getRepository(OrdreReparation::class)->findOneBy(
            ['rendezVous' => $rdv],
            ['id' => 'DESC']
        );

        $checkupState = $this->buildCheckupState($rdv, $or);

        return $this->json([
            'statut' => $rdv->getStatut(),
            'photos_count' => $photosCount,
            'has_signature' => $or?->isSigned() ?? false,
            'or_status' => $or?->getStatut(),
            'kilometrage' => $rdv->getKilometrage(),
            'etat_vehicule' => $rdv->getEtatVehicule(),
            'checkup_done' => $checkupState['checkup_done'],
        ]);
    }

    #[Route('/{token}/photo', methods: ['POST'])]
    public function uploadPhoto(string $token, Request $request): JsonResponse
    {
        if ($response = $this->ensureRateLimit($request)) {
            return $response;
        }

        $rdv = $this->findRdvByToken($token);
        if (!$rdv) {
            return $this->json(['error' => 'Lien invalide'], Response::HTTP_NOT_FOUND);
        }

        $file = $request->files->get('photo');
        if (!$file) {
            return $this->json(['error' => 'Photo requise'], Response::HTTP_BAD_REQUEST);
        }

        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/heic', 'image/heif'];
        if (!in_array((string) $file->getMimeType(), $allowedMimes, true)) {
            return $this->json(['error' => 'Format non supporté (JPEG, PNG, WebP, HEIC)'], Response::HTTP_BAD_REQUEST);
        }
        if ($file->getSize() > 10 * 1024 * 1024) {
            return $this->json(['error' => 'Fichier trop volumineux (max 10 Mo)'], Response::HTTP_BAD_REQUEST);
        }

        $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeName = (string) $this->slugger->slug($originalName ?: 'photo');
        $extension = $this->resolvePhotoExtension($file);
        $filename = $safeName . '-' . uniqid() . '.' . $extension;

        $uploadDir = $this->getParameter('kernel.project_dir') . '/var/photos';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        $file->move($uploadDir, $filename);

        $photo = new PhotoIntervention();
        $photo->setRendezVous($rdv);
        $photo->setFilename($filename);
        $photo->setOriginalName($file->getClientOriginalName());
        $photo->setDescription($request->request->get('description', 'Photo réception'));
        $photo->setAtelierId($rdv->getAtelierId());

        $this->em->persist($photo);
        $this->em->flush();

        return $this->json([
            'id' => $photo->getId(),
            'filename' => $filename,
            'url' => $this->buildPublicPhotoUrl($rdv, $photo),
        ], Response::HTTP_CREATED);
    }

    /**
     * [C5] Retourne les clauses légales actives à afficher avant signature OR.
     * Codes requis : cgv, garantie, rgpd
     */
    #[Route('/{token}/clauses', methods: ['GET'])]
    public function getClauses(string $token, Request $request): JsonResponse
    {
        if ($response = $this->ensureRateLimit($request)) {
            return $response;
        }

        $rdv = $this->findRdvByToken($token);
        if (!$rdv) {
            return $this->json(['error' => 'Lien invalide'], Response::HTTP_NOT_FOUND);
        }

        $atelierId = $rdv->getAtelierId();
        $requiredCodes = ['cgv', 'garantie', 'rgpd'];

        $clauses = $this->em->getRepository(ClauseLegale::class)->createQueryBuilder('c')
            ->where('c.code IN (:codes)')
            ->andWhere('c.atelierId = :atelierId OR c.atelierId IS NULL')
            ->andWhere('c.isActive = true')
            ->setParameter('codes', $requiredCodes)
            ->setParameter('atelierId', $atelierId)
            ->getQuery()
            ->getResult();

        $visible = $this->clauseVisibilityService->pickVisibleClauses($clauses, true);

        return $this->json(array_map(fn(ClauseLegale $c) => [
            'code' => $c->getCode(),
            'libelle' => $c->getLibelle(),
            'texte' => $c->getTexte(),
            'version' => $c->getVersion(),
        ], $visible));
    }

    #[Route('/{token}/signature', methods: ['POST'])]
    public function saveSignature(string $token, Request $request): JsonResponse
    {
        if ($response = $this->ensureRateLimit($request)) {
            return $response;
        }

        $rdv = $this->findRdvByToken($token);
        if (!$rdv) {
            return $this->json(['error' => 'Lien invalide'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $signatureData = $data['signature'] ?? null;

        if (!$signatureData || !str_starts_with($signatureData, 'data:image/')) {
            return $this->json(['error' => 'Signature invalide'], Response::HTTP_BAD_REQUEST);
        }

        // [C5] Vérifier que les clauses obligatoires ont été acceptées
        $requiredCodes = ['cgv', 'garantie', 'rgpd'];
        $acceptedCodes = (array) ($data['clausesAcceptees'] ?? []);
        $missingCodes = array_diff($requiredCodes, $acceptedCodes);
        if (!empty($missingCodes)) {
            return $this->json([
                'error' => 'Vous devez accepter toutes les conditions avant de signer.',
                'clausesManquantes' => array_values($missingCodes),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Find or create OR for this RDV
        $or = $this->em->getRepository(OrdreReparation::class)->findOneBy(
            ['rendezVous' => $rdv],
            ['id' => 'DESC']
        );

        if (!$or) {
            $or = new OrdreReparation();
            $or->setRendezVous($rdv);
            $or->setNumeroOr('OR-' . $rdv->getId() . '-' . date('Ymd'));
            $or->setTypeOr('initial');
            $or->snapshotFromRdv();
            $this->em->persist($or);
        }

        if ($rdv->getKilometrage()) {
            $or->setKilometrage($rdv->getKilometrage());
        }
        if ($rdv->getEtatVehicule()) {
            $orState = $this->decodeEtatVehicule($or->getEtatVehicule());
            $rdvState = $this->decodeEtatVehicule($rdv->getEtatVehicule());

            if (!empty($orState) || !empty($rdvState)) {
                $or->setEtatVehicule(json_encode(
                    array_replace_recursive($orState, $rdvState),
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                ));
            } elseif (!$or->getEtatVehicule()) {
                $or->setEtatVehicule($rdv->getEtatVehicule());
            }
        }

        if (!$this->ordreReparationPolicy->canSign($or)) {
            return $this->json([
                'error' => 'Cette signature a déjà été finalisée',
                'statut' => $or->getStatut(),
            ], Response::HTTP_CONFLICT);
        }

        $hash = $this->ordreReparationPolicy->sign($or, $signatureData, $request);

        $this->em->flush();

        return $this->json([
            'success' => true,
            'message' => 'Signature enregistrée',
            'statut' => $or->getStatut(),
            'signedHash' => $hash,
        ]);
    }

    #[Route('/{token}/vehicule', methods: ['PUT'])]
    public function updateVehicule(string $token, Request $request): JsonResponse
    {
        if ($response = $this->ensureRateLimit($request)) {
            return $response;
        }

        $rdv = $this->findRdvByToken($token);
        if (!$rdv) {
            return $this->json(['error' => 'Lien invalide'], Response::HTTP_NOT_FOUND);
        }

        $vehicule = $rdv->getVehicule();
        if (!$vehicule) {
            return $this->json(['error' => 'Aucun véhicule lié'], Response::HTTP_BAD_REQUEST);
        }

        $data = json_decode($request->getContent(), true) ?? [];

        if (isset($data['marque'])) $vehicule->setMarque($data['marque']);
        if (isset($data['modele'])) $vehicule->setModele($data['modele']);
        if (isset($data['vin'])) $vehicule->setVin($data['vin'] ? strtoupper(substr((string) $data['vin'], 0, 17)) : null);
        if (isset($data['annee']) && (int)$data['annee'] > 0) $vehicule->setAnnee((int) $data['annee']);
        if (isset($data['cylindree'])) $vehicule->setCylindree((string) $data['cylindree']);
        if (isset($data['type_moto'])) $vehicule->setTypeMoto($data['type_moto']);
        if (isset($data['plaque'])) $vehicule->setPlaque($data['plaque']);

        $this->em->flush();

        return $this->json([
            'success' => true,
            'vehicule' => [
                'plaque' => $vehicule->getPlaque(),
                'marque' => $vehicule->getMarque(),
                'modele' => $vehicule->getModele(),
                'vin' => $vehicule->getVin(),
                'annee' => $vehicule->getAnnee(),
                'cylindree' => $vehicule->getCylindree(),
                'type_moto' => $vehicule->getTypeMoto(),
            ],
        ]);
    }

    #[Route('/{token}/reception-data', methods: ['PUT'])]
    public function updateReceptionData(string $token, Request $request): JsonResponse
    {
        if ($response = $this->ensureRateLimit($request)) {
            return $response;
        }

        $rdv = $this->findRdvByToken($token);
        if (!$rdv) {
            return $this->json(['error' => 'Lien invalide'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true) ?? [];

        $or = $this->em->getRepository(OrdreReparation::class)->findOneBy(
            ['rendezVous' => $rdv],
            ['id' => 'DESC']
        );

        if (isset($data['kilometrage']) && $data['kilometrage'] !== '') {
            $kilometrage = (int) $data['kilometrage'];
            $rdv->setKilometrage($kilometrage);
            if ($or) {
                $or->setKilometrage($kilometrage);
            }
        }

        $state = $this->decodeEtatVehicule($rdv->getEtatVehicule());

        if (isset($data['etat_vehicule'])) {
            if (is_array($data['etat_vehicule'])) {
                $state = array_replace_recursive($state, $data['etat_vehicule']);
            } elseif (is_string($data['etat_vehicule']) && trim($data['etat_vehicule']) !== '') {
                $state['observations'] = trim($data['etat_vehicule']);
            }
        }

        $incomingCheckup = $data['checkup'] ?? $data['mechanic_checkup'] ?? null;
        $incomingNotes = $data['checkup_notes'] ?? $data['mechanic_notes'] ?? null;

        if (is_array($incomingCheckup) || is_string($incomingNotes)) {
            if (is_array($incomingCheckup)) {
                $state['reception_checkup'] = $incomingCheckup;
            }
            if (is_string($incomingNotes)) {
                $state['reception_notes'] = $incomingNotes;
            }
            $state['last_reception_update_at'] = (new \DateTime())->format(DATE_ATOM);
        }

        $serializedState = json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (is_string($serializedState)) {
            $rdv->setEtatVehicule($serializedState);
            if ($or) {
                $or->setEtatVehicule($serializedState);
            }
        }

        $this->em->flush();

        return $this->json(['success' => true]);
    }
}
