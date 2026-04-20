<?php

namespace App\Controller;

use App\Entity\EssaiRoutier;
use App\Entity\RapportIntervention;
use App\Entity\RendezVous;
use App\Service\AuditService;
use App\Service\PdfService;
use App\Service\RapportInterventionService;
use Doctrine\ORM\EntityManagerInterface;
use Dompdf\Dompdf;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;

use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class RapportInterventionController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private RapportInterventionService $rapportService,
        private AuditService $audit,
        private Environment $twig,
        private PdfService $pdfService,
        private MailerInterface $mailer,
    ) {}

    #[Route('/api/rdv/{rdvId}/rapport', methods: ['GET'])]
    public function show(int $rdvId): JsonResponse
    {
        $rdv = $this->em->getRepository(RendezVous::class)->find($rdvId);
        if (!$rdv) {
            return $this->json(['error' => 'RDV not found'], Response::HTTP_NOT_FOUND);
        }

        $rapport = $this->em->getRepository(RapportIntervention::class)->findOneBy(
            ['rendezVous' => $rdv, 'statut' => ['brouillon', 'en_validation', 'signe']],
            ['id' => 'DESC'],
        );

        if (!$rapport) {
            return $this->json(['error' => 'Aucun rapport trouvé'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->serializeRapport($rapport));
    }

    #[Route('/api/rapport/{id}', methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $rapport = $this->em->getRepository(RapportIntervention::class)->find($id);
        if (!$rapport) {
            return $this->json(['error' => 'Rapport not found'], Response::HTTP_NOT_FOUND);
        }

        if ($rapport->isSigned()) {
            return $this->json(['error' => 'Rapport déjà signé, utilisez la rectification'], Response::HTTP_CONFLICT);
        }

        $data = json_decode($request->getContent(), true) ?? [];

        if (isset($data['travauxRealises'])) $rapport->setTravauxRealises($data['travauxRealises']);
        if (isset($data['alertes'])) $rapport->setAlertes($data['alertes']);
        if (isset($data['recommandations'])) $rapport->setRecommandations($data['recommandations']);
        if (isset($data['prochaineRevisionKm'])) $rapport->setProchaineRevisionKm($data['prochaineRevisionKm']);
        if (isset($data['prochaineRevisionDate'])) $rapport->setProchaineRevisionDate(new \DateTime($data['prochaineRevisionDate']));
        if (isset($data['kilometrageRestitution'])) $rapport->setKilometrageRestitution($data['kilometrageRestitution']);
        if (isset($data['garantie'])) $rapport->setGarantie($data['garantie']);

        $rapport->setUpdatedAt(new \DateTime());
        $this->em->flush();

        return $this->json($this->serializeRapport($rapport));
    }

    #[Route('/api/rapport/{id}/essai', methods: ['POST'])]
    public function saveEssai(int $id, Request $request): JsonResponse
    {
        $rapport = $this->em->getRepository(RapportIntervention::class)->find($id);
        if (!$rapport) {
            return $this->json(['error' => 'Rapport not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true) ?? [];

        $essai = $rapport->getEssaiRoutier();
        if (!$essai) {
            $essai = $this->em->getRepository(EssaiRoutier::class)->findOneBy(
                ['rendezVous' => $rapport->getRendezVous()],
                ['id' => 'DESC'],
            );
        }
        if (!$essai) {
            $essai = new EssaiRoutier();
            $essai->setRendezVous($rapport->getRendezVous());
            $essai->setAtelierId($rapport->getAtelierId());
            $essai->setKmDebut($rapport->getRendezVous()->getKilometrage());
            $this->em->persist($essai);
        }

        if (isset($data['kmDebut'])) $essai->setKmDebut($data['kmDebut']);
        if (isset($data['kmFin'])) $essai->setKmFin($data['kmFin']);
        if (isset($data['dureeMinutes'])) $essai->setDureeMinutes($data['dureeMinutes']);
        if (isset($data['pointsControle'])) $essai->setPointsControle($data['pointsControle']);
        if (isset($data['checkpoints'])) $essai->setCheckpoints($data['checkpoints']);
        if (isset($data['anomalies'])) $essai->setAnomalies($data['anomalies']);
        if (isset($data['observations'])) $essai->setObservations($data['observations']);
        if (isset($data['actionsCorrectives'])) $essai->setActionsCorrectives($data['actionsCorrectives']);
        if (isset($data['signatureMecanicien'])) $essai->setSignatureMecanicien($data['signatureMecanicien']);
        if (isset($data['statut'])) $essai->setStatut((string) $data['statut']);

        if ($essai->getKmDebut() && $essai->getKmFin()) {
            $essai->setDistance((string)($essai->getKmFin() - $essai->getKmDebut()));
        }
        if (($data['valider'] ?? false) === true) {
            $essai->setStatut($essai->hasAnomalies() ? 'anomalie_detectee' : 'valide');
            $essai->setValidatedAt(new \DateTime());
        }
        $essai->setRealiseAt(new \DateTime());

        $rapport->setEssaiRoutier($essai);
        $rapport->setUpdatedAt(new \DateTime());
        $this->em->flush();

        return $this->json([
            'id' => $essai->getId(),
            'kmDebut' => $essai->getKmDebut(),
            'kmFin' => $essai->getKmFin(),
            'distance' => $essai->getDistance(),
            'dureeMinutes' => $essai->getDureeMinutes(),
            'pointsControle' => $essai->getPointsControle(),
            'checkpoints' => $essai->getCheckpoints(),
            'anomalies' => $essai->getAnomalies(),
            'observations' => $essai->getObservations(),
            'actionsCorrectives' => $essai->getActionsCorrectives(),
            'isComplete' => $essai->isComplete(),
            'statut' => $essai->getStatut(),
            'isValide' => $essai->isValide(),
        ]);
    }

    #[Route('/api/rapport/{id}/sign-mecanicien', methods: ['POST'])]
    public function signMecanicien(int $id, Request $request): JsonResponse
    {
        $rapport = $this->em->getRepository(RapportIntervention::class)->find($id);
        if (!$rapport) {
            return $this->json(['error' => 'Rapport not found'], Response::HTTP_NOT_FOUND);
        }

        $errors = $this->rapportService->validateCompleteness($rapport);
        if (!empty($errors)) {
            return $this->json(['error' => 'Rapport incomplet', 'validation_errors' => $errors], Response::HTTP_BAD_REQUEST);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $signature = $data['signature'] ?? null;
        if (!$signature) {
            return $this->json(['error' => 'Signature requise'], Response::HTTP_BAD_REQUEST);
        }

        $user = $this->getUser();
        $this->rapportService->signByMecanicien($rapport, $signature, $user?->getId() ?? 0);

        $this->audit->log('sign_rapport_mecanicien', 'rapport_intervention', $rapport->getId());

        return $this->json($this->serializeRapport($rapport));
    }

    #[Route('/api/rapport/{id}/sign-client', methods: ['POST'])]
    public function signClient(int $id, Request $request): JsonResponse
    {
        $rapport = $this->em->getRepository(RapportIntervention::class)->find($id);
        if (!$rapport) {
            return $this->json(['error' => 'Rapport not found'], Response::HTTP_NOT_FOUND);
        }

        if (!$rapport->getSignatureMecanicien()) {
            return $this->json(['error' => 'Le mécanicien doit signer en premier'], Response::HTTP_CONFLICT);
        }

        $errors = $this->rapportService->validateCompleteness($rapport);
        if (!empty($errors)) {
            return $this->json(['error' => 'Rapport incomplet', 'validation_errors' => $errors], Response::HTTP_BAD_REQUEST);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $signature = $data['signature'] ?? null;
        if (!$signature) {
            return $this->json(['error' => 'Signature requise'], Response::HTTP_BAD_REQUEST);
        }

        $this->rapportService->signByClient($rapport, $signature, $request->getClientIp() ?? '0.0.0.0');

        $this->audit->log('sign_rapport_client', 'rapport_intervention', $rapport->getId());

        return $this->json($this->serializeRapport($rapport));
    }

    #[Route('/api/rapport/{id}/pdf', methods: ['GET'])]
    public function pdf(int $id): Response
    {
        $rapport = $this->em->getRepository(RapportIntervention::class)->find($id);
        if (!$rapport) {
            return $this->json(['error' => 'Rapport not found'], Response::HTTP_NOT_FOUND);
        }

        $essai = $this->resolveEssai($rapport);
        $filePath = $this->pdfService->generateRapportPdf($rapport, $essai);

        $this->audit->log('export_rapport_pdf', 'rapport_intervention', $rapport->getId());

        return new Response(file_get_contents($filePath), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf('inline; filename="rapport-intervention-%d.pdf"', $rapport->getId()),
        ]);
    }

    #[Route('/api/rapport/{id}/send-email', methods: ['POST'])]
    public function sendEmail(int $id): JsonResponse
    {
        $rapport = $this->em->getRepository(RapportIntervention::class)->find($id);
        if (!$rapport) {
            return $this->json(['error' => 'Rapport not found'], Response::HTTP_NOT_FOUND);
        }

        $rdv = $rapport->getRendezVous();
        $client = $rdv?->getClient();
        $clientEmail = $client?->getEmail();

        if (!$clientEmail) {
            return $this->json(['error' => 'Le client n\'a pas d\'adresse email renseignée'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $pdfContent = $this->pdfService->generateRapportPdf($rapport);

        $email = (new Email())
            ->to($clientEmail)
            ->subject('Votre rapport d\'intervention — RDV #' . ($rdv?->getId() ?? ''))
            ->html('<p>Bonjour ' . htmlspecialchars($client?->getPrenom() ?? '') . ',</p><p>Veuillez trouver ci-joint le rapport d\'intervention de votre véhicule.</p><p>Merci de votre confiance.</p>')
            ->attach($pdfContent, 'rapport-intervention.pdf', 'application/pdf');

        try {
            $this->mailer->send($email);
        } catch (\Throwable $e) {
            return $this->json(['error' => 'Échec de l\'envoi : ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $rapport->setEmailSentAt(new \DateTimeImmutable());
        $this->em->flush();

        $this->audit->log('send_rapport_email', 'rapport_intervention', $rapport->getId(), json_encode([
            'client_email' => $clientEmail,
        ]));

        return $this->json($this->serializeRapport($rapport));
    }

    #[Route('/api/rapport/{id}/rectifier', methods: ['POST'])]
    public function rectifier(int $id, Request $request): JsonResponse
    {
        $rapport = $this->em->getRepository(RapportIntervention::class)->find($id);
        if (!$rapport) {
            return $this->json(['error' => 'Rapport not found'], Response::HTTP_NOT_FOUND);
        }

        if (!$rapport->isSigned()) {
            return $this->json(['error' => 'Seul un rapport signé peut être rectifié'], Response::HTTP_CONFLICT);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $motif = $data['motif'] ?? null;
        if (!$motif) {
            return $this->json(['error' => 'Motif de rectification obligatoire'], Response::HTTP_BAD_REQUEST);
        }

        $user = $this->getUser();
        $newRapport = $this->rapportService->rectify($rapport, $motif, $user?->getId() ?? 0);

        $this->audit->log('rectify_rapport', 'rapport_intervention', $rapport->getId(), json_encode([
            'new_rapport_id' => $newRapport->getId(),
            'motif' => $motif,
        ]));

        return $this->json($this->serializeRapport($newRapport), Response::HTTP_CREATED);
    }

    private function serializeRapport(RapportIntervention $r): array
    {
        $essai = $this->resolveEssai($r);
        return [
            'id' => $r->getId(),
            'rdv_id' => $r->getRendezVous()->getId(),
            'statut' => $r->getStatut(),
            'travauxRealises' => $r->getTravauxRealises(),
            'alertes' => $r->getAlertes(),
            'recommandations' => $r->getRecommandations(),
            'prochaineRevisionKm' => $r->getProchaineRevisionKm(),
            'prochaineRevisionDate' => $r->getProchaineRevisionDate()?->format('Y-m-d'),
            'kilometrageRestitution' => $r->getKilometrageRestitution(),
            'garantie' => $r->getGarantie(),
            'signatureMecanicien' => $r->getSignatureMecanicien() ? true : false,
            'signeMecanicienAt' => $r->getSigneMecanicienAt()?->format('c'),
            'signatureClient' => $r->getSignatureClient() ? true : false,
            'signeClientAt' => $r->getSigneClientAt()?->format('c'),
            'signedHash' => $r->getSignedHash(),
            'isSignedByBoth' => $r->isSignedByBoth(),
            'rectifiedFrom' => $r->getRectifiedFrom()?->getId(),
            'motifRectification' => $r->getMotifRectification(),
            'emailSentAt' => $r->getEmailSentAt()?->format('c'),
            'essaiRoutier' => $essai ? [
                'id' => $essai->getId(),
                'kmDebut' => $essai->getKmDebut(),
                'kmFin' => $essai->getKmFin(),
                'distance' => $essai->getDistance(),
                'dureeMinutes' => $essai->getDureeMinutes(),
                'pointsControle' => $essai->getPointsControle(),
                'checkpoints' => $essai->getCheckpoints(),
                'anomalies' => $essai->getAnomalies(),
                'observations' => $essai->getObservations(),
                'actionsCorrectives' => $essai->getActionsCorrectives(),
                'isComplete' => $essai->isComplete(),
                'statut' => $essai->getStatut(),
                'isValide' => $essai->isValide(),
            ] : null,
            'createdAt' => $r->getCreatedAt()->format('c'),
            'updatedAt' => $r->getUpdatedAt()?->format('c'),
        ];
    }

    private function resolveEssai(RapportIntervention $rapport): ?EssaiRoutier
    {
        return $rapport->getEssaiRoutier()
            ?? $this->em->getRepository(EssaiRoutier::class)->findOneBy(
                ['rendezVous' => $rapport->getRendezVous()],
                ['id' => 'DESC'],
            );
    }
}
