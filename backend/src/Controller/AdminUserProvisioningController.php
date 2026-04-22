<?php

namespace App\Controller;

use App\Entity\Atelier;
use App\Entity\RoleMetier;
use App\Entity\User;
use App\Service\UserMecanicienSyncService;
use App\Service\UserRoleMapper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/users')]
final class AdminUserProvisioningController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserRoleMapper $roleMapper,
        private \App\Service\AuditService $auditService,
        private UserMecanicienSyncService $mecanicienSyncService,
    ) {}

    #[Route('/pending', methods: ['GET'])]
    public function pending(): JsonResponse
    {
        /** @var User|null $currentUser */
        $currentUser = $this->getUser();
        if (!$currentUser || !in_array('ROLE_SUPER_ADMIN', $currentUser->getRoles(), true)) {
            return $this->json(['error' => 'SuperAdmin only'], Response::HTTP_FORBIDDEN);
        }

        $users = $this->em->getRepository(User::class)->findBy([
            'accessStatus' => 'pending_validation',
        ], ['createdAt' => 'DESC']);

        return $this->json(array_map(fn (User $user) => $this->serializeUser($user), $users));
    }

    #[Route('/{id}/approve', methods: ['POST'])]
    public function approve(int $id, Request $request): JsonResponse
    {
        /** @var User|null $currentUser */
        $currentUser = $this->getUser();
        if (!$currentUser || !in_array('ROLE_SUPER_ADMIN', $currentUser->getRoles(), true)) {
            return $this->json(['error' => 'SuperAdmin only'], Response::HTTP_FORBIDDEN);
        }

        $user = $this->em->getRepository(User::class)->find($id);
        if (!$user instanceof User) {
            return $this->json(['error' => 'User not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $atelierId = isset($data['atelier_id']) ? (int) $data['atelier_id'] : (int) ($user->getAtelierId() ?? 0);
        if ($atelierId <= 0) {
            return $this->json(['error' => 'atelier_id is required'], Response::HTTP_BAD_REQUEST);
        }

        $atelier = $this->em->getRepository(Atelier::class)->find($atelierId);
        if (!$atelier instanceof Atelier) {
            return $this->json(['error' => 'Atelier not found'], Response::HTTP_NOT_FOUND);
        }

        $roleMetier = null;
        $roleMetierId = isset($data['role_metier_id']) ? (int) $data['role_metier_id'] : 0;
        if ($roleMetierId > 0) {
            $roleMetier = $this->em->getRepository(RoleMetier::class)->find($roleMetierId);
        }

        if (!$roleMetier instanceof RoleMetier) {
            $roleMetier = $this->em->getRepository(RoleMetier::class)->findOneBy([
                'atelierId' => $atelierId,
                'code' => 'service_client',
            ]) ?? $this->em->getRepository(RoleMetier::class)->findOneBy([
                'atelierId' => null,
                'code' => 'service_client',
            ]);
        }

        if (!$roleMetier instanceof RoleMetier) {
            return $this->json(['error' => 'RoleMetier not found'], Response::HTTP_BAD_REQUEST);
        }

        if ($roleMetier->getAtelierId() !== null && $roleMetier->getAtelierId() !== $atelierId) {
            return $this->json(['error' => 'RoleMetier does not belong to the selected atelier'], Response::HTTP_BAD_REQUEST);
        }

        $user->setAtelierId($atelierId);
        $user->setRoleMetier($roleMetier);
        $user->setRole($this->roleMapper->mapRoleMetierToLegacyRole($roleMetier));
        $user->setAccessStatus('active');
        $user->setValidatedBy($currentUser->getId());
        $user->setValidatedAt(new \DateTime());
        $user->setIsActive(1);

        $this->em->flush();

        // [C14] Synchroniser l'entité Mecanicien si le rôle assigné est ROLE_MECANICIEN
        if (in_array('ROLE_MECANICIEN', $user->getRoles(), true)) {
            $this->mecanicienSyncService->syncFromUser($user);
        }

        $this->auditService->log(
            'user_approve',
            'User',
            $user->getId(),
            sprintf('User %s (%s) approuvé — atelier #%d, rôle %s', $user->getUsername(), $user->getEmail(), $atelierId, $roleMetier->getCode()),
        );

        return $this->json([
            'success' => true,
            'message' => 'User approved successfully',
            'user' => $this->serializeUser($user),
        ]);
    }

    #[Route('/{id}/reject', methods: ['POST'])]
    public function reject(int $id, Request $request): JsonResponse
    {
        /** @var User|null $currentUser */
        $currentUser = $this->getUser();
        if (!$currentUser || !in_array('ROLE_SUPER_ADMIN', $currentUser->getRoles(), true)) {
            return $this->json(['error' => 'SuperAdmin only'], Response::HTTP_FORBIDDEN);
        }

        $user = $this->em->getRepository(User::class)->find($id);
        if (!$user instanceof User) {
            return $this->json(['error' => 'User not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $reason = trim((string) ($data['reason'] ?? 'Validation refused by administrator'));

        $user->setAccessStatus('disabled');
        $user->setValidatedBy($currentUser->getId());
        $user->setValidatedAt(new \DateTime());
        $user->setIsActive(0);
        $this->em->flush();

        $this->auditService->log(
            'user_reject',
            'User',
            $user->getId(),
            sprintf('User %s (%s) rejeté — raison: %s', $user->getUsername(), $user->getEmail(), $reason),
        );

        return $this->json([
            'success' => true,
            'message' => $reason,
            'user' => $this->serializeUser($user),
        ]);
    }

    private function serializeUser(User $user): array
    {
        return [
            'id' => $user->getId(),
            'username' => $user->getUsername(),
            'email' => $user->getEmail(),
            'prenom' => $user->getPrenom(),
            'nom' => $user->getNom(),
            'role' => $user->getRole(),
            'atelier_id' => $user->getAtelierId(),
            'auth_provider' => $user->getAuthProvider(),
            'access_status' => $user->getAccessStatus(),
            'validated_at' => $user->getValidatedAt()?->format(DATE_ATOM),
            'validated_by' => $user->getValidatedBy(),
            'role_metier' => $user->getRoleMetier() ? [
                'id' => $user->getRoleMetier()?->getId(),
                'code' => $user->getRoleMetier()?->getCode(),
                'libelle' => $user->getRoleMetier()?->getLibelle(),
            ] : null,
        ];
    }
}
