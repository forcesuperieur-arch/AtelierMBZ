<?php

namespace App\Controller;

use App\Entity\Client;
use App\Security\ClientUserAdapter;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/client')]
class ClientAuthController extends AbstractController
{
    private const REFRESH_TTL = 7 * 86400; // 7 jours
    private const REFRESH_COOKIE_PATH = '/api/client';

    public function __construct(
        private EntityManagerInterface $em,
        private JWTTokenManagerInterface $jwtManager,
        private UserPasswordHasherInterface $passwordHasher,
        private MailerInterface $mailer,
    ) {}

    #[Route('/login', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $email = trim($data['email'] ?? '');
        $password = $data['password'] ?? '';

        if (!$email || !$password) {
            return $this->json(['error' => 'Email et mot de passe requis'], Response::HTTP_BAD_REQUEST);
        }

        $client = $this->em->getRepository(Client::class)->findOneBy(['email' => $email]);
        if (!$client || !$client->getPassword()) {
            return $this->json(['error' => 'Identifiants invalides'], Response::HTTP_UNAUTHORIZED);
        }

        $adapter = new ClientUserAdapter($client);
        if (!$this->passwordHasher->isPasswordValid($adapter, $password)) {
            return $this->json(['error' => 'Identifiants invalides'], Response::HTTP_UNAUTHORIZED);
        }

        $jti = bin2hex(random_bytes(16));
        $accessToken = $this->jwtManager->createFromPayload($adapter, ['type' => 'client', 'jti' => $jti]);

        // Refresh token : JWT avec expiration étendue à 7 jours (exp explicite)
        $refreshToken = $this->jwtManager->createFromPayload($adapter, [
            'type' => 'client',
            'refresh' => true,
            'jti' => bin2hex(random_bytes(16)),
            'exp' => time() + self::REFRESH_TTL,
        ]);

        $client->touchActivity();
        $this->em->flush();

        $response = $this->json([
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'client' => [
                'id' => $client->getId(),
                'nom' => $client->getNom(),
                'prenom' => $client->getPrenom(),
                'email' => $client->getEmail(),
            ],
        ]);

        // Set HttpOnly cookie for SPA convenience
        $secure = $this->isCookieSecure($request);
        $response->headers->setCookie(
            new Cookie('client_access_token', $accessToken, time() + 3600, '/', null, $secure, true, false, 'Lax')
        );
        // Refresh token en cookie HttpOnly, restreint aux routes /api/client
        $response->headers->setCookie(
            new Cookie('client_refresh_token', $refreshToken, time() + self::REFRESH_TTL, self::REFRESH_COOKIE_PATH, null, $secure, true, false, 'Lax')
        );

