<?php

namespace App\Controller;

use App\Entity\AuditLog;
use App\Entity\OrdreReparation;
use App\Entity\User;
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

        // Audit log
        $log = new AuditLog();
        $log->setAtelierId($user->getAtelierId());
        $log->setUserId($user->getId());
        $log->setUsername($user->getUsername());
        $log->setAction('or_rectification');
        $log->setEntityType('OrdreReparation');
        $log->setEntityId($or->getId());
        $log->setDetails(json_encode([
            'original_id' => $or->getId(),
            'rectified_id' => 'pending',
            'motif' => $motif,
            'ip' => $request->getClientIp(),
        ], JSON_UNESCAPED_UNICODE));
        $log->setIpAddress($request->getClientIp());
        $this->em->persist($log);

        $this->em->flush();

        // Update audit log with rectified ID
        $log->setDetails(json_encode([
            'original_id' => $or->getId(),
            'rectified_id' => $rectified->getId(),
            'motif' => $motif,
            'ip' => $request->getClientIp(),
        ], JSON_UNESCAPED_UNICODE));
        $this->em->flush();

        return $this->json([
            'success' => true,
            'rectified_or_id' => $rectified->getId(),
            'numero_or' => $rectified->getNumeroOr(),
            'statut' => $rectified->getStatut(),
        ], Response::HTTP_CREATED);
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
