<?php

namespace App\Controller;

use App\Entity\OrdreReparation;
use App\Service\PdfService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/rapport')]
class RapportPdfController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private PdfService $pdfService,
    ) {}

    #[Route('/{id}/pdf', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function downloadPdf(int $id): Response
    {
        $or = $this->em->getRepository(OrdreReparation::class)->find($id);

        if (!$or) {
            throw $this->createNotFoundException('Ordre de réparation non trouvé');
        }

        $user = $this->getUser();
        $userAtelierId = method_exists($user, 'getAtelierId') ? $user->getAtelierId() : null;
        $rdv = $or->getRendezVous();
        $orAtelierId = $rdv?->getAtelierId();

        if ($userAtelierId && $orAtelierId && $userAtelierId !== $orAtelierId) {
            throw $this->createNotFoundException('Accès refusé');
        }

        // Une fois l'OR scellé à la restitution (final_hash), SEUL le PDF archivé
        // immuable fait foi — jamais un re-rendu depuis l'entité vivante (dont
        // etat_vehicule / notes mécano restent modifiables et différeraient du
        // document scellé). Aligné sur OrdreReparationPdfController.
        if ($or->getFinalHash() !== null) {
            $archived = $this->pdfService->getArchivedOrPdfPath($or);
            if ($archived === null) {
                throw $this->createNotFoundException('Document archivé momentanément indisponible');
            }
            return $this->file($archived, 'OR-' . $or->getNumeroOr() . '.pdf');
        }

        // OR non encore scellé : brouillon de travail (page mécanicien) — rendu à
        // la volée autorisé (document non opposable à ce stade).
        $pdfPath = $this->pdfService->getOrPdfPath($or);
        if (!is_file($pdfPath) || !is_readable($pdfPath)) {
            $pdfPath = $this->pdfService->generateOrPdf($or);
        }

        $content = file_get_contents($pdfPath);
        if ($content === false) {
            throw $this->createNotFoundException('PDF non générable');
        }

        return new Response($content, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="OR-' . $or->getNumeroOr() . '.pdf"',
        ]);
    }
}
