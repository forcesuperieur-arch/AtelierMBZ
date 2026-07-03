<?php

namespace App\Controller;

use App\Entity\EtatDesLieux;
use App\Entity\RendezVous;
use App\Entity\User;
use App\Service\AuditService;
use App\Service\EtatDesLieuxDocumentService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Endpoints staff de l'état des lieux d'entrée (Lot B).
 *
 * Contrôle tenant : les lookups passent par les repositories Doctrine, sur
 * lesquels le TenantFilter (atelier_id) est actif pour tout staff authentifié
 * — un RDV / état des lieux d'un autre atelier renvoie donc 404, comme dans
 * les autres contrôleurs staff.
 */
#[IsGranted('ROLE_USER')]
class EtatDesLieuxController extends AbstractController
{
    private const ERROR_MESSAGES = [
        'DEJA_SIGNE' => 'L\'état des lieux est déjà signé : document figé.',
        'DONNEES_INCOMPLETES' => 'Kilométrage et niveau de carburant sont obligatoires avant signature.',
        'PHOTOS_MANQUANTES' => 'Au moins 4 photos d\'entrée (check-in ou réception) sont requises avant signature.',
        'SIGNATURE_INVALIDE' => 'Signature invalide : une image data-URI est attendue.',
        'KILOMETRAGE_INVALIDE' => 'Le kilométrage doit être un entier positif ou nul.',
        'CARBURANT_INVALIDE' => 'Niveau de carburant invalide (vide, quart, moitie, trois_quarts, plein).',
    ];

    public function __construct(
        private EntityManagerInterface $em,
        private EtatDesLieuxDocumentService $documentService,
        private AuditService $audit,
    ) {}

    #[Route('/api/rendez-vous/{id}/etat-des-lieux', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $rdv = $this->em->getRepository(RendezVous::class)->find($id);
        if (!$rdv) {
            return $this->json(['error' => 'RDV introuvable'], Response::HTTP_NOT_FOUND);
        }

        $etatDesLieux = $this->findEtatDesLieux($rdv);

