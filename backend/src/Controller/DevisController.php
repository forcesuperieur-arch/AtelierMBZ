<?php
namespace App\Controller;

use App\Entity\Atelier;
use App\Entity\Client;
use App\Entity\ConfigAtelier;
use App\Entity\Devis;
use App\Entity\LigneDevis;
use App\Entity\RendezVous;
use App\Entity\Vehicule;
use App\Service\AuditService;
use App\Service\CurrentAtelierResolver;
use App\Service\PdfService;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/devis')]
#[IsGranted('ROLE_USER')]
class DevisController extends AbstractController
{
    private const TYPES_LIGNE = ['forfait_mo', 'main_oeuvre_libre', 'piece'];

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

    /** Accepte un id brut (5) ou une IRI ApiPlatform ("/api/clients/5"). */
    private function extractId(mixed $value): ?int
    {
        if (is_int($value)) return $value;
        if (is_string($value) && preg_match('#(\d+)$#', $value, $m)) return (int) $m[1];
        return null;
    }

    #[Route('', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        $clientId = $this->extractId($data['client'] ?? null);
        $client = $clientId ? $this->em->getRepository(Client::class)->find($clientId) : null;
        if (!$client) {
            return $this->json(['error' => 'Client requis'], Response::HTTP_BAD_REQUEST);
        }

        $lignesInput = array_values(array_filter(
            is_array($data['lignes'] ?? null) ? $data['lignes'] : [],
            fn ($l) => is_array($l) && trim((string) ($l['designation'] ?? '')) !== '',
        ));
        if (count($lignesInput) === 0) {
            return $this->json(['error' => 'Le devis doit contenir au moins une ligne'], Response::HTTP_BAD_REQUEST);
        }

        $devis = new Devis();
        $devis->setClient($client);

        $vehiculeId = $this->extractId($data['vehicule'] ?? null);
        if ($vehiculeId) {
            $vehicule = $this->em->getRepository(Vehicule::class)->find($vehiculeId);
            // Le véhicule doit appartenir au client choisi, sinon on l'ignore silencieusement
            // plutôt que de lier un devis à la moto d'un autre client.
            if ($vehicule && $vehicule->getClient()?->getId() === $client->getId()) {
                $devis->setVehicule($vehicule);
            }
        }

        if (isset($data['kilometrage']) && is_numeric($data['kilometrage'])) {
            $devis->setKilometrage((int) $data['kilometrage']);
        }
        if (isset($data['notes_client']) && is_string($data['notes_client'])) {
            $devis->setNotesClient($data['notes_client']);
        }

        $remisePourcentage = max(0.0, min(100.0, (float) ($data['remise_pourcentage'] ?? 0)));
        $devis->setRemisePourcentage(number_format($remisePourcentage, 2, '.', ''));

        if (isset($data['acompte_demande']) && is_numeric($data['acompte_demande'])) {
            $devis->setAcompteDemande(number_format(max(0.0, (float) $data['acompte_demande']), 2, '.', ''));
        }

        // Totaux calculés ICI, côté serveur, à partir des lignes — jamais depuis des montants
        // envoyés par le client de l'API (voir commentaire sur Devis::$totalHt et suivants).
        $totalMoHt = 0.0;
        $totalPiecesHt = 0.0;
        $totalHtBrut = 0.0;
        $totalTtcBrut = 0.0;
        $ordre = 0;

        foreach ($lignesInput as $l) {
            $type = in_array($l['type'] ?? null, self::TYPES_LIGNE, true) ? $l['type'] : 'piece';
            $quantite = max(1, (int) ($l['quantite'] ?? 1));
            $prixUnitaireHt = max(0.0, (float) ($l['prix_unitaire_ht'] ?? 0));
            $tauxTva = max(0.0, min(100.0, (float) ($l['taux_tva'] ?? 20)));

            $ligneHt = round($prixUnitaireHt * $quantite, 2);
            $ligneTtc = round($ligneHt * (1 + $tauxTva / 100), 2);

            $ligne = new LigneDevis();
            $ligne->setDevis($devis);
            $ligne->setTypeLigne($type);
            $ligne->setDesignation(trim((string) $l['designation']));
            $ligne->setDescriptionDetail(isset($l['description_detail']) && is_string($l['description_detail']) ? $l['description_detail'] : null);
            $ligne->setQuantite($quantite);
            $ligne->setPrixUnitaireHt(number_format($prixUnitaireHt, 2, '.', ''));
            $ligne->setTauxTva($tauxTva);
            $ligne->setTotalLigneHt(number_format($ligneHt, 2, '.', ''));
            $ligne->setTotalLigneTtc(number_format($ligneTtc, 2, '.', ''));
            $ligne->setOrdre($ordre++);
            $devis->getLignes()->add($ligne);
            $this->em->persist($ligne);

            if ($type === 'piece') {
                $totalPiecesHt += $ligneHt;
            } else {
                $totalMoHt += $ligneHt;
            }
            $totalHtBrut += $ligneHt;
            $totalTtcBrut += $ligneTtc;
        }

