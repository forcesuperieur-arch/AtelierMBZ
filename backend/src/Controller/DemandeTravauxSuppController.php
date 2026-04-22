<?php

namespace App\Controller;

use App\Entity\AuditLog;
use App\Entity\DemandeTravauxSupp;
use App\Entity\Notification;
use App\Entity\NotificationEscalation;
use App\Entity\OrdreReparation;
use App\Entity\Prestation;
use App\Entity\RendezVous;
use App\Entity\User;
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
use Symfony\Component\RateLimiter\RateLimiterFactory;

class DemandeTravauxSuppController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private PrestationCatalogService $catalogService,
        private OrdreReparationPolicy $orPolicy,
        private MercureNotifier $mercureNotifier,
        private RateLimiterFactory $publicDemandeLimiter,
    ) {}

    private function ensurePublicRateLimit(Request $request): ?JsonResponse
    {
        $limiter = $this->publicDemandeLimiter->create((string) $request->getClientIp());
        if ($limiter->consume()->isAccepted()) {
            return null;
        }

        return $this->json(['error' => 'Trop de requêtes'], Response::HTTP_TOO_MANY_REQUESTS);
    }

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

        $orInitial = $this->findSignedInitialOrder($rdv);
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

        $demande = $this->createDemandeAndNotify(
            $rdv,
            $commentaire,
            $prestationsChoisies,
            $totalPrix,
            $totalTemps,
            $photosIds,
            sprintf('%s€ TTC', $totalPrix),
        );

        return $this->json([
            'id' => $demande->getId(),
            'token' => $demande->getTokenValidation(),
            'prestations' => $prestationsChoisies,
            'prix_total_ttc' => $totalPrix,
            'temps_total_minutes' => $totalTemps,
            'statut' => $demande->getStatut(),
        ], Response::HTTP_CREATED);
    }

    #[Route('/api/mecanicien/me/demandes-travaux-supp', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function createFromMecanicien(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Non authentifié'], Response::HTTP_UNAUTHORIZED);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $rdvId = (int) ($data['rdv_id'] ?? 0);
        if ($rdvId <= 0) {
            return $this->json(['error' => 'rdv_id requis'], Response::HTTP_BAD_REQUEST);
        }

        $rdv = $this->em->getRepository(RendezVous::class)->find($rdvId);
        if (!$rdv) {
            return $this->json(['error' => 'RDV introuvable'], Response::HTTP_NOT_FOUND);
        }

        $mecanicien = $rdv->getMecanicien();
        if (!$mecanicien || $mecanicien->getUserId() !== $user->getId()) {
            return $this->json(['error' => 'Non autorisé sur ce RDV'], Response::HTTP_FORBIDDEN);
        }

        $description = trim((string) ($data['description'] ?? ''));
        if (mb_strlen($description) < 10) {
            return $this->json(['error' => 'Description trop courte (min 10 caractères)'], Response::HTTP_BAD_REQUEST);
        }

        $urgence = (string) ($data['urgence'] ?? 'normal');
        if (!in_array($urgence, ['normal', 'urgent', 'critique'], true)) {
            return $this->json(['error' => 'Urgence invalide'], Response::HTTP_BAD_REQUEST);
        }

        $orInitial = $this->findSignedInitialOrder($rdv);
        if (!$orInitial || !$orInitial->isSigned()) {
            return $this->json(['error' => 'L\'OR initial doit être signé'], Response::HTTP_CONFLICT);
        }

        $photosIds = array_values(array_filter(array_map('intval', $data['photos_ids'] ?? []), static fn (int $id) => $id > 0));

        $demande = $this->createDemandeAndNotify(
            $rdv,
            $description,
            [],
            null,
            null,
            $photosIds,
            'sans chiffrage, validation réception requise',
            $urgence,
        );

        return $this->json($this->serializeDemande($demande), Response::HTTP_CREATED);
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
    public function envoyer(
        int $id,
        \App\Service\NotificationDispatcher $dispatcher,
        Request $request
    ): JsonResponse {
        $demande = $this->em->getRepository(DemandeTravauxSupp::class)->find($id);
        if (!$demande) {
            return $this->json(['error' => 'Demande non trouvée'], Response::HTTP_NOT_FOUND);
        }

        if (!in_array($demande->getStatut(), [DemandeTravauxSupp::STATUT_EN_ATTENTE, DemandeTravauxSupp::STATUT_EN_ATTENTE_VALIDATION])) {
            return $this->json(['error' => 'Demande déjà envoyée ou décidée'], Response::HTTP_CONFLICT);
        }

        $demande->setStatut(DemandeTravauxSupp::STATUT_EN_ATTENTE_DECISION_CLIENT);
        $this->em->flush();

        $rdv = $demande->getRendezVous();
        $client = $rdv?->getClient();
        $atelierId = $rdv?->getAtelierId() ?? 0;
        
        $companionUrl = rtrim($request->getSchemeAndHttpHost(), '/') . '/public/demande/' . $demande->getTokenValidation();
        $reference = 'OR-' . ($rdv?->getId() ?? 0) . '-SUPP';
        
        if ($client) {
            $variables = [
                'client_prenom' => $client->getPrenom() ?? 'Client',
                'reference' => $reference,
                'companion_url' => $companionUrl,
            ];

            if ($client->getTelephone()) {
                $dispatcher->sendFromTemplate(
                    'demande_complementaire',
                    'sms',
                    $atelierId,
                    $client->getTelephone(),
                    $variables,
                    'DemandeTravauxSupp',
                    $demande->getId()
                );
            }
            if ($client->getEmail()) {
                $dispatcher->sendFromTemplate(
                    'demande_complementaire',
                    'email',
                    $atelierId,
                    $client->getEmail(),
                    $variables,
                    'DemandeTravauxSupp',
                    $demande->getId()
                );
            }
        }

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
    public function consultationPublique(string $token, Request $request): JsonResponse
    {
        if ($response = $this->ensurePublicRateLimit($request)) {
            return $response;
        }

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
        if ($response = $this->ensurePublicRateLimit($request)) {
            return $response;
        }

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

    private function findSignedInitialOrder(RendezVous $rdv): ?OrdreReparation
    {
        return $this->em->getRepository(OrdreReparation::class)->findOneBy(
            ['rendezVous' => $rdv, 'typeOr' => 'initial'],
            ['id' => 'DESC'],
        );
    }

    private function createDemandeAndNotify(
        RendezVous $rdv,
        ?string $description,
        array $prestationsChoisies,
        ?string $prixEstime,
        ?int $tempsEstime,
        array $photosIds,
        string $messageTail,
        string $urgence = 'normal',
    ): DemandeTravauxSupp {
        $demande = new DemandeTravauxSupp();
        $demande->setRendezVous($rdv);
        $demande->setDescription($description);
        $demande->setPrestationsChoisies($prestationsChoisies);
        $demande->setPrestationsDemandees($description);
        $demande->setPrixEstime($prixEstime);
        $demande->setTempsEstime($tempsEstime);
        $demande->setUrgence($urgence);
        $demande->setStatut(DemandeTravauxSupp::STATUT_EN_ATTENTE_VALIDATION);

        if ($photosIds !== []) {
            $demande->setPhotosJustificatives(implode(',', $photosIds));
        }

        $this->em->persist($demande);

        $notif = $this->buildReceptionNotification($demande, $rdv, $messageTail);
        $this->em->persist($notif);
        $this->em->flush();

        $notif->setRelatedEntityId($demande->getId());
        $this->em->flush();

        $this->createEscalationSchedule($notif);

        try {
            $this->mercureNotifier->publishToAtelier($rdv->getAtelierId(), $notif);
        } catch (\Throwable) {
        }

        return $demande;
    }

    private function buildReceptionNotification(DemandeTravauxSupp $demande, RendezVous $rdv, string $messageTail): Notification
    {
        $client = $rdv->getClient();
        $vehicule = $rdv->getVehicule();
        $clientNom = $client ? ($client->getPrenom() . ' ' . $client->getNom()) : 'Client';
        $vehiculePlaque = $vehicule?->getPlaque() ?? '';

        $notif = new Notification();
        $notif->setAtelierId($rdv->getAtelierId());
        $notif->setType('demande_complementaire');
        $notif->setSeverity($demande->getUrgence() === 'urgent' || $demande->getUrgence() === 'critique' ? 'critical' : 'warning');
        $notif->setTitle('Demande travaux complémentaires');
        $notif->setMessage(sprintf(
            'Nouvelle demande travaux supplémentaires — %s (%s) — %s',
            $clientNom,
            $vehiculePlaque,
            $messageTail,
        ));
        $notif->setRelatedEntityType('DemandeTravauxSupp');
        $notif->setTargetRoles(['ROLE_ADMIN', 'ROLE_RECEPTIONNAIRE']);
        $notif->setTargetRole('ROLE_RECEPTIONNAIRE');
        $notif->setPriority($demande->getUrgence() === 'urgent' || $demande->getUrgence() === 'critique' ? 'high' : 'normal');

        return $notif;
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
