<?php
namespace App\Controller;

use App\Entity\PhotoIntervention;
use App\Entity\RendezVous;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/public/photos')]
class PublicPhotoController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em) {}

    #[Route('/{token}/{filename}', methods: ['GET'])]
    public function serve(string $token, string $filename): Response
    {
        $rdv = $this->em->getRepository(RendezVous::class)->findOneBy(['tokenSuivi' => $token]);
        if (!$rdv) {
            return $this->json(['error' => 'Invalid token'], Response::HTTP_NOT_FOUND);
        }

        // Token expires 30 days after RDV creation
        $expiry = (clone $rdv->getCreatedAt())->modify('+30 days');
        if (new \DateTime() > $expiry) {
            return $this->json(['error' => 'Token expired'], Response::HTTP_GONE);
        }

        // Verify the photo belongs to this RDV
        $safeFilename = basename($filename);
        $photo = $this->em->getRepository(PhotoIntervention::class)->findOneBy([
            'rendezVous' => $rdv,
            'filename' => $safeFilename,
        ]);

        if (!$photo) {
            return $this->json(['error' => 'Photo not found for this RDV'], Response::HTTP_NOT_FOUND);
        }

        $path = $this->getParameter('kernel.project_dir') . '/var/photos/' . $safeFilename;
        if (!file_exists($path)) {
            return $this->json(['error' => 'File not found'], Response::HTTP_NOT_FOUND);
        }

        $response = new BinaryFileResponse($path);
        $response->headers->set('Cache-Control', 'public, max-age=86400');
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, $safeFilename);
        return $response;
    }
}
