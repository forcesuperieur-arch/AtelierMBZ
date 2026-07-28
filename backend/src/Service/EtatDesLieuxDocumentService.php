<?php

namespace App\Service;

use App\Entity\EtatDesLieux;
use App\Entity\PhotoIntervention;
use App\Entity\RendezVous;
use App\Entity\User;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Gel du document « état des lieux d'entrée » (Lot B).
 *
 * Canon de hash = celui de VORemiseEnEtatDocumentService (tri RÉCURSIF +
 * JSON_THROW_ON_ERROR|JSON_PRESERVE_ZERO_FRACTION) — PAS le ksort racine de
 * OrdreReparationPolicy. Ne jamais mélanger les deux canons.
 *
 * Le PDF est généré UNE SEULE FOIS à la signature puis archivé sous nom
 * aléatoire hors webroot (var/pdf/etat-des-lieux/). Jamais régénéré.
 */
class EtatDesLieuxDocumentService
{
    /** Types de photos comptant comme « photos d'entrée » du véhicule. */
    public const TYPES_PHOTOS_ENTREE = ['checkin', 'reception'];

    /** Nombre minimal de photos d'entrée exigé pour signer. */
    /** @deprecated Valeur historique : le seuil vient désormais de ReglesAtelier. */
    public const MIN_PHOTOS_ENTREE = 4;

    private const ARCHIVE_SUBDIR = '/var/pdf/etat-des-lieux';

    /** Préfixes data-URI acceptés pour la signature (JAMAIS de SVG : surface php-svg-lib/dompdf). */
    private const SIGNATURE_ALLOWED_PREFIXES = [
        'data:image/png;base64,',
        'data:image/jpeg;base64,',
        'data:image/webp;base64,',
    ];

    /** Une vraie signature tracée pèse toujours plus qu'un pixel : plancher anti-canvas-vide. */
    private const SIGNATURE_MIN_BYTES = 800;

    /** Plafond : la signature est stockée en TEXT puis rendue par dompdf. */
    private const SIGNATURE_MAX_BYTES = 2 * 1024 * 1024;

    public function __construct(
        private EntityManagerInterface $em,
        private PdfService $pdfService,
        private string $projectDir,
        private ReglesAtelier $regles,
    ) {}

    /** Nombre de photos d'entrée exigées pour cet atelier (réglage back-office). */
    public function minPhotosEntree(?RendezVous $rdv = null): int
    {
        return $this->regles->minPhotosEntree($rdv?->getAtelierId());
    }

    /**
     * État normalisé pour l'UI / l'API staff (contrat figé du design).
     *
     * @return array<string, mixed>
     */
    public function normalizeState(?EtatDesLieux $etatDesLieux, RendezVous $rdv): array
    {
        // Après signature, le compte vient du snapshot FIGÉ : une photo
        // d'entrée insérée après coup ne fait jamais bouger le document signé
        $photosEntreeCount = $etatDesLieux?->isSigned()
            ? count($etatDesLieux->getSignedSnapshot()['photos'] ?? [])
            : $this->countPhotosEntree($rdv);

        if (!$etatDesLieux) {
            return [
                'exists' => false,
                'id' => null,
                'signe' => false,
                'kilometrage' => null,
                'niveau_carburant' => null,
                'observations' => null,
                'photos_entree_count' => $photosEntreeCount,
                'signed_at' => null,
                'pdf_disponible' => false,
                'signed_by' => null,
            ];
        }

        return [
            'exists' => true,
            'id' => $etatDesLieux->getId(),
            'signe' => $etatDesLieux->isSigned(),
            'kilometrage' => $etatDesLieux->getKilometrage(),
            'niveau_carburant' => $etatDesLieux->getNiveauCarburant(),
            'observations' => $etatDesLieux->getObservations(),
            'photos_entree_count' => $photosEntreeCount,
            'signed_at' => $etatDesLieux->getSignedAt()?->format('c'),
            'pdf_disponible' => $etatDesLieux->isSigned() && $etatDesLieux->getPdfFilename() !== null,
            'signed_by' => $etatDesLieux->getSignedByNom(),
        ];
    }

