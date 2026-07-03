<?php

namespace App\Service;

use App\Entity\EtatDesLieux;
use App\Entity\PhotoIntervention;
use App\Entity\RendezVous;
use App\Entity\User;
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
    public const MIN_PHOTOS_ENTREE = 4;

    private const ARCHIVE_SUBDIR = '/var/pdf/etat-des-lieux';

    public function __construct(
        private EntityManagerInterface $em,
        private PdfService $pdfService,
        private string $projectDir,
    ) {}

    /**
     * État normalisé pour l'UI / l'API staff (contrat figé du design).
     *
     * @return array<string, mixed>
     */
    public function normalizeState(?EtatDesLieux $etatDesLieux, RendezVous $rdv): array
    {
        $photosEntreeCount = $this->countPhotosEntree($rdv);

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
        if ($etatDesLieux->isSigned()) {
            throw new \DomainException('DEJA_SIGNE');
        }

        if ($etatDesLieux->getKilometrage() === null
            || !in_array($etatDesLieux->getNiveauCarburant(), EtatDesLieux::NIVEAUX_CARBURANT, true)) {
            throw new \DomainException('DONNEES_INCOMPLETES');
        }

        $rdv = $etatDesLieux->getRendezVous();
        if ($this->countPhotosEntree($rdv) < self::MIN_PHOTOS_ENTREE) {
            throw new \DomainException('PHOTOS_MANQUANTES');
        }

        if (!is_string($signature) || !str_starts_with($signature, 'data:image/')) {
            throw new \DomainException('SIGNATURE_INVALIDE');
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
        $etatDesLieux->setPdfFilename($this->archivePdf($etatDesLieux));

        $this->em->flush();

        return $hash;
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
