<?php

namespace App\Service;

use App\Entity\VODocument;
use App\Entity\VODepotVente;
use App\Entity\VOPurchase;
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
        $mimeType = $file->getMimeType() ?? $file->getClientMimeType();
        if (!in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            throw new \InvalidArgumentException('Type de fichier non autorisé. Formats acceptés : PDF, JPEG, PNG, WebP.');
        }

        $uploadDir = $this->projectDir . '/public/uploads/vo';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $safeFilename = bin2hex(random_bytes(16)) . '.' . ($file->guessExtension() ?? 'bin');
        $file->move($uploadDir, $safeFilename);

        $doc = new VODocument();
        $doc->setType($type);
        $doc->setFilePath('/uploads/vo/' . $safeFilename);
        $doc->setOriginalFilename($file->getClientOriginalName());
        $doc->setMimeType($mimeType);
        $doc->setVoPurchase($purchase);
        $doc->setVoDepotVente($depot);
        $doc->setDateExpiration($dateExpiration);
        $doc->setUploadedBy($user);
        $doc->setAtelierId($purchase?->getAtelierId() ?? $depot?->getAtelierId());

        $this->em->persist($doc);

        return $doc;
    }

    public function archiveGeneratedPdf(
        string $generatedFilePath,
        string $type,
        ?VOPurchase $purchase = null,
        ?VODepotVente $depot = null,
        ?User $user = null,
        ?string $originalFilename = null,
    ): VODocument {
        if (!is_file($generatedFilePath)) {
            throw new \RuntimeException(sprintf('Fichier PDF introuvable : %s', $generatedFilePath));
        }

        $uploadDir = $this->projectDir . '/public/uploads/vo/generated';
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
        ]) ?? new VODocument();

        $doc->setType($type);
        $doc->setFilePath('/uploads/vo/generated/' . $safeFilename);
        $doc->setOriginalFilename($originalFilename ?: basename($generatedFilePath));
        $doc->setMimeType('application/pdf');
        $doc->setVoPurchase($purchase);
        $doc->setVoDepotVente($depot);
        $doc->setUploadedBy($user);
        $doc->setAtelierId($purchase?->getAtelierId() ?? $depot?->getAtelierId());

        $this->em->persist($doc);

        return $doc;
    }

    /**
     * Check which required documents are missing for a purchase.
     * @return array<string> list of missing document type codes
     */
    public function getMissingDocuments(VOPurchase $purchase, bool $includeSale = false): array
    {
        $existing = $this->em->getRepository(VODocument::class)->createQueryBuilder('d')
            ->select('d.type')
            ->where('d.voPurchase = :p')
            ->setParameter('p', $purchase)
            ->getQuery()
            ->getSingleColumnResult();

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
        $existing = $this->em->getRepository(VODocument::class)->createQueryBuilder('d')
            ->select('d.type')
            ->where('d.voDepotVente = :d')
            ->setParameter('d', $depot)
            ->getQuery()
            ->getSingleColumnResult();

        return array_values(array_diff(self::DEPOT_REQUIRED_DOCS, $existing));
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
            foreach ($missing as $docType) {
                $alerts[] = [
                    'type' => 'missing',
                    'document_type' => $docType,
                    'purchase_id' => $purchase->getId(),
                    'message' => sprintf('Document "%s" manquant pour le véhicule %s', $docType, $purchase->getVehicule()->getPlaque()),
                ];
            }
        }

        return $alerts;
    }
}
