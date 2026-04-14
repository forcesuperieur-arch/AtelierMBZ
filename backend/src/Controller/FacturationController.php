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
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api/facturation')]
class FacturationController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private PdfService $pdfService,
        private AuditService $audit,
        private SerializerInterface $serializer,
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
}
