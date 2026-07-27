<?php

namespace App\Controller;

use App\Entity\Atelier;
use App\Service\DocumentHeaderRenderer;
use App\Service\DocumentPreviewFixtures;
use App\Service\PdfRenderer;
use App\Service\PdfTemplateRegistry;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Twig\Environment;

/**
 * Prévisualisation des documents PDF depuis l'administration.
 *
 * La liste des documents et leurs jeux de données fictives vivent dans
 * PdfTemplateRegistry / DocumentPreviewFixtures : ce contrôleur ne fait plus que
 * les assembler, ce qui évite qu'il reparte en divergence avec les templates
 * réellement présents sur le disque.
 */
#[Route('/api/admin/templates')]
#[IsGranted('ROLE_ADMIN')]
class AdminTemplatePreviewController extends AbstractController
{
    public function __construct(
        private Environment $twig,
        private EntityManagerInterface $em,
        private string $projectDir,
        private PdfTemplateRegistry $registry,
        private DocumentPreviewFixtures $fixtures,
        private DocumentHeaderRenderer $headerRenderer,
        private PdfRenderer $pdfRenderer,
    ) {}

    #[Route('', methods: ['GET'])]
    public function list(): JsonResponse
    {
        return $this->json($this->registry->all());
    }

    #[Route('/{code}/preview', methods: ['GET'])]
    public function preview(string $code): Response
    {
        if (!$this->registry->has($code)) {
            return $this->json(['error' => 'Template inconnu'], Response::HTTP_NOT_FOUND);
        }

        $meta = $this->registry->get($code);
        $atelier = $this->resolveAtelier();
        $logoDataUri = $this->resolveLogoDataUri($atelier);

        $customHeader = $this->headerRenderer->renderFor(
            $code,
            $atelier?->getId(),
            $atelier,
            $logoDataUri,
            ['doc_title' => $meta['label']],
        );

        $html = $this->twig->render($meta['template'], [
            'atelier' => $atelier,
            'logo_data_uri' => $logoDataUri,
            'custom_header_html' => $customHeader,
            ...$this->fixtures->forCode($code),
        ]);

        $pdf = $this->pdfRenderer->render(
            $html,
            sprintf('%s — %s (aperçu)', $atelier?->getNom() ?? 'Atelier', $meta['label']),
            true,
            $meta['orientation'],
        );

        return new Response($pdf, Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf('inline; filename="apercu-%s.pdf"', $code),
        ]);
    }

    private function resolveAtelier(): ?Atelier
    {
        $user = $this->getUser();
        $atelierId = $user !== null && method_exists($user, 'getAtelierId') ? $user->getAtelierId() : null;

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
