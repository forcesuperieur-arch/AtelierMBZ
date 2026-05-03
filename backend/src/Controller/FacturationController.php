<?php
namespace App\Controller;

use App\Entity\Atelier;
use App\Entity\ConfigAtelier;
use App\Entity\Facture;
use App\Entity\LigneFacture;
use App\Entity\Paiement;
use App\Entity\RendezVous;
use App\Entity\User;
use App\Service\AuditService;
use App\Service\CurrentAtelierResolver;
use App\Service\PdfService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api/facturation')]
class FacturationController extends AbstractController
{
    private function getDefaultTvaRate(): string
    {
        return trim((string) ($_ENV['TVA_RATE_DEFAULT'] ?? '20.00'));
    }

    private function getDefaultTauxHoraire(): string
    {
        return trim((string) ($_ENV['TAUX_HORAIRE_DEFAULT'] ?? '65.00'));
    }

    private function resolveCurrentAtelierIdOrFail(): int
    {
        $atelierId = $this->atelierResolver->resolveAtelierId();
        if (!$atelierId) {
            throw $this->createAccessDeniedException('Contexte atelier introuvable.');
        }

        return $atelierId;
    }

    private function findFactureForScope(int $id): ?Facture
    {
        if ($this->isGranted('ROLE_SUPER_ADMIN')) {
            return $this->em->getRepository(Facture::class)->find($id);
        }

        return $this->em->getRepository(Facture::class)->findOneBy([
            'id' => $id,
            'atelierId' => $this->resolveCurrentAtelierIdOrFail(),
        ]);
    }

    private function calculateSettledAmount(Facture $facture, string $operationType): string
    {
        $total = '0.00';
        foreach ($facture->getPaiements() as $paiement) {
            if ($paiement->getTypeOperation() !== $operationType) {
                continue;
            }

            $total = bcadd($total, (string) $paiement->getMontant(), 2);
        }

        return $total;
    }

    private function getRemainingBalance(Facture $facture, string $operationType): string
    {
        $total = ltrim((string) $facture->getTotalTtc(), '-');
        $settled = $this->calculateSettledAmount($facture, $operationType);
        $remaining = bcsub($total, $settled, 2);

        return bccomp($remaining, '0', 2) < 0 ? '0.00' : $remaining;
    }

    private function refreshSettlementStatus(Facture $facture): void
    {
        if (!$facture->isAvoir() && $facture->getStatut() === Facture::STATUS_CORRIGEE) {
            return;
        }

        $operationType = $facture->isAvoir() ? Paiement::TYPE_REMBOURSEMENT : Paiement::TYPE_ENCAISSEMENT;
        $settled = $this->calculateSettledAmount($facture, $operationType);
        $due = ltrim((string) $facture->getTotalTtc(), '-');

        if (bccomp($settled, '0', 2) <= 0) {
            $facture->setStatut(Facture::STATUS_EMISE);
            return;
        }

        if (bccomp($settled, $due, 2) >= 0) {
            $facture->setStatut(Facture::STATUS_PAYEE);
            return;
        }

        $facture->setStatut(Facture::STATUS_PARTIELLEMENT_PAYEE);
    }

    private function createMoneyMovement(Facture $facture, array $data, string $operationType): Paiement
    {
        $paiement = new Paiement();
        $paiement->setFacture($facture);
        $paiement->setAtelierId($facture->getAtelierId());
        $paiement->setTypeOperation($operationType);
        $paiement->setMontant((string) $data['montant']);
        $paiement->setModePaiement((string) $data['mode_paiement']);
        $paiement->setReference($data['reference'] ?? null);
        $paiement->setNotes($data['notes'] ?? null);

        if (isset($data['date_paiement'])) {
            $paiement->setDatePaiement(new \DateTime((string) $data['date_paiement']));
        }

        $facture->addPaiement($paiement);
        $this->em->persist($paiement);

        return $paiement;
    }