        $remiseMontantHt = round($totalHtBrut * $remisePourcentage / 100, 2);
        $remiseMontantTtc = round($totalTtcBrut * $remisePourcentage / 100, 2);

        $devis->setTotalMoHt(number_format($totalMoHt, 2, '.', ''));
        $devis->setTotalPiecesHt(number_format($totalPiecesHt, 2, '.', ''));
        $devis->setTotalHt(number_format($totalHtBrut - $remiseMontantHt, 2, '.', ''));
        $devis->setTotalTtc(number_format($totalTtcBrut - $remiseMontantTtc, 2, '.', ''));
        $devis->setRemiseMontant(number_format($remiseMontantHt, 2, '.', ''));

        $validiteJours = 30;
        $atelierId = $this->atelierResolver->resolveAtelierId();
        if ($atelierId) {
            $config = $this->em->getRepository(ConfigAtelier::class)->findOneBy(['atelierId' => $atelierId]);
            if ($config) {
                $validiteJours = $config->getValiditeDevisJours();
            }
        }
        $devis->setDateValidite((new \DateTime())->modify(sprintf('+%d days', $validiteJours)));

        $this->em->persist($devis);
        $this->em->flush();

        $this->audit->log('creer', 'devis', $devis->getId(), json_encode([
            'total_ttc' => $devis->getTotalTtc(),
            'nb_lignes' => count($lignesInput),
        ]));

        return $this->json(['id' => $devis->getId(), 'numero_devis' => $devis->getNumeroDevis()], Response::HTTP_CREATED);
    }

    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $devis = $this->em->getRepository(Devis::class)->find($id);
        if (!$devis) return $this->json(['error' => 'Devis introuvable'], Response::HTTP_NOT_FOUND);
        if (!in_array($devis->getStatut(), ['brouillon', 'envoye'], true)) {
            return $this->json(['error' => 'Seul un devis en brouillon ou envoyé peut être supprimé'], Response::HTTP_BAD_REQUEST);
        }

        $numero = $devis->getNumeroDevis();
        $this->em->remove($devis);
        $this->em->flush();

        $this->audit->log('supprimer', 'devis', $id, json_encode(['numero_devis' => $numero]));

        return $this->json(['statut' => 'supprime']);
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
        if ($devis->getLignes()->isEmpty()) {
            return $this->json(['error' => 'Impossible d\'envoyer un devis sans aucune ligne'], Response::HTTP_BAD_REQUEST);
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
        if ($devis->getLignes()->isEmpty()) {
            return $this->json(['error' => 'Impossible d\'envoyer un devis sans aucune ligne'], Response::HTTP_BAD_REQUEST);
        }

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
        // Le devis représente une décision du CLIENT (en ligne ou rapportée par le staff après
        // accord oral) : réservé à ROLE_ADMIN, comme envoyer/email/convertir, pour éviter qu'un
        // compte staff sans responsabilité ne l'acte à la place du client.
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

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
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

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

        $conn = $this->em->getConnection();
        $conn->beginTransaction();
        try {
            // Verrou pessimiste : deux appels concurrents sur le même devis accepté ne doivent
            // créer qu'UN seul RDV (sinon la seconde requête lit encore statut=accepte avant que
            // la première n'ait flush son changement).
            $devis = $this->em->find(Devis::class, $id, LockMode::PESSIMISTIC_WRITE);
            if (!$devis) {
                $conn->rollBack();
                return $this->json(['error' => 'Devis introuvable'], Response::HTTP_NOT_FOUND);
            }
            if ($devis->getStatut() !== 'accepte') {
                $conn->rollBack();
                return $this->json(['error' => 'Le devis doit être accepté pour être converti'], Response::HTTP_BAD_REQUEST);
            }

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
            $rdv->setOrigine('devis'); // KPI pilote : RDV issu d'une conversion de devis

            // Estimation grossière : 1h de main d'œuvre pour 65€ HT de MO. Un devis composé
            // uniquement de pièces (totalMoHt=0) plancherait sinon systématiquement à 30 min.
            $moHt = (float) $devis->getTotalMoHt();
            $tempsEstime = max(30, (int) round($moHt / 65 * 60));
            $rdv->setTempsEstime($tempsEstime);

            $this->em->persist($rdv);
            $devis->setStatut('converti');
            $this->em->flush();
            $conn->commit();
        } catch (\Throwable $e) {
            if ($conn->isTransactionActive()) {
                $conn->rollBack();
            }
            throw $e;
        }

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
