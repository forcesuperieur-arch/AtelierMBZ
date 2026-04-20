<?php

namespace App\Controller;

use App\Entity\AuditLog;
use App\Entity\DemandeTravauxSupp;
use App\Entity\Notification;
use App\Entity\NotificationEscalation;
use App\Entity\OrdreReparation;
use App\Entity\Prestation;
use App\Entity\RendezVous;
use App\Service\MercureNotifier;
use App\Service\OrdreReparationPolicy;
use App\Service\PrestationCatalogService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class DemandeTravauxSuppController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private PrestationCatalogService $catalogService,
        private OrdreReparationPolicy $orPolicy,
        private MercureNotifier $mercureNotifier,
    ) {}

    /**
     * Companion: mécanicien crée une demande complémentaire.
     * Prix/temps calculés depuis le catalogue, jamais fournis par le mécanicien.
     */
    #[Route('/api/companion/{token}/demande-complementaire', methods: ['POST'])]
    public function createFromCompanion(string $token, Request $request): JsonResponse
    {
        if (strlen($token) < 16) {
            return $this->json(['error' => 'Token invalide'], Response::HTTP_BAD_REQUEST);
        }

        $rdv = $this->em->getRepository(RendezVous::class)->findOneBy(['tokenSuivi' => $token]);
        if (!$rdv) {
            return $this->json(['error' => 'Lien invalide ou expiré'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $prestationIds = $data['prestations_ids'] ?? [];
        $commentaire = $data['commentaire'] ?? null;
        $photosIds = $data['photos_ids'] ?? [];

        if (empty($prestationIds)) {
            return $this->json(['error' => 'Au moins une prestation requise'], Response::HTTP_BAD_REQUEST);
        }

        // Check the initial OR exists and is signed/in execution
        $orInitial = $this->em->getRepository(OrdreReparation::class)->findOneBy(
            ['rendezVous' => $rdv, 'typeOr' => 'initial'],
            ['id' => 'DESC'],
        );
        if (!$orInitial || !$orInitial->isSigned()) {
            return $this->json(['error' => 'L\'OR initial doit être signé'], Response::HTTP_CONFLICT);
        }

        $vehicule = $rdv->getVehicule();

        // Build prestations choisies from catalog (prices are server-calculated)
        $prestationsChoisies = [];
        $totalPrix = '0.00';
        $totalTemps = 0;

        foreach ($prestationIds as $pid) {
            $prestation = $this->em->getRepository(Prestation::class)->find($pid);
            if (!$prestation || !$prestation->getIsActive()) {
                continue;
            }

            $pricing = $vehicule
                ? $this->catalogService->calculatePrice($prestation, $vehicule, $rdv->getAtelierId())
                : [
                    'prix_ht' => $prestation->getPrixBaseHt(),
                    'prix_ttc' => $prestation->getPrixBaseTtc(),
                    'temps_minutes' => $prestation->getTempsEstimeMinutes(),
                    'mode' => $prestation->getModeTarification()->value,
                ];

            $prestationsChoisies[] = [
                'prestation_id' => $prestation->getId(),
                'designation' => $prestation->getLibelle(),
                'prix_ht' => $pricing['prix_ht'],
                'prix_ttc' => $pricing['prix_ttc'],
                'temps_minutes' => $pricing['temps_minutes'],
                'from_catalog' => true,
            ];

            $totalPrix = bcadd($totalPrix, $pricing['prix_ttc'], 2);
            $totalTemps += $pricing['temps_minutes'];
        }

        if (empty($prestationsChoisies)) {
            return $this->json(['error' => 'Aucune prestation valide trouvée'], Response::HTTP_BAD_REQUEST);
        }

        $demande = new DemandeTravauxSupp();
        $demande->setRendezVous($rdv);
        $demande->setDescription($commentaire);
        $demande->setPrestationsChoisies($prestationsChoisies);
        $demande->setPrixEstime($totalPrix);
        $demande->setTempsEstime($totalTemps);
        $demande->setStatut(DemandeTravauxSupp::STATUT_EN_ATTENTE_VALIDATION);

        if (!empty($photosIds)) {
            $demande->setPhotosJustificatives(implode(',', array_map('intval', $photosIds)));
        }

        $this->em->persist($demande);

        // 4.5 — Create notification for réceptionniste + admin
        $client = $rdv->getClient();
        $clientNom = $client ? ($client->getPrenom() . ' ' . $client->getNom()) : 'Client';
        $vehiculePlaque = $vehicule?->getPlaque() ?? '';

        $notif = new Notification();
        $notif->setAtelierId($rdv->getAtelierId());
        $notif->setType('demande_complementaire');
        $notif->setSeverity($demande->getUrgence() === 'urgent' ? 'critical' : 'warning');
        $notif->setTitle('Demande travaux complémentaires');
        $notif->setMessage(sprintf(
            'Nouvelle demande travaux supplémentaires — %s (%s) — %s€ TTC',
            $clientNom,
            $vehiculePlaque,
            $totalPrix,
        ));
        $notif->setRelatedEntityType('DemandeTravauxSupp');
        $notif->setTargetRoles(['ROLE_ADMIN', 'ROLE_RECEPTIONNAIRE']);
        $notif->setTargetRole('ROLE_RECEPTIONNAIRE');
        $notif->setPriority($demande->getUrgence() === 'urgent' ? 'high' : 'normal');
        $this->em->persist($notif);

        $this->em->flush();

        // Update notification entity ID after flush (demande now has its ID)
        $notif->setRelatedEntityId($demande->getId());
        $this->em->flush();

        // Create escalation schedule for demande_complementaire
        $this->createEscalationSchedule($notif);

        // Publish Mercure real-time push
        try {
            $this->mercureNotifier->publishToAtelier($rdv->getAtelierId(), $notif);
        } catch (\Throwable $e) {
            // Mercure failure is non-blocking
        }

        return $this->json([
            'id' => $demande->getId(),
            'token' => $demande->getTokenValidation(),
            'prestations' => $prestationsChoisies,
            'prix_total_ttc' => $totalPrix,
            'temps_total_minutes' => $totalTemps,
            'statut' => $demande->getStatut(),
        ], Response::HTTP_CREATED);
    }

    /**
     * Admin/réceptionniste: liste des demandes en attente.
     */
    #[Route('/api/demandes-travaux-supp', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function list(Request $request): JsonResponse
    {
        $qb = $this->em->getRepository(DemandeTravauxSupp::class)->createQueryBuilder('d')
            ->orderBy('d.createdAt', 'DESC');

        $statut = $request->query->get('statut');
        if ($statut) {
            $qb->andWhere('d.statut = :statut')->setParameter('statut', $statut);
        }

        $demandes = $qb->getQuery()->getResult();

        $items = array_map(fn(DemandeTravauxSupp $d) => $this->serializeDemande($d), $demandes);

        return $this->json($items);
    }

    /**
     * Réceptionniste envoie la demande au client (change statut → en_attente_decision_client).
     */
    #[Route('/api/demandes-travaux-supp/{id}/envoyer', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function envoyer(int $id): JsonResponse
    {
        $demande = $this->em->getRepository(DemandeTravauxSupp::class)->find($id);
        if (!$demande) {
            return $this->json(['error' => 'Demande non trouvée'], Response::HTTP_NOT_FOUND);
        }

        if (!in_array($demande->getStatut(), [DemandeTravauxSupp::STATUT_EN_ATTENTE, DemandeTravauxSupp::STATUT_EN_ATTENTE_VALIDATION])) {
            return $this->json(['error' => 'Demande déjà envoyée ou décidée'], Response::HTTP_CONFLICT);
        }

        $demande->setStatut(DemandeTravauxSupp::STATUT_EN_ATTENTE_DECISION_CLIENT);
        $this->em->flush();

        return $this->json([
            'id' => $demande->getId(),
            'statut' => $demande->getStatut(),
            'token' => $demande->getTokenValidation(),
            'lien_client' => '/public/demande/' . $demande->getTokenValidation(),
        ]);
    }

    /**
     * Endpoint public: client consulte la demande via token.
     */
    #[Route('/api/public/demandes-travaux-supp/{token}', methods: ['GET'])]
    public function consultationPublique(string $token): JsonResponse
    {
        $demande = $this->findByToken($token);
        if (!$demande) {
            return $this->json(['error' => 'Lien invalide ou expiré'], Response::HTTP_NOT_FOUND);
        }

        $rdv = $demande->getRendezVous();
        $vehicule = $rdv->getVehicule();
        $client = $rdv->getClient();

        return $this->json([
            'id' => $demande->getId(),
            'statut' => $demande->getStatut(),
            'description' => $demande->getDescription(),
            'urgence' => $demande->getUrgence(),
            'prestations' => $demande->getPrestationsChoisies(),
            'prix_estime' => $demande->getPrixEstime(),
            'temps_estime' => $demande->getTempsEstime(),
            'decision' => $demande->getDecisionClient(),
            'vehicule' => $vehicule ? [
                'plaque' => $vehicule->getPlaque(),
                'marque' => $vehicule->getMarque(),
                'modele' => $vehicule->getModele(),
            ] : null,
            'client_prenom' => $client?->getPrenom(),
            'created_at' => $demande->getCreatedAt()->format('c'),
        ]);
    }

    /**
     * Endpoint public: client accepte ou refuse la demande.
     * Si accepte → crée OR complémentaire signé + figé (4.4).
     */
    #[Route('/api/public/demandes-travaux-supp/{token}/decision', methods: ['POST'])]
    public function decisionPublique(string $token, Request $request): JsonResponse
    {
        $demande = $this->findByToken($token);
        if (!$demande) {
            return $this->json(['error' => 'Lien invalide ou expiré'], Response::HTTP_NOT_FOUND);
        }

        if ($demande->getStatut() !== DemandeTravauxSupp::STATUT_EN_ATTENTE_DECISION_CLIENT) {
            return $this->json(['error' => 'Décision déjà prise ou demande non envoyée'], Response::HTTP_CONFLICT);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $decision = $data['decision'] ?? null;

        if (!in_array($decision, [DemandeTravauxSupp::STATUT_ACCEPTE, DemandeTravauxSupp::STATUT_REFUSE], true)) {
            return $this->json(['error' => 'Décision invalide (accepte ou refuse)'], Response::HTTP_BAD_REQUEST);
        }

        $demande->setDecisionClient($decision);
        $demande->setDecisionClientAt(new \DateTime());
        $demande->setDecisionIp($request->getClientIp());
        $demande->setDecisionUserAgent(mb_substr($request->headers->get('User-Agent', ''), 0, 500));

        if ($decision === DemandeTravauxSupp::STATUT_ACCEPTE) {
            // Signature required for acceptance
            $signatureData = $data['signature'] ?? null;
            if (!$signatureData || !str_starts_with($signatureData, 'data:image/')) {
                return $this->json(['error' => 'Signature requise pour accepter'], Response::HTTP_BAD_REQUEST);
            }
            $demande->setSignatureClient($signatureData);
            $demande->setSignedAt(new \DateTime());
            $demande->setStatut(DemandeTravauxSupp::STATUT_ACCEPTE);

            // 4.4 — Auto-create OR complémentaire
            $or = $this->createOrComplementaire($demande, $signatureData, $request);
            $demande->setOrComplementaire($or);
        } else {
            $demande->setStatut(DemandeTravauxSupp::STATUT_REFUSE);
        }

        $this->em->flush();

        return $this->json([
            'id' => $demande->getId(),
            'decision' => $decision,
            'statut' => $demande->getStatut(),
            'or_complementaire_id' => $demande->getOrComplementaire()?->getId(),
        ]);
    }

    /**
     * 4.4 — Create a signed OR complémentaire from the accepted demande.
     */
    private function createOrComplementaire(DemandeTravauxSupp $demande, string $signatureData, Request $request): OrdreReparation
    {
        $rdv = $demande->getRendezVous();

        // Find the initial OR
        $orInitial = $this->em->getRepository(OrdreReparation::class)->findOneBy(
            ['rendezVous' => $rdv, 'typeOr' => 'initial'],
            ['id' => 'DESC'],
        );

        // Build travaux description from prestations choisies
        $travauxLines = array_map(
            fn(array $p) => sprintf('%s — %s€ TTC (%d min)', $p['designation'], $p['prix_ttc'], $p['temps_minutes']),
            $demande->getPrestationsChoisies(),
        );

        $or = new OrdreReparation();
        $or->setRendezVous($rdv);
        $or->setNumeroOr(($orInitial ? $orInitial->getNumeroOr() : 'OR-' . $rdv->getId() . '-' . date('Ymd')) . '-C' . $demande->getId());
        $or->setTypeOr('complementaire');
        $or->setTravaux(implode("\n", $travauxLines));
        $or->setDemandeTravauxSupp($demande);
        $or->setKilometrage($orInitial?->getKilometrage());
        $or->snapshotFromRdv();

        $this->em->persist($or);

        // Sign the OR immediately (client just signed)
        $this->orPolicy->sign($or, $signatureData, $request);

        return $or;
    }

    /**
     * 5.5 — Create escalation schedule for a demande_complementaire notification.
     * T+0: push web (ROLE_RECEPTIONNAIRE + ROLE_ADMIN)
     * T+5min: push web renforcé
     * T+10min: SMS ROLE_RESPONSABLE_ATELIER
     * T+30min: SMS ROLE_RESPONSABLE_MAGASIN
     */
    private function createEscalationSchedule(Notification $notif): void
    {
        $now = new \DateTimeImmutable();
        $escalations = [
            ['level' => 1, 'channel' => 'push', 'delay' => 0, 'target' => 'ROLE_RECEPTIONNAIRE,ROLE_ADMIN'],
            ['level' => 2, 'channel' => 'push', 'delay' => 5, 'target' => 'ROLE_RECEPTIONNAIRE,ROLE_ADMIN (renforcé)'],
            ['level' => 3, 'channel' => 'sms', 'delay' => 10, 'target' => 'ROLE_RESPONSABLE_ATELIER'],
            ['level' => 4, 'channel' => 'sms', 'delay' => 30, 'target' => 'ROLE_RESPONSABLE_MAGASIN'],
        ];

        foreach ($escalations as $esc) {
            $e = new NotificationEscalation();
            $e->setNotification($notif);
            $e->setLevel($esc['level']);
            $e->setChannel($esc['channel']);
            $e->setScheduledAt($now->modify("+{$esc['delay']} minutes"));
            $e->setTargetInfo($esc['target']);
            $this->em->persist($e);
        }

        $this->em->flush();
    }

    private function findByToken(string $token): ?DemandeTravauxSupp
    {
        if (strlen($token) < 32) {
            return null;
        }
        return $this->em->getRepository(DemandeTravauxSupp::class)->findOneBy(['tokenValidation' => $token]);
    }

    private function serializeDemande(DemandeTravauxSupp $d): array
    {
        $rdv = $d->getRendezVous();
        $vehicule = $rdv->getVehicule();
        $client = $rdv->getClient();

        return [
            'id' => $d->getId(),
            'rendez_vous_id' => $rdv->getId(),
            'client_nom' => $client ? ($client->getPrenom() . ' ' . $client->getNom()) : null,
            'vehicule_plaque' => $vehicule?->getPlaque(),
            'vehicule_info' => $vehicule ? trim(($vehicule->getMarque() ?? '') . ' ' . ($vehicule->getModele() ?? '')) : null,
            'description' => $d->getDescription(),
            'urgence' => $d->getUrgence(),
            'prestations' => $d->getPrestationsChoisies(),
            'prix_estime' => $d->getPrixEstime(),
            'temps_estime' => $d->getTempsEstime(),
            'statut' => $d->getStatut(),
            'decision_client' => $d->getDecisionClient(),
            'decision_client_at' => $d->getDecisionClientAt()?->format('c'),
            'or_complementaire_id' => $d->getOrComplementaire()?->getId(),
            'token' => $d->getTokenValidation(),
            'created_at' => $d->getCreatedAt()->format('c'),
        ];
    }
}
