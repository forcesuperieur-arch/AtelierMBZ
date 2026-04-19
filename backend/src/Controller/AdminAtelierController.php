<?php

namespace App\Controller;

use App\Entity\Atelier;
use App\Entity\ConfigAtelier;
use App\Service\AuditService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/api/admin/ateliers')]
final class AdminAtelierController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private SluggerInterface $slugger,
        private AuditService $audit,
    ) {}

    #[Route('', methods: ['GET'])]
    public function index(): JsonResponse
    {
        if (!$this->isGranted('ROLE_SUPER_ADMIN')) {
            return $this->json(['error' => 'SuperAdmin only'], Response::HTTP_FORBIDDEN);
        }

        $configs = $this->em->getRepository(ConfigAtelier::class)->findBy([]);
        $configMap = [];
        foreach ($configs as $config) {
            if ($config->getAtelierId()) {
                $configMap[$config->getAtelierId()] = true;
            }
        }

        $ateliers = $this->em->getRepository(Atelier::class)->findBy([], ['createdAt' => 'DESC']);

        return $this->json(array_map(
            fn (Atelier $atelier) => $this->serializeAtelier($atelier, isset($configMap[$atelier->getId()])),
            $ateliers
        ));
    }

    #[Route('', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        if (!$this->isGranted('ROLE_SUPER_ADMIN')) {
            return $this->json(['error' => 'SuperAdmin only'], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $nom = trim((string) ($data['nom'] ?? ''));

        if ($nom === '') {
            return $this->json(['error' => 'Le nom de l’atelier est obligatoire'], Response::HTTP_BAD_REQUEST);
        }

        $email = $this->nullableString($data['email'] ?? null);
        if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->json(['error' => 'Adresse email invalide'], Response::HTTP_BAD_REQUEST);
        }

        $atelier = (new Atelier())
            ->setNom($nom)
            ->setSlug($this->buildUniqueSlug((string) ($data['slug'] ?? $nom)))
            ->setAdresse($this->nullableString($data['adresse'] ?? null))
            ->setCp($this->nullableString($data['cp'] ?? null))
            ->setVille($this->nullableString($data['ville'] ?? null))
            ->setTelephone($this->nullableString($data['telephone'] ?? null))
            ->setEmail($email)
            ->setSiret($this->nullableString($data['siret'] ?? null))
            ->setTvaIntracom($this->nullableString($data['tva_intracom'] ?? $data['tvaIntracom'] ?? null))
            ->setLogoUrl($this->nullableString($data['logo_url'] ?? $data['logoUrl'] ?? null))
            ->setPlan($this->nullableString($data['plan'] ?? null) ?: 'starter')
            ->setActif($this->normalizeBool($data['actif'] ?? true))
            ->setConfigJson($this->nullableString($data['config_json'] ?? $data['configJson'] ?? null));

        $this->em->persist($atelier);
        $this->em->flush();

        $this->ensureConfigExists($atelier->getId());

        $this->audit->log(
            'create_atelier',
            'atelier',
            $atelier->getId(),
            json_encode([
                'nom' => $atelier->getNom(),
                'slug' => $atelier->getSlug(),
                'plan' => $atelier->getPlan(),
            ], JSON_UNESCAPED_UNICODE)
        );

        return $this->json([
            'success' => true,
            'atelier' => $this->serializeAtelier($atelier, true),
        ], Response::HTTP_CREATED);
    }

    #[Route('/{id}', methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        if (!$this->isGranted('ROLE_SUPER_ADMIN')) {
            return $this->json(['error' => 'SuperAdmin only'], Response::HTTP_FORBIDDEN);
        }

        $atelier = $this->em->getRepository(Atelier::class)->find($id);
        if (!$atelier instanceof Atelier) {
            return $this->json(['error' => 'Atelier introuvable'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true) ?? [];

        if (array_key_exists('nom', $data)) {
            $nom = trim((string) $data['nom']);
            if ($nom === '') {
                return $this->json(['error' => 'Le nom de l’atelier est obligatoire'], Response::HTTP_BAD_REQUEST);
            }
            $atelier->setNom($nom);
        }

        if (array_key_exists('slug', $data)) {
            $atelier->setSlug($this->buildUniqueSlug((string) $data['slug'], $atelier->getId()));
        }

        if (array_key_exists('adresse', $data)) $atelier->setAdresse($this->nullableString($data['adresse']));
        if (array_key_exists('cp', $data)) $atelier->setCp($this->nullableString($data['cp']));
        if (array_key_exists('ville', $data)) $atelier->setVille($this->nullableString($data['ville']));
        if (array_key_exists('telephone', $data)) $atelier->setTelephone($this->nullableString($data['telephone']));
        if (array_key_exists('email', $data)) {
            $email = $this->nullableString($data['email']);
            if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return $this->json(['error' => 'Adresse email invalide'], Response::HTTP_BAD_REQUEST);
            }
            $atelier->setEmail($email);
        }
        if (array_key_exists('siret', $data)) $atelier->setSiret($this->nullableString($data['siret']));
        if (array_key_exists('tva_intracom', $data) || array_key_exists('tvaIntracom', $data)) {
            $atelier->setTvaIntracom($this->nullableString($data['tva_intracom'] ?? $data['tvaIntracom'] ?? null));
        }
        if (array_key_exists('logo_url', $data) || array_key_exists('logoUrl', $data)) {
            $atelier->setLogoUrl($this->nullableString($data['logo_url'] ?? $data['logoUrl'] ?? null));
        }
        if (array_key_exists('plan', $data)) $atelier->setPlan($this->nullableString($data['plan']) ?: 'starter');
        if (array_key_exists('actif', $data)) $atelier->setActif($this->normalizeBool($data['actif']));
        if (array_key_exists('config_json', $data) || array_key_exists('configJson', $data)) {
            $atelier->setConfigJson($this->nullableString($data['config_json'] ?? $data['configJson'] ?? null));
        }

        $this->em->flush();
        $this->ensureConfigExists($atelier->getId());

        $this->audit->log(
            'update_atelier',
            'atelier',
            $atelier->getId(),
            json_encode([
                'nom' => $atelier->getNom(),
                'slug' => $atelier->getSlug(),
                'actif' => $atelier->isActif(),
            ], JSON_UNESCAPED_UNICODE)
        );

        return $this->json([
            'success' => true,
            'atelier' => $this->serializeAtelier($atelier, true),
        ]);
    }

    private function ensureConfigExists(?int $atelierId): void
    {
        if (!$atelierId) {
            return;
        }

        $config = $this->em->getRepository(ConfigAtelier::class)->findOneBy(['atelierId' => $atelierId]);
        if ($config instanceof ConfigAtelier) {
            return;
        }

        $config = (new ConfigAtelier())->setAtelierId($atelierId);
        $this->em->persist($config);
        $this->em->flush();
    }

    private function buildUniqueSlug(string $base, ?int $ignoreAtelierId = null): string
    {
        $base = trim($base) !== '' ? $base : 'atelier';
        $slug = strtolower((string) $this->slugger->slug($base));
        $slug = $slug !== '' ? $slug : 'atelier';

        $candidate = $slug;
        $suffix = 2;

        while (true) {
            $existing = $this->em->getRepository(Atelier::class)->findOneBy(['slug' => $candidate]);
            if (!$existing instanceof Atelier || $existing->getId() === $ignoreAtelierId) {
                return $candidate;
            }

            $candidate = sprintf('%s-%d', $slug, $suffix);
            $suffix++;
        }
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }

    private function normalizeBool(mixed $value): bool
    {
        return !in_array($value, [false, 0, '0', 'false', 'off', null], true);
    }

    private function serializeAtelier(Atelier $atelier, bool $hasConfig): array
    {
        return [
            'id' => $atelier->getId(),
            'nom' => $atelier->getNom(),
            'slug' => $atelier->getSlug(),
            'adresse' => $atelier->getAdresse(),
            'cp' => $atelier->getCp(),
            'ville' => $atelier->getVille(),
            'telephone' => $atelier->getTelephone(),
            'email' => $atelier->getEmail(),
            'siret' => $atelier->getSiret(),
            'tva_intracom' => $atelier->getTvaIntracom(),
            'logo_url' => $atelier->getLogoUrl(),
            'plan' => $atelier->getPlan(),
            'actif' => $atelier->isActif(),
            'config_json' => $atelier->getConfigJson(),
            'created_at' => $atelier->getCreatedAt()->format(DATE_ATOM),
            'has_config' => $hasConfig,
        ];
    }
}