        return $this->json($this->documentService->normalizeState($etatDesLieux, $rdv));
    }

    #[Route('/api/rendez-vous/{id}/etat-des-lieux', methods: ['POST'])]
    public function upsert(int $id, Request $request): JsonResponse
    {
        $rdv = $this->em->getRepository(RendezVous::class)->find($id);
        if (!$rdv) {
            return $this->json(['error' => 'RDV introuvable'], Response::HTTP_NOT_FOUND);
        }

        $etatDesLieux = $this->findEtatDesLieux($rdv);
        if ($etatDesLieux && $etatDesLieux->isSigned()) {
            return $this->errorResponse('DEJA_SIGNE');
        }

        $data = json_decode($request->getContent(), true) ?? [];

        // Validation des entrées (fail fast, codes machine-readable)
        $kilometrage = null;
        if (isset($data['kilometrage']) && $data['kilometrage'] !== '') {
            if (!is_numeric($data['kilometrage']) || (int) $data['kilometrage'] < 0 || (int) $data['kilometrage'] != $data['kilometrage']) {
                return $this->errorResponse('KILOMETRAGE_INVALIDE');
            }
            $kilometrage = (int) $data['kilometrage'];
        }

        $niveauCarburant = null;
        if (isset($data['niveau_carburant']) && $data['niveau_carburant'] !== '') {
            if (!in_array($data['niveau_carburant'], EtatDesLieux::NIVEAUX_CARBURANT, true)) {
                return $this->errorResponse('CARBURANT_INVALIDE');
            }
            $niveauCarburant = (string) $data['niveau_carburant'];
        }

        $isNew = false;
        if (!$etatDesLieux) {
            $etatDesLieux = new EtatDesLieux();
            $etatDesLieux->setRendezVous($rdv);
            $etatDesLieux->setAtelierId($rdv->getAtelierId());
            $etatDesLieux->snapshotFromRdv();
            $this->em->persist($etatDesLieux);
            $isNew = true;
        }

        if ($kilometrage !== null) {
            $etatDesLieux->setKilometrage($kilometrage);
        }
        if ($niveauCarburant !== null) {
            $etatDesLieux->setNiveauCarburant($niveauCarburant);
        }
        if (array_key_exists('observations', $data)) {
            $observations = trim((string) ($data['observations'] ?? ''));
            $etatDesLieux->setObservations($observations !== '' ? $observations : null);
        }
        $etatDesLieux->setUpdatedAt(new \DateTime());

        $this->em->flush();

        return $this->json(
            $this->documentService->normalizeState($etatDesLieux, $rdv),
            $isNew ? Response::HTTP_CREATED : Response::HTTP_OK,
        );
    }

    #[Route('/api/rendez-vous/{id}/etat-des-lieux/sign', methods: ['POST'])]
    public function sign(int $id, Request $request): JsonResponse
    {
        $rdv = $this->em->getRepository(RendezVous::class)->find($id);
        if (!$rdv) {
            return $this->json(['error' => 'RDV introuvable'], Response::HTTP_NOT_FOUND);
        }

        $etatDesLieux = $this->findEtatDesLieux($rdv);
        if (!$etatDesLieux) {
            // Aucun brouillon : km / carburant nécessairement absents
            return $this->errorResponse('DONNEES_INCOMPLETES');
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $user = $this->getUser();

        try {
            $hash = $this->documentService->sign(
                $etatDesLieux,
                $data['signature'] ?? null,
                $user instanceof User ? $user : null,
                $request,
            );
        } catch (\DomainException $e) {
            return $this->errorResponse($e->getMessage(), $rdv);
        }

        $this->audit->log('sign_etat_des_lieux', 'EtatDesLieux', $etatDesLieux->getId(), json_encode([
            'rdv_id' => $rdv->getId(),
            'signed_hash' => $hash,
            'kilometrage' => $etatDesLieux->getKilometrage(),
            'niveau_carburant' => $etatDesLieux->getNiveauCarburant(),
        ], JSON_UNESCAPED_UNICODE));

        return $this->json([
            'success' => true,
            'signed_hash' => $hash,
            ...$this->documentService->normalizeState($etatDesLieux, $rdv),
        ]);
    }

    #[Route('/api/etat-des-lieux/{id}/pdf', methods: ['GET'])]
    public function pdf(int $id): Response
    {
        $etatDesLieux = $this->em->getRepository(EtatDesLieux::class)->find($id);
        if (!$etatDesLieux) {
            return $this->json(['error' => 'État des lieux introuvable'], Response::HTTP_NOT_FOUND);
        }

        if (!$etatDesLieux->isSigned()) {
            return $this->json([
                'code' => 'NON_SIGNE',
                'error' => 'Le document n\'est disponible qu\'après signature.',
            ], Response::HTTP_NOT_FOUND);
        }

        // Document archivé à la signature, jamais régénéré (aucun fallback)
        $pdfPath = $this->documentService->getArchivedPdfPath($etatDesLieux);
        if ($pdfPath === null) {
            return $this->json([
                'code' => 'PDF_INDISPONIBLE',
                'error' => 'Document momentanément indisponible. Contactez le support.',
            ], Response::HTTP_GONE);
        }

        $response = new BinaryFileResponse($pdfPath);
        $response->headers->set('Content-Type', 'application/pdf');
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_INLINE,
            sprintf('etat-des-lieux-rdv-%d.pdf', $etatDesLieux->getRendezVous()->getId()),
        );

        return $response;
    }

    #[Route('/api/etat-des-lieux/{id}/verify-integrity', methods: ['GET'])]
    public function verifyIntegrity(int $id): JsonResponse
    {
        $etatDesLieux = $this->em->getRepository(EtatDesLieux::class)->find($id);
        if (!$etatDesLieux) {
            return $this->json(['error' => 'État des lieux introuvable'], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'signed' => $etatDesLieux->isSigned(),
            ...$this->documentService->verifyIntegrity($etatDesLieux),
        ]);
    }

    private function findEtatDesLieux(RendezVous $rdv): ?EtatDesLieux
    {
        return $this->em->getRepository(EtatDesLieux::class)->findOneBy(['rendezVous' => $rdv]);
    }

    private function errorResponse(string $code, ?RendezVous $rdv = null): JsonResponse
    {
        $payload = [
            'code' => $code,
            'error' => self::ERROR_MESSAGES[$code] ?? $code,
        ];

        if ($code === 'PHOTOS_MANQUANTES' && $rdv) {
            $payload['missing'] = max(
                0,
                EtatDesLieuxDocumentService::MIN_PHOTOS_ENTREE - $this->documentService->countPhotosEntree($rdv),
            );
        }

        return $this->json($payload, Response::HTTP_BAD_REQUEST);
    }
}
