<?php
namespace App\Controller;

use App\Entity\ConfigAtelier;
use App\Entity\Facture;
use App\Entity\LigneFacture;
use App\Entity\Paiement;
use App\Entity\RendezVous;
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
        $atelierId = $this->atelierResolver->getAtelierId();
        if (!$atelierId) {
            return null;
        }
        return $this->em->getRepository(ConfigAtelier::class)->findOneBy(['atelierId' => $atelierId]);
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
        $this->denyAccessUnlessGranted('ROLE_USER');

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
        $this->denyAccessUnlessGranted('ROLE_USER');

        $rdv = $this->em->getRepository(RendezVous::class)->find($rdvId);
        if (!$rdv) {
            return $this->json(['error' => 'RDV not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->buildRdvInvoicePreview($rdv));
    }

    #[Route('/rendez-vous/{rdvId}/facturer', methods: ['POST'])]
    public function facturerRendezVous(int $rdvId, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

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
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $data = json_decode($request->getContent(), true) ?? [];

        $rdv = $this->em->getRepository(RendezVous::class)->find($rdvId);
        if (!$rdv) {
            return $this->json(['error' => 'RDV not found'], Response::HTTP_NOT_FOUND);
        }

        // Generate invoice number via PostgreSQL sequence to avoid race conditions
        $conn = $this->em->getConnection();
        $year = date('Y');
        $seqName = 'facture_seq_' . $year;

        // Ensure sequence exists for this year
        try {
            $conn->executeStatement(sprintf('CREATE SEQUENCE IF NOT EXISTS %s START 1', $seqName));
        } catch (\Throwable) {
            // sequence already exists — ok
        }
        $nextVal = (int) $conn->fetchOne(sprintf("SELECT nextval('%s')", $seqName));
        $numero = sprintf('FAC-%s-%04d', $year, $nextVal);

        $facture = new Facture();
        $facture->setNumeroFacture($numero);
        $facture->setRendezVous($rdv);
        $facture->setClient($rdv->getClient());
        $facture->setVehicule($rdv->getVehicule());
        $facture->setAtelierId($rdv->getAtelierId());

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
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $facture = $this->em->getRepository(Facture::class)->find($id);
        if (!$facture) {
            return $this->json(['error' => 'Invoice not found'], Response::HTTP_NOT_FOUND);
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
            $facture->setStatut('payee');
        } else {
            $facture->setStatut('partiellement_payee');
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
        $this->denyAccessUnlessGranted('ROLE_USER');

        $facture = $this->em->getRepository(Facture::class)->find($id);
        if (!$facture) {
            return $this->json(['error' => 'Invoice not found'], Response::HTTP_NOT_FOUND);
        }

        $filePath = $this->pdfService->generateFacturePdf($facture);

        return $this->file($filePath, 'Facture-' . $facture->getNumeroFacture() . '.pdf');
    }

    /**
     * Send invoice by email to the client.
     */
    #[Route('/{id}/email', methods: ['POST'])]
    public function sendEmail(int $id): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $facture = $this->em->getRepository(Facture::class)->find($id);
        if (!$facture) {
            return $this->json(['error' => 'Invoice not found'], Response::HTTP_NOT_FOUND);
        }

        $client = $facture->getClient();
        if (!$client || !$client->getEmail()) {
            return $this->json(['error' => 'Aucune adresse email client'], Response::HTTP_BAD_REQUEST);
        }

        $filePath = $this->pdfService->generateFacturePdf($facture);

        $email = (new Email())
            ->from('noreply@atelier-moto.fr')
            ->to($client->getEmail())
            ->subject('Votre facture ' . $facture->getNumeroFacture() . ' — Atelier Moto')
            ->html(sprintf(
                '<p>Bonjour %s,</p><p>Veuillez trouver ci-joint votre facture <strong>%s</strong> d\'un montant de <strong>%s €</strong>.</p><p>Cordialement,<br>L\'équipe Atelier Moto</p>',
                htmlspecialchars($client->getPrenom() ?? ''),
                htmlspecialchars($facture->getNumeroFacture()),
                number_format((float) $facture->getTotalTtc(), 2, ',', ' ')
            ))
            ->attachFromPath($filePath);

        $this->mailer->send($email);

        $this->audit->log('email', 'facture', $facture->getId(), json_encode([
            'to' => $client->getEmail(),
            'numero' => $facture->getNumeroFacture(),
        ]));

        return $this->json(['success' => true, 'sent_to' => $client->getEmail()]);
    }
}
