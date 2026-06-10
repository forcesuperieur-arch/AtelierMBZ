<?php

namespace App\Service;

use App\Entity\VODocument;
use App\Entity\VODepotVente;
use App\Entity\VOPurchase;
use App\Entity\VORemiseEnEtat;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class VODocumentService
{
    private const ALLOWED_MIME_TYPES = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

    private const MAX_SIZE = 10 * 1024 * 1024; // 10MB

    private const PURCHASE_REQUIRED_DOCS = [
        VODocument::TYPE_CERFA_CESSION_ACHAT,
        VODocument::TYPE_CARTE_GRISE,
        VODocument::TYPE_NON_GAGE,
        VODocument::TYPE_PIECE_IDENTITE,
        VODocument::TYPE_PV_RACHAT,
    ];

    private const SALE_REQUIRED_DOCS = [
        VODocument::TYPE_CERFA_CESSION_VENTE,
        VODocument::TYPE_FACTURE_VO,
        VODocument::TYPE_NOTICE_GARANTIE,
    ];

    private const DEPOT_REQUIRED_DOCS = [
        VODocument::TYPE_CONTRAT_DEPOT_VENTE,
        VODocument::TYPE_CARTE_GRISE,
        VODocument::TYPE_PIECE_IDENTITE,
    ];

    public function __construct(
        private EntityManagerInterface $em,
        private string $projectDir,
    ) {}

    public function upload(
        UploadedFile $file,
        string $type,
        ?VOPurchase $purchase = null,
        ?VODepotVente $depot = null,
        ?User $user = null,
        ?\DateTimeInterface $dateExpiration = null,
    ): VODocument {
        if ($file->getSize() > self::MAX_SIZE) {
            throw new \InvalidArgumentException('Fichier trop volumineux (max 10 Mo)');
        }

        $uploadDir = $this->projectDir . '/var/uploads/vo';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $mimeType = $file->getMimeType() ?? $file->getClientMimeType() ?? 'application/octet-stream';
        $this->assertAllowedMimeType($mimeType);

        $safeFilename = bin2hex(random_bytes(16)) . '.' . ($file->guessExtension() ?? 'bin');
        $file->move($uploadDir, $safeFilename);

        return $this->createDocumentRecord(
            type: $type,
            filePath: '/uploads/vo/' . $safeFilename,
            originalFilename: $file->getClientOriginalName(),
            mimeType: $mimeType,
            purchase: $purchase,
            depot: $depot,
            user: $user,
            dateExpiration: $dateExpiration,
        );
    }

    public function storeRawContent(
        string $content,
        string $originalFilename,
        string $mimeType,
        string $type,
        ?VOPurchase $purchase = null,
        ?VODepotVente $depot = null,
        ?User $user = null,
        ?\DateTimeInterface $dateExpiration = null,
    ): VODocument {
        $this->assertAllowedMimeType($mimeType);

        $uploadDir = $this->projectDir . '/var/uploads/vo';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $extension = $this->guessExtensionFromMimeType($mimeType);
        $safeFilename = bin2hex(random_bytes(16)) . '.' . $extension;
        $destination = $uploadDir . '/' . $safeFilename;
        file_put_contents($destination, $content);

        return $this->createDocumentRecord(
            type: $type,
            filePath: '/uploads/vo/' . $safeFilename,
            originalFilename: $originalFilename,
            mimeType: $mimeType,
            purchase: $purchase,
            depot: $depot,
            user: $user,
            dateExpiration: $dateExpiration,
        );
    }

    public function archiveGeneratedPdf(
        string $generatedFilePath,
        string $type,
        ?VOPurchase $purchase = null,
        ?VODepotVente $depot = null,
        ?User $user = null,
        ?string $originalFilename = null,
        ?VORemiseEnEtat $campaign = null,
    ): VODocument {
        if (!is_file($generatedFilePath)) {
            throw new \RuntimeException(sprintf('Fichier PDF introuvable : %s', $generatedFilePath));
        }

        $uploadDir = $this->projectDir . '/var/uploads/vo/generated';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $safeFilename = bin2hex(random_bytes(16)) . '.pdf';
        $destination = $uploadDir . '/' . $safeFilename;

        if (!copy($generatedFilePath, $destination)) {
            throw new \RuntimeException('Impossible d\'archiver le PDF généré dans les uploads VO.');
        }

        $doc = $this->em->getRepository(VODocument::class)->findOneBy([
            'type' => $type,
            'voPurchase' => $purchase,
            'voDepotVente' => $depot,
            'voRemiseEnEtat' => $campaign,
        ]) ?? new VODocument();

        $doc->setType($type);
        $doc->setFilePath('/uploads/vo/generated/' . $safeFilename);
        $doc->setOriginalFilename($originalFilename ?: basename($generatedFilePath));
        $doc->setMimeType('application/pdf');
        $doc->setVoPurchase($purchase);
        $doc->setVoDepotVente($depot);
        $doc->setVoRemiseEnEtat($campaign);
        $doc->setUploadedBy($user);
        $doc->setAtelierId($purchase?->getAtelierId() ?? $depot?->getAtelierId() ?? $campaign?->getAtelierId());

        $this->em->persist($doc);

        return $doc;
    }

    /**
     * Check which required documents are missing for a purchase.
     * @return array<string> list of missing document type codes
     */
    public function getMissingDocuments(VOPurchase $purchase, bool $includeSale = false): array
    {
        $existing = $this->getPurchaseDocumentTypes($purchase);

        $required = self::PURCHASE_REQUIRED_DOCS;
        if ($includeSale) {
            $required = array_merge($required, self::SALE_REQUIRED_DOCS);
        }

        return array_values(array_diff($required, $existing));
    }

    /**
     * Check which required documents are missing for a dépôt-vente.
     */
    public function getMissingDocumentsDepot(VODepotVente $depot): array
    {
        $existing = $this->getDepotDocumentTypes($depot);

        return array_values(array_diff(self::DEPOT_REQUIRED_DOCS, $existing));
    }

    public function getPurchaseSivSummary(VOPurchase $purchase): array
    {
        $existing = $this->getPurchaseDocumentTypes($purchase);

        return [
            'status' => $purchase->getSivStatus(),
            'label' => match ($purchase->getSivStatus()) {
                VOPurchase::SIV_STATUS_EN_COURS => 'En cours de saisie',
                VOPurchase::SIV_STATUS_ENREGISTREE => 'Enregistrée',
                VOPurchase::SIV_STATUS_REJETEE => 'Rejetée',
                VOPurchase::SIV_STATUS_EXPIREE => 'Expirée',
                default => 'À préparer',
            },
            'reference' => $purchase->getSivReference(),
            'recordedAt' => $purchase->getSivRecordedAt()?->format(DATE_ATOM),
            'notes' => $purchase->getSivNotes(),
            'isComplete' => $purchase->isSivRegistered(),
            'daDocumentGenerated' => in_array(VODocument::TYPE_DA_SIV, $existing, true),
            'recepisseUploaded' => in_array(VODocument::TYPE_RECEPISSE_DA, $existing, true),
            'mandatReady' => in_array(VODocument::TYPE_MANDAT_IMMATRICULATION, $existing, true),
        ];
    }

    public function buildPurchaseLegalChecklist(VOPurchase $purchase): array
    {
        $existing = $this->getPurchaseDocumentTypes($purchase);
        $controleTechniqueRequired = $this->isControleTechniqueRequired($purchase->getVehicule());
        $controleTechniqueOk = !$controleTechniqueRequired
            || $purchase->getControleTechniqueOk()
            || in_array(VODocument::TYPE_CONTROLE_TECHNIQUE, $existing, true);

        return [
            $this->buildChecklistItem('seller_identity', 'Identité vendeur retranscrite', $purchase->getSellerIdType() !== null && $purchase->getSellerIdNumber() !== null, true, 'purchase'),
            $this->buildChecklistItem('carte_grise', 'Carte grise archivée', in_array(VODocument::TYPE_CARTE_GRISE, $existing, true), true, 'purchase'),
            $this->buildChecklistItem('non_gage', 'Non-gage archivé', in_array(VODocument::TYPE_NON_GAGE, $existing, true), true, 'purchase'),
            $this->buildChecklistItem('cession_achat', 'CERFA achat archivé', in_array(VODocument::TYPE_CERFA_CESSION_ACHAT, $existing, true), true, 'purchase'),
            $this->buildChecklistItem('pv_rachat', 'PV de rachat archivé', in_array(VODocument::TYPE_PV_RACHAT, $existing, true), false, 'purchase'),
            $this->buildChecklistItem('controle_technique', 'Contrôle technique valide', $controleTechniqueOk, $controleTechniqueRequired, 'purchase'),
            $this->buildChecklistItem('da_pdf', 'DA SIV préremplie générée', in_array(VODocument::TYPE_DA_SIV, $existing, true), false, 'sale'),
            $this->buildChecklistItem('da_siv', 'DA SIV enregistrée', $purchase->isSivRegistered(), true, 'sale'),
            $this->buildChecklistItem('recepisse_da', 'Récépissé DA archivé', in_array(VODocument::TYPE_RECEPISSE_DA, $existing, true), false, 'sale'),
            $this->buildChecklistItem('mandat_immat', 'Mandat immatriculation prêt', in_array(VODocument::TYPE_MANDAT_IMMATRICULATION, $existing, true), false, 'sale'),
        ];
    }

    public function buildDepotLegalChecklist(VODepotVente $depot): array
    {
        $existing = $this->getDepotDocumentTypes($depot);
        $controleTechniqueRequired = $this->isControleTechniqueRequired($depot->getVehicule());
        $controleTechniqueOk = !$controleTechniqueRequired || in_array(VODocument::TYPE_CONTROLE_TECHNIQUE, $existing, true);

        return [
            $this->buildChecklistItem('deposant_identity', 'Identité déposant retranscrite', $depot->getDeposantIdType() !== null && $depot->getDeposantIdNumber() !== null, true, 'depot'),
            $this->buildChecklistItem('contrat_depot', 'Contrat dépôt-vente archivé', in_array(VODocument::TYPE_CONTRAT_DEPOT_VENTE, $existing, true), true, 'depot'),
            $this->buildChecklistItem('carte_grise', 'Carte grise archivée', in_array(VODocument::TYPE_CARTE_GRISE, $existing, true), true, 'depot'),
            $this->buildChecklistItem('controle_technique', 'Contrôle technique valide', $controleTechniqueOk, $controleTechniqueRequired, 'sale'),
            $this->buildChecklistItem('mandat_actif', 'Mandat encore actif', !$depot->isMandatExpire(), true, 'sale'),
        ];
    }

    public function getPurchaseSaleBlockers(VOPurchase $purchase): array
    {
        $blockers = [];

        foreach ($this->getMissingDocuments($purchase) as $docType) {
            $blockers[] = sprintf('Document requis manquant : %s.', $this->getDocumentLabel($docType));
        }

        if ($this->isControleTechniqueRequired($purchase->getVehicule()) && !$purchase->getControleTechniqueOk() && !in_array(VODocument::TYPE_CONTROLE_TECHNIQUE, $this->getPurchaseDocumentTypes($purchase), true)) {
            $blockers[] = 'Contrôle technique non validé.';
        }

        if (!$purchase->isSivRegistered()) {
            $blockers[] = 'DA SIV non enregistrée.';
        }

        return array_values(array_unique($blockers));
    }

    public function getDepotSaleBlockers(VODepotVente $depot): array
    {
        $blockers = [];

        foreach ($this->getMissingDocumentsDepot($depot) as $docType) {
            $blockers[] = sprintf('Document requis manquant : %s.', $this->getDocumentLabel($docType));
        }

        if ($this->isControleTechniqueRequired($depot->getVehicule()) && !in_array(VODocument::TYPE_CONTROLE_TECHNIQUE, $this->getDepotDocumentTypes($depot), true)) {
            $blockers[] = 'Contrôle technique manquant pour la vente.';
        }

        if ($depot->isMandatExpire()) {
            $blockers[] = 'Mandat de dépôt-vente expiré.';
        }

        return array_values(array_unique($blockers));
    }

    public function getPurchaseDossierStatus(VOPurchase $purchase): string
    {
        if ($purchase->getStatus() === 'vendu') {
            return 'vendu';
        }

        if ($purchase->getStatus() === 'brouillon') {
            return $this->getMissingDocuments($purchase) === [] ? 'pret_confirmation' : 'incomplet';
        }

        return $this->getPurchaseSaleBlockers($purchase) === [] ? 'pret_vente' : 'a_regulariser';
    }

    public function getDepotDossierStatus(VODepotVente $depot): string
    {
        if ($depot->getStatus() === 'vendu') {
            return 'vendu';
        }

        if ($depot->getStatus() === 'restitue') {
            return 'restitue';
        }

        return $this->getDepotSaleBlockers($depot) === [] ? 'pret_vente' : 'a_regulariser';
    }

    /**
     * Get all documents that are expired.
     */
    public function getExpiredDocuments(?int $atelierId = null): array
    {
        $qb = $this->em->getRepository(VODocument::class)->createQueryBuilder('d')
            ->where('d.dateExpiration IS NOT NULL')
            ->andWhere('d.dateExpiration < :today')
            ->setParameter('today', new \DateTime('today'));

        if ($atelierId) {
            $qb->andWhere('d.atelierId = :aid')->setParameter('aid', $atelierId);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Get all alerts: missing + expired documents for active purchases.
     */
    public function getAlerts(?int $atelierId = null): array
    {
        $alerts = [];

        // Expired documents
        $expired = $this->getExpiredDocuments($atelierId);
        foreach ($expired as $doc) {
            $alerts[] = [
                'type' => 'expired',
                'document_type' => $doc->getType(),
                'document_id' => $doc->getId(),
                'purchase_id' => $doc->getVoPurchase()?->getId(),
                'depot_id' => $doc->getVoDepotVente()?->getId(),
                'expiration_date' => $doc->getDateExpiration()->format('Y-m-d'),
                'message' => sprintf('Document "%s" expiré depuis le %s', $doc->getType(), $doc->getDateExpiration()->format('d/m/Y')),
            ];
        }

        // Missing documents for active purchases
        $qb = $this->em->getRepository(VOPurchase::class)->createQueryBuilder('p')
            ->where('p.status IN (:statuses)')
            ->setParameter('statuses', ['brouillon', 'en_stock', 'en_vente']);

        if ($atelierId) {
            $qb->andWhere('p.atelierId = :aid')->setParameter('aid', $atelierId);
        }

        foreach ($qb->getQuery()->getResult() as $purchase) {
            $missing = $this->getMissingDocuments($purchase);
            $vehicule = $purchase->getVehicule();
            $vehiculeLabel = $vehicule?->getPlaque()
                ?: trim((string) (($vehicule?->getMarque() ?? '') . ' ' . ($vehicule?->getModele() ?? '')))
                ?: sprintf('rachat #%d', $purchase->getId());

            foreach ($missing as $docType) {
                $alerts[] = [
                    'type' => 'missing',
                    'document_type' => $docType,
                    'purchase_id' => $purchase->getId(),
                    'message' => sprintf('Document "%s" manquant pour le véhicule %s', $docType, $vehiculeLabel),
                ];
            }
        }

        return $alerts;
    }

    private function assertAllowedMimeType(string $mimeType): void
    {
        if (!in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            throw new \InvalidArgumentException('Type de fichier non autorise. Formats acceptes : PDF, JPEG, PNG, WebP.');
        }
    }

    private function guessExtensionFromMimeType(string $mimeType): string
    {
        return match ($mimeType) {
            'application/pdf' => 'pdf',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };
    }

    /**
     * Batch load document types for multiple purchases and depots in 2 queries.
     * Returns ['purchases' => [id => [type, ...]], 'depots' => [id => [type, ...]]]
     */
    public function batchDocumentTypes(array $purchaseIds, array $depotIds): array
    {
        $result = ['purchases' => [], 'depots' => []];

        if ($purchaseIds !== []) {
            $rows = $this->em->getRepository(VODocument::class)->createQueryBuilder('d')
                ->select('IDENTITY(d.voPurchase) AS pid, d.type')
                ->where('d.voPurchase IN (:pids)')
                ->setParameter('pids', $purchaseIds)
                ->getQuery()
                ->getResult();
            foreach ($rows as $row) {
                $result['purchases'][(int) $row['pid']][] = $row['type'];
            }
        }

        if ($depotIds !== []) {
            $rows = $this->em->getRepository(VODocument::class)->createQueryBuilder('d')
                ->select('IDENTITY(d.voDepotVente) AS did, d.type')
                ->where('d.voDepotVente IN (:dids)')
                ->setParameter('dids', $depotIds)
                ->getQuery()
                ->getResult();
            foreach ($rows as $row) {
                $result['depots'][(int) $row['did']][] = $row['type'];
            }
        }

        return $result;
    }

    public function getMissingDocumentsFromTypes(array $presentTypes, bool $includeSale = false): array
    {
        $required = self::PURCHASE_REQUIRED_DOCS;
        if ($includeSale) {
            $required = array_merge($required, self::SALE_REQUIRED_DOCS);
        }

        return array_values(array_diff($required, $presentTypes));
    }

    public function getMissingDepotDocumentsFromTypes(array $presentTypes): array
    {
        return array_values(array_diff(self::DEPOT_REQUIRED_DOCS, $presentTypes));
    }

    private function getPurchaseDocumentTypes(VOPurchase $purchase): array
    {
        return $this->em->getRepository(VODocument::class)->createQueryBuilder('d')
            ->select('d.type')
            ->where('d.voPurchase = :p')
            ->setParameter('p', $purchase)
            ->getQuery()
            ->getSingleColumnResult();
    }

    private function getDepotDocumentTypes(VODepotVente $depot): array
    {
        return $this->em->getRepository(VODocument::class)->createQueryBuilder('d')
            ->select('d.type')
            ->where('d.voDepotVente = :d')
            ->setParameter('d', $depot)
            ->getQuery()
            ->getSingleColumnResult();
    }

    private function buildChecklistItem(string $key, string $label, bool $completed, bool $blocking, string $scope): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'completed' => $completed,
            'blocking' => $blocking,
            'scope' => $scope,
            'status' => $completed ? 'ok' : ($blocking ? 'missing' : 'pending'),
        ];
    }

    private function getDocumentLabel(string $type): string
    {
        return match ($type) {
            VODocument::TYPE_CERFA_CESSION_ACHAT => 'CERFA achat',
            VODocument::TYPE_CERFA_CESSION_VENTE => 'CERFA vente',
            VODocument::TYPE_CARTE_GRISE => 'carte grise',
            VODocument::TYPE_NON_GAGE => 'certificat de situation administrative',
            VODocument::TYPE_CONTROLE_TECHNIQUE => 'contrôle technique',
            VODocument::TYPE_PIECE_IDENTITE => 'pièce d’identité',
            VODocument::TYPE_JUSTIFICATIF_DOMICILE => 'justificatif de domicile',
            VODocument::TYPE_CONTRAT_DEPOT_VENTE => 'contrat dépôt-vente',
            VODocument::TYPE_DA_SIV => 'DA SIV',
            VODocument::TYPE_RECEPISSE_DA => 'récépissé DA',
            VODocument::TYPE_MANDAT_IMMATRICULATION => 'mandat immatriculation',
            VODocument::TYPE_FACTURE_VO => 'facture VO',
            VODocument::TYPE_PV_RACHAT => 'PV de rachat',
            VODocument::TYPE_NOTICE_GARANTIE => 'notice de garantie',
            default => $type,
        };
    }

    private function isControleTechniqueRequired(?object $vehicule): bool
    {
        if ($vehicule === null || !method_exists($vehicule, 'getDatePremiereMiseEnCirculation')) {
            return false;
        }

        $premiereMec = $vehicule->getDatePremiereMiseEnCirculation();
        if ($premiereMec instanceof \DateTimeInterface) {
            return $premiereMec < new \DateTime('-5 years');
        }

        if (method_exists($vehicule, 'getAnnee')) {
            $annee = (int) ($vehicule->getAnnee() ?? 0);
            return $annee > 0 && $annee <= ((int) date('Y') - 5);
        }

        return false;
    }

    private function createDocumentRecord(
        string $type,
        string $filePath,
        string $originalFilename,
        string $mimeType,
        ?VOPurchase $purchase = null,
        ?VODepotVente $depot = null,
        ?User $user = null,
        ?\DateTimeInterface $dateExpiration = null,
        ?VORemiseEnEtat $campaign = null,
    ): VODocument {
        $doc = new VODocument();
        $doc->setType($type);
        $doc->setFilePath($filePath);
        $doc->setOriginalFilename($originalFilename);
        $doc->setMimeType($mimeType);
        $doc->setVoPurchase($purchase);
        $doc->setVoDepotVente($depot);
        $doc->setVoRemiseEnEtat($campaign);
        $doc->setDateExpiration($dateExpiration);
        $doc->setUploadedBy($user);
        $doc->setAtelierId($purchase?->getAtelierId() ?? $depot?->getAtelierId() ?? $campaign?->getAtelierId());

        $this->em->persist($doc);

        return $doc;
    }

    /**
     * Purge identity documents (retention = 0 days) that have been transcribed to the Livre de Police.
     * RGPD: pièce d'identité et justificatif de domicile doivent être détruits après transcription.
     *
     * @return int Number of documents purged
     */
    public function purgeExpiredIdentityDocuments(): int
    {
        $typesToPurge = [
            VODocument::TYPE_PIECE_IDENTITE,
            VODocument::TYPE_JUSTIFICATIF_DOMICILE,
        ];

        $docs = $this->em->getRepository(VODocument::class)->createQueryBuilder('d')
            ->where('d.type IN (:types)')
            ->setParameter('types', $typesToPurge)
            ->getQuery()
            ->getResult();

        $count = 0;
        foreach ($docs as $doc) {
            // Only purge if the associated purchase/depot has a Livre de Police entry
            $purchase = $doc->getVoPurchase();
            $depot = $doc->getVoDepotVente();

            $hasLpEntry = false;
            if ($purchase) {
                $hasLpEntry = !empty($this->em->getRepository(\App\Entity\VOLivrePolice::class)->findOneBy(['voPurchase' => $purchase]));
            } elseif ($depot) {
                $hasLpEntry = !empty($this->em->getRepository(\App\Entity\VOLivrePolice::class)->findOneBy(['voDepotVente' => $depot]));
            }

            if (!$hasLpEntry) {
                continue;
            }

            // Delete the physical file
            $filePath = $this->projectDir . '/public' . $doc->getFilePath();
            if (is_file($filePath)) {
                @unlink($filePath);
            }

            $this->em->remove($doc);
            $count++;
        }

        if ($count > 0) {
            $this->em->flush();
        }

        return $count;
    }
}
