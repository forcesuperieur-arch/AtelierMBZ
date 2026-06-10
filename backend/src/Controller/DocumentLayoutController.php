<?php

namespace App\Controller;

use App\Entity\DocumentLayout;
use App\Service\CurrentAtelierResolver;
use Doctrine\ORM\EntityManagerInterface;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/admin/document-layouts')]
#[IsGranted('ROLE_ADMIN')]
class DocumentLayoutController extends AbstractController
{
    private const PAGE_WIDTH = 210;
    private const PAGE_HEIGHT = 297;

    public function __construct(
        private EntityManagerInterface $em,
        private CurrentAtelierResolver $atelierResolver,
    ) {}

    #[Route('', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $atelierId = $this->atelierResolver->resolveAtelierId();
        $qb = $this->em->getRepository(DocumentLayout::class)->createQueryBuilder('d')
            ->where('d.atelierId = :atelierId OR d.isDefault = true')
            ->setParameter('atelierId', $atelierId)
            ->orderBy('d.isDefault', 'DESC')
            ->addOrderBy('d.code', 'ASC');

        $items = $qb->getQuery()->getResult();

        // Deduplicate: prefer atelier-specific over default
        $byCode = [];
        foreach ($items as $item) {
            $code = $item->getCode();
            if (!isset($byCode[$code]) || !$item->isDefault()) {
                $byCode[$code] = $item;
            }
        }

