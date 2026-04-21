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
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/api/photos')]
#[IsGranted('ROLE_USER')]
class PhotoController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private SluggerInterface $slugger,
        private \App\Service\AuditService $auditService,
        private PhotoService $photoService,
    ) {}

    #[Route('/upload', methods: ['POST'])]
    public function upload(Request $request): JsonResponse
    {
        $file = $request->files->get('photo');
        $rdvId = $request->request->get('rendez_vous_id');
        $type = (string) ($request->request->get('type') ?? 'en_cours');

        if (!$file || !$rdvId) {
            return $this->json(['error' => 'Photo and rendez_vous_id required'], Response::HTTP_BAD_REQUEST);
        }

        $rdv = $this->em->getRepository(RendezVous::class)->find($rdvId);
        if (!$rdv) {
            return $this->json(['error' => 'RDV not found'], Response::HTTP_NOT_FOUND);
        }

        if (!in_array($type, $this->photoService->allowedTypes(), true)) {
            return $this->json([
                'error' => 'Type de photo invalide',
                'allowed_types' => $this->photoService->allowedTypes(),
            ], Response::HTTP_BAD_REQUEST);
        }

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

        $this->auditService->log(
            'photo_upload',
            'PhotoIntervention',
            $photo->getId(),
            sprintf('Photo %s uploadée pour RDV #%d — %s', $type, $rdvId, $photo->getFilename()),
        );

        return $this->json([
            'id' => $photo->getId(),
            'filename' => $photo->getFilename(),
            'type' => $photo->getType(),
        ], Response::HTTP_CREATED);
    }

    #[Route('/file/{filename}', methods: ['GET'])]
    public function serve(string $filename): Response
    {
        $filename = basename($filename);
        $path = $this->getParameter('kernel.project_dir') . '/var/photos/' . $filename;

        if (!file_exists($path)) {
            return $this->json(['error' => 'File not found'], Response::HTTP_NOT_FOUND);
        }

        $response = new BinaryFileResponse($path);
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
            'taken_at' => $p->getTakenAt()?->format('c'),
            'url' => '/api/photos/file/' . $p->getFilename(),
        ], $photos));
    }
}