    public function countPhotosEntree(RendezVous $rdv): int
    {
        return (int) $this->em->getRepository(PhotoIntervention::class)->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.rendezVous = :rdv')
            ->andWhere('p.type IN (:types)')
            ->setParameter('rdv', $rdv)
            ->setParameter('types', self::TYPES_PHOTOS_ENTREE)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Snapshot complet et déterministe du constat au moment T (photos incluses).
     *
     * @return array<string, mixed>
     */
    public function buildSnapshot(EtatDesLieux $etatDesLieux): array
    {
        $rdv = $etatDesLieux->getRendezVous();

        $photos = $this->em->getRepository(PhotoIntervention::class)->createQueryBuilder('p')
            ->where('p.rendezVous = :rdv')
            ->andWhere('p.type IN (:types)')
            ->setParameter('rdv', $rdv)
            ->setParameter('types', self::TYPES_PHOTOS_ENTREE)
            ->getQuery()
            ->getResult();

        // Tri déterministe (pattern VO : usort sur clés stables)
        usort($photos, static fn (PhotoIntervention $left, PhotoIntervention $right): int =>
            [$left->getFilename(), $left->getId() ?? 0] <=> [$right->getFilename(), $right->getId() ?? 0]);

        return [
            'etat_des_lieux' => [
                'id' => $etatDesLieux->getId(),
                'rendez_vous_id' => $rdv->getId(),
                'kilometrage' => $etatDesLieux->getKilometrage(),
                'niveau_carburant' => $etatDesLieux->getNiveauCarburant(),
                'observations' => $etatDesLieux->getObservations(),
                'etat_vehicule' => $etatDesLieux->getEtatVehicule(),
                'created_at' => $etatDesLieux->getCreatedAt()->format(DATE_ATOM),
            ],
            'client' => [
                'nom' => $etatDesLieux->getSnapClientNom(),
                'prenom' => $etatDesLieux->getSnapClientPrenom(),
            ],
            'vehicule' => [
                'plaque' => $etatDesLieux->getSnapVehiculePlaque(),
                'marque' => $etatDesLieux->getSnapVehiculeMarque(),
                'modele' => $etatDesLieux->getSnapVehiculeModele(),
            ],
            'rdv' => [
                'date' => $rdv->getDateRdv()->format('Y-m-d'),
                'heure' => $rdv->getHeureRdv()->format('H:i'),
                'type_intervention' => $rdv->getTypeIntervention(),
            ],
            'photos' => array_map(static fn (PhotoIntervention $photo): array => [
                'filename' => $photo->getFilename(),
                'sha256' => $photo->getSha256(),
                'takenAt' => $photo->getTakenAt()?->format(DATE_ATOM),
            ], $photos),
        ];
    }

    /**
     * Canon VO : tri récursif des clés + encodage strict.
     *
     * @param array<string, mixed> $snapshot
     */
    public function computeHash(array $snapshot): string
    {
        $normalized = $this->sortSnapshotRecursively($snapshot);

        return hash('sha256', json_encode($normalized, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION));
    }

    /**
     * Signe et gèle l'état des lieux : snapshot + hash + PDF archivé une fois.
     *
     * Le message des DomainException est un code machine-readable figé du
     * design : DEJA_SIGNE, DONNEES_INCOMPLETES, PHOTOS_MANQUANTES,
     * SIGNATURE_INVALIDE (le contrôleur les mappe en JSON 400).
     *
     * @return string le hash sha256 du snapshot signé
     */
    public function sign(EtatDesLieux $etatDesLieux, ?string $signature, ?User $user, Request $request): string
    {
        // Fast-path hors transaction (état déjà chargé)
        if ($etatDesLieux->isSigned()) {
            throw new \DomainException('DEJA_SIGNE');
        }

        $this->assertSignatureValide($signature);

        // Transaction + verrou pessimiste : deux signatures concurrentes se
        // sérialisent sur la ligne EDL — la seconde attend le commit de la
        // première, re-lit l'entité verrouillée, voit le document signé et
        // échoue en DEJA_SIGNE. Un seul PDF archivé, jamais écrasé.
        // DomainException => rollback automatique (wrapInTransaction).
        return $this->em->wrapInTransaction(function () use ($etatDesLieux, $signature, $user, $request): string {
            $this->em->lock($etatDesLieux, LockMode::PESSIMISTIC_WRITE);
            $this->em->refresh($etatDesLieux);

            // Re-check APRÈS verrou : l'état en mémoire pouvait être périmé
            if ($etatDesLieux->isSigned()) {
                throw new \DomainException('DEJA_SIGNE');
            }

            if ($etatDesLieux->getKilometrage() === null
                || !in_array($etatDesLieux->getNiveauCarburant(), EtatDesLieux::NIVEAUX_CARBURANT, true)) {
                throw new \DomainException('DONNEES_INCOMPLETES');
            }

            $rdv = $etatDesLieux->getRendezVous();
            if ($this->countPhotosEntree($rdv) < $this->minPhotosEntree($rdv)) {
                throw new \DomainException('PHOTOS_MANQUANTES');
            }

            // Copie figée de l'état véhicule libre du RDV au moment T
            $rdvEtatVehicule = $rdv->getEtatVehicule();
            if (is_string($rdvEtatVehicule) && trim($rdvEtatVehicule) !== '') {
                $decoded = json_decode($rdvEtatVehicule, true);
                if (is_array($decoded)) {
                    $etatDesLieux->setEtatVehicule($decoded);
                }
            }

            $etatDesLieux->snapshotFromRdv();

            $snapshot = $this->buildSnapshot($etatDesLieux);
            $hash = $this->computeHash($snapshot);
            $now = new \DateTime();

            $etatDesLieux
                ->setSignatureClient($signature)
                ->setSignedSnapshot($snapshot)
                ->setSignedHash($hash)
                ->setSignedAt($now)
                ->setSignedIp($request->getClientIp())
                ->setSignedUserAgent(mb_substr((string) $request->headers->get('User-Agent', ''), 0, 500))
                ->setUpdatedAt($now);

            if ($user instanceof User) {
                $etatDesLieux->setSignedByUserId($user->getId());
                $etatDesLieux->setSignedByNom(trim(($user->getPrenom() ?? '') . ' ' . ($user->getNom() ?? '')) ?: $user->getUsername());
            }

            // Génération + archivage du PDF, UNE SEULE FOIS (jamais régénéré)
            $archivedFilename = $this->archivePdf($etatDesLieux);
            $etatDesLieux->setPdfFilename($archivedFilename);

            try {
                $this->em->flush();
            } catch (\Throwable $e) {
                // Flush raté APRÈS archivage : ne jamais laisser un PDF
                // orphelin se faire passer pour un document signé
                @unlink($this->projectDir . self::ARCHIVE_SUBDIR . '/' . $archivedFilename);
                throw $e;
            }

            return $hash;
        });
    }

    /**
     * Garde-fous de la signature (Lot B revue contradictoire) : préfixe PNG /
     * JPEG / WebP uniquement (jamais SVG — parseur php-svg-lib de dompdf),
     * payload base64 valide, taille décodée entre 800 octets (rejette le
     * canvas vide / pixel 1×1) et 2 Mo (stockage TEXT + rendu dompdf).
     */
    private function assertSignatureValide(?string $signature): void
    {
        if (!is_string($signature)) {
            throw new \DomainException('SIGNATURE_INVALIDE');
        }

        $payload = null;
        foreach (self::SIGNATURE_ALLOWED_PREFIXES as $prefix) {
            if (str_starts_with($signature, $prefix)) {
                $payload = substr($signature, strlen($prefix));
                break;
            }
        }

        if ($payload === null) {
            throw new \DomainException('SIGNATURE_INVALIDE');
        }

        $decoded = base64_decode($payload, true);
        if ($decoded === false) {
            throw new \DomainException('SIGNATURE_INVALIDE');
        }

        $size = strlen($decoded);
        if ($size < self::SIGNATURE_MIN_BYTES || $size > self::SIGNATURE_MAX_BYTES) {
            throw new \DomainException('SIGNATURE_INVALIDE');
        }
    }

    /**
     * @return array{valid: bool, computed_hash: ?string, stored_hash: ?string}
     */
    public function verifyIntegrity(EtatDesLieux $etatDesLieux): array
    {
        $snapshot = $etatDesLieux->getSignedSnapshot();
        $storedHash = $etatDesLieux->getSignedHash();

        if ($snapshot === null || $storedHash === null) {
            return ['valid' => false, 'computed_hash' => null, 'stored_hash' => $storedHash];
        }

        $computedHash = $this->computeHash($snapshot);

        return [
            'valid' => hash_equals($storedHash, $computedHash),
            'computed_hash' => $computedHash,
            'stored_hash' => $storedHash,
        ];
    }

    /**
     * Chemin réel du PDF archivé, avec containment realpath dans le dossier
     * d'archive (hors webroot). Null si absent ou hors périmètre.
     */
    public function getArchivedPdfPath(EtatDesLieux $etatDesLieux): ?string
    {
        $filename = $etatDesLieux->getPdfFilename();
        if ($filename === null || $filename === '') {
            return null;
        }

        $archiveDir = realpath($this->projectDir . self::ARCHIVE_SUBDIR);
        if ($archiveDir === false) {
            return null;
        }

        $realPath = realpath($archiveDir . '/' . basename($filename));
        if ($realPath === false || !str_starts_with($realPath, $archiveDir . '/') || !is_file($realPath)) {
            return null;
        }

        return $realPath;
    }

    /**
     * Copie le PDF fraîchement généré sous nom aléatoire (pattern
     * VODocumentService::archiveGeneratedPdf) et retourne le nom archivé.
     */
    private function archivePdf(EtatDesLieux $etatDesLieux): string
    {
        $generatedFilePath = $this->pdfService->generateEtatDesLieuxPdf($etatDesLieux);

        if (!is_file($generatedFilePath)) {
            throw new \RuntimeException(sprintf('Fichier PDF introuvable : %s', $generatedFilePath));
        }

        $archiveDir = $this->projectDir . self::ARCHIVE_SUBDIR;
        if (!is_dir($archiveDir)) {
            mkdir($archiveDir, 0755, true);
        }

        $safeFilename = bin2hex(random_bytes(16)) . '.pdf';

        if (!copy($generatedFilePath, $archiveDir . '/' . $safeFilename)) {
            throw new \RuntimeException('Impossible d\'archiver le PDF de l\'état des lieux.');
        }

        // Pas de copie « vivante » résiduelle : le document de référence est l'archive
        @unlink($generatedFilePath);

        return $safeFilename;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function sortSnapshotRecursively(array $payload): array
    {
        if (!array_is_list($payload)) {
            ksort($payload);
        }

        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $payload[$key] = $this->sortSnapshotRecursively($value);
            }
        }

        return $payload;
    }
}
