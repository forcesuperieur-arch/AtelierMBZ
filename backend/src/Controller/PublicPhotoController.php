<?php
namespace App\Controller;

use App\Entity\PhotoIntervention;
use App\Entity\RendezVous;
use App\Service\PublicTokenPolicy;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/public/photos')]
class PublicPhotoController extends AbstractController
{
    private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'heic', 'heif'];

    public function __construct(
        private EntityManagerInterface $em,
        private PublicTokenPolicy $publicTokenPolicy,
    ) {}

    #[Route('/{token}/{filename}', methods: ['GET'])]
    public function serve(string $token, string $filename): Response
    {
        if (strlen($token) < 16) {
            return $this->json(['error' => 'Not found'], Response::HTTP_NOT_FOUND);
        }

        $rdv = $this->em->getRepository(RendezVous::class)->findOneBy(['tokenSuivi' => $token]);
        if (!$rdv) {
            return $this->json(['error' => 'Not found'], Response::HTTP_NOT_FOUND);
        }

        // Même règle d'expiration que le lien de suivi/restitution
        // (PublicTokenPolicy) : valide tant que le RDV vit, puis dateRdv+30j
        // après clôture — les photos ne meurent jamais avant le lien qui les
        // référence (galerie EDL / comparatif de restitution).
        if ($this->publicTokenPolicy->isTokenExpired($rdv)) {
            return $this->json(['error' => 'Token expired'], Response::HTTP_GONE);
        }

        $safeFilename = basename($filename);
        $ext = strtolower(pathinfo($safeFilename, PATHINFO_EXTENSION));
        if (!in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
            return $this->json(['error' => 'Not found'], Response::HTTP_NOT_FOUND);
        }

        // Verify the photo belongs to this RDV (prevents cross-RDV enumeration)
        $photo = $this->em->getRepository(PhotoIntervention::class)->findOneBy([
            'rendezVous' => $rdv,
            'filename' => $safeFilename,
        ]);
        if (!$photo) {
            return $this->json(['error' => 'Not found'], Response::HTTP_NOT_FOUND);
        }

        $photoDir = realpath($this->getParameter('kernel.project_dir') . '/var/photos');
        $realPath = realpath($photoDir . '/' . $safeFilename);

        if ($realPath === false || !str_starts_with($realPath, $photoDir . '/') || !is_file($realPath)) {
            return $this->json(['error' => 'Not found'], Response::HTTP_NOT_FOUND);
        }

        $response = new BinaryFileResponse($realPath);
        $response->headers->set('Cache-Control', 'private, max-age=3600');
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, $safeFilename);
        return $response;
    }
}