        return $response;
    }

    #[Route('/refresh', methods: ['POST'])]
    public function refresh(Request $request): JsonResponse
    {
        $refreshToken = $request->cookies->get('client_refresh_token')
            ?? $request->request->get('refresh_token')
            ?? (json_decode($request->getContent(), true)['refresh_token'] ?? null);

        if (!$refreshToken) {
            return $this->json(['error' => 'Refresh token requis'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $payload = $this->jwtManager->parse($refreshToken);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Token invalide'], Response::HTTP_UNAUTHORIZED);
        }

        if (($payload['type'] ?? null) !== 'client' || ($payload['refresh'] ?? false) !== true) {
            return $this->json(['error' => 'Token invalide'], Response::HTTP_UNAUTHORIZED);
        }

        $email = $payload['username'] ?? null;
        $client = $this->em->getRepository(Client::class)->findOneBy(['email' => $email]);
        if (!$client) {
            return $this->json(['error' => 'Client introuvable'], Response::HTTP_UNAUTHORIZED);
        }

        $adapter = new ClientUserAdapter($client);
        $accessToken = $this->jwtManager->createFromPayload($adapter, ['type' => 'client', 'jti' => bin2hex(random_bytes(16))]);

        $response = $this->json(['access_token' => $accessToken]);
        $response->headers->setCookie(
            new Cookie('client_access_token', $accessToken, time() + 3600, '/', null, $this->isCookieSecure($request), true, false, 'Lax')
        );

        return $response;
    }

    /**
     * Secure dès que la requête est en HTTPS ; CLIENT_COOKIE_SECURE=1 force le
     * flag en prod derrière un proxy TLS non détecté (X-Forwarded-Proto absent).
     */
    private function isCookieSecure(Request $request): bool
    {
        return $request->isSecure()
            || filter_var($_ENV['CLIENT_COOKIE_SECURE'] ?? '0', FILTER_VALIDATE_BOOL);
    }

    #[Route('/logout', methods: ['POST'])]
    public function logout(): JsonResponse
    {
        $response = $this->json(['message' => 'Déconnecté']);
        $response->headers->clearCookie('client_access_token');
        $response->headers->clearCookie('client_refresh_token', self::REFRESH_COOKIE_PATH);
        return $response;
    }

    #[Route('/forgot-password', methods: ['POST'])]
    public function forgotPassword(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $email = trim($data['email'] ?? '');

        if (!$email) {
            return $this->json(['error' => 'Email requis'], Response::HTTP_BAD_REQUEST);
        }

        $client = $this->em->getRepository(Client::class)->findOneBy(['email' => $email]);
        if (!$client) {
            // Always return success to avoid email enumeration
            return $this->json(['message' => 'Si un compte existe avec cet email, un lien vous a été envoyé.']);
        }

        $token = bin2hex(random_bytes(32));
        $client->setResetToken($token);
        $client->setResetTokenExpiresAt(new \DateTime('+24 hours'));
        $this->em->flush();

        $resetUrl = $request->getSchemeAndHttpHost() . '/client/reset-password?token=' . $token;

        $emailMessage = (new Email())
            ->from('noreply@paddock.fr')
            ->to($client->getEmail())
            ->subject('Activez votre espace client')
            ->html(sprintf(
                '<p>Bonjour %s,</p>' .
                '<p>Vous avez demandé à activer votre espace client ou à réinitialiser votre mot de passe.</p>' .
                '<p>Cliquez sur le lien ci-dessous pour définir votre mot de passe :</p>' .
                '<p><a href="%s">%s</a></p>' .
                '<p>Ce lien est valable 24 heures.</p>' .
                '<p>Cordialement,<br>L\'équipe Paddock</p>',
                htmlspecialchars($client->getPrenom() ?? ''),
                htmlspecialchars($resetUrl),
                htmlspecialchars($resetUrl)
            ));

        $this->mailer->send($emailMessage);

        return $this->json(['message' => 'Si un compte existe avec cet email, un lien vous a été envoyé.']);
    }

    #[Route('/reset-password/validate', methods: ['GET'])]
    public function validateResetToken(Request $request): JsonResponse
    {
        $token = $request->query->get('token');
        if (!$token) {
            return $this->json(['valid' => false, 'error' => 'Token manquant'], Response::HTTP_BAD_REQUEST);
        }

        $client = $this->em->getRepository(Client::class)->findOneBy(['resetToken' => $token]);
        if (!$client || !$client->getResetTokenExpiresAt() || $client->getResetTokenExpiresAt() < new \DateTime()) {
            return $this->json(['valid' => false, 'error' => 'Token invalide ou expiré'], Response::HTTP_BAD_REQUEST);
        }

        return $this->json(['valid' => true, 'email' => $client->getEmail()]);
    }

    #[Route('/reset-password', methods: ['POST'])]
    public function resetPassword(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $token = $data['token'] ?? '';
        $password = $data['password'] ?? '';

        if (!$token || !$password || strlen($password) < 6) {
            return $this->json(['error' => 'Token et mot de passe (min. 6 caractères) requis'], Response::HTTP_BAD_REQUEST);
        }

        $client = $this->em->getRepository(Client::class)->findOneBy(['resetToken' => $token]);
        if (!$client || !$client->getResetTokenExpiresAt() || $client->getResetTokenExpiresAt() < new \DateTime()) {
            return $this->json(['error' => 'Token invalide ou expiré'], Response::HTTP_BAD_REQUEST);
        }

        $adapter = new ClientUserAdapter($client);
        $hashed = $this->passwordHasher->hashPassword($adapter, $password);
        $client->setPassword($hashed);
        $client->setResetToken(null);
        $client->setResetTokenExpiresAt(null);
        $this->em->flush();

        return $this->json(['message' => 'Mot de passe mis à jour avec succès.']);
    }
}