        return $this->json(array_map(fn($d) => $this->serialize($d), array_values($byCode)));
    }

    #[Route('/{code}', methods: ['GET'])]
    public function get(string $code): JsonResponse
    {
        if (!in_array($code, DocumentLayout::CODES, true)) {
            return $this->json(['error' => 'Code invalide'], Response::HTTP_BAD_REQUEST);
        }

        $atelierId = $this->atelierResolver->resolveAtelierId();
        $layout = $this->findLayout($code, $atelierId);

        if (!$layout) {
            return $this->json(['error' => 'Layout non trouvé'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->serialize($layout));
    }

    #[Route('', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $code = $data['code'] ?? null;

        if (!$code || !in_array($code, DocumentLayout::CODES, true)) {
            return $this->json(['error' => 'Code invalide', 'allowed' => DocumentLayout::CODES], Response::HTTP_BAD_REQUEST);
        }

        $atelierId = $this->atelierResolver->resolveAtelierId();
        if ($atelierId === null) {
            return $this->json(['error' => 'Aucun atelier actif'], Response::HTTP_BAD_REQUEST);
        }

        $existing = $this->em->getRepository(DocumentLayout::class)->findOneBy([
            'atelierId' => $atelierId,
            'code' => $code,
        ]);
        if ($existing) {
            return $this->json(['error' => 'Un layout existe déjà pour ce code'], Response::HTTP_CONFLICT);
        }

        $layout = new DocumentLayout();
        $layout->setAtelierId($atelierId);
        $layout->setCode($code);
        $layout->setLabel($data['label'] ?? $code);
        $layout->setLayoutJson($data['layoutJson'] ?? []);
        $layout->setIsDefault(false);

        $this->em->persist($layout);
        $this->em->flush();

        return $this->json($this->serialize($layout), Response::HTTP_CREATED);
    }

    #[Route('/{id}', methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $layout = $this->em->getRepository(DocumentLayout::class)->find($id);
        if (!$layout) {
            return $this->json(['error' => 'Layout non trouvé'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true) ?? [];

        if (isset($data['label'])) {
            $layout->setLabel($data['label']);
        }
        if (isset($data['layoutJson'])) {
            $layout->setLayoutJson($data['layoutJson']);
        }
        $layout->setUpdatedAt(new \DateTime());

        $this->em->flush();

        return $this->json($this->serialize($layout));
    }

    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $layout = $this->em->getRepository(DocumentLayout::class)->find($id);
        if (!$layout) {
            return $this->json(['error' => 'Layout non trouvé'], Response::HTTP_NOT_FOUND);
        }

        if ($layout->isDefault()) {
            return $this->json(['error' => 'Impossible de supprimer un template système'], Response::HTTP_FORBIDDEN);
        }

        $this->em->remove($layout);
        $this->em->flush();

        return $this->json(['deleted' => true]);
    }

    #[Route('/{code}/preview', methods: ['POST'])]
    public function preview(string $code, Request $request): Response
    {
        if (!in_array($code, DocumentLayout::CODES, true)) {
            return $this->json(['error' => 'Code invalide'], Response::HTTP_BAD_REQUEST);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $layoutJson = $data['layoutJson'] ?? null;
        $sampleData = $data['sampleData'] ?? [];

        // Support both formats: [elements] and { elements: [...] }
        if (is_array($layoutJson) && isset($layoutJson['elements'])) {
            $layoutJson = $layoutJson['elements'];
        }

        // If no layoutJson provided, load from DB
        if ($layoutJson === null) {
            $atelierId = $this->atelierResolver->resolveAtelierId();
            $layout = $this->findLayout($code, $atelierId);
            if (!$layout) {
                return $this->json(['error' => 'Layout non trouvé'], Response::HTTP_NOT_FOUND);
            }
            $layoutJson = $layout->getLayoutJson();
        }

        $html = $this->buildHtml($layoutJson, $sampleData);
        return $this->renderPdf($html, "preview-{$code}");
    }

    private function findLayout(string $code, ?int $atelierId): ?DocumentLayout
    {
        if ($atelierId) {
            $custom = $this->em->getRepository(DocumentLayout::class)->findOneBy([
                'atelierId' => $atelierId,
                'code' => $code,
            ]);
            if ($custom) {
                return $custom;
            }
        }
        return $this->em->getRepository(DocumentLayout::class)->findOneBy([
            'code' => $code,
            'isDefault' => true,
        ]);
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

    private function buildHtml(array $layoutJson, array $data): string
    {
        $elements = [];
        foreach ($layoutJson as $el) {
            $elements[] = $this->renderElement($el, $data);
        }

        $elementsHtml = implode("\n", $elements);

        return <<<HTML
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                @page { margin: 0; size: 210mm 297mm; }
                * { box-sizing: border-box; margin: 0; padding: 0; }
                body {
                    font-family: DejaVu Sans, sans-serif;
                    font-size: 11px;
                    color: #1f2937;
                }
                .page {
                    position: relative;
                    width: 210mm;
                    height: 297mm;
                    overflow: hidden;
                }
            </style>
        </head>
        <body>
            <div class="page">
                {$elementsHtml}
            </div>
        </body>
        </html>
        HTML;
    }

    private function renderElement(array $el, array $data): string
    {
        $type = $el['type'] ?? 'text';
        $x = $el['x'] ?? 0;
        $y = $el['y'] ?? 0;
        $w = $el['w'] ?? 50;
        $h = $el['h'] ?? 10;
        $style = $el['style'] ?? [];
        $content = $el['content'] ?? '';

        // Replace variables {{key}} with data
        $content = preg_replace_callback('/\{\{(\w+)\}\}/', function ($m) use ($data) {
            return $data[$m[1]] ?? $m[0];
        }, $content);

        $css = sprintf(
            'position:absolute; left:%smm; top:%smm; width:%smm; height:%smm;',
            $x, $y, $w, $h
        );

        $fontSize = $style['fontSize'] ?? 11;
        $css .= sprintf(' font-size:%spx;', $fontSize);

        if (!empty($style['bold'])) {
            $css .= ' font-weight:bold;';
        }
        if (!empty($style['italic'])) {
            $css .= ' font-style:italic;';
        }
        if (!empty($style['color'])) {
            $css .= sprintf(' color:%s;', $style['color']);
        }
        if (!empty($style['align'])) {
            $css .= sprintf(' text-align:%s;', $style['align']);
        }
        if (!empty($style['backgroundColor'])) {
            $css .= sprintf(' background-color:%s;', $style['backgroundColor']);
        }

        return match ($type) {
            'image' => sprintf(
                '<div style="%s overflow:hidden;"><img src="%s" style="width:100%%; height:100%%; object-fit:contain;" /></div>',
                $css, htmlspecialchars($content, ENT_QUOTES)
            ),
            'line' => sprintf(
                '<div style="%s border-top:1px solid %s;"></div>',
                $css, htmlspecialchars($style['color'] ?? '#1f2937', ENT_QUOTES)
            ),
            'rect' => sprintf(
                '<div style="%s border:1px solid %s;"></div>',
                $css, htmlspecialchars($style['color'] ?? '#1f2937', ENT_QUOTES)
            ),
            default => sprintf(
                '<div style="%s overflow:hidden; word-wrap:break-word;">%s</div>',
                $css, nl2br(htmlspecialchars($content, ENT_QUOTES))
            ),
        };
    }

    private function renderPdf(string $html, string $filename): Response
    {
        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return new Response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf('inline; filename="%s.pdf"', $filename),
        ]);
    }
}