    private function getTvaRate(): string
    {
        $config = $this->getConfigAtelier();
        return $config ? (string) ($config->getTvaMoTaux() ?? $this->getDefaultTvaRate()) : $this->getDefaultTvaRate();
    }

    private function getTauxHoraire(): string
    {
        $config = $this->getConfigAtelier();
        return $config ? ($config->getTauxHoraireMoStandard() ?? $this->getDefaultTauxHoraire()) : $this->getDefaultTauxHoraire();
    }

    private function getConfigAtelier(): ?ConfigAtelier
    {
        $atelierId = $this->atelierResolver->resolveAtelierId();
        if (!$atelierId) {
            return null;
        }
        return $this->em->getRepository(ConfigAtelier::class)->findOneBy(['atelierId' => $atelierId]);
    }

    private function resolveAtelierBranding(): array
    {
        $atelierId = $this->atelierResolver->resolveAtelierId();
        $atelier = $atelierId ? $this->em->getRepository(Atelier::class)->find($atelierId) : null;
        return [
            'from' => $atelier?->getEmail() ?? 'noreply@paddock.fr',
            'nom' => $atelier?->getNom() ?? 'Paddock',
        ];
    }

    private function getAuthenticatedUser(): ?User
    {
        $user = $this->getUser();

        return $user instanceof User ? $user : null;
    }

    private function hasLegacyAdminFallback(): bool
    {
        return $this->isGranted('ROLE_ADMIN') && $this->getAuthenticatedUser()?->getRoleMetier() === null;
    }

    private function assertFacturationWriteAccess(): void
    {
        if ($this->isGranted('ROLE_SUPER_ADMIN')
            || $this->isGranted('ROLE_COMPTABLE')
            || $this->isGranted('PERM_facturation.edit')
            || $this->isGranted('PERM_facturation.create')
            || $this->hasLegacyAdminFallback()) {
            return;
        }

        throw $this->createAccessDeniedException('Accès facturation refusé.');
    }

    private function assertFacturationReadAccess(): void
    {
        if ($this->isGranted('ROLE_SUPER_ADMIN')
            || $this->isGranted('ROLE_COMPTABLE')
            || $this->isGranted('PERM_facturation.edit')
            || $this->isGranted('PERM_facturation.create')
            || $this->isGranted('ROLE_USER')) {
            return;
        }

        throw $this->createAccessDeniedException('Accès facturation refusé.');
    }

    private function nextDocumentNumber(string $prefix): string
    {
        $conn = $this->em->getConnection();
        $year = date('Y');
        $seqName = strtolower($prefix) . '_seq_' . $year;

        try {
            $conn->executeStatement(sprintf('CREATE SEQUENCE IF NOT EXISTS %s START 1', $seqName));
        } catch (\Throwable) {
        }

        $nextVal = (int) $conn->fetchOne(sprintf("SELECT nextval('%s')", $seqName));

        return sprintf('%s-%s-%04d', strtoupper($prefix), $year, $nextVal);
    }

    private function buildRdvInvoicePreview(RendezVous $rdv, string $remisePourcent = '0'): array
    {
        $baseHt = $rdv->getPrixEstime() ?? '0';
        if (bccomp($baseHt, '0', 2) <= 0) {
            $minutes = max(30, (int) ($rdv->getTempsEstime() ?? 60));
            $baseHt = bcdiv(bcmul((string) $minutes, $this->getTauxHoraire(), 2), '60', 2);
        }

        $designation = trim((string) ($rdv->getTypeIntervention() ?: 'Intervention atelier'));

        $vehiculeInfo = $rdv->getVehicule()
            ? trim(($rdv->getVehicule()?->getMarque() ?? '') . ' ' . ($rdv->getVehicule()?->getModele() ?? ''))
            : null;

        return [
            'rdv_id' => $rdv->getId(),
            'client_nom' => trim(($rdv->getClient()?->getPrenom() ?? '') . ' ' . ($rdv->getClient()?->getNom() ?? '')),
            'vehicule' => $vehiculeInfo,
            'vehicule_info' => $vehiculeInfo,
            'remise' => $remisePourcent,
            'tva_mo_taux' => $this->getTvaRate(),
            'tva_pieces_taux' => $this->getTvaRate(),
            'lignes_mo' => [[
                'designation' => $designation,
                'label' => $designation,
                'montant_ht' => $baseHt,
            ]],
            'lignes_pieces' => [],
            'total_ht' => $baseHt,
        ];
    }

