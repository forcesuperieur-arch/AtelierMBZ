<?php
namespace App\Security;

use App\Entity\RevokedToken;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\TokenExtractor\TokenExtractorInterface;

/**
 * Custom authenticator that reads JWT from HttpOnly cookie (access_token).
 * Falls back to Authorization: Bearer header for API clients.
 *
 * [LOT-0] Vérifie l'inactivité de session (> 30 min) et invalide le token côté serveur (RGPD).
 */
class CookieJwtAuthenticator extends AbstractAuthenticator
{
    private const INACTIVITY_MINUTES = 30;

    public function __construct(
        private JWTTokenManagerInterface $jwtManager,
        private TokenExtractorInterface $tokenExtractor,
        private EntityManagerInterface $em,
    ) {}

    public function supports(Request $request): ?bool
    {
        return $request->cookies->has('access_token')
            || $this->tokenExtractor->extract($request) !== false;
    }

    public function authenticate(Request $request): Passport
    {
        // Try cookie first, then Authorization header
        $token = $request->cookies->get('access_token');
        if (!$token) {
            $token = $this->tokenExtractor->extract($request);
        }

        if (!$token) {
            throw new AuthenticationException('No JWT token found.');
        }

        try {
            $payload = $this->jwtManager->parse($token);
        } catch (\Exception $e) {
            throw new AuthenticationException('Invalid JWT token: ' . $e->getMessage());
        }

        $username = $payload['username'] ?? null;
        if (!$username) {
            throw new AuthenticationException('JWT token missing username.');
        }

        /** @var User|null $user */
        $user = $this->em->getRepository(User::class)->findOneBy(['username' => $username]);

        if ($user && $this->isSessionInactive($user)) {
            $jti = $payload['jti'] ?? null;
            if ($jti) {
                $revoked = new RevokedToken();
                $revoked->setJti($jti);
                $revoked->setExpiresAt(new \DateTime('+15 minutes'));
                $revoked->setReason('inactivity');
                $this->em->persist($revoked);
                $this->em->flush();
            }

            throw new AuthenticationException('Session expired due to inactivity');
        }

        return new SelfValidatingPassport(
            new UserBadge($username)
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        // Let the request continue
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new JsonResponse(
            ['error' => 'Authentication failed', 'message' => $exception->getMessage()],
            Response::HTTP_UNAUTHORIZED
        );
    }

    /**
     * Tolérance backward-compat : lastActivityAt null = pas inactif (utilisateurs créés avant la migration).
     */
    private function isSessionInactive(User $user): bool
    {
        $lastActivity = $user->getLastActivityAt();

        if (!$lastActivity) {
            return false;
        }

        $threshold = new \DateTime(sprintf('-%d minutes', self::INACTIVITY_MINUTES));
        return $lastActivity < $threshold;
    }
}
