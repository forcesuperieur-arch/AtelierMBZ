<?php
namespace App\Controller;

use App\Entity\Atelier;
use App\Entity\Devis;
use App\Entity\RendezVous;
use App\Service\AuditService;
use App\Service\CurrentAtelierResolver;
use App\Service\PdfService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/devis')]
#[IsGranted('ROLE_USER')]
class DevisController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private AuditService $audit,
        private MailerInterface $mailer,
        private PdfService $pdfService,
        private CurrentAtelierResolver $atelierResolver,
    ) {}

    private function resolveAtelierBranding(): array
    {
        $atelierId = $this->atelierResolver->resolveAtelierId();
        $atelier = $atelierId ? $this->em->getRepository(Atelier::class)->find($atelierId) : null;
        return [
            'from' => $atelier?->getEmail() ?? 'noreply@paddock.fr',
            'nom' => $atelier?->getNom() ?? 'Paddock',
        ];
    }

    #[Route('/{id}/envoyer', methods: ['POST'])]
    public function envoyer(int $id): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $devis = $this->em->getRepository(Devis::class)->find($id);
        if (!$devis) return $this->json(['error' => 'Devis introuvable'], Response::HTTP_NOT_FOUND);
        if ($devis->getStatut() !== 'brouillon') {
            return $this->json(['error' => 'Le devis doit être en brouillon pour être envoyé'], Response::HTTP_BAD_REQUEST);
        }

        $devis->setStatut('envoye');
        $this->em->flush();

        // Send email if client has an email address
        $client = $devis->getClient();
        if ($client && $client->getEmail()) {
            try {
                $branding = $this->resolveAtelierBranding();
                $email = (new Email())
                    ->from($branding['from'])
                    ->to($client->getEmail())
                    ->subject('Devis ' . $devis->getNumeroDevis() . ' — ' . $branding['nom'])
                    ->html(sprintf(
                        '<p>Bonjour %s,</p><p>Veuillez trouver ci-joint votre devis <strong>%s</strong> d\'un montant de <strong>%s €</strong>.</p><p>Le devis est valable jusqu\'au %s.</p><p>Cordialement,<br>L\'équipe %s</p>',
                        htmlspecialchars($client->getPrenom() ?? ''),
                        htmlspecialchars($devis->getNumeroDevis()),
                        number_format((float) $devis->getTotalTtc(), 2, ',', ' '),
                        $devis->getDateValidite()->format('d/m/Y'),
                        htmlspecialchars($branding['nom']),
                    ));
                $this->mailer->send($email);
            } catch (\Exception $e) {
                // Log but don't fail - the status change is the critical part
            }
        }

        $this->audit->log('envoyer', 'devis', $devis->getId(), json_encode(['statut' => 'envoye']));

        return $this->json(['statut' => 'envoye']);
    }

    #[Route('/{id}/email', methods: ['POST'])]
    public function sendEmail(int $id): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $devis = $this->em->getRepository(Devis::class)->find($id);
        if (!$devis) return $this->json(['error' => 'Devis introuvable'], Response::HTTP_NOT_FOUND);

        $client = $devis->getClient();
        if (!$client || !$client->getEmail()) {
            return $this->json(['error' => 'Aucune adresse email client'], Response::HTTP_BAD_REQUEST);
        }

        $branding = $this->resolveAtelierBranding();
        $email = (new Email())
            ->from($branding['from'])
            ->to($client->getEmail())
            ->subject('Devis ' . $devis->getNumeroDevis() . ' — ' . $branding['nom'])
            ->html(sprintf(
                '<p>Bonjour %s,</p><p>Veuillez trouver ci-joint votre devis <strong>%s</strong> d\'un montant de <strong>%s €</strong>.</p><p>Le devis est valable jusqu\'au %s.</p><p>Cordialement,<br>L\'équipe %s</p>',
                htmlspecialchars($client->getPrenom() ?? ''),
                htmlspecialchars($devis->getNumeroDevis()),
                number_format((float) $devis->getTotalTtc(), 2, ',', ' '),
                $devis->getDateValidite()->format('d/m/Y'),
                htmlspecialchars($branding['nom']),
            ));

        $this->mailer->send($email);

        $this->audit->log('email', 'devis', $devis->getId(), json_encode(['to' => $client->getEmail()]));

        return $this->json(['success' => true, 'sent_to' => $client->getEmail()]);
    }

    #[Route('/{id}/accepter', methods: ['POST'])]
    public function accepter(int $id): JsonResponse
    {
        $devis = $this->em->getRepository(Devis::class)->find($id);
        if (!$devis) return $this->json(['error' => 'Devis introuvable'], Response::HTTP_NOT_FOUND);
        if ($devis->getStatut() !== 'envoye') {
            return $this->json(['error' => 'Le devis doit être envoyé pour être accepté'], Response::HTTP_BAD_REQUEST);
        }

        $devis->setStatut('accepte');
        $this->em->flush();

        $this->audit->log('accepter', 'devis', $devis->getId(), json_encode(['statut' => 'accepte']));

        return $this->json(['statut' => 'accepte']);
    }

    #[Route('/{id}/refuser', methods: ['POST'])]
    public function refuser(int $id): JsonResponse
    {
        $devis = $this->em->getRepository(Devis::class)->find($id);
        if (!$devis) return $this->json(['error' => 'Devis introuvable'], Response::HTTP_NOT_FOUND);
        if ($devis->getStatut() !== 'envoye') {
            return $this->json(['error' => 'Le devis doit être envoyé pour être refusé'], Response::HTTP_BAD_REQUEST);
        }

        $devis->setStatut('refuse');
        $this->em->flush();

        $this->audit->log('refuser', 'devis', $devis->getId(), json_encode(['statut' => 'refuse']));

        return $this->json(['statut' => 'refuse']);
    }

    #[Route('/{id}/convertir', methods: ['POST'])]
    public function convertir(int $id): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $devis = $this->em->getRepository(Devis::class)->find($id);
        if (!$devis) return $this->json(['error' => 'Devis introuvable'], Response::HTTP_NOT_FOUND);
        if ($devis->getStatut() !== 'accepte') {
            return $this->json(['error' => 'Le devis doit être accepté pour être converti'], Response::HTTP_BAD_REQUEST);
        }

        // Create RDV from devis
        $rdv = new RendezVous();
        $rdv->setClient($devis->getClient());
        if ($devis->getVehicule()) $rdv->setVehicule($devis->getVehicule());
        $rdv->setTypeIntervention('devis_' . $devis->getNumeroDevis());
        $rdv->setCommentaire('Converti depuis devis ' . $devis->getNumeroDevis());
        $rdv->setDateRdv(new \DateTime('+3 days'));
        $rdv->setHeureRdv(new \DateTime('09:00'));
        $rdv->setStatut('en_attente');
        $rdv->setPrixEstime($devis->getTotalTtc());
        $rdv->setAtelierId($devis->getAtelierId());

        // Estimate duration from line items (rough: 1h per 100€ MO)
        $moHt = (float) $devis->getTotalMoHt();
        $tempsEstime = max(30, (int) round($moHt / 65 * 60)); // 65€/h
        $rdv->setTempsEstime($tempsEstime);

        $this->em->persist($rdv);

        $devis->setStatut('converti');
        $this->em->flush();

        $this->audit->log('convertir', 'devis', $devis->getId(), json_encode([
            'rdv_id' => $rdv->getId(),
            'statut' => 'converti',
        ]));

        return $this->json([
            'statut' => 'converti',
            'rdv_id' => $rdv->getId(),
        ]);
    }

    #[Route('/{id}/pdf', methods: ['GET'])]
    public function pdf(int $id): BinaryFileResponse|JsonResponse
    {
        $devis = $this->em->getRepository(Devis::class)->find($id);
        if (!$devis) {
            return $this->json(['error' => 'Devis introuvable'], Response::HTTP_NOT_FOUND);
        }

        $filePath = $this->pdfService->generateDevisPdf($devis);

        return $this->file($filePath, 'Devis-' . $devis->getNumeroDevis() . '.pdf');
    }
}
