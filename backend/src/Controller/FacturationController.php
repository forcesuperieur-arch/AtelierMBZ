<?php
namespace App\Controller;

use App\Entity\Facture;
use App\Entity\LigneFacture;
use App\Entity\Paiement;
use App\Entity\RendezVous;
use App\Service\AuditService;
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
    private function buildRdvInvoicePreview(RendezVous $rdv, float $remisePourcent = 0.0): array
    {
        $baseHt = (float) ($rdv->getPrixEstime() ?? 0);

        if ($baseHt <= 0) {
            $minutes = max(30, (int) ($rdv->getTempsEstime() ?? 60));
            $baseHt = round(($minutes / 60) * 65, 2);
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
            'tva_mo_taux' => 20,
            'tva_pieces_taux' => 20,
            'lignes_mo' => [[
                'designation' => $designation,
                'label' => $designation,
                'montant_ht' => round($baseHt, 2),
            ]],
            'lignes_pieces' => [],
            'total_ht' => round($baseHt, 2),
        ];
    }

    public function __construct(
        private EntityManagerInterface $em,
        private PdfService $pdfService,
        private AuditService $audit,
        private SerializerInterface $serializer,
        private MailerInterface $mailer,
    ) {}

    /**
     * List all invoices.
     */
    #[Route('', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
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
        $rdv = $this->em->getRepository(RendezVous::class)->find($rdvId);
        if (!$rdv) {
            return $this->json(['error' => 'RDV not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->buildRdvInvoicePreview($rdv));
    }

    #[Route('/rendez-vous/{rdvId}/facturer', methods: ['POST'])]
    public function facturerRendezVous(int $rdvId, Request $request): JsonResponse
    {
        $rdv = $this->em->getRepository(RendezVous::class)->find($rdvId);
        if (!$rdv) {
            return $this->json(['error' => 'RDV not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $remisePourcent = max(0, min(100, (float) ($data['remise_pourcent'] ?? 0)));
        $preview = $this->buildRdvInvoicePreview($rdv, $remisePourcent);

        $moHt = array_reduce($preview['lignes_mo'], fn(float $sum, array $line) => $sum + (float) ($line['montant_ht'] ?? 0), 0.0);
        $piecesHt = array_reduce($preview['lignes_pieces'], fn(float $sum, array $line) => $sum + (float) ($line['montant_ht'] ?? 0), 0.0);
        $discountFactor = 1 - ($remisePourcent / 100);
        $moHtDiscounted = round($moHt * $discountFactor, 2);
        $piecesHtDiscounted = round($piecesHt * $discountFactor, 2);
        $totalHt = round($moHtDiscounted + $piecesHtDiscounted, 2);
        $tvaMo = round($moHtDiscounted * 0.2, 2);
        $tvaPieces = round($piecesHtDiscounted * 0.2, 2);
        $totalTva = round($tvaMo + $tvaPieces, 2);
        $totalTtc = round($totalHt + $totalTva, 2);

        $payload = [
            'total_mo_ht' => number_format($moHtDiscounted, 2, '.', ''),
            'total_pieces_ht' => number_format($piecesHtDiscounted, 2, '.', ''),
            'total_ht' => number_format($totalHt, 2, '.', ''),
            'total_ttc' => number_format($totalTtc, 2, '.', ''),
            'tva_mo' => number_format($tvaMo, 2, '.', ''),
            'tva_pieces' => number_format($tvaPieces, 2, '.', ''),
            'total_tva' => number_format($totalTva, 2, '.', ''),
            'temps_facture_minutes' => (int) ($rdv->getTempsEstime() ?? 60),
            'taux_horaire' => '65.00',
            'notes' => $data['notes'] ?? null,
            'lignes' => [[
                'type_ligne' => 'main_oeuvre',
                'designation' => $rdv->getTypeIntervention() ?: 'Intervention atelier',
                'quantite' => 1,
                'prix_unitaire_ht' => number_format($totalHt, 2, '.', ''),
                'taux_tva' => 20,
                'total_ligne_ht' => number_format($totalHt, 2, '.', ''),
                'total_ligne_ttc' => number_format($totalTtc, 2, '.', ''),
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
        $data = json_decode($request->getContent(), true) ?? [];

        $rdv = $this->em->getRepository(RendezVous::class)->find($rdvId);
        if (!$rdv) {
            return $this->json(['error' => 'RDV not found'], Response::HTTP_NOT_FOUND);
        }

        // Generate invoice number: FAC-YYYY-NNNN
        $year = date('Y');
        $count = $this->em->getRepository(Facture::class)->createQueryBuilder('f')
            ->select('COUNT(f.id)')
            ->where('f.numeroFacture LIKE :prefix')
            ->setParameter('prefix', "FAC-$year-%")
            ->getQuery()->getSingleScalarResult();

        $numero = sprintf('FAC-%s-%04d', $year, $count + 1);

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
        $facture->setTauxHoraire($data['taux_horaire'] ?? '65.00');
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

        // Check if fully paid
        $totalPaye = 0;
        foreach ($facture->getPaiements() as $p) {
            $totalPaye += (float) $p->getMontant();
        }
        $totalPaye += (float) $data['montant'];

        if ($totalPaye >= (float) $facture->getTotalTtc()) {
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

        return $this->json([
            'facture_id' => $facture->getId(),
            'statut' => $facture->getStatut(),
            'total_paye' => round($totalPaye, 2),
            'reste_a_payer' => round((float) $facture->getTotalTtc() - $totalPaye, 2),
        ]);
    }

    /**
     * Download invoice PDF.
     */
    #[Route('/{id}/pdf', methods: ['GET'])]
    public function downloadPdf(int $id): BinaryFileResponse|JsonResponse
    {
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
