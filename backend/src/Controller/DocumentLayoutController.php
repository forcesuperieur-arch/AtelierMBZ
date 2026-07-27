<?php

namespace App\Controller;

use App\Entity\Atelier;
use App\Entity\DocumentLayout;
use App\Service\CurrentAtelierResolver;
use App\Service\DocumentHeaderRenderer;
use App\Service\DocumentPreviewFixtures;
use App\Service\PdfRenderer;
use App\Service\PdfTemplateRegistry;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Twig\Environment;

/**
 * Personnalisation du bandeau d'en-tête des documents PDF.
 *
 * Le layout enregistré ici est appliqué aux PDF réellement produits, via
 * DocumentHeaderRenderer (voir PdfService::renderDocument). La prévisualisation
 * rend le document complet avec l'en-tête composé, et non les seuls éléments
 * posés sur une page vide : c'est la seule façon de juger le résultat.
 */
#[Route('/api/admin/document-layouts')]
#[IsGranted('ROLE_ADMIN')]
class DocumentLayoutController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private CurrentAtelierResolver $atelierResolver,
        private PdfTemplateRegistry $registry,
        private DocumentHeaderRenderer $headerRenderer,
        private DocumentPreviewFixtures $fixtures,
        private PdfRenderer $pdfRenderer,
        private Environment $twig,
        private string $projectDir,
    ) {}

    /** Documents dont l'en-tête est personnalisable. */
    #[Route('/codes', methods: ['GET'])]
    public function codes(): JsonResponse
    {
        $allowed = $this->registry->customisableHeaderCodes();

        return $this->json(array_values(array_filter(
            $this->registry->all(),
            static fn (array $meta): bool => in_array($meta['code'], $allowed, true),
        )));
    }

    #[Route('', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $atelierId = $this->atelierResolver->resolveAtelierId();
        $items = $this->em->getRepository(DocumentLayout::class)->createQueryBuilder('d')
            ->where('d.atelierId = :atelierId OR d.isDefault = true')
            ->setParameter('atelierId', $atelierId)
            ->orderBy('d.isDefault', 'DESC')
            ->addOrderBy('d.code', 'ASC')
            ->getQuery()
            ->getResult();

        // Déduplication : le layout propre à l'atelier prime sur le layout système.
        $byCode = [];
        foreach ($items as $item) {
            $code = $item->getCode();
            if (!isset($byCode[$code]) || !$item->isDefault()) {
                $byCode[$code] = $item;
            }
        }

        return $this->json(array_map(fn (DocumentLayout $d) => $this->serialize($d), array_values($byCode)));
    }

    #[Route('/{code}', methods: ['GET'])]
    public function get(string $code): JsonResponse
    {
        if (!$this->isCustomisable($code)) {
            return $this->json(['error' => 'Code invalide'], Response::HTTP_BAD_REQUEST);
        }

        $layout = $this->findLayout($code, $this->atelierResolver->resolveAtelierId());
        if (!$layout) {
            return $this->json(['error' => 'Layout non trouvé'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->serialize($layout));
    }

    #[Route('', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $code = is_string($data['code'] ?? null) ? $data['code'] : null;

        if ($code === null || !$this->isCustomisable($code)) {
            return $this->json([
                'error' => 'Code invalide',
                'allowed' => $this->registry->customisableHeaderCodes(),
            ], Response::HTTP_BAD_REQUEST);
        }

        $atelierId = $this->atelierResolver->resolveAtelierId();
        if ($atelierId === null) {
            return $this->json(['error' => 'Aucun atelier actif'], Response::HTTP_BAD_REQUEST);
        }

        $existing = $this->em->getRepository(DocumentLayout::class)
            ->findOneBy(['atelierId' => $atelierId, 'code' => $code]);
        if ($existing) {
            return $this->json(['error' => 'Un layout existe déjà pour ce code'], Response::HTTP_CONFLICT);
        }

        $layout = new DocumentLayout();
        $layout->setAtelierId($atelierId);
        $layout->setCode($code);
        $layout->setLabel(is_string($data['label'] ?? null) ? $data['label'] : $this->registry->labelFor($code));
        $layout->setLayoutJson($this->sanitizeLayout($data['layoutJson'] ?? []));
        $layout->setIsDefault(false);

        $this->em->persist($layout);
        $this->em->flush();

        return $this->json($this->serialize($layout), Response::HTTP_CREATED);
    }

    #[Route('/{id}', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $layout = $this->em->getRepository(DocumentLayout::class)->find($id);
        if (!$layout) {
            return $this->json(['error' => 'Layout non trouvé'], Response::HTTP_NOT_FOUND);
        }

        // Un layout système est partagé par tous les ateliers : il ne doit pas
        // être réécrit depuis l'administration d'un atelier.
        if ($layout->isDefault()) {
            return $this->json(['error' => 'Impossible de modifier un template système'], Response::HTTP_FORBIDDEN);
        }

        $atelierId = $this->atelierResolver->resolveAtelierId();
        if ($atelierId !== null && $layout->getAtelierId() !== $atelierId) {
            return $this->json(['error' => 'Layout non trouvé'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true) ?? [];

        if (is_string($data['label'] ?? null)) {
            $layout->setLabel($data['label']);
        }
        if (array_key_exists('layoutJson', $data)) {
            $layout->setLayoutJson($this->sanitizeLayout($data['layoutJson']));
        }
        $layout->setUpdatedAt(new \DateTime());

        $this->em->flush();

        return $this->json($this->serialize($layout));
    }

    #[Route('/{id}', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(int $id): JsonResponse
    {
        $layout = $this->em->getRepository(DocumentLayout::class)->find($id);
        if (!$layout) {
            return $this->json(['error' => 'Layout non trouvé'], Response::HTTP_NOT_FOUND);
        }

        if ($layout->isDefault()) {
            return $this->json(['error' => 'Impossible de supprimer un template système'], Response::HTTP_FORBIDDEN);
        }

        $atelierId = $this->atelierResolver->resolveAtelierId();
        if ($atelierId !== null && $layout->getAtelierId() !== $atelierId) {
            return $this->json(['error' => 'Layout non trouvé'], Response::HTTP_NOT_FOUND);
        }

        $this->em->remove($layout);
        $this->em->flush();

        return $this->json(['deleted' => true]);
    }

    /**
     * Aperçu du document complet avec l'en-tête proposé.
     *
     * Le corps vient du vrai template Twig et de données fictives : c'est ce que
     * verra le client, contrairement à l'ancien aperçu qui n'affichait que les
     * blocs du designer sur une page blanche.
     */
    #[Route('/{code}/preview', methods: ['POST'])]
    public function preview(string $code, Request $request): Response
    {
        if (!$this->isCustomisable($code)) {
            return $this->json(['error' => 'Code invalide'], Response::HTTP_BAD_REQUEST);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $atelier = $this->resolveAtelier();
        $logoDataUri = $this->resolveLogoDataUri($atelier);
        $meta = $this->registry->get($code);

        $elements = array_key_exists('layoutJson', $data)
            ? $this->headerRenderer->normalizeElements($data['layoutJson'])
            : $this->headerRenderer->normalizeElements($this->findLayout($code, $atelier?->getId())?->getLayoutJson() ?? []);

        // Jetons libres fournis par le designer pour son propre aperçu.
        $sampleTokens = [];
        foreach (is_array($data['sampleData'] ?? null) ? $data['sampleData'] : [] as $key => $value) {
            if (is_string($key) && (is_scalar($value) || $value === null)) {
                $sampleTokens[$key] = (string) $value;
            }
        }

        $tokens = $this->headerRenderer->buildTokens($atelier, $logoDataUri, [
            'doc_title' => $meta['label'],
            ...$sampleTokens,
        ]);

        $customHeader = $elements === [] ? null : $this->headerRenderer->render($elements, $tokens);

        $html = $this->twig->render($meta['template'], [
            'atelier' => $atelier,
            'logo_data_uri' => $logoDataUri,
            'custom_header_html' => $customHeader,
            ...$this->fixtures->forCode($code),
        ]);

        $pdf = $this->pdfRenderer->render(
            $html,
            sprintf('%s — %s (aperçu en-tête)', $atelier?->getNom() ?? 'Atelier', $meta['label']),
            true,
            $meta['orientation'],
        );

        return new Response($pdf, Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf('inline; filename="apercu-entete-%s.pdf"', $code),
        ]);
    }

    private function isCustomisable(string $code): bool
    {
        return in_array($code, $this->registry->customisableHeaderCodes(), true);
    }

    /**
     * Ne conserve que des éléments exploitables : un layout corrompu ne doit pas
     * pouvoir casser la génération des documents réels.
     *
     * @return list<array<string, mixed>>
     */
    private function sanitizeLayout(mixed $layoutJson): array
    {
        return $this->headerRenderer->normalizeElements($layoutJson);
    }

    private function findLayout(string $code, ?int $atelierId): ?DocumentLayout
    {
        $repository = $this->em->getRepository(DocumentLayout::class);

        if ($atelierId !== null) {
            $custom = $repository->findOneBy(['atelierId' => $atelierId, 'code' => $code]);
            if ($custom) {
                return $custom;
            }
        }

        return $repository->findOneBy(['code' => $code, 'isDefault' => true]);
    }

    private function serialize(DocumentLayout $d): array
    {
        return [
            'id' => $d->getId(),
            'code' => $d->getCode(),
            'label' => $d->getLabel(),
            'layoutJson' => $d->getLayoutJson(),
            'isDefault' => $d->isDefault(),
            'createdAt' => $d->getCreatedAt()->format('c'),
            'updatedAt' => $d->getUpdatedAt()->format('c'),
        ];
    }

    private function resolveAtelier(): ?Atelier
    {
        $atelierId = $this->atelierResolver->resolveAtelierId();

        return $atelierId !== null ? $this->em->getRepository(Atelier::class)->find($atelierId) : null;
    }

    private function resolveLogoDataUri(?Atelier $atelier): ?string
    {
        $logoUrl = $atelier?->getLogoUrl();
        if (!$logoUrl) {
            return null;
        }

        $relativePath = parse_url($logoUrl, PHP_URL_PATH) ?: $logoUrl;
        $filePath = $this->projectDir . '/public' . $relativePath;
        if (!is_file($filePath) || !is_readable($filePath)) {
            return null;
        }

        $contents = file_get_contents($filePath);
        if ($contents === false) {
            return null;
        }

        return sprintf('data:%s;base64,%s', mime_content_type($filePath) ?: 'image/png', base64_encode($contents));
    }
}
