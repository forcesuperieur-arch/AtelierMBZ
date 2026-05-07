<?php

namespace App\Controller;

use App\Entity\VOLivrePolice;
use App\Service\CurrentAtelierResolver;
use App\Service\LivrePolicePdfService;
use App\Service\VOLivrePoliceService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/vo')]
#[IsGranted('ROLE_USER')]
class LivrePoliceExportController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private LivrePolicePdfService $pdfService,
        private CurrentAtelierResolver $currentAtelierResolver,
        private VOLivrePoliceService $livrePoliceService,
    ) {}

    /**
     * [LOT-0] Export PDF du Livre de Police pour contrôle des autorités.
     * Portrait A4, filtres date/type, hash d'intégrité.
     */
    #[Route('/livre-de-police/export-pdf', methods: ['GET'])]
    public function exportPdf(Request $request): Response
    {
        if (
            !$this->isGranted('ROLE_SUPER_ADMIN')
            && !$this->isGranted('ROLE_RESPONSABLE_MAGASIN')
            && !$this->isGranted('ROLE_COMPTABLE')
            && !$this->isGranted('ROLE_VO_MANAGER')
        ) {
            throw $this->createAccessDeniedException("Accès refusé à l'export du Livre de Police.");
        }

        $atelierId = $this->currentAtelierResolver->resolveAtelierId();
        if (!$atelierId) {
            return $this->json(
                ['error' => "Sélectionnez un atelier avant d'exporter le Livre de Police."],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $qb = $this->em->getRepository(VOLivrePolice::class)->createQueryBuilder('lp')
            ->andWhere('lp.atelierId = :atelierId')
            ->setParameter('atelierId', $atelierId)
            ->orderBy('lp.dateAcquisition', 'ASC')
            ->addOrderBy('lp.numeroOrdre', 'ASC');

        $filters = [];

        $dateDebut = $request->query->get('date_debut');
        if ($dateDebut) {
            try {
                $dt = new \DateTime($dateDebut);
                $qb->andWhere('lp.dateAcquisition >= :dateDebut')
                   ->setParameter('dateDebut', $dt->format('Y-m-d'));
                $filters['date_debut'] = $dt->format('d/m/Y');
            } catch (\Exception) {
                return $this->json(
                    ['error' => 'Format date_debut invalide (YYYY-MM-DD attendu).'],
                    Response::HTTP_BAD_REQUEST
                );
            }
        }

        $dateFin = $request->query->get('date_fin');
        if ($dateFin) {
            try {
                $dt = new \DateTime($dateFin);
                $qb->andWhere('lp.dateAcquisition <= :dateFin')
                   ->setParameter('dateFin', $dt->format('Y-m-d'));
                $filters['date_fin'] = $dt->format('d/m/Y');
            } catch (\Exception) {
                return $this->json(
                    ['error' => 'Format date_fin invalide (YYYY-MM-DD attendu).'],
                    Response::HTTP_BAD_REQUEST
                );
            }
        }

        $type = $request->query->get('type');
        if ($type) {
            $validTypes = ['achat', 'depot_vente', 'vente'];
            if (!in_array($type, $validTypes, true)) {
                return $this->json(
                    ['error' => 'Type invalide. Valeurs acceptées : ' . implode(', ', $validTypes) . '.'],
                    Response::HTTP_BAD_REQUEST
                );
            }
            if ($type === 'vente') {
                $qb->andWhere('lp.dateVente IS NOT NULL');
            } else {
                $qb->andWhere('lp.type = :type')
                   ->setParameter('type', $type);
            }
            $filters['type'] = $type;
        }

        $entries = $qb->getQuery()->getResult();

        $filePath = $this->pdfService->generateExportPdf($entries, $atelierId, $filters);

        $response = new BinaryFileResponse($filePath);
        $response->headers->set('Content-Type', 'application/pdf');
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            'livre-de-police-export.pdf'
        );

        return $response;
    }

    /**
     * [LOT-11] Vérification d'intégrité d'une entrée LP : recalcule le hash et compare.
     */
    #[Route('/livre-police/{id}/verify', methods: ['GET'])]
    public function verifyEntry(int $id): JsonResponse
    {
        if (
            !$this->isGranted('ROLE_SUPER_ADMIN')
            && !$this->isGranted('ROLE_RESPONSABLE_MAGASIN')
            && !$this->isGranted('ROLE_COMPTABLE')
            && !$this->isGranted('ROLE_VO_MANAGER')
        ) {
            throw $this->createAccessDeniedException("Accès refusé à la vérification du Livre de Police.");
        }

        $entry = $this->em->getRepository(VOLivrePolice::class)->find($id);
        if (!$entry instanceof VOLivrePolice) {
            return $this->json(['error' => 'Entrée non trouvée.'], Response::HTTP_NOT_FOUND);
        }

        $storedHash = $entry->getIntegrityHash();
        $computedHash = $this->livrePoliceService->computeIntegrityHash($entry);

        return $this->json([
            'id' => $entry->getId(),
            'numeroOrdre' => $entry->getNumeroOrdre(),
            'integrity_hash' => $storedHash,
            'computed_hash' => $computedHash,
            'valid' => $storedHash !== null && hash_equals($storedHash, $computedHash),
        ]);
    }
}
