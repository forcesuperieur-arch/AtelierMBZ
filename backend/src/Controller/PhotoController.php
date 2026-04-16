<?php
namespace App\Controller;

use App\Entity\PhotoIntervention;
use App\Entity\RendezVous;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/api/photos')]
class PhotoController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private SluggerInterface $slugger,
    ) {}

    #[Route('/upload', methods: ['POST'])]
    public function upload(Request $request): JsonResponse
    {
        $file = $request->files->get('photo');
        $rdvId = $request->request->get('rendez_vous_id');

        if (!$file || !$rdvId) {
            return $this->json(['error' => 'Photo and rendez_vous_id required'], Response::HTTP_BAD_REQUEST);
        }

        $rdv = $this->em->getRepository(RendezVous::class)->find($rdvId);
        if (!$rdv) {
            return $this->json(['error' => 'RDV not found'], Response::HTTP_NOT_FOUND);
        }

        // Validate file type
        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
        if (!in_array($file->getMimeType(), $allowedMimes, true)) {
            return $this->json(['error' => 'Only JPEG, PNG, and WebP images are allowed'], Response::HTTP_BAD_REQUEST);
        }

        // Max 10MB
        if ($file->getSize() > 10 * 1024 * 1024) {
            return $this->json(['error' => 'File too large (max 10MB)'], Response::HTTP_BAD_REQUEST);
        }

        $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeName = $this->slugger->slug($originalName);
        $filename = $safeName . '-' . uniqid() . '.' . $file->guessExtension();

        $uploadDir = $this->getParameter('kernel.project_dir') . '/var/photos';
        $file->move($uploadDir, $filename);

        $photo = new PhotoIntervention();
        $photo->setRendezVous($rdv);
        $photo->setFilename($filename);
        $photo->setOriginalName($file->getClientOriginalName());
        $photo->setDescription($request->request->get('description'));
        $photo->setAnnotationJson($request->request->get('annotation_json'));
        $photo->setAtelierId($rdv->getAtelierId());

        $this->em->persist($photo);
        $this->em->flush();

        return $this->json([
            'id' => $photo->getId(),
            'filename' => $filename,
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
            'url' => '/api/photos/file/' . $p->getFilename(),
        ], $photos));
    }
}
