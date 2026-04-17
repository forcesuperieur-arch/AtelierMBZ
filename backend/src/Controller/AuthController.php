<?php
namespace App\Controller;

use App\Entity\Atelier;
use App\Entity\RevokedToken;
use App\Entity\RolePermission;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/auth')]
class AuthController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private JWTTokenManagerInterface $jwtManager,
        private UserPasswordHasherInterface $passwordHasher,
    ) {}

    private function buildUserPayload(User $user): array
    {
        $rolePermission = $this->em->getRepository(RolePermission::class)->find($user->getRole());
        $atelier = $user->getAtelierId()
            ? $this->em->getRepository(Atelier::class)->find($user->getAtelierId())
            : null;

        // Build structured permissions from RoleMetier
        $roleMetier = $user->getRoleMetier();
        $roleMetierData = null;
        if ($roleMetier && $roleMetier->isActive()) {
            $perms = [];
            foreach ($roleMetier->getPermissions() as $entry) {
                if ($entry->isGranted()) {
                    $perms[] = [
                        'module' => $entry->getModule(),
                        'action' => $entry->getAction(),
                        'scope' => $entry->getScope(),
                    ];
                }
            }
            $roleMetierData = [
                'id' => $roleMetier->getId(),
                'code' => $roleMetier->getCode(),
                'libelle' => $roleMetier->getLibelle(),
                'base_role' => $roleMetier->getBaseRole(),
                'permissions' => $perms,
            ];
        }

        return [
            'id' => $user->getId(),
            'username' => $user->getUsername(),
            'email' => $user->getEmail(),
            'role' => $user->getRole(),
            'roles' => $user->getRoles(),
            'atelier_id' => $user->getAtelierId(),
            'atelier_nom' => $atelier?->getNom(),
            'role_permissions' => $rolePermission ? [
                'sections_json' => $rolePermission->getSections(),
                'permissions_json' => $rolePermission->getPermissions(),
            ] : null,
            'role_metier' => $roleMetierData,
        ];
    }

    #[Route('/login', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $login = $data['email'] ?? $data['username'] ?? '';
        $password = $data['password'] ?? '';

        if (!$login || !$password) {
            return $this->json(['error' => 'Email/username and password required'], Response::HTTP_BAD_REQUEST);
        }

        // Try email first, then username
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => $login])
            ?? $this->em->getRepository(User::class)->findOneBy(['username' => $login]);
        if (!$user || !$this->passwordHasher->isPasswordValid($user, $password)) {
            return $this->json(['error' => 'Invalid credentials'], Response::HTTP_UNAUTHORIZED);
        }

        if (!$user->getIsActive()) {
            return $this->json(['error' => 'Account disabled'], Response::HTTP_FORBIDDEN);
        }

        $accessToken = $this->jwtManager->create($user);

        // Build refresh token (longer lived)
        $refreshPayload = [
            'username' => $user->getUserIdentifier(),
            'user_id' => $user->getId(),
            'jti' => bin2hex(random_bytes(16)),
            'type' => 'refresh',
        ];
        $refreshToken = $this->jwtManager->createFromPayload($user, $refreshPayload);

        $response = $this->json([
            'user' => $this->buildUserPayload($user),
        ]);

        $response->headers->setCookie(
            Cookie::create('access_token')
                ->withValue($accessToken)
                ->withExpires(new \DateTime('+15 minutes'))
                ->withPath('/')
                ->withHttpOnly(true)
                ->withSameSite('lax')
                ->withSecure($this->getParameter('kernel.environment') === 'prod')
        );

        $response->headers->setCookie(
            Cookie::create('refresh_token')
                ->withValue($refreshToken)
                ->withExpires(new \DateTime('+7 days'))
                ->withPath('/api/auth/refresh')
                ->withHttpOnly(true)
                ->withSameSite('lax')
                ->withSecure($this->getParameter('kernel.environment') === 'prod')
        );

        $defaultActiveAtelier = in_array('ROLE_SUPER_ADMIN', $user->getRoles(), true)
            ? ($user->getAtelierId() ? (string) $user->getAtelierId() : 'all')
            : '';

        if ($defaultActiveAtelier !== '') {
            $response->headers->setCookie(
                Cookie::create('active_atelier_id')
                    ->withValue($defaultActiveAtelier)
                    ->withPath('/')
                    ->withHttpOnly(false)
                    ->withSameSite('lax')
                    ->withSecure($this->getParameter('kernel.environment') === 'prod')
            );
        } else {
            $response->headers->clearCookie('active_atelier_id', '/');
        }

        return $response;
    }

    #[Route('/me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Not authenticated'], Response::HTTP_UNAUTHORIZED);
        }

        return $this->json($this->buildUserPayload($user));
    }

    /**
     * SuperAdmin: switch the active atelier for tenant filter (session-based).
     * Accepts {atelier_id: int|null|'all'}. Null/'all' = no filter (global view).
     */
    #[Route('/switch-atelier', methods: ['POST'])]
    public function switchAtelier(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Not authenticated'], Response::HTTP_UNAUTHORIZED);
        }
        if (!in_array('ROLE_SUPER_ADMIN', $user->getRoles(), true)) {
            return $this->json(['error' => 'SuperAdmin only'], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $atelierId = $data['atelier_id'] ?? null;

        if ($atelierId === null || $atelierId === 'all' || $atelierId === '') {
            $response = $this->json(['active_atelier_id' => 'all']);
            $response->headers->setCookie(
                Cookie::create('active_atelier_id')
                    ->withValue('all')
                    ->withPath('/')
                    ->withHttpOnly(false)
                    ->withSameSite('lax')
                    ->withSecure($this->getParameter('kernel.environment') === 'prod')
            );
            return $response;
        }

        $atelier = $this->em->getRepository(Atelier::class)->find((int) $atelierId);
        if (!$atelier) {
            return $this->json(['error' => 'Atelier not found'], Response::HTTP_NOT_FOUND);
        }

        $response = $this->json([
            'active_atelier_id' => (int) $atelierId,
            'atelier_nom' => $atelier->getNom(),
        ]);
        $response->headers->setCookie(
            Cookie::create('active_atelier_id')
                ->withValue((string) ((int) $atelierId))
                ->withPath('/')
                ->withHttpOnly(false)
                ->withSameSite('lax')
                ->withSecure($this->getParameter('kernel.environment') === 'prod')
        );

        return $response;
    }

    #[Route('/refresh', methods: ['POST'])]
    public function refresh(Request $request): JsonResponse
    {
        $refreshToken = $request->cookies->get('refresh_token');
        if (!$refreshToken) {
            return $this->json(['error' => 'No refresh token'], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $payload = $this->jwtManager->parse($refreshToken);
        } catch (\Exception) {
            return $this->json(['error' => 'Invalid refresh token'], Response::HTTP_UNAUTHORIZED);
        }

        if (($payload['type'] ?? '') !== 'refresh') {
            return $this->json(['error' => 'Invalid token type'], Response::HTTP_UNAUTHORIZED);
        }

        $user = $this->em->getRepository(User::class)->findOneBy([
            'username' => $payload['username'] ?? '',
        ]);

        if (!$user || !$user->getIsActive()) {
            return $this->json(['error' => 'User not found or disabled'], Response::HTTP_UNAUTHORIZED);
        }

        $accessToken = $this->jwtManager->create($user);

        $response = $this->json(['message' => 'Token refreshed']);
        $response->headers->setCookie(
            Cookie::create('access_token')
                ->withValue($accessToken)
                ->withExpires(new \DateTime('+15 minutes'))
                ->withPath('/')
                ->withHttpOnly(true)
                ->withSameSite('lax')
                ->withSecure($this->getParameter('kernel.environment') === 'prod')
        );

        return $response;
    }

    #[Route('/logout', methods: ['POST'])]
    public function logout(Request $request): JsonResponse
    {
        // Revoke current access token JTI if present
        $accessToken = $request->cookies->get('access_token');
        if ($accessToken) {
            try {
                $payload = $this->jwtManager->parse($accessToken);
                if (isset($payload['jti'])) {
                    $revoked = new RevokedToken();
                    $revoked->setJti($payload['jti']);
                    $revoked->setExpiresAt(new \DateTime('+15 minutes'));
                    $revoked->setReason('logout');
                    $this->em->persist($revoked);
                    $this->em->flush();
                }
            } catch (\Exception) {
                // Token already invalid, that's fine
            }
        }

        $response = $this->json(['message' => 'Logged out']);

        // Clear cookies
        $response->headers->clearCookie('access_token', '/');
        $response->headers->clearCookie('refresh_token', '/api/auth/refresh');
        $response->headers->clearCookie('active_atelier_id', '/');

        return $response;
    }
}
