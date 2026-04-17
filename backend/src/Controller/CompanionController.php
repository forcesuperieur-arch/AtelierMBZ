<?php
namespace App\Controller;

use App\Entity\OrdreReparation;
use App\Entity\PhotoIntervention;
use App\Entity\RendezVous;
use App\Service\OrdreReparationPolicy;
use App\Service\PrestationCatalogService;
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
        private RateLimiterFactory $companionUploadLimiter,
        private OrdreReparationPolicy $orPolicy,
        private PrestationCatalogService $catalogService,
    ) {}

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
        $checkup = is_array($state['mechanic_checkup'] ?? null) ? $state['mechanic_checkup'] : [];
        $notes = is_string($state['mechanic_notes'] ?? null) ? $state['mechanic_notes'] : '';
        $done = count(array_filter($checkup, fn($value) => in_array($value, ['ok', 'nok'], true)));

        return [
            'checkup' => $checkup,
            'checkup_notes' => $notes,
            'checkup_done' => $done,
        ];
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
                'annee' => $vehicule->getAnnee(),
                'cylindree' => $vehicule->getCylindree(),
                'type_moto' => $vehicule->getTypeMoto(),
            ] : null,
            'photos' => array_map(fn(PhotoIntervention $p) => [
                'id' => $p->getId(),
                'filename' => $p->getFilename(),
                'description' => $p->getDescription(),
                'url' => '/api/photos/file/' . $p->getFilename(),
            ], $photos),
            'has_signature' => $or && $or->getSignatureClient() ? true : false,
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
            'has_signature' => $or && $or->getSignatureClient() ? true : false,
            'kilometrage' => $rdv->getKilometrage(),
            'etat_vehicule' => $rdv->getEtatVehicule(),
            'checkup_done' => $checkupState['checkup_done'],
        ]);
    }

    #[Route('/{token}/photo', methods: ['POST'])]
    public function uploadPhoto(string $token, Request $request): JsonResponse
    {
        $limiter = $this->companionUploadLimiter->create($token);
        if (!$limiter->consume()->isAccepted()) {
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

        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
        if (!in_array($file->getMimeType(), $allowedMimes, true)) {
            return $this->json(['error' => 'Format non supporté (JPEG, PNG, WebP uniquement)'], Response::HTTP_BAD_REQUEST);
        }
        if ($file->getSize() > 10 * 1024 * 1024) {
            return $this->json(['error' => 'Fichier trop volumineux (max 10 Mo)'], Response::HTTP_BAD_REQUEST);
        }

        $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeName = $this->slugger->slug($originalName);
        $filename = $safeName . '-' . uniqid() . '.' . $file->guessExtension();

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
            'url' => '/api/photos/file/' . $filename,
        ], Response::HTTP_CREATED);
    }

    #[Route('/{token}/signature', methods: ['POST'])]
    public function saveSignature(string $token, Request $request): JsonResponse
    {
        $rdv = $this->findRdvByToken($token);
        if (!$rdv) {
            return $this->json(['error' => 'Lien invalide'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $signatureData = $data['signature'] ?? null;

        if (!$signatureData || !str_starts_with($signatureData, 'data:image/')) {
            return $this->json(['error' => 'Signature invalide'], Response::HTTP_BAD_REQUEST);
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

        if (!$this->orPolicy->canSign($or)) {
            return $this->json(['error' => 'Cet OR ne peut plus être signé'], Response::HTTP_CONFLICT);
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

        // Sign via policy: snapshot, hash, freeze
        $hash = $this->orPolicy->sign($or, $signatureData, $request);

        $this->em->flush();

        return $this->json([
            'success' => true,
            'message' => 'Signature enregistrée',
            'hash' => $hash,
        ]);
    }

    #[Route('/{token}/vehicule', methods: ['PUT'])]
    public function updateVehicule(string $token, Request $request): JsonResponse
    {
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
                'annee' => $vehicule->getAnnee(),
                'cylindree' => $vehicule->getCylindree(),
                'type_moto' => $vehicule->getTypeMoto(),
            ],
        ]);
    }

    #[Route('/{token}/reception-data', methods: ['PUT'])]
    public function updateReceptionData(string $token, Request $request): JsonResponse
    {
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

    #[Route('/{token}/prestations-disponibles', methods: ['GET'])]
    public function getPrestationsDisponibles(string $token): JsonResponse
    {
        $rdv = $this->findRdvByToken($token);
        if (!$rdv) {
            return $this->json(['error' => 'Lien invalide'], Response::HTTP_NOT_FOUND);
        }

        $vehicule = $rdv->getVehicule();
        if (!$vehicule) {
            return $this->json(['error' => 'Aucun véhicule lié au rendez-vous'], Response::HTTP_BAD_REQUEST);
        }

        $applicable = $this->catalogService->getApplicablePrestations($vehicule);

        $items = array_map(fn(array $entry) => [
            'id' => $entry['prestation']->getId(),
            'code' => $entry['prestation']->getCode(),
            'libelle' => $entry['prestation']->getLibelle(),
            'description' => $entry['prestation']->getDescription(),
            'categorie' => $entry['prestation']->getCategorie(),
            'prix_ht' => $entry['prix_ht'],
            'prix_ttc' => $entry['prix_ttc'],
            'temps_minutes' => $entry['temps_minutes'],
            'mode' => $entry['mode'],
            'garantie_jours' => $entry['prestation']->getGarantieJours(),
            'necessite_essai' => $entry['prestation']->getNecessiteEssai(),
        ], $applicable);

        return $this->json([
            'vehicule' => [
                'plaque' => $vehicule->getPlaque(),
                'marque' => $vehicule->getMarque(),
                'modele' => $vehicule->getModele(),
                'categorie' => $vehicule->getCategorie()?->getNom(),
            ],
            'prestations' => $items,
            'count' => count($items),
        ]);
    }
}
