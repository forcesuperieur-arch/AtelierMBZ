<?php
namespace App\Controller;

use App\Entity\OrdreReparation;
use App\Entity\PhotoIntervention;
use App\Entity\RendezVous;
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
        private RateLimiterFactory $companionUploadLimiter,
    ) {}

    private function findRdvByToken(string $token): ?RendezVous
    {
        if (strlen($token) < 16) return null;
        return $this->em->getRepository(RendezVous::class)->findOneBy(['tokenSuivi' => $token]);
    }

    /** Limite les écritures token-only (signature, véhicule, photos) : 429 au-delà. */
    private function writeLimitExceeded(Request $request): bool
    {
        $limiter = $this->companionUploadLimiter->create($request->getClientIp() ?? 'default');

        return !$limiter->consume()->isAccepted();
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
        $checkup = is_array($state['mechanic_checkup'] ?? null) ? $state['mechanic_checkup'] : [];
        $notes = is_string($state['mechanic_notes'] ?? null) ? $state['mechanic_notes'] : '';
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
    public function getRdvInfo(string $token): JsonResponse
    {
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
                'telephone' => $client->getTelephone(),
                'email' => $client->getEmail(),
            ] : null,
            'vehicule' => $vehicule ? [
                'id' => $vehicule->getId(),
                'plaque' => $vehicule->getPlaque(),
                'marque' => $vehicule->getMarque(),
                'modele' => $vehicule->getModele(),
                'vin' => $vehicule->getVin(),
                'annee' => $vehicule->getAnnee(),
                'cylindree' => $vehicule->getCylindree(),
                'type_moto' => $vehicule->getTypeMoto(),
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
    public function getStatus(string $token): JsonResponse
    {
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
        if ($this->writeLimitExceeded($request)) {
            return $this->json(['error' => 'Too many requests'], Response::HTTP_TOO_MANY_REQUESTS);
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

    #[Route('/{token}/signature', methods: ['POST'])]
    public function saveSignature(string $token, Request $request): JsonResponse
    {
        if ($this->writeLimitExceeded($request)) {
            return $this->json(['error' => 'Too many requests'], Response::HTTP_TOO_MANY_REQUESTS);
        }
        $rdv = $this->findRdvByToken($token);
        if (!$rdv) {
            return $this->json(['error' => 'Lien invalide'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $signatureClient = $data['signature_client'] ?? $data['signature'] ?? null;
        $signatureAtelier = $data['signature_atelier'] ?? null;
        $kilometrage = $data['kilometrage'] ?? null;

        if (!$signatureClient || !str_starts_with($signatureClient, 'data:image/')) {
            return $this->json(['error' => 'Signature client invalide'], Response::HTTP_BAD_REQUEST);
        }
        if (!$signatureAtelier || !str_starts_with($signatureAtelier, 'data:image/')) {
            return $this->json(['error' => 'Signature atelier invalide'], Response::HTTP_BAD_REQUEST);
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

        if ($kilometrage !== null && $kilometrage !== '') {
            $or->setKilometrage((int) $kilometrage);
            $rdv->setKilometrage((int) $kilometrage);
        } elseif ($rdv->getKilometrage()) {
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

        if (!$this->ordreReparationPolicy->canSignReception($or)) {
            return $this->json([
                'error' => 'Cette signature a déjà été finalisée',
                'statut' => $or->getStatut(),
            ], Response::HTTP_CONFLICT);
        }

        $hash = $this->ordreReparationPolicy->signReception($or, $signatureClient, $signatureAtelier, $request);

        $this->em->flush();

        return $this->json([
            'success' => true,
            'message' => 'Réception signée',
            'statut' => $or->getStatut(),
            'signedHash' => $hash,
        ]);
    }

    #[Route('/{token}/vehicule', methods: ['PUT'])]
    public function updateVehicule(string $token, Request $request): JsonResponse
    {
        if ($this->writeLimitExceeded($request)) {
            return $this->json(['error' => 'Too many requests'], Response::HTTP_TOO_MANY_REQUESTS);
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
        if ($this->writeLimitExceeded($request)) {
            return $this->json(['error' => 'Too many requests'], Response::HTTP_TOO_MANY_REQUESTS);
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

        if (isset($data['etat_vehicule'])) {
            $rdv->setEtatVehicule(is_array($data['etat_vehicule'])
                ? json_encode($data['etat_vehicule'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                : $data['etat_vehicule']);
        }

        $incomingCheckup = $data['checkup'] ?? $data['mechanic_checkup'] ?? null;
        $incomingNotes = $data['checkup_notes'] ?? $data['mechanic_notes'] ?? null;

        if (is_array($incomingCheckup) || is_string($incomingNotes)) {
            if (!$or) {
                $or = new OrdreReparation();
                $or->setRendezVous($rdv);
                $or->setNumeroOr('OR-' . $rdv->getId() . '-' . date('Ymd'));
                $or->setTypeOr('initial');
                $or->snapshotFromRdv();
                $this->em->persist($or);
            }

            $state = $this->decodeEtatVehicule($or->getEtatVehicule());
            if (is_array($incomingCheckup)) {
                $state['mechanic_checkup'] = $incomingCheckup;
            }
            if (is_string($incomingNotes)) {
                $state['mechanic_notes'] = $incomingNotes;
            }
            $state['last_mechanic_update_at'] = (new \DateTime())->format(DATE_ATOM);
            $or->setEtatVehicule(json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }

        $this->em->flush();

        return $this->json(['success' => true]);
    }
}