    public function __construct(
        private EntityManagerInterface $em,
        private PdfService $pdfService,
        private AuditService $audit,
        private SerializerInterface $serializer,
        private MailerInterface $mailer,
        private CurrentAtelierResolver $atelierResolver,
    ) {}

    /**
     * List all invoices.
     */
    #[Route('', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $this->assertFacturationReadAccess();

        $page = max(1, (int) $request->query->get('page', 1));
        $itemsPerPage = min(100, max(1, (int) $request->query->get('itemsPerPage', 50)));

        $qb = $this->em->getRepository(Facture::class)->createQueryBuilder('f')
            ->orderBy('f.createdAt', 'DESC');

        if (!$this->isGranted('ROLE_SUPER_ADMIN')) {
            $qb->andWhere('f.atelierId = :atelierId')
                ->setParameter('atelierId', $this->resolveCurrentAtelierIdOrFail());
        }

        if ($statut = $request->query->get('statut')) {
            $qb->andWhere('f.statut = :statut')->setParameter('statut', $statut);
        }

        // [SPRINT-5] I12 — Pagination server-side
        $countQb = clone $qb;
        $totalItems = (int) $countQb->select('COUNT(f.id)')->getQuery()->getSingleScalarResult();

        $factures = $qb
            ->setFirstResult(($page - 1) * $itemsPerPage)
            ->setMaxResults($itemsPerPage)
            ->getQuery()
            ->getResult();

