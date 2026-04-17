<?php
namespace App\Controller;

use App\Entity\PhotoIntervention;
use App\Entity\RendezVous;
use App\Service\PhotoService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/photos')]
class PhotoController extends AbstractController
{
    private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    public function __construct(
        private EntityManagerInterface $em,
        private PhotoService $photoService,
        private RateLimiterFactory $companionUploadLimiter,
    ) {}

    #[Route('/upload', methods: ['POST'])]
    public function upload(Request $request): JsonResponse
    {
        $limiter = $this->companionUploadLimiter->create($request->getClientIp());
        if (!$limiter->consume()->isAccepted()) {
            return $this->json(['error' => 'Too many requests'], Response::HTTP_TOO_MANY_REQUESTS);
        }

        $file = $request->files->get('photo');
        $rdvId = $request->request->get('rendez_vous_id');
        $type = $request->request->get('type', 'en_cours');

        if (!$file || !$rdvId) {
            return $this->json(['error' => 'Photo and rendez_vous_id required'], Response::HTTP_BAD_REQUEST);
        }

        $rdv = $this->em->getRepository(RendezVous::class)->find($rdvId);
        if (!$rdv) {
            return $this->json(['error' => 'RDV not found'], Response::HTTP_NOT_FOUND);
        }

        $this->denyAccessUnlessGranted('ATELIER_ACCESS', $rdv);

        try {
            $photo = $this->photoService->upload(
                $file,
                $type,
                $rdv,
                $request->request->get('description'),
                $request->request->get('annotation_json'),
            );
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        return $this->json([
            'id' => $photo->getId(),
            'filename' => $photo->getFilename(),
            'type' => $photo->getType(),
            'sha256' => $photo->getSha256(),
            'takenAt' => $photo->getTakenAt()?->format('c'),
        ], Response::HTTP_CREATED);
    }

    #[Route('/file/{filename}', methods: ['GET'])]
    public function serve(string $filename): Response
    {
        $filename = basename($filename);

        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (!in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
            return $this->json(['error' => 'File not found'], Response::HTTP_NOT_FOUND);
        }

        $photoDir = realpath($this->getParameter('kernel.project_dir') . '/var/photos');
        $path = $photoDir . '/' . $filename;
        $realPath = realpath($path);

        if ($realPath === false || !str_starts_with($realPath, $photoDir . '/') || !is_file($realPath)) {
            return $this->json(['error' => 'File not found'], Response::HTTP_NOT_FOUND);
        }

        $response = new BinaryFileResponse($realPath);
        $response->headers->set('Cache-Control', 'public, max-age=86400');
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, $filename);
        return $response;
    }

    #[Route('/rdv/{rdvId}', methods: ['GET'])]
    public function listByRdv(int $rdvId): JsonResponse
    {
        $rdv = $this->em->getRepository(RendezVous::class)->find($rdvId);
        if (!$rdv) {
            return $this->json(['error' => 'RDV not found'], Response::HTTP_NOT_FOUND);
        }

        $this->denyAccessUnlessGranted('ATELIER_ACCESS', $rdv);

        $photos = $this->em->getRepository(PhotoIntervention::class)->findBy(
            ['rendezVous' => $rdv],
            ['createdAt' => 'DESC']
        );

        return $this->json(array_map(fn(PhotoIntervention $p) => [
            'id' => $p->getId(),
            'filename' => $p->getFilename(),
            'original_name' => $p->getOriginalName(),
            'description' => $p->getDescription(),
            'type' => $p->getType(),
            'sha256' => $p->getSha256(),
            'takenAt' => $p->getTakenAt()?->format('c'),
            'url' => '/api/photos/file/' . $p->getFilename(),
        ], $photos));
    }

    #[Route('/rdv/{rdvId}/check/{transition}', methods: ['GET'])]
    public function checkPhotosForTransition(int $rdvId, string $transition): JsonResponse
    {
        $rdv = $this->em->getRepository(RendezVous::class)->find($rdvId);
        if (!$rdv) {
            return $this->json(['error' => 'RDV not found'], Response::HTTP_NOT_FOUND);
        }

        $this->denyAccessUnlessGranted('ATELIER_ACCESS', $rdv);

        $missing = $this->photoService->requirePhotosForTransition($transition, $rdv);

        return $this->json([
            'transition' => $transition,
            'canProceed' => empty($missing),
            'missing' => $missing,
        ]);
    }

    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $photo = $this->em->getRepository(PhotoIntervention::class)->find($id);
        if (!$photo) {
            return $this->json(['error' => 'Photo not found'], Response::HTTP_NOT_FOUND);
        }

        $this->denyAccessUnlessGranted('ATELIER_ACCESS', $photo->getRendezVous());

        $path = $this->getParameter('kernel.project_dir') . '/var/photos/' . basename($photo->getFilename());
        if (file_exists($path)) {
            unlink($path);
        }

        $this->em->remove($photo);
        $this->em->flush();

        return $this->json(['deleted' => true]);
    }
}
