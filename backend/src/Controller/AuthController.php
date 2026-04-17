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

        return $response;
    }
}