        $data = json_decode($this->serializer->serialize($factures, 'json', ['groups' => ['facture:read']]), true);
        return $this->json([
            'member' => $data,
            'totalItems' => $totalItems,
            'page' => $page,
            'itemsPerPage' => $itemsPerPage,
        ]);
    }

    #[Route('/rendez-vous/{rdvId}/preview-facture', methods: ['GET'])]
    public function previewFacture(int $rdvId): JsonResponse
    {
        $this->assertFacturationReadAccess();

        $criteria = ['id' => $rdvId];
        if (!$this->isGranted('ROLE_SUPER_ADMIN')) {
            $criteria['atelierId'] = $this->resolveCurrentAtelierIdOrFail();
        }

        $rdv = $this->em->getRepository(RendezVous::class)->findOneBy($criteria);
        if (!$rdv) {
            return $this->json(['error' => 'RDV not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->buildRdvInvoicePreview($rdv));
    }

    #[Route('/rendez-vous/{rdvId}/facturer', methods: ['POST'])]
    public function facturerRendezVous(int $rdvId, Request $request): JsonResponse
    {
        $this->assertFacturationWriteAccess();

        $criteria = ['id' => $rdvId];
        if (!$this->isGranted('ROLE_SUPER_ADMIN')) {
            $criteria['atelierId'] = $this->resolveCurrentAtelierIdOrFail();
        }

        $rdv = $this->em->getRepository(RendezVous::class)->findOneBy($criteria);
        if (!$rdv) {
            return $this->json(['error' => 'RDV not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $remisePourcent = (string) max(0, min(100, (float) ($data['remise_pourcent'] ?? 0)));
        $preview = $this->buildRdvInvoicePreview($rdv, $remisePourcent);

        $moHt = '0';
        foreach ($preview['lignes_mo'] as $line) {
            $moHt = bcadd($moHt, (string) ($line['montant_ht'] ?? '0'), 2);
        }
        $piecesHt = '0';
        foreach ($preview['lignes_pieces'] as $line) {
            $piecesHt = bcadd($piecesHt, (string) ($line['montant_ht'] ?? '0'), 2);
        }
        $discountFactor = bcsub('1', bcdiv($remisePourcent, '100', 6), 6);
        $moHtDiscounted = bcmul($moHt, $discountFactor, 2);
        $piecesHtDiscounted = bcmul($piecesHt, $discountFactor, 2);
        $totalHt = bcadd($moHtDiscounted, $piecesHtDiscounted, 2);
        $tvaRate = bcdiv($this->getTvaRate(), '100', 4);
        $tvaMo = bcmul($moHtDiscounted, $tvaRate, 2);
        $tvaPieces = bcmul($piecesHtDiscounted, $tvaRate, 2);
        $totalTva = bcadd($tvaMo, $tvaPieces, 2);
        $totalTtc = bcadd($totalHt, $totalTva, 2);

        $payload = [
            'total_mo_ht' => $moHtDiscounted,
            'total_pieces_ht' => $piecesHtDiscounted,
            'total_ht' => $totalHt,
            'total_ttc' => $totalTtc,
            'tva_mo' => $tvaMo,
            'tva_pieces' => $tvaPieces,
            'total_tva' => $totalTva,
            'temps_facture_minutes' => (int) ($rdv->getTempsEstime() ?? 60),
            'taux_horaire' => $this->getTauxHoraire(),
            'notes' => $data['notes'] ?? null,
            'lignes' => [[
                'type_ligne' => 'main_oeuvre',
                'designation' => $rdv->getTypeIntervention() ?: 'Intervention atelier',
                'quantite' => 1,
                'prix_unitaire_ht' => $totalHt,
                'taux_tva' => $this->getTvaRate(),
                'total_ligne_ht' => $totalHt,
                'total_ligne_ttc' => $totalTtc,
            ]],
        ];

        $wrappedRequest = new Request(content: json_encode($payload));
        return $this->createFacture($rdvId, $wrappedRequest);
    }

    /**
     * Create an invoice from a RDV.
     */
    #[Route('/rendez-vous/{rdvId}', methods: ['POST'])]
    public function createFacture(int $rdvId, Request $request): JsonResponse
    {
        $this->assertFacturationWriteAccess();

        $data = json_decode($request->getContent(), true) ?? [];

        $rdv = $this->em->getRepository(RendezVous::class)->find($rdvId);
        if (!$rdv) {
            return $this->json(['error' => 'RDV not found'], Response::HTTP_NOT_FOUND);
        }

        // Generate invoice number via PostgreSQL sequence to avoid race conditions
        $numero = $this->nextDocumentNumber('FAC');

        $facture = new Facture();
        $facture->setNumeroFacture($numero);
        $facture->setRendezVous($rdv);
        $facture->setClient($rdv->getClient());
        $facture->setVehicule($rdv->getVehicule());
        $facture->setAtelierId($rdv->getAtelierId());
        $facture->setNature(Facture::NATURE_FACTURE);

        // Set amounts from request data or defaults
        $facture->setTotalMoHt($data['total_mo_ht'] ?? '0.00');
        $facture->setTotalPiecesHt($data['total_pieces_ht'] ?? '0.00');
        $facture->setTotalHt($data['total_ht'] ?? '0.00');
        $facture->setTotalTtc($data['total_ttc'] ?? '0.00');
        $facture->setTvaMo($data['tva_mo'] ?? '0.00');
        $facture->setTvaPieces($data['tva_pieces'] ?? '0.00');
        $facture->setTotalTva($data['total_tva'] ?? '0.00');
        $facture->setTempsFactureMinutes($data['temps_facture_minutes'] ?? 0);
        $facture->setTauxHoraire($data['taux_horaire'] ?? $this->getTauxHoraire());
        $facture->setTvaMoTaux((float) ($data['tva_mo_taux'] ?? $this->getTvaRate()));
        $facture->setTvaPiecesTaux((float) ($data['tva_pieces_taux'] ?? $this->getTvaRate()));
        $facture->setNotes($data['notes'] ?? null);

        // Add lines
        foreach ($data['lignes'] ?? [] as $i => $ligne) {
            $lf = new LigneFacture();
            $lf->setFacture($facture);
            $lf->setTypeLigne($ligne['type_ligne']);
            $lf->setDesignation($ligne['designation']);
            $lf->setReference($ligne['reference'] ?? null);
            $lf->setQuantite($ligne['quantite'] ?? 1);
            $lf->setPrixUnitaireHt($ligne['prix_unitaire_ht']);
            $lf->setTauxTva($ligne['taux_tva'] ?? 20.0);
            $lf->setTotalLigneHt($ligne['total_ligne_ht']);
            $lf->setTotalLigneTtc($ligne['total_ligne_ttc']);
            $lf->setOrdre($i);
            $lf->setAtelierId($rdv->getAtelierId());
            $this->em->persist($lf);
        }

        $this->em->persist($facture);
        $this->em->flush();

        $this->audit->log('create', 'facture', $facture->getId(), json_encode(['numero' => $numero]));

        return $this->json([
            'id' => $facture->getId(),
            'numero_facture' => $numero,
        ], Response::HTTP_CREATED);
    }

    /**
     * Record a payment on an invoice.
     */
    #[Route('/{id}/paiement', methods: ['POST'])]
    public function addPaiement(int $id, Request $request): JsonResponse
    {
        $this->assertFacturationWriteAccess();

        $facture = $this->findFactureForScope($id);
        if (!$facture) {
            return $this->json(['error' => 'Invoice not found'], Response::HTTP_NOT_FOUND);
        }

        if ($facture->isAvoir() || $facture->getStatut() === Facture::STATUS_CORRIGEE) {
            return $this->json(['error' => 'Cette facture ne peut pas recevoir d\'encaissement.'], Response::HTTP_CONFLICT);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $montant = number_format((float) ($data['montant'] ?? 0), 2, '.', '');
        if (bccomp($montant, '0', 2) <= 0) {
            return $this->json(['error' => 'Le montant doit être strictement positif.'], Response::HTTP_BAD_REQUEST);
        }

        $resteAvantPaiement = $this->getRemainingBalance($facture, Paiement::TYPE_ENCAISSEMENT);
        if (bccomp($montant, $resteAvantPaiement, 2) > 0) {
            return $this->json([
                'error' => 'Le montant dépasse le solde restant à encaisser.',
                'reste_a_payer' => $resteAvantPaiement,
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $data['montant'] = $montant;
        $this->createMoneyMovement($facture, $data, Paiement::TYPE_ENCAISSEMENT);
        $this->refreshSettlementStatus($facture);

        $this->em->flush();

        $this->audit->log('payment', 'facture', $facture->getId(), json_encode([
            'montant' => $montant,
            'mode' => $data['mode_paiement'],
            'statut' => $facture->getStatut(),
        ]));

        return $this->json([
            'facture_id' => $facture->getId(),
            'statut' => $facture->getStatut(),
            'total_paye' => $facture->getMontantPaye(),
            'reste_a_payer' => $facture->getResteAPayer(),
        ]);
    }

    /**
     * Download invoice PDF.
     */
    #[Route('/{id}/pdf', methods: ['GET'])]
    public function downloadPdf(int $id): BinaryFileResponse|JsonResponse
    {
        $this->assertFacturationReadAccess();

        $facture = $this->findFactureForScope($id);
        if (!$facture) {
            return $this->json(['error' => 'Invoice not found'], Response::HTTP_NOT_FOUND);
        }

        $filePath = $this->pdfService->generateFacturePdf($facture);

        $prefix = $facture->isAvoir() ? 'Avoir-' : 'Facture-';

        return $this->file($filePath, $prefix . $facture->getNumeroFacture() . '.pdf');
    }

    /**
     * Send invoice by email to the client.
     */
    #[Route('/{id}/email', methods: ['POST'])]
    public function sendEmail(int $id): JsonResponse
    {
        $this->assertFacturationWriteAccess();

        $facture = $this->findFactureForScope($id);
        if (!$facture) {
            return $this->json(['error' => 'Invoice not found'], Response::HTTP_NOT_FOUND);
        }

        $client = $facture->getClient();
        if (!$client || !$client->getEmail()) {
            return $this->json(['error' => 'Aucune adresse email client'], Response::HTTP_BAD_REQUEST);
        }

        $filePath = $this->pdfService->generateFacturePdf($facture);

        $branding = $this->resolveAtelierBranding();
        $email = (new Email())
            ->from($branding['from'])
            ->to($client->getEmail())
            ->subject('Votre facture ' . $facture->getNumeroFacture() . ' — ' . $branding['nom'])
            ->html(sprintf(
                '<p>Bonjour %s,</p><p>Veuillez trouver ci-joint votre facture <strong>%s</strong> d\'un montant de <strong>%s €</strong>.</p><p>Cordialement,<br>L\'équipe %s</p>',
                htmlspecialchars($client->getPrenom() ?? ''),
                htmlspecialchars($facture->getNumeroFacture()),
                number_format((float) $facture->getTotalTtc(), 2, ',', ' '),
                htmlspecialchars($branding['nom']),
            ))
            ->attachFromPath($filePath);

        $this->mailer->send($email);

        $this->audit->log('email', 'facture', $facture->getId(), json_encode([
            'to' => $client->getEmail(),
            'numero' => $facture->getNumeroFacture(),
        ]));

        return $this->json(['success' => true, 'sent_to' => $client->getEmail()]);
    }

    #[Route('/{id}/avoir', methods: ['POST'])]
    public function issueAvoir(int $id, Request $request): JsonResponse
    {
        $this->assertFacturationWriteAccess();

        $facture = $this->findFactureForScope($id);
        if (!$facture) {
            return $this->json(['error' => 'Invoice not found'], Response::HTTP_NOT_FOUND);
        }

        if ($facture->isAvoir()) {
            return $this->json(['error' => 'Un avoir ne peut pas être corrigé par un autre avoir via ce flux.'], Response::HTTP_CONFLICT);
        }

        if ($facture->getStatut() === Facture::STATUS_CORRIGEE) {
            return $this->json(['error' => 'Cette facture a déjà été corrigée par un avoir.'], Response::HTTP_CONFLICT);
        }

        $motif = trim((string) ((json_decode($request->getContent(), true) ?? [])['motif'] ?? ''));
        if ($motif === '') {
            return $this->json(['error' => 'Le motif d\'avoir est obligatoire.'], Response::HTTP_BAD_REQUEST);
        }

        $avoir = new Facture();
        $avoir->setNumeroFacture($this->nextDocumentNumber('AVO'));
        $avoir->setRendezVous($facture->getRendezVous());
        $avoir->setClient($facture->getClient());
        $avoir->setVehicule($facture->getVehicule());
        $avoir->setAtelierId($facture->getAtelierId());
        $avoir->setNature(Facture::NATURE_AVOIR);
        $avoir->setFactureOrigine($facture);
        $avoir->setMotifCorrection($motif);
        $avoir->setStatut(Facture::STATUS_EMISE);
        $avoir->setTotalMoHt(bcmul((string) $facture->getTotalMoHt(), '-1', 2));
        $avoir->setTotalPiecesHt(bcmul((string) $facture->getTotalPiecesHt(), '-1', 2));
        $avoir->setTotalHt(bcmul((string) $facture->getTotalHt(), '-1', 2));
        $avoir->setTvaMo(bcmul((string) $facture->getTvaMo(), '-1', 2));
        $avoir->setTvaPieces(bcmul((string) $facture->getTvaPieces(), '-1', 2));
        $avoir->setTotalTva(bcmul((string) $facture->getTotalTva(), '-1', 2));
        $avoir->setTotalTtc(bcmul((string) $facture->getTotalTtc(), '-1', 2));
        $avoir->setRemisePourcentage($facture->getRemisePourcentage());
        $avoir->setRemiseMontant(bcmul((string) $facture->getRemiseMontant(), '-1', 2));
        $avoir->setTempsFactureMinutes($facture->getTempsFactureMinutes());
        $avoir->setTauxHoraire($facture->getTauxHoraire());
        $avoir->setTvaMoTaux($facture->getTvaMoTaux());
        $avoir->setTvaPiecesTaux($facture->getTvaPiecesTaux());
        $avoir->setNotes(trim(sprintf("Avoir de correction de %s\nMotif : %s\n%s", $facture->getNumeroFacture(), $motif, (string) ($facture->getNotes() ?? ''))));

        $this->em->persist($avoir);

        foreach ($facture->getLignes() as $index => $ligne) {
            $copie = new LigneFacture();
            $copie->setFacture($avoir);
            $copie->setAtelierId($facture->getAtelierId());
            $copie->setTypeLigne($ligne->getTypeLigne());
            $copie->setDesignation('Avoir - ' . $ligne->getDesignation());
            $copie->setReference($ligne->getReference());
            $copie->setQuantite($ligne->getQuantite());
            $copie->setPrixUnitaireHt(bcmul((string) $ligne->getPrixUnitaireHt(), '-1', 2));
            $copie->setTauxTva($ligne->getTauxTva());
            $copie->setTotalLigneHt(bcmul((string) $ligne->getTotalLigneHt(), '-1', 2));
            $copie->setTotalLigneTtc(bcmul((string) $ligne->getTotalLigneTtc(), '-1', 2));
            $copie->setOrdre($index);
            $this->em->persist($copie);
        }

        $facture->setStatut(Facture::STATUS_CORRIGEE);
        $this->em->flush();

        $this->audit->log('credit_note', 'facture', $facture->getId(), json_encode([
            'facture_origine' => $facture->getNumeroFacture(),
            'avoir' => $avoir->getNumeroFacture(),
            'motif' => $motif,
        ]));

        return $this->json([
            'id' => $avoir->getId(),
            'numero_facture' => $avoir->getNumeroFacture(),
            'facture_origine_id' => $facture->getId(),
            'nature' => $avoir->getNature(),
        ], Response::HTTP_CREATED);
    }

    #[Route('/{id}/remboursement', methods: ['POST'])]
    public function addRemboursement(int $id, Request $request): JsonResponse
    {
        $this->assertFacturationWriteAccess();

        $facture = $this->findFactureForScope($id);
        if (!$facture) {
            return $this->json(['error' => 'Invoice not found'], Response::HTTP_NOT_FOUND);
        }

        if (!$facture->isAvoir()) {
            return $this->json(['error' => 'Seul un avoir peut recevoir un remboursement.'], Response::HTTP_CONFLICT);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $montant = number_format((float) ($data['montant'] ?? 0), 2, '.', '');
        if (bccomp($montant, '0', 2) <= 0) {
            return $this->json(['error' => 'Le montant doit être strictement positif.'], Response::HTTP_BAD_REQUEST);
        }

        $resteAvantRemboursement = $this->getRemainingBalance($facture, Paiement::TYPE_REMBOURSEMENT);
        if (bccomp($montant, $resteAvantRemboursement, 2) > 0) {
            return $this->json([
                'error' => 'Le montant dépasse le solde restant à rembourser.',
                'reste_a_payer' => $resteAvantRemboursement,
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $data['montant'] = $montant;
        $this->createMoneyMovement($facture, $data, Paiement::TYPE_REMBOURSEMENT);
        $this->refreshSettlementStatus($facture);

        $this->em->flush();

        $this->audit->log('refund', 'facture', $facture->getId(), json_encode([
            'montant' => $montant,
            'mode' => $data['mode_paiement'] ?? null,
            'statut' => $facture->getStatut(),
        ]));

        return $this->json([
            'facture_id' => $facture->getId(),
            'statut' => $facture->getStatut(),
            'total_paye' => $facture->getMontantPaye(),
            'reste_a_payer' => $facture->getResteAPayer(),
        ]);
    }

    #[Route('/fec', methods: ['GET'])]
    public function exportFec(Request $request): Response
    {
        $this->assertFacturationReadAccess();

        $year = (int) $request->query->get('year', date('Y'));
        $start = new \DateTime("$year-01-01");
        $end = new \DateTime("$year-12-31 23:59:59");

        $qb = $this->em->getRepository(Facture::class)->createQueryBuilder('f')
            ->where('f.dateCreation >= :start')
            ->andWhere('f.dateCreation <= :end')
            ->orderBy('f.dateCreation', 'ASC')
            ->setParameter('start', $start)
            ->setParameter('end', $end);

        if (!$this->isGranted('ROLE_SUPER_ADMIN')) {
            $qb->andWhere('f.atelierId = :atelierId')
                ->setParameter('atelierId', $this->resolveCurrentAtelierIdOrFail());
        }

        $factures = $qb->getQuery()->getResult();

        $output = fopen('php://temp', 'r+');
        // BOM UTF-8 for Excel
        fprintf($output, "\xEF\xBB\xBF");

        // Header
        fputcsv($output, [
            'JournalCode', 'JournalLib', 'EcritureNum', 'EcritureDate',
            'CompteNum', 'CompteLib', 'CompAuxNum', 'CompAuxLib',
            'PieceRef', 'PieceDate', 'EcritureLib', 'Debit', 'Credit',
            'EcritureLet', 'DateLet', 'ValidDate', 'Montantdevise', 'Idevise',
        ], ';');

        foreach ($factures as $facture) {
            /** @var Facture $facture */
            $date = $facture->getDateCreation()->format('Ymd');
            $pieceRef = $facture->getNumeroFacture();
            $clientName = trim(($facture->getSnapClientPrenom() ?? '') . ' ' . ($facture->getSnapClientNom() ?? ''));

            // HT
            fputcsv($output, [
                'VEN', 'Ventes', $pieceRef, $date,
                '706000', 'Prestations de services', '', $clientName,
                $pieceRef, $date, 'Facture ' . $pieceRef . ' — HT',
                str_replace('.', ',', (string) $facture->getTotalHt()), '0',
                '', '', $date, '', '',
            ], ';');

            // TVA
            if (bccomp($facture->getTotalTva(), '0', 2) > 0) {
                fputcsv($output, [
                    'VEN', 'Ventes', $pieceRef, $date,
                    '445710', 'TVA collectée', '', $clientName,
                    $pieceRef, $date, 'Facture ' . $pieceRef . ' — TVA',
                    str_replace('.', ',', (string) $facture->getTotalTva()), '0',
                    '', '', $date, '', '',
                ], ';');
            }

            // TTC (client)
            fputcsv($output, [
                'VEN', 'Ventes', $pieceRef, $date,
                '411000', 'Clients — ' . $clientName, '', $clientName,
                $pieceRef, $date, 'Facture ' . $pieceRef . ' — TTC',
                '0', str_replace('.', ',', (string) $facture->getTotalTtc()),
                '', '', $date, '', '',
            ], ';');
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        $response = new Response($csv);
        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="FEC-' . $year . '.csv"');

        return $response;
    }
}
