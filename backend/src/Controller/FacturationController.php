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
    private const TVA_RATE_FALLBACK = '20.00';
    private const TAUX_HORAIRE_FALLBACK = '65.00';

    private function getTvaRate(): string
    {
        $config = $this->getConfigAtelier();
        return $config ? (string) ($config->getTvaMoTaux() ?? self::TVA_RATE_FALLBACK) : self::TVA_RATE_FALLBACK;
    }

    private function getTauxHoraire(): string
    {
        $config = $this->getConfigAtelier();
        return $config ? ($config->getTauxHoraireMoStandard() ?? self::TAUX_HORAIRE_FALLBACK) : self::TAUX_HORAIRE_FALLBACK;
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

        $qb = $this->em->getRepository(Facture::class)->createQueryBuilder('f')
            ->orderBy('f.createdAt', 'DESC');

        if ($statut = $request->query->get('statut')) {
            $qb->andWhere('f.statut = :statut')->setParameter('statut', $statut);
        }

        $factures = $qb->getQuery()->getResult();
        $data = json_decode($this->serializer->serialize($factures, 'json', ['groups' => ['facture:read']]), true);
        return $this->json($data);
    }

    #[Route('/rendez-vous/{rdvId}/preview-facture', methods: ['GET'])]
    public function previewFacture(int $rdvId): JsonResponse
    {
        $this->assertFacturationReadAccess();

        $rdv = $this->em->getRepository(RendezVous::class)->find($rdvId);
        if (!$rdv) {
            return $this->json(['error' => 'RDV not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->buildRdvInvoicePreview($rdv));
    }

    #[Route('/rendez-vous/{rdvId}/facturer', methods: ['POST'])]
    public function facturerRendezVous(int $rdvId, Request $request): JsonResponse
    {
        $this->assertFacturationWriteAccess();

        $rdv = $this->em->getRepository(RendezVous::class)->find($rdvId);
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

        $facture = $this->em->getRepository(Facture::class)->find($id);
        if (!$facture) {
            return $this->json(['error' => 'Invoice not found'], Response::HTTP_NOT_FOUND);
        }

        if ($facture->isAvoir() || $facture->getStatut() === Facture::STATUS_CORRIGEE) {
            return $this->json(['error' => 'Cette facture ne peut pas recevoir d\'encaissement.'], Response::HTTP_CONFLICT);
        }

        $data = json_decode($request->getContent(), true);

        $paiement = new Paiement();
        $paiement->setFacture($facture);
        $paiement->setMontant($data['montant']);
        $paiement->setModePaiement($data['mode_paiement']);
        $paiement->setReference($data['reference'] ?? null);
        $paiement->setNotes($data['notes'] ?? null);

        if (isset($data['date_paiement'])) {
            $paiement->setDatePaiement(new \DateTime($data['date_paiement']));
        }

        $this->em->persist($paiement);

        // Check if fully paid — bcmath
        $totalPaye = '0';
        foreach ($facture->getPaiements() as $p) {
            $totalPaye = bcadd($totalPaye, (string) $p->getMontant(), 2);
        }
        $totalPaye = bcadd($totalPaye, (string) $data['montant'], 2);

        if (bccomp($totalPaye, (string) $facture->getTotalTtc(), 2) >= 0) {
            $facture->setStatut(Facture::STATUS_PAYEE);
        } else {
            $facture->setStatut(Facture::STATUS_PARTIELLEMENT_PAYEE);
        }

        $this->em->flush();

        $this->audit->log('payment', 'facture', $facture->getId(), json_encode([
            'montant' => $data['montant'],
            'mode' => $data['mode_paiement'],
            'statut' => $facture->getStatut(),
        ]));

        $resteAPayer = bcsub((string) $facture->getTotalTtc(), $totalPaye, 2);

        return $this->json([
            'facture_id' => $facture->getId(),
            'statut' => $facture->getStatut(),
            'total_paye' => $totalPaye,
            'reste_a_payer' => $resteAPayer,
        ]);
    }

    /**
     * Download invoice PDF.
     */
    #[Route('/{id}/pdf', methods: ['GET'])]
    public function downloadPdf(int $id): BinaryFileResponse|JsonResponse
    {
        $this->assertFacturationReadAccess();

        $facture = $this->em->getRepository(Facture::class)->find($id);
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

        $facture = $this->em->getRepository(Facture::class)->find($id);
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

        $facture = $this->em->getRepository(Facture::class)->find($id);
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
}
