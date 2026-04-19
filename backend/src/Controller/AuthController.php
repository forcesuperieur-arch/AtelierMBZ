<?php

namespace App\Controller;

use App\Entity\Atelier;
use App\Entity\Notification;
use App\Entity\RevokedToken;
use App\Entity\RoleMetier;
use App\Entity\User;
use App\Service\BookingAtelierAccessService;
use App\Service\UserRoleMapper;
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
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[Route('/api/auth')]
class AuthController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private JWTTokenManagerInterface $jwtManager,
        private UserPasswordHasherInterface $passwordHasher,
        private HttpClientInterface $httpClient,
        private MailerInterface $mailer,
        private UserRoleMapper $userRoleMapper,
        private ?BookingAtelierAccessService $bookingAtelierAccess = null,
    ) {}

    private function bookingAtelierAccess(): BookingAtelierAccessService
    {
        return $this->bookingAtelierAccess ??= new BookingAtelierAccessService($this->em);
    }

    private function expandLegacyPermissionAliases(string $module, string $action): array
    {
        $aliases = [sprintf('%s.%s', $module, $action)];

        if ($module === 'clients') {
            $aliases[] = sprintf('client.%s', $action);
        }

        if ($module === 'admin') {
            $aliases[] = 'admin.users';
            $aliases[] = 'admin.config';
            $aliases[] = 'admin.roles';
        }

        if ($module === 'workshop') {
            $aliases[] = 'mecanicien.view';
        }

        return array_values(array_unique($aliases));
    }

    private function resolveEffectiveRoleMetier(User $user): ?RoleMetier
    {
        $roleMetier = $user->getRoleMetier();
        if ($roleMetier?->isActive()) {
            return $roleMetier;
        }

        if (in_array('ROLE_SUPER_ADMIN', $user->getRoles(), true)) {
            return null;
        }

        $targetCode = $this->userRoleMapper->mapLegacyRoleToRoleMetierCode($user->getRole());
        if ($targetCode === null) {
            return null;
        }

        $repo = $this->em->getRepository(RoleMetier::class);

        if ($user->getAtelierId()) {
            $atelierRole = $repo->findOneBy([
                'atelierId' => $user->getAtelierId(),
                'code' => $targetCode,
                'isActive' => true,
            ]);

            if ($atelierRole instanceof RoleMetier) {
                return $atelierRole;
            }
        }

        $sharedRole = $repo->findOneBy([
            'atelierId' => null,
            'code' => $targetCode,
            'isActive' => true,
        ]);

        return $sharedRole instanceof RoleMetier ? $sharedRole : null;
    }

    private function buildRolePermissionsCompatibility(User $user, ?RoleMetier $roleMetier): ?array
    {
        if (in_array('ROLE_SUPER_ADMIN', $user->getRoles(), true)) {
            return [
                'sections_json' => ['*'],
                'permissions_json' => ['*.*'],
            ];
        }

        if (!$roleMetier || !$roleMetier->isActive()) {
            return null;
        }

        $sections = [];
        $permissions = [];

        foreach ($roleMetier->getPermissions() as $entry) {
            if (!$entry->isGranted()) {
                continue;
            }

            $module = $entry->getModule();
            $action = $entry->getAction();

            $sections[] = $module;
            if ($module === 'workshop' || $roleMetier->getCode() === 'mecanicien') {
                $sections[] = 'mecanicien';
            }

            foreach ($this->expandLegacyPermissionAliases($module, $action) as $alias) {
                $permissions[] = $alias;
            }
        }

        return [
            'sections_json' => array_values(array_unique($sections)),
            'permissions_json' => array_values(array_unique($permissions)),
        ];
    }

    private function buildUserPayload(User $user): array
    {
        $atelier = $user->getAtelierId()
            ? $this->em->getRepository(Atelier::class)->find($user->getAtelierId())
            : null;

        $roleMetier = $this->resolveEffectiveRoleMetier($user);
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
            'prenom' => $user->getPrenom(),
            'nom' => $user->getNom(),
            'role' => $user->getRole(),
            'roles' => $user->getRoles(),
            'auth_provider' => $user->getAuthProvider(),
            'access_status' => $user->getAccessStatus(),
            'is_pending_validation' => $user->isPendingValidation(),
            'needs_atelier_assignment' => !in_array('ROLE_SUPER_ADMIN', $user->getRoles(), true) && !$user->getAtelierId(),
            'atelier_id' => $user->getAtelierId(),
            'atelier_nom' => $atelier?->getNom(),
            'role_permissions' => $this->buildRolePermissionsCompatibility($user, $roleMetier),
            'role_metier' => $roleMetierData,
        ];
    }

    private function createAuthenticatedResponse(User $user): JsonResponse
    {
        $accessToken = $this->jwtManager->create($user);

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
            ? $this->findDefaultActiveAtelier($user)
            : null;

        if ($defaultActiveAtelier instanceof Atelier) {
            $response->headers->setCookie(
                Cookie::create('active_atelier_id')
                    ->withValue((string) $defaultActiveAtelier->getId())
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

    private function findDefaultActiveAtelier(User $user): ?Atelier
    {
        if ($user->getAtelierId()) {
            $atelier = $this->em->getRepository(Atelier::class)->find($user->getAtelierId());
            if ($atelier instanceof Atelier) {
                return $atelier;
            }
        }

        return $this->em->getRepository(Atelier::class)->findOneBy(['actif' => true], ['id' => 'ASC'])
            ?? $this->em->getRepository(Atelier::class)->findOneBy([], ['id' => 'ASC']);
    }

    private function getGoogleConfig(): array
    {
        return [
            'client_id' => trim((string) ($_ENV['GOOGLE_OAUTH_CLIENT_ID'] ?? '')),
            'client_secret' => trim((string) ($_ENV['GOOGLE_OAUTH_CLIENT_SECRET'] ?? '')),
            'redirect_uri' => trim((string) ($_ENV['GOOGLE_OAUTH_REDIRECT_URI'] ?? '')),
            'hosted_domain' => trim((string) ($_ENV['GOOGLE_OAUTH_HOSTED_DOMAIN'] ?? '')),
        ];
    }

    private function isDevSsoSimulationEnabled(): bool
    {
        return $this->getParameter('kernel.environment') !== 'prod';
    }

    private function generateUniqueUsername(string $email): string
    {
        $base = preg_replace('/[^a-z0-9]+/i', '.', strtolower(strtok($email, '@') ?: 'google.user'));
        $base = trim((string) $base, '.');
        $base = $base !== '' ? $base : 'google.user';

        $candidate = $base;
        $index = 2;
        while ($this->em->getRepository(User::class)->findOneBy(['username' => $candidate]) !== null) {
            $candidate = sprintf('%s.%d', $base, $index++);
        }

        return $candidate;
    }

    private function resolveGoogleNames(array $googleUser, string $email): array
    {
        $prenom = trim((string) ($googleUser['given_name'] ?? ''));
        $nom = trim((string) ($googleUser['family_name'] ?? ''));

        if ($prenom === '' && $nom === '') {
            $fullName = trim((string) ($googleUser['name'] ?? ''));
            if ($fullName !== '') {
                $parts = preg_split('/\s+/', $fullName) ?: [];
                $prenom = trim((string) array_shift($parts));
                $nom = trim(implode(' ', $parts));
            }
        }

        if ($prenom === '') {
            $prenom = ucfirst((string) strtok($email, '.@')) ?: 'Google';
        }

        return [
            'prenom' => $prenom !== '' ? $prenom : 'Google',
            'nom' => $nom !== '' ? $nom : 'User',
        ];
    }

    private function extractGoogleIdToken(Request $request, array $data, array $config): string
    {
        $idToken = trim((string) ($data['id_token'] ?? ''));
        if ($idToken !== '') {
            return $idToken;
        }

        $code = trim((string) ($data['code'] ?? ''));
        if ($code === '') {
            throw new \RuntimeException('Missing Google authorization code');
        }

        $state = trim((string) ($data['state'] ?? ''));
        $expectedState = trim((string) $request->cookies->get('google_oauth_state', ''));
        if ($state === '' || $expectedState === '' || !hash_equals($expectedState, $state)) {
            throw new \RuntimeException('Invalid Google OAuth state');
        }

        if (($config['client_secret'] ?? '') === '' || ($config['redirect_uri'] ?? '') === '') {
            throw new \RuntimeException('Google SSO code exchange is not fully configured');
        }

        $tokenResponse = $this->httpClient
            ->request('POST', 'https://oauth2.googleapis.com/token', [
                'body' => [
                    'code' => $code,
                    'client_id' => $config['client_id'],
                    'client_secret' => $config['client_secret'],
                    'redirect_uri' => $config['redirect_uri'],
                    'grant_type' => 'authorization_code',
                ],
            ])
            ->toArray(false);

        $idToken = trim((string) ($tokenResponse['id_token'] ?? ''));
        if ($idToken === '') {
            throw new \RuntimeException('Unable to retrieve Google ID token');
        }

        return $idToken;
    }

    private function notifySuperAdminsOfPendingGoogleUser(User $user): void
    {
        $notification = (new Notification())
            ->setType('google_sso_pending')
            ->setSeverity('info')
            ->setPriority('high')
            ->setTitle('Nouveau compte Google en attente')
            ->setMessage(sprintf(
                '%s (%s) attend une validation administrateur et une affectation atelier.',
                trim(($user->getPrenom() ?? '') . ' ' . ($user->getNom() ?? '')) ?: $user->getUsername(),
                $user->getEmail()
            ))
            ->setActionUrl('/admin/users')
            ->setRelatedEntityType('user')
            ->setRelatedEntityId($user->getId())
            ->setTargetRoles(['ROLE_SUPER_ADMIN']);

        $this->em->persist($notification);

        $superAdmins = $this->em->getRepository(User::class)->findBy(['role' => 'super_admin']);
        foreach ($superAdmins as $admin) {
            if (!$admin instanceof User || $admin->getEmail() === '') {
                continue;
            }

            try {
                $email = (new Email())
                    ->from('noreply@atelier-moto.fr')
                    ->to($admin->getEmail())
                    ->subject('Nouveau compte Google en attente de validation')
                    ->html(sprintf(
                        '<p>Bonjour,</p><p>Un nouveau compte Google attend une validation administrateur.</p><p><strong>Utilisateur :</strong> %s<br><strong>Email :</strong> %s</p><p>Merci de vous rendre dans la gestion des utilisateurs pour lui attribuer un atelier et un rôle métier.</p>',
                        htmlspecialchars(trim(($user->getPrenom() ?? '') . ' ' . ($user->getNom() ?? '')) ?: $user->getUsername()),
                        htmlspecialchars($user->getEmail())
                    ));

                $this->mailer->send($email);
            } catch (\Throwable) {
                // Ignore mail transport issues for pending notifications.
            }
        }
    }

    #[Route('/google/dev-simulate', methods: ['GET'])]
    public function googleDevSimulate(Request $request): Response
    {
        if (!$this->isDevSsoSimulationEnabled()) {
            return $this->json(['error' => 'Unavailable'], Response::HTTP_NOT_FOUND);
        }

        $email = strtolower(trim((string) $request->query->get('email', 'admin@atelier.local')));
        $mode = strtolower(trim((string) $request->query->get('mode', 'login')));
        $prenom = trim((string) $request->query->get('prenom', 'Google'));
        $nom = trim((string) $request->query->get('nom', 'Test'));

        $user = $this->em->getRepository(User::class)->findOneBy(['email' => $email])
            ?? $this->em->getRepository(User::class)->findOneBy(['username' => $email]);

        if ($mode === 'request' || $mode === 'pending') {
            if ($user && !$user->isPendingValidation()) {
                return $this->redirect('/login?google_status=email_in_use&email=' . rawurlencode($user->getEmail() ?? $email));
            }

            if (!$user) {
                $user = new User();
                $user->setUsername($this->generateUniqueUsername($email));
                $user->setEmail($email);
                $user->setPrenom($prenom !== '' ? $prenom : 'Google');
                $user->setNom($nom !== '' ? $nom : 'Test');
                $user->setRole('service_client');
                $user->setAuthProvider('google');
                $user->setGoogleSub('dev-google-' . bin2hex(random_bytes(6)));
                $user->setAccessStatus('pending_validation');
                $user->setIsActive(true);
                $user->setPlainPassword(bin2hex(random_bytes(16)));
                $this->em->persist($user);
                $this->em->flush();
            } else {
                $user->setAuthProvider('google');
                if (!$user->getGoogleSub()) {
                    $user->setGoogleSub('dev-google-' . $user->getId());
                }
                if (!$user->getPrenom() && $prenom !== '') {
                    $user->setPrenom($prenom);
                }
                if (!$user->getNom() && $nom !== '') {
                    $user->setNom($nom);
                }
                $user->setIsActive(true);
                $this->em->flush();
            }

            $this->notifySuperAdminsOfPendingGoogleUser($user);
            $this->em->flush();

            return $this->redirect('/login?google_status=pending_validation&email=' . rawurlencode($user->getEmail() ?? $email));
        }

        if (!$user) {
            $user = new User();
            $user->setUsername($this->generateUniqueUsername($email));
            $user->setEmail($email);
            $user->setPrenom('Google');
            $user->setNom('Test');
            $user->setRole('service_client');
            $user->setAuthProvider('google');
            $user->setGoogleSub('dev-google-' . bin2hex(random_bytes(6)));
            $user->setAccessStatus('active');
            $user->setIsActive(true);
            $user->setPlainPassword(bin2hex(random_bytes(16)));

            if ($atelier = $this->em->getRepository(Atelier::class)->findOneBy([])) {
                $user->setAtelierId($atelier->getId());
            }

            if ($defaultRoleMetier = $this->em->getRepository(RoleMetier::class)->findOneBy(['code' => 'service_client'])) {
                $user->setRoleMetier($defaultRoleMetier);
            }

            $this->em->persist($user);
            $this->em->flush();
        }

        if (!$user->getAuthProvider()) {
            $user->setAuthProvider('google');
        }
        if (!$user->getGoogleSub()) {
            $user->setGoogleSub('dev-google-' . $user->getId());
        }
        if (!$user->getIsActive()) {
            $user->setIsActive(true);
        }
        if ($user->getAccessStatus() !== 'active') {
            $user->setAccessStatus('active');
        }
        if (!in_array('ROLE_SUPER_ADMIN', $user->getRoles(), true) && !$user->getAtelierId()) {
            if ($atelier = $this->em->getRepository(Atelier::class)->findOneBy([])) {
                $user->setAtelierId($atelier->getId());
            }
        }

        $user->markLoginSuccess();
        $this->em->flush();

        $response = $this->createAuthenticatedResponse($user);
        $response->setStatusCode(Response::HTTP_FOUND);
        $response->headers->set('Location', '/');

        return $response;
    }

    #[Route('/google/url', methods: ['GET'])]
    public function googleUrl(Request $request): JsonResponse
    {
        $config = $this->getGoogleConfig();
        if ($config['client_id'] === '' || $config['redirect_uri'] === '') {
            if ($this->isDevSsoSimulationEnabled()) {
                $mode = strtolower(trim((string) $request->query->get('mode', 'login')));
                $email = strtolower(trim((string) $request->query->get('email', 'admin@atelier.local')));

                return $this->json([
                    'enabled' => true,
                    'simulated' => true,
                    'auth_url' => '/api/auth/google/dev-simulate?email=' . rawurlencode($email) . '&mode=' . rawurlencode($mode),
                ]);
            }

            return $this->json(['enabled' => false, 'error' => 'Google SSO not configured'], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $state = bin2hex(random_bytes(16));
        $params = [
            'client_id' => $config['client_id'],
            'redirect_uri' => $config['redirect_uri'],
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'prompt' => 'select_account',
            'access_type' => 'offline',
        ];

        if ($config['hosted_domain'] !== '') {
            $params['hd'] = $config['hosted_domain'];
        }

        $response = $this->json([
            'enabled' => true,
            'auth_url' => 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params),
        ]);

        $response->headers->setCookie(
            Cookie::create('google_oauth_state')
                ->withValue($state)
                ->withExpires(new \DateTime('+10 minutes'))
                ->withPath('/')
                ->withHttpOnly(true)
                ->withSameSite('lax')
                ->withSecure($this->getParameter('kernel.environment') === 'prod')
        );

        return $response;
    }

    #[Route('/google/exchange', methods: ['POST'])]
    public function googleExchange(Request $request): JsonResponse
    {
        $config = $this->getGoogleConfig();
        if ($config['client_id'] === '') {
            return $this->json(['error' => 'Google SSO not configured'], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $data = json_decode($request->getContent(), true) ?? [];

        try {
            $idToken = $this->extractGoogleIdToken($request, $data, $config);
            $googleUser = $this->httpClient
                ->request('GET', 'https://oauth2.googleapis.com/tokeninfo', ['query' => ['id_token' => $idToken]])
                ->toArray(false);
        } catch (\RuntimeException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (\Throwable) {
            return $this->json(['error' => 'Unable to validate Google token'], Response::HTTP_UNAUTHORIZED);
        }

        if (($googleUser['aud'] ?? '') !== $config['client_id']) {
            return $this->json(['error' => 'Invalid Google audience'], Response::HTTP_UNAUTHORIZED);
        }

        if (($googleUser['email_verified'] ?? 'false') !== 'true') {
            return $this->json(['error' => 'Google email is not verified'], Response::HTTP_UNAUTHORIZED);
        }

        if ($config['hosted_domain'] !== '' && ($googleUser['hd'] ?? '') !== $config['hosted_domain']) {
            return $this->json(['error' => 'Google account domain is not allowed'], Response::HTTP_UNAUTHORIZED);
        }

        $googleSub = trim((string) ($googleUser['sub'] ?? ''));
        $email = strtolower(trim((string) ($googleUser['email'] ?? '')));
        if ($googleSub === '' || $email === '') {
            return $this->json(['error' => 'Incomplete Google identity'], Response::HTTP_UNAUTHORIZED);
        }

        $user = $this->em->getRepository(User::class)->findOneBy(['googleSub' => $googleSub])
            ?? $this->em->getRepository(User::class)->findOneBy(['email' => $email]);

        $isNewUser = false;
        if (!$user) {
            $isNewUser = true;
            $googleNames = $this->resolveGoogleNames($googleUser, $email);

            $user = new User();
            $user->setUsername($this->generateUniqueUsername($email));
            $user->setEmail($email);
            $user->setPrenom($googleNames['prenom']);
            $user->setNom($googleNames['nom']);
            $user->setRole('service_client');
            $user->setAuthProvider('google');
            $user->setGoogleSub($googleSub);
            $user->setAccessStatus('pending_validation');
            $user->setPlainPassword(bin2hex(random_bytes(16)));
            $this->em->persist($user);
        } else {
            $googleNames = $this->resolveGoogleNames($googleUser, $email);

            $user->setAuthProvider('google');
            $user->setGoogleSub($googleSub);
            if (!$user->getPrenom()) {
                $user->setPrenom($googleNames['prenom']);
            }
            if (!$user->getNom()) {
                $user->setNom($googleNames['nom']);
            }
        }

        $this->em->flush();

        if ($isNewUser) {
            $this->notifySuperAdminsOfPendingGoogleUser($user);
            $this->em->flush();
        }

        if ($user->isPendingValidation()) {
            return $this->json([
                'status' => 'pending_validation',
                'user' => $this->buildUserPayload($user),
                'message' => 'Account pending admin validation',
            ], Response::HTTP_FORBIDDEN);
        }

        if (!$user->getIsActive() || $user->getAccessStatus() !== 'active') {
            return $this->json(['error' => 'Account disabled'], Response::HTTP_FORBIDDEN);
        }

        if (!in_array('ROLE_SUPER_ADMIN', $user->getRoles(), true) && !$user->getAtelierId()) {
            return $this->json([
                'status' => 'pending_atelier_assignment',
                'user' => $this->buildUserPayload($user),
                'message' => 'Atelier assignment pending',
            ], Response::HTTP_FORBIDDEN);
        }

        $user->markLoginSuccess();
        $this->em->flush();

        return $this->createAuthenticatedResponse($user);
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

        $user = $this->em->getRepository(User::class)->findOneBy(['email' => $login])
            ?? $this->em->getRepository(User::class)->findOneBy(['username' => $login]);
        if (!$user || !$this->passwordHasher->isPasswordValid($user, $password)) {
            return $this->json(['error' => 'Invalid credentials'], Response::HTTP_UNAUTHORIZED);
        }

        if ($user->isPendingValidation()) {
            return $this->json(['error' => 'Account pending admin validation'], Response::HTTP_FORBIDDEN);
        }

        if (!$user->getIsActive() || $user->getAccessStatus() !== 'active') {
            return $this->json(['error' => 'Account disabled'], Response::HTTP_FORBIDDEN);
        }

        if (!in_array('ROLE_SUPER_ADMIN', $user->getRoles(), true) && !$user->getAtelierId()) {
            return $this->json(['error' => 'Atelier assignment pending'], Response::HTTP_FORBIDDEN);
        }

        $user->markLoginSuccess();
        $this->em->flush();

        return $this->createAuthenticatedResponse($user);
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

    #[Route('/rdv-ateliers', methods: ['GET'])]
    public function bookingAteliers(): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Not authenticated'], Response::HTTP_UNAUTHORIZED);
        }

        $ateliers = array_map(
            static fn (Atelier $atelier): array => [
                'id' => (int) $atelier->getId(),
                'nom' => $atelier->getNom(),
                'actif' => $atelier->isActif(),
            ],
            $this->bookingAtelierAccess()->getAllowedAteliers($user)
        );

        return $this->json($ateliers);
    }

    /**
     * Switch the active atelier for tenant-filtered screens.
     */
    #[Route('/switch-atelier', methods: ['POST'])]
    public function switchAtelier(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Not authenticated'], Response::HTTP_UNAUTHORIZED);
        }

        $canSwitchContext = $this->bookingAtelierAccess()->isSuperAdmin($user)
            || $this->bookingAtelierAccess()->isServiceClient($user);

        if (!$canSwitchContext) {
            return $this->json(['error' => 'Atelier switch not allowed'], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $atelierId = $data['atelier_id'] ?? null;

        if ($atelierId === 'all') {
            $atelierId = null;
        }

        if ($atelierId !== null && $atelierId !== '') {
            $atelierId = (int) $atelierId;
            if (!$this->bookingAtelierAccess()->canAccessAtelier($user, $atelierId)) {
                return $this->json(['error' => 'Atelier not allowed'], Response::HTTP_FORBIDDEN);
            }

            $atelier = $this->em->getRepository(Atelier::class)->find($atelierId);
            if (!$atelier) {
                return $this->json(['error' => 'Atelier not found'], Response::HTTP_NOT_FOUND);
            }
        } else {
            $resolvedAtelierId = $this->bookingAtelierAccess()->resolvePreferredAtelierId($user);
            if (!$resolvedAtelierId) {
                return $this->json(['error' => 'Aucun atelier disponible'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $atelier = $this->em->getRepository(Atelier::class)->find($resolvedAtelierId);
            if (!$atelier) {
                return $this->json(['error' => 'Atelier not found'], Response::HTTP_NOT_FOUND);
            }
        }

        $response = $this->json([
            'active_atelier_id' => (int) $atelier->getId(),
            'atelier_nom' => $atelier->getNom(),
        ]);
        $response->headers->setCookie(
            Cookie::create('active_atelier_id')
                ->withValue((string) $atelier->getId())
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
            }
        }

        $response = $this->json(['message' => 'Logged out']);
        $response->headers->clearCookie('access_token', '/');
        $response->headers->clearCookie('refresh_token', '/api/auth/refresh');
        $response->headers->clearCookie('active_atelier_id', '/');

        return $response;
    }
}
