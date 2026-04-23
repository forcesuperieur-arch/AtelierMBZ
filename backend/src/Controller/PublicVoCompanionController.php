<?php

namespace App\Controller;

use App\Entity\ClauseLegale;
use App\Entity\Vehicule;
use App\Entity\VODepotVente;
use App\Entity\VODocument;
use App\Entity\VOPurchase;
use App\Infrastructure\InputNormalizer;
use App\Service\VOGeneratedDocumentService;
use App\Service\ClauseLegaleVisibilityService;
use App\Service\VODocumentService;
use App\Service\VOCompanionWorkflowService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/public/vo-companion')]
class PublicVoCompanionController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private NormalizerInterface $serializer,
        private VODocumentService $documentService,
        private VOCompanionWorkflowService $workflowService,
        private VOGeneratedDocumentService $generatedDocumentService,
        private RateLimiterFactory $publicVoCompanionLimiter,
        private ClauseLegaleVisibilityService $clauseVisibilityService,
        private InputNormalizer $inputNormalizer,
    ) {}

    private function ensureRateLimit(Request $request): ?JsonResponse
    {
        $limiter = $this->publicVoCompanionLimiter->create((string) $request->getClientIp());
        if ($limiter->consume()->isAccepted()) {
            return null;
        }

        return $this->json(['error' => 'Trop de requêtes'], Response::HTTP_TOO_MANY_REQUESTS);
    }

    #[Route('/{token}', methods: ['GET'])]
    public function show(string $token, Request $request): JsonResponse
    {
        if ($response = $this->ensureRateLimit($request)) {
            return $response;
        }

        $context = $this->findContextByToken($token);
        if ($context === null) {
            return $this->json(['error' => 'Lien invalide ou expire'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->buildPayload($context['record'], $context['documents']));
    }

    /**
     * [C6] Retourne les clauses légales actives selon le rôle de la partie (partyRole).
     * vendeur_rachat  → livre_police, cgv
     * deposant        → mandat_depot, commission
     * acheteur        → garantie_legale, cession
     */
    #[Route('/{token}/clauses', methods: ['GET'])]
    public function getClauses(string $token, Request $request): JsonResponse
    {
        if ($response = $this->ensureRateLimit($request)) {
            return $response;
        }

        $context = $this->findContextByToken($token);
        if ($context === null) {
            return $this->json(['error' => 'Lien invalide ou expire'], Response::HTTP_NOT_FOUND);
        }

        $record = $context['record'];
        $partyRole = $request->query->get('partyRole', '');

        $codesByRole = [
            'vendeur_rachat' => ['livre_police', 'cgv'],
            'deposant'       => ['mandat_depot', 'commission'],
            'acheteur'       => ['garantie_legale', 'cession'],
        ];

        if (!isset($codesByRole[$partyRole])) {
            return $this->json(['error' => 'partyRole invalide. Valeurs acceptées : ' . implode(', ', array_keys($codesByRole))], Response::HTTP_BAD_REQUEST);
        }

        $requiredCodes = $codesByRole[$partyRole];
        $atelierId = $record->getAtelierId();

        $clauses = $this->em->getRepository(ClauseLegale::class)->createQueryBuilder('c')
            ->where('c.code IN (:codes)')
            ->andWhere('c.atelierId = :atelierId OR c.atelierId IS NULL')
            ->andWhere('c.isActive = true')
            ->setParameter('codes', $requiredCodes)
            ->setParameter('atelierId', $atelierId)
            ->getQuery()
            ->getResult();

        $visible = $this->clauseVisibilityService->pickVisibleClauses($clauses, true);

        return $this->json([
            'partyRole' => $partyRole,
            'clauses' => array_map(fn(ClauseLegale $c) => [
                'code' => $c->getCode(),
                'libelle' => $c->getLibelle(),
                'texte' => $c->getTexte(),
                'version' => $c->getVersion(),
            ], $visible),
        ]);
    }

    #[Route('/{token}/seller', methods: ['POST'])]
    public function saveSeller(string $token, Request $request): JsonResponse
    {
        if ($response = $this->ensureRateLimit($request)) {
            return $response;
        }

        $context = $this->findContextByToken($token);
        if ($context === null) {
            return $this->json(['error' => 'Lien invalide ou expire'], Response::HTTP_NOT_FOUND);
        }

        $record = $context['record'];
        $files = $this->extractFiles($request, ['files', 'file']);

        if ($files !== []) {
            return $this->json([
                'error' => 'Le compagnon PDA ne stocke plus la piece d\'identite ni le justificatif de domicile. Retranscrivez les informations du document puis detruisez le support selon la procedure atelier.',
                'code' => 'IDENTITY_UPLOAD_DISABLED',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($record instanceof VOPurchase) {
            if ($request->request->has('idType')) {
                $record->setSellerIdType($this->inputNormalizer->nullableString($request->request->get('idType')));
            }
            if ($request->request->has('idNumber')) {
                $record->setSellerIdNumber($this->inputNormalizer->nullableString($request->request->get('idNumber')));
            }
            if ($request->request->get('idDate')) {
                $record->setSellerIdDate(new \DateTime((string) $request->request->get('idDate')));
            }
        } else {
            if ($request->request->has('idType')) {
                $record->setDeposantIdType($this->inputNormalizer->nullableString($request->request->get('idType')));
            }
            if ($request->request->has('idNumber')) {
                $record->setDeposantIdNumber($this->inputNormalizer->nullableString($request->request->get('idNumber')));
            }
            if ($request->request->get('idDate')) {
                $record->setDeposantIdDate(new \DateTime((string) $request->request->get('idDate')));
            }
        }

        $identityType = $record instanceof VOPurchase ? $record->getSellerIdType() : $record->getDeposantIdType();
        $identityNumber = $record instanceof VOPurchase ? $record->getSellerIdNumber() : $record->getDeposantIdNumber();
        $identityDate = $record instanceof VOPurchase ? $record->getSellerIdDate() : $record->getDeposantIdDate();

        if (trim((string) $identityType) === '' || trim((string) $identityNumber) === '' || !$identityDate instanceof \DateTimeInterface) {
            return $this->json([
                'error' => 'Type, numero et date de piece sont obligatoires pour finaliser la transcription vendeur.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $this->em->flush();

        return $this->json($this->buildPayload($record, $this->loadDocuments($record)));
    }

    #[Route('/{token}/vehicle-document', methods: ['POST'])]
    public function saveVehicleDocument(string $token, Request $request): JsonResponse
    {
        if ($response = $this->ensureRateLimit($request)) {
            return $response;
        }

        $context = $this->findContextByToken($token);
        if ($context === null) {
            return $this->json(['error' => 'Lien invalide ou expire'], Response::HTTP_NOT_FOUND);
        }

        $files = $this->extractFiles($request, ['files', 'file']);
        if ($files === []) {
            return $this->json(['error' => 'Document carte grise requis'], Response::HTTP_BAD_REQUEST);
        }

        foreach ($files as $file) {
            $this->documentService->upload(
                $file,
                VODocument::TYPE_CARTE_GRISE,
                $context['record'] instanceof VOPurchase ? $context['record'] : null,
                $context['record'] instanceof VODepotVente ? $context['record'] : null,
            );
        }

        $this->em->flush();

        return $this->json($this->buildPayload($context['record'], $this->loadDocuments($context['record'])));
    }

    #[Route('/{token}/vehicle-photo', methods: ['POST'])]
    public function saveVehiclePhoto(string $token, Request $request): JsonResponse
    {
        if ($response = $this->ensureRateLimit($request)) {
            return $response;
        }

        $context = $this->findContextByToken($token);
        if ($context === null) {
            return $this->json(['error' => 'Lien invalide ou expire'], Response::HTTP_NOT_FOUND);
        }

        $files = $this->extractFiles($request, ['files', 'file']);
        if ($files === []) {
            return $this->json(['error' => 'Photo vehicule requise'], Response::HTTP_BAD_REQUEST);
        }

        foreach ($files as $file) {
            $this->documentService->upload(
                $file,
                VODocument::TYPE_PHOTO_VEHICULE,
                $context['record'] instanceof VOPurchase ? $context['record'] : null,
                $context['record'] instanceof VODepotVente ? $context['record'] : null,
            );
        }

        $this->em->flush();

        return $this->json($this->buildPayload($context['record'], $this->loadDocuments($context['record'])));
    }

    #[Route('/{token}/vehicle-data', methods: ['PUT'])]
    public function saveVehicleData(string $token, Request $request): JsonResponse
    {
        if ($response = $this->ensureRateLimit($request)) {
            return $response;
        }

        $context = $this->findContextByToken($token);
        if ($context === null) {
            return $this->json(['error' => 'Lien invalide ou expire'], Response::HTTP_NOT_FOUND);
        }

        $payload = $request->toArray();
        $vehicule = $context['record']->getVehicule();

        if (!$vehicule instanceof Vehicule) {
            $vehicule = new Vehicule();
            $vehicule->setAtelierId($context['record']->getAtelierId());
            $vehicule->setPlaque((string) ($payload['plaque'] ?? $payload['vin'] ?? ('TEMP-VO-' . $context['record']->getId())));
            $this->em->persist($vehicule);
            $context['record']->setVehicule($vehicule);
        }

        $this->applyVehiculePayload($vehicule, $payload);
        if (trim((string) $vehicule->getPlaque()) === '') {
            $vehicule->setPlaque('TEMP-VO-' . $context['record']->getId());
        }
        $this->em->flush();

        return $this->json($this->buildPayload($context['record'], $this->loadDocuments($context['record'])));
    }

    #[Route('/{token}/document', methods: ['POST'])]
    public function saveDocument(string $token, Request $request): JsonResponse
    {
        if ($response = $this->ensureRateLimit($request)) {
            return $response;
        }

        $context = $this->findContextByToken($token);
        if ($context === null) {
            return $this->json(['error' => 'Lien invalide ou expire'], Response::HTTP_NOT_FOUND);
        }

        $type = $this->inputNormalizer->nullableString($request->request->get('type'));
        if ($type === null) {
            return $this->json(['error' => 'Type de document requis'], Response::HTTP_BAD_REQUEST);
        }

        if (in_array($type, [VODocument::TYPE_PIECE_IDENTITE, VODocument::TYPE_JUSTIFICATIF_DOMICILE], true)) {
            return $this->json([
                'error' => 'Le stockage de piece d\'identite et de justificatif de domicile est desactive sur ce parcours. Retranscrivez les informations utiles puis detruisez le document selon la procedure atelier.',
                'code' => 'SENSITIVE_IDENTITY_STORAGE_DISABLED',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $files = $this->extractFiles($request, ['files', 'file']);
        if ($files === []) {
            return $this->json(['error' => 'Document requis'], Response::HTTP_BAD_REQUEST);
        }

        foreach ($files as $file) {
            $this->documentService->upload(
                $file,
                $type,
                $context['record'] instanceof VOPurchase ? $context['record'] : null,
                $context['record'] instanceof VODepotVente ? $context['record'] : null,
            );
        }

        $this->em->flush();

        return $this->json($this->buildPayload($context['record'], $this->loadDocuments($context['record'])));
    }

    #[Route('/{token}/signature', methods: ['POST'])]
    public function saveSignature(string $token, Request $request): JsonResponse
    {
        if ($response = $this->ensureRateLimit($request)) {
            return $response;
        }

        $context = $this->findContextByToken($token);
        if ($context === null) {
            return $this->json(['error' => 'Lien invalide ou expire'], Response::HTTP_NOT_FOUND);
        }

        $record = $context['record'];
        $documents = $this->loadDocuments($record);
        $steps = $this->workflowService->buildSteps($record, $documents);
        if (!$steps['seller']['completed'] || !$steps['vehicle']['completed'] || !$steps['documents']['completed']) {
            return $this->json([
                'error' => 'Completez les etapes vendeur, vehicule et documents avant la signature.',
                'steps' => $steps,
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $payload = json_decode($request->getContent(), true) ?? [];
        $signature = trim((string) ($payload['signature'] ?? ''));
        if (!str_starts_with($signature, 'data:image/')) {
            return $this->json(['error' => 'Signature invalide'], Response::HTTP_BAD_REQUEST);
        }

        // [C6] Vérifier que les clauses ont été acceptées selon le partyRole
        $partyRole = $payload['partyRole'] ?? '';
        $codesByRole = [
            'vendeur_rachat' => ['livre_police', 'cgv'],
            'deposant'       => ['mandat_depot', 'commission'],
            'acheteur'       => ['garantie_legale', 'cession'],
        ];
        if (isset($codesByRole[$partyRole])) {
            $acceptedCodes = (array) ($payload['clausesAcceptees'] ?? []);
            $missingCodes = array_diff($codesByRole[$partyRole], $acceptedCodes);
            if (!empty($missingCodes)) {
                return $this->json([
                    'error' => 'Vous devez accepter toutes les conditions avant de signer.',
                    'clausesManquantes' => array_values($missingCodes),
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        $content = preg_replace('#^data:image/[^;]+;base64,#', '', $signature);
        $binary = base64_decode($content ?: '', true);
        if ($binary === false) {
            return $this->json(['error' => 'Signature impossible a decoder'], Response::HTTP_BAD_REQUEST);
        }

        $existingSignature = $this->em->getRepository(VODocument::class)->findOneBy([
            'type' => VODocument::TYPE_SIGNATURE_CLIENT,
            'voPurchase' => $record instanceof VOPurchase ? $record : null,
            'voDepotVente' => $record instanceof VODepotVente ? $record : null,
        ]);
        if ($existingSignature === null) {
            $this->documentService->storeRawContent(
                $binary,
                sprintf('signature-%s-%d.png', $this->workflowService->getMode($record), $record->getId()),
                'image/png',
                VODocument::TYPE_SIGNATURE_CLIENT,
                $record instanceof VOPurchase ? $record : null,
                $record instanceof VODepotVente ? $record : null,
            );
        }

        $record->setCompanionSignatureData($signature);
        $record->setCompanionSignedAt(new \DateTime());
        $this->em->flush();

        $this->generatedDocumentService->archiveCompanionDocumentIfReady($record);
        $this->em->flush();

        return $this->json($this->buildPayload($record, $this->loadDocuments($record)));
    }

    private function findContextByToken(string $token): ?array
    {
        if (strlen($token) < 16) {
            return null;
        }

        $purchase = $this->em->getRepository(VOPurchase::class)->findOneBy(['companionToken' => $token]);
        if ($purchase instanceof VOPurchase && !$purchase->isCompanionTokenExpired()) {
            return ['record' => $purchase, 'documents' => $this->loadDocuments($purchase)];
        }

        $depot = $this->em->getRepository(VODepotVente::class)->findOneBy(['companionToken' => $token]);
        if ($depot instanceof VODepotVente && !$depot->isCompanionTokenExpired()) {
            return ['record' => $depot, 'documents' => $this->loadDocuments($depot)];
        }

        return null;
    }

    private function extractFiles(Request $request, array $keys): array
    {
        $files = [];

        foreach ($keys as $key) {
            $candidate = $request->files->get($key);
            if ($candidate === null) {
                continue;
            }

            if (is_array($candidate)) {
                foreach ($candidate as $file) {
                    if ($file !== null) {
                        $files[] = $file;
                    }
                }
            } else {
                $files[] = $candidate;
            }
        }

        return $files;
    }

    private function loadDocuments(VOPurchase|VODepotVente $record): array
    {
        return $this->em->getRepository(VODocument::class)->findBy(
            $record instanceof VOPurchase ? ['voPurchase' => $record] : ['voDepotVente' => $record],
            ['uploadedAt' => 'DESC'],
        );
    }

    private function buildPayload(VOPurchase|VODepotVente $record, array $documents): array
    {
        $mode = $this->workflowService->getMode($record);
        $party = $this->workflowService->getParty($record);
        $steps = $this->workflowService->buildSteps($record, $documents);

        return [
            'mode' => $mode,
            'partyRole' => $this->workflowService->getPartyRoleLabel($record),
            'dossier' => [
                'id' => $record->getId(),
                'status' => $record->getStatus(),
                'publicPath' => $record->getCompanionPublicPath(),
                'expiresAt' => $record->getCompanionTokenExpiresAt()?->format(DATE_ATOM),
                'signedAt' => $record->getCompanionSignedAt()?->format(DATE_ATOM),
                'generatedDocuments' => $this->workflowService->getGeneratedDocuments($record),
            ],
            'party' => [
                'prenom' => $party?->getPrenom(),
                'nom' => $party?->getNom(),
                'idType' => $record instanceof VOPurchase ? $record->getSellerIdType() : $record->getDeposantIdType(),
                'idNumber' => $record instanceof VOPurchase ? $record->getSellerIdNumber() : $record->getDeposantIdNumber(),
                'idDate' => ($record instanceof VOPurchase ? $record->getSellerIdDate() : $record->getDeposantIdDate())?->format('Y-m-d'),
            ],
            'vehicule' => $record->getVehicule() ? $this->serializer->normalize($record->getVehicule(), null, ['groups' => 'vehicule:read']) : null,
            'documents' => array_map(static fn (VODocument $document) => [
                'id' => $document->getId(),
                'type' => $document->getType(),
                'originalFilename' => $document->getOriginalFilename(),
                'uploadedAt' => $document->getUploadedAt()?->format(DATE_ATOM),
            ], $documents),
            'steps' => $steps,
        ];
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


}