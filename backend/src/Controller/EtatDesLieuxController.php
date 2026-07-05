<?php

namespace App\Controller;

use App\Entity\EtatDesLieux;
use App\Entity\RendezVous;
use App\Entity\User;
use App\Service\AuditService;
use App\Service\EtatDesLieuxDocumentService;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
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
        'SIGNATURE_INVALIDE' => 'Signature invalide : image PNG, JPEG ou WebP en data-URI attendue (entre 800 octets et 2 Mo).',
        'KILOMETRAGE_INVALIDE' => 'Le kilométrage doit être un entier positif ou nul.',
        'CARBURANT_INVALIDE' => 'Niveau de carburant invalide (vide, quart, moitie, trois_quarts, plein).',
        'STATUT_RDV_INCOMPATIBLE' => 'Le statut du rendez-vous ne permet plus d\'établir ou de modifier l\'état des lieux d\'entrée.',
    ];

    /**
     * Statuts RDV où l'état des lieux d'entrée peut encore être créé/signé :
     * l'EDL est un constat fait À L'ARRIVÉE du véhicule — au-delà de la
     * réception (en_cours, terminé, restitué, annulé…), le document n'a plus
     * de sens et pourrait servir à antidater un constat.
     */
    private const STATUTS_RDV_EDL = ['en_attente', 'reserve', 'confirme', 'reception'];

    /** Plafond des observations libres (même règle que litige_commentaire ×1). */
    private const OBSERVATIONS_MAX_LENGTH = 2000;

    public function __construct(
        private EntityManagerInterface $em,
        private EtatDesLieuxDocumentService $documentService,
        private AuditService $audit,
        private ManagerRegistry $doctrine,
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

        if (!in_array($rdv->getStatut(), self::STATUTS_RDV_EDL, true)) {
            return $this->errorResponse('STATUT_RDV_INCOMPATIBLE');
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

        $this->applyDraftData($etatDesLieux, $kilometrage, $niveauCarburant, $data);

        try {
            $this->em->flush();
        } catch (UniqueConstraintViolationException) {
            // Deux créations concurrentes du brouillon (uniq_etat_des_lieux_rdv) :
            // l'INSERT perdant re-fetch l'EDL gagnant et applique sa mise à
            // jour dessus (upsert réel) au lieu d'un 500 brut.
            return $this->retryUpsertAfterConflict($id, $kilometrage, $niveauCarburant, $data);
        }

        return $this->json(
            $this->documentService->normalizeState($etatDesLieux, $rdv),
            $isNew ? Response::HTTP_CREATED : Response::HTTP_OK,
        );
    }

    /**
     * Rejoue l'upsert après violation de l'index unique : le flush raté a
     * fermé l'EntityManager, on le reset (proxy lazy partagé par tous les
     * services) puis on applique la mise à jour sur l'EDL créé en parallèle.
     */
    private function retryUpsertAfterConflict(int $rdvId, ?int $kilometrage, ?string $niveauCarburant, array $data): JsonResponse
    {
        $this->doctrine->resetManager();

        $rdv = $this->em->getRepository(RendezVous::class)->find($rdvId);
        $etatDesLieux = $rdv ? $this->findEtatDesLieux($rdv) : null;
        if (!$rdv || !$etatDesLieux) {
            // Conflit sans gagnant retrouvé : état incohérent, on renonce
            return $this->json(['error' => 'Conflit de création de l\'état des lieux, veuillez réessayer.'], Response::HTTP_CONFLICT);
        }

        if ($etatDesLieux->isSigned()) {
            return $this->errorResponse('DEJA_SIGNE');
        }

        $this->applyDraftData($etatDesLieux, $kilometrage, $niveauCarburant, $data);
        $this->em->flush();

        return $this->json($this->documentService->normalizeState($etatDesLieux, $rdv), Response::HTTP_OK);
    }

    /** @param array<string, mixed> $data */
    private function applyDraftData(EtatDesLieux $etatDesLieux, ?int $kilometrage, ?string $niveauCarburant, array $data): void
    {
        if ($kilometrage !== null) {
            $etatDesLieux->setKilometrage($kilometrage);
        }
        if ($niveauCarburant !== null) {
            $etatDesLieux->setNiveauCarburant($niveauCarburant);
        }
        if (array_key_exists('observations', $data)) {
            // Borné comme litige_commentaire : le snapshot/hash/PDF gelés ne
            // doivent jamais embarquer un texte non plafonné
            $observations = mb_substr(trim((string) ($data['observations'] ?? '')), 0, self::OBSERVATIONS_MAX_LENGTH);
            $etatDesLieux->setObservations($observations !== '' ? $observations : null);
        }
        $etatDesLieux->setUpdatedAt(new \DateTime());
    }

    #[Route('/api/rendez-vous/{id}/etat-des-lieux/sign', methods: ['POST'])]
    public function sign(int $id, Request $request): JsonResponse
    {
        $rdv = $this->em->getRepository(RendezVous::class)->find($id);
        if (!$rdv) {
            return $this->json(['error' => 'RDV introuvable'], Response::HTTP_NOT_FOUND);
        }

        if (!in_array($rdv->getStatut(), self::STATUTS_RDV_EDL, true)) {
            return $this->errorResponse('STATUT_RDV_INCOMPATIBLE');
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
