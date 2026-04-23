<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Vehicule;
use App\Entity\VODepotVente;
use App\Entity\VODocument;
use App\Entity\VOLivrePolice;
use App\Entity\VOPurchase;
use App\Infrastructure\InputNormalizer;
use App\Service\AuditService;
use App\Service\CurrentAtelierResolver;
use App\Service\PdfService;
use App\Service\VOCompanionWorkflowService;
use App\Service\VODocumentService;
use App\Service\VOGeneratedDocumentService;
use App\Service\VOLivrePoliceService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

#[Route('/api/vo')]
class VODocumentController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private PdfService $pdfService,
        private VOLivrePoliceService $livrePoliceService,
        private VODocumentService $documentService,
        private VOCompanionWorkflowService $companionWorkflowService,
        private VOGeneratedDocumentService $generatedDocumentService,
        private SerializerInterface&NormalizerInterface $serializer,
        private AuditService $audit,
        private CurrentAtelierResolver $currentAtelierResolver,
        private InputNormalizer $inputNormalizer,
    ) {}

    #[Route('/livre-police', methods: ['GET'])]
    public function listLivrePolice(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_VO_MANAGER');
        $atelierId = $this->currentAtelierResolver->resolveAtelierId();
        if (!$atelierId) {
            return $this->json(['error' => 'Sélectionnez un atelier avant d\'ouvrir le Livre de Police.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $qb = $this->em->getRepository(VOLivrePolice::class)->createQueryBuilder('lp')
            ->andWhere('lp.atelierId = :atelierId')
            ->setParameter('atelierId', $atelierId)
            ->orderBy('lp.numeroOrdre', 'ASC');

        $data = $this->serializer->normalize($qb->getQuery()->getResult(), null, ['groups' => 'livrepolice:read']);

        return $this->json($data);
    }

    #[Route('/livre-police/pdf', methods: ['GET'])]
    public function downloadLivrePolicePdf(Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_VO_MANAGER');
        $atelierId = $this->currentAtelierResolver->resolveAtelierId();
        if (!$atelierId) {
            return $this->json(['error' => 'Sélectionnez un atelier avant de télécharger le Livre de Police.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $qb = $this->em->getRepository(VOLivrePolice::class)->createQueryBuilder('lp')
            ->andWhere('lp.atelierId = :atelierId')
            ->setParameter('atelierId', $atelierId)
            ->orderBy('lp.numeroOrdre', 'ASC');

        $entries = $qb->getQuery()->getResult();
        $filePath = $this->pdfService->generateLivrePolicePdf($entries, $atelierId);

        $response = new BinaryFileResponse($filePath);
        $response->headers->set('Content-Type', 'application/pdf');
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, 'livre-police.pdf');

        return $response;
    }

    #[Route('/documents', methods: ['GET'])]
    public function listDocuments(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_VO_MANAGER');
        $purchaseId = $request->query->getInt('purchaseId');
        $depotId = $request->query->getInt('depotId');

        $qb = $this->em->getRepository(VODocument::class)->createQueryBuilder('d')
            ->orderBy('d.uploadedAt', 'DESC');

        if ($purchaseId) {
            $qb->andWhere('d.voPurchase = :pid')->setParameter('pid', $purchaseId);
        }
        if ($depotId) {
            $qb->andWhere('d.voDepotVente = :did')->setParameter('did', $depotId);
        }

        $data = $this->serializer->normalize($qb->getQuery()->getResult(), null, ['groups' => 'vodoc:read']);

        return $this->json($data);
    }

    #[Route('/documents/upload', methods: ['POST'])]
    public function uploadDocument(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_VO_MANAGER');

        $file = $request->files->get('file');
        $type = $request->request->get('type');

        if (!$file || !$type) {
            return $this->json(['error' => 'File and type are required'], 400);
        }

        if (in_array($type, [VODocument::TYPE_PIECE_IDENTITE, VODocument::TYPE_JUSTIFICATIF_DOMICILE], true)) {
            return $this->json([
                'error' => 'La pièce d\'identité et le justificatif de domicile ne doivent pas être archivés. Retranscrivez type, numéro et date puis détruisez le support.',
            ], 422);
        }

        $purchase = null;
        $depot = null;
        $purchaseId = $request->request->getInt('purchaseId');
        $depotId = $request->request->getInt('depotId');

        if ($purchaseId) {
            $purchase = $this->em->getRepository(VOPurchase::class)->find($purchaseId);
        }
        if ($depotId) {
            $depot = $this->em->getRepository(VODepotVente::class)->find($depotId);
        }

        $dateExpiration = $request->request->get('dateExpiration')
            ? new \DateTime($request->request->get('dateExpiration'))
            : null;

        try {
            $doc = $this->documentService->upload(
                $file,
                $type,
                $purchase,
                $depot,
                $this->getUser(),
                $dateExpiration,
            );
            $this->em->flush();

            $this->audit->log('upload_vo_document', 'vo_document', $doc->getId(), json_encode([
                'type' => $type,
                'purchaseId' => $purchase?->getId(),
                'depotId' => $depot?->getId(),
            ]));

            return $this->json(
                $this->serializer->normalize($doc, null, ['groups' => 'vodoc:read']),
                201,
            );
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }

    #[Route('/documents/alerts', methods: ['GET'])]
    public function documentAlerts(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_VO_MANAGER');
        $atelierId = $this->getAuthenticatedUser()?->getAtelierId();

        return $this->json($this->documentService->getAlerts($atelierId));
    }

    #[Route('/documents/{id}/download', methods: ['GET'])]
    public function downloadDocument(int $id): Response
    {
        $this->denyAccessUnlessGranted('ROLE_VO_MANAGER');
        $atelierId = $this->getAuthenticatedUser()?->getAtelierId();

        $document = $this->em->getRepository(VODocument::class)->find($id);
        if (!$document) {
            return $this->json(['error' => 'Not found'], 404);
        }
        if ($atelierId !== null && $document->getAtelierId() !== $atelierId) {
            return $this->json(['error' => 'Not found'], 404);
        }

        $projectDir = (string) $this->getParameter('kernel.project_dir');
        $relativePath = $document->getFilePath();
        if (!str_starts_with($relativePath, '/uploads/vo/')) {
            return $this->json(['error' => 'Document path is not allowed'], 404);
        }

        $uploadsDir = realpath($projectDir . '/public/uploads/vo');
        if ($uploadsDir === false) {
            return $this->json(['error' => 'Document storage unavailable'], 404);
        }

        $resolvedPath = realpath($projectDir . '/public' . $relativePath);
        if ($resolvedPath === false || !str_starts_with($resolvedPath, $uploadsDir . '/') || !is_file($resolvedPath)) {
            return $this->json(['error' => 'File not found'], 404);
        }

        $response = new BinaryFileResponse($resolvedPath);
        $response->headers->set('Cache-Control', 'private, max-age=3600');
        $response->headers->set('Content-Type', $document->getMimeType() ?: 'application/octet-stream');
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, $document->getOriginalFilename());

        return $response;
    }

    #[Route('/purchases/{id}/vehicule', methods: ['PUT'])]
    public function updatePurchaseVehicule(int $id, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_VO_MANAGER');
        $atelierId = $this->getAuthenticatedUser()?->getAtelierId();

        $purchase = $this->em->getRepository(VOPurchase::class)->find($id);
        if (!$purchase || !$purchase->getVehicule()) {
            return $this->json(['error' => 'Not found'], 404);
        }
        if ($atelierId !== null && $purchase->getAtelierId() !== $atelierId) {
            return $this->json(['error' => 'Not found'], 404);
        }

        $this->applyVehiculePayload($purchase->getVehicule(), $request->toArray());
        $this->em->flush();

        return $this->json([
            'success' => true,
            'vehicule' => $this->serializer->normalize($purchase->getVehicule(), null, ['groups' => 'vehicule:read']),
        ]);
    }

    #[Route('/depots/{id}/vehicule', methods: ['PUT'])]
    public function updateDepotVehicule(int $id, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_VO_MANAGER');
        $atelierId = $this->getAuthenticatedUser()?->getAtelierId();

        $depot = $this->em->getRepository(VODepotVente::class)->find($id);
        if (!$depot || !$depot->getVehicule()) {
            return $this->json(['error' => 'Not found'], 404);
        }
        if ($atelierId !== null && $depot->getAtelierId() !== $atelierId) {
            return $this->json(['error' => 'Not found'], 404);
        }

        $this->applyVehiculePayload($depot->getVehicule(), $request->toArray());
        $this->em->flush();

        return $this->json([
            'success' => true,
            'vehicule' => $this->serializer->normalize($depot->getVehicule(), null, ['groups' => 'vehicule:read']),
        ]);
    }

    private function applyVehiculePayload(Vehicule $vehicule, array $data): void
    {
        if (isset($data['marque'])) {
            $vehicule->setMarque($this->inputNormalizer->nullableString($data['marque']));
        }
        if (isset($data['modele'])) {
            $vehicule->setModele($this->inputNormalizer->nullableString($data['modele']));
        }
        if (isset($data['vin'])) {
            $vin = $this->inputNormalizer->nullableString($data['vin']);
            $vehicule->setVin($vin ? strtoupper(substr($vin, 0, 17)) : null);
        }
        if (isset($data['annee'])) {
            $annee = (int) $data['annee'];
            $vehicule->setAnnee($annee > 0 ? $annee : null);
        }
        if (isset($data['cylindree'])) {
            $vehicule->setCylindree($this->inputNormalizer->nullableString($data['cylindree']));
        }
        if (isset($data['type_moto'])) {
            $vehicule->setTypeMoto($this->inputNormalizer->nullableString($data['type_moto']));
        }
        if (isset($data['plaque'])) {
            $plaque = $this->inputNormalizer->nullableString($data['plaque']);
            if ($plaque !== null) {
                $vehicule->setPlaque($plaque);
            }
        }
    }

    private function getAuthenticatedUser(): ?User
    {
        $user = $this->getUser();

        return $user instanceof User ? $user : null;
    }

    private function resolveAtelierId(?int $atelierId = null): int
    {
        return $atelierId ?? $this->getAuthenticatedUser()?->getAtelierId() ?? 0;
    }
}
