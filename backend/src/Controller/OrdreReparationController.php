<?php

namespace App\Controller;

use App\Entity\OrdreReparation;
use App\Entity\User;
use App\Service\AuditService;
use App\Service\OrdreReparationPolicy;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/or')]
class OrdreReparationController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private OrdreReparationPolicy $orPolicy,
        private AuditService $auditService,
    ) {}

    #[Route('/{id}/rectifier', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function rectifier(int $id, Request $request): JsonResponse
    {
        $or = $this->em->getRepository(OrdreReparation::class)->find($id);
        if (!$or) {
            return $this->json(['error' => 'OR non trouvé'], Response::HTTP_NOT_FOUND);
        }

        /** @var User $user */
        $user = $this->getUser();

        if (!$this->orPolicy->canRectify($or, $user)) {
            return $this->json(
                ['error' => 'Vous ne pouvez pas rectifier cet OR'],
                Response::HTTP_FORBIDDEN
            );
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $motif = trim($data['motif'] ?? '');
        if ($motif === '') {
            return $this->json(['error' => 'Le motif de rectification est requis'], Response::HTTP_BAD_REQUEST);
        }

        $rectified = $this->orPolicy->rectify($or, $user, $motif);
        $this->em->persist($rectified);
        $this->em->flush();

        $this->auditService->log('or_rectification', 'OrdreReparation', $or->getId(), json_encode([
            'original_id' => $or->getId(),
            'rectified_id' => $rectified->getId(),
            'motif' => $motif,
        ], JSON_UNESCAPED_UNICODE));

        return $this->json([
            'success' => true,
            'rectified_or_id' => $rectified->getId(),
            'numero_or' => $rectified->getNumeroOr(),
            'statut' => $rectified->getStatut(),
        ], Response::HTTP_CREATED);
    }

    #[Route('/{id}', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function show(int $id): JsonResponse
    {
        $or = $this->em->getRepository(OrdreReparation::class)->find($id);
        if (!$or) {
            return $this->json(['error' => 'OR non trouvé'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($or);
    }

    #[Route('/{id}/photos', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function photos(int $id): JsonResponse
    {
        $or = $this->em->getRepository(OrdreReparation::class)->find($id);
        if (!$or) {
            return $this->json(['error' => 'OR non trouvé'], Response::HTTP_NOT_FOUND);
        }

        $rdv = $or->getRendezVous();
        $token = $rdv->getTokenSuivi();

        $result = [
            'reception' => [],
            'avant_travaux' => [],
            'en_cours' => [],
            'apres_travaux' => [],
            'restitution' => [],
            'probleme' => [],
            'reception_base64' => [],
        ];

        // Legacy inline base64 photos from PDA companion
        $raw = $rdv->getPhotosEtat();
        if (is_string($raw) && trim($raw) !== '') {
            $decoded = json_decode($raw, true);
            $candidatePhotos = is_array($decoded) ? ($decoded['photos'] ?? []) : [];
            if (is_array($candidatePhotos)) {
                foreach ($candidatePhotos as $photo) {
                    $src = null;
                    if (is_string($photo)) {
                        $src = $photo;
                    } elseif (is_array($photo)) {
                        $src = $photo['src'] ?? $photo['data'] ?? $photo['url'] ?? null;
                    }
                    if (is_string($src) && str_starts_with($src, 'data:image/')) {
                        $result['reception_base64'][] = [
                            'src' => $src,
                            'label' => 'Photo réception PDA',
                        ];
                    }
                }
            }
        }

        // Stored PhotoIntervention files
        foreach ($rdv->getPhotosIntervention() as $photo) {
            $type = strtolower((string) ($photo->getType() ?? ''));
            if ($type === '' || in_array($type, ['reception', 'checkin', 'etat'], true)) {
                $type = 'reception';
            }

            $url = $token
                ? sprintf('/api/public/photos/%s/%s', $token, $photo->getFilename())
                : null;

            $item = [
                'id' => $photo->getId(),
                'filename' => $photo->getFilename(),
                'original_name' => $photo->getOriginalName(),
                'description' => $photo->getDescription(),
                'type' => $photo->getType(),
                'created_at' => $photo->getCreatedAt()?->format('c'),
                'url' => $url,
            ];

            if (array_key_exists($type, $result)) {
                $result[$type][] = $item;
            } else {
                $result['probleme'][] = $item;
            }
        }

        return $this->json($result);
    }

    #[Route('/{id}/verify-integrity', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function verifyIntegrity(int $id): JsonResponse
    {
        $or = $this->em->getRepository(OrdreReparation::class)->find($id);
        if (!$or) {
            return $this->json(['error' => 'OR non trouvé'], Response::HTTP_NOT_FOUND);
        }

        if ($or->getSignedSnapshot() === null) {
            return $this->json([
                'signed' => false,
                'message' => 'Cet OR n\'est pas encore signé',
            ]);
        }

        $isValid = $this->orPolicy->verifyIntegrity($or);

        return $this->json([
            'signed' => true,
            'integrity_ok' => $isValid,
            'signed_at' => $or->getSignedAt()?->format('c'),
            'signed_hash' => $or->getSignedHash(),
            'message' => $isValid
                ? 'Intégrité vérifiée — aucune altération détectée'
                : 'ALERTE — le contenu a été altéré après signature',
        ]);
    }
}
