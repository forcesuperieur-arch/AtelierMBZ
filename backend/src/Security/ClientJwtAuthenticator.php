<?php

namespace App\Security;

use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\TokenExtractor\TokenExtractorInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\CustomCredentials;

/**
 * Authenticator for client JWT tokens (claim type=client).
 * Reads from "client_access_token" cookie or Authorization: Bearer header.
 */
class ClientJwtAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        private JWTTokenManagerInterface $jwtManager,
        private TokenExtractorInterface $tokenExtractor,
        private ClientUserProvider $clientProvider,
    ) {}

    public function supports(Request $request): ?bool
    {
        return $request->cookies->has('client_access_token')
            || $this->tokenExtractor->extract($request) !== false;
    }

    public function authenticate(Request $request): Passport
    {
        $token = $request->cookies->get('client_access_token');
        if (!$token) {
            $token = $this->tokenExtractor->extract($request);
        }

        if (!$token) {
            throw new AuthenticationException('No client JWT token found.');
        }

        try {
            $payload = $this->jwtManager->parse($token);
        } catch (\Exception $e) {
            throw new AuthenticationException('Invalid client JWT token: ' . $e->getMessage());
        }

        if (($payload['type'] ?? null) !== 'client') {
            throw new AuthenticationException('Invalid token type.');
        }

        // Le refresh token (7 jours) ne doit jamais servir d'access token :
        // seul POST /api/client/refresh est habilité à le consommer.
        if (($payload['refresh'] ?? false) === true) {
            throw new AuthenticationException('Refresh token cannot be used as access token.');
        }

        $email = $payload['username'] ?? null;
        if (!$email) {
            throw new AuthenticationException('JWT token missing username.');
        }

        return new \Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport(
            new UserBadge($email)
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {

        return new JsonResponse(
            ['error' => 'Authentication failed', 'message' => $exception->getMessage()],
            Response::HTTP_UNAUTHORIZED
        );
    }
}
