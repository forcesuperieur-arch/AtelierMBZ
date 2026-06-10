<?php

namespace App\Controller;

use App\Entity\EssaiRoutier;
use App\Entity\Mecanicien;
use App\Entity\OrdreReparation;
use App\Entity\RendezVous;
use App\Service\NotificationDispatcher;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/mecanicien')]
class MecanicienController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private \App\Service\AuditService $auditService,
        private \App\Service\MercureNotifier $mercureNotifier,
        private NotificationDispatcher $notificationDispatcher,
    ) {}

    private function getCurrentMecanicien(): ?Mecanicien
    {
        $user = $this->getUser();
        if (!$user || !method_exists($user, 'getId')) {
            return null;
        }
        return $this->em->getRepository(Mecanicien::class)->findOneBy(['userId' => $user->getId()]);
    }

    #[Route('/me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        $mecanicien = $this->getCurrentMecanicien();
        if (!$mecanicien) {
            return $this->json([
                'error' => 'Aucun profil mécanicien lié à ce compte.',
                'code' => 'MECANICIEN_NOT_LINKED',
                'hint' => 'Un administrateur doit lier ce compte à un mécanicien dans la gestion des utilisateurs.',
            ], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'id' => $mecanicien->getId(),
            'nom' => $mecanicien->getNom(),
            'prenom' => $mecanicien->getPrenom(),
            'specialites' => $mecanicien->getSpecialites(),
            'couleur' => $mecanicien->getCouleur(),
            'userId' => $mecanicien->getUserId(),
            'atelierId' => $mecanicien->getAtelierId(),
            'isActive' => $mecanicien->getIsActive(),
        ]);
    }

    #[Route('/me/absences', methods: ['GET'])]
    public function myAbsences(Request $request): JsonResponse
    {
        $mecanicien = $this->getCurrentMecanicien();
        if (!$mecanicien) {
            return $this->json(['error' => 'MECANICIEN_NOT_LINKED'], Response::HTTP_NOT_FOUND);
        }

        $from = new \DateTime($request->query->get('from', 'now'));
        $to   = new \DateTime($request->query->get('to', '+30 days'));

        $absences = $this->em->getRepository(\App\Entity\Absence::class)
            ->createQueryBuilder('a')
            ->where('a.mecanicien = :meca')
            ->andWhere('a.dateDebut <= :to')
            ->andWhere('a.dateFin >= :from')
            ->setParameter('meca', $mecanicien)
            ->setParameter('from', $from->format('Y-m-d'))
            ->setParameter('to', $to->format('Y-m-d'))
            ->getQuery()
            ->getResult();

        return $this->json(array_map(fn($a) => [
            'id'         => $a->getId(),
            'date_debut' => $a->getDateDebut()->format('Y-m-d'),
            'date_fin'   => $a->getDateFin()->format('Y-m-d'),
            'motif'      => $a->getMotif(),
            'notes'      => $a->getNotes(),
        ], $absences));
    }

    #[Route('/me/rdvs', methods: ['GET'])]
    public function myRdVs(Request $request): JsonResponse
    {
        $mecanicien = $this->getCurrentMecanicien();
        if (!$mecanicien) {
            return $this->json([
                'error' => 'Aucun profil mécanicien lié.',
                'code' => 'MECANICIEN_NOT_LINKED',
            ], Response::HTTP_NOT_FOUND);
        }

        $date = $request->query->get('date', date('Y-m-d'));
        try {
            $dateValue = new \DateTimeImmutable($date);
        } catch (\Throwable) {
            return $this->json(['error' => 'Date invalide'], Response::HTTP_BAD_REQUEST);
        }

        $rdvs = $this->em->getRepository(RendezVous::class)
            ->createQueryBuilder('r')
            ->where('r.mecanicien = :meca')
            ->andWhere('r.dateRdv = :date')
            ->setParameter('meca', $mecanicien)
            ->setParameter('date', $dateValue)
            ->orderBy('r.heureRdv', 'ASC')
            ->getQuery()
            ->getResult();

        $today = date('Y-m-d');
        $absenceToday = $this->em->getRepository(\App\Entity\Absence::class)
            ->createQueryBuilder('a')
            ->where('a.mecanicien = :meca')
            ->andWhere('a.dateDebut <= :today')
            ->andWhere('a.dateFin >= :today')
            ->setParameter('meca', $mecanicien)
            ->setParameter('today', $today)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $this->json([
            'absence_today' => $absenceToday ? [
                'id' => $absenceToday->getId(),
                'date_debut' => $absenceToday->getDateDebut()->format('Y-m-d'),
                'date_fin' => $absenceToday->getDateFin()->format('Y-m-d'),
                'motif' => $absenceToday->getMotif(),
                'notes' => $absenceToday->getNotes(),
            ] : null,
            'rdvs' => array_map(fn (RendezVous $rdv) => $this->flattenRdvForMecanicien($rdv), $rdvs),
        ]);
    }

    #[Route('/me/rapport/{orId}', methods: ['PATCH'])]
    public function saveRapport(int $orId, Request $request): JsonResponse
    {
        $mecanicien = $this->getCurrentMecanicien();
        if (!$mecanicien) {
            return $this->json(['error' => 'MECANICIEN_NOT_LINKED'], Response::HTTP_FORBIDDEN);
        }

        $ordre = $this->em->getRepository(OrdreReparation::class)->find($orId);
        if (!$ordre) {
            return $this->json(['error' => 'OR not found'], Response::HTTP_NOT_FOUND);
        }

        if ($ordre->getStatut() === 'termine') {
            return $this->json(['error' => 'Cet ordre de réparation est finalisé et ne peut plus être modifié.'], Response::HTTP_FORBIDDEN);
        }

        $rdv = $ordre->getRendezVous();
        if ($rdv->getMecanicien()?->getId() !== $mecanicien->getId()) {
            return $this->json(['error' => 'Non autorisé sur cet OR'], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true) ?? [];

        if (array_key_exists('mechanic_notes', $data)) {
            $ordre->setMechanicNotes($data['mechanic_notes']);
        }

        if (array_key_exists('mechanic_checkup', $data) && is_array($data['mechanic_checkup'])) {
            $ordre->setMechanicCheckup($data['mechanic_checkup']);
        }

        if (array_key_exists('travaux_realises', $data)) {
            $ordre->setTravauxRealises($data['travaux_realises']);
        }

        if (array_key_exists('alertes', $data)) {
            $ordre->setAlertes(is_array($data['alertes']) ? $data['alertes'] : null);
        }

        if (array_key_exists('recommandations', $data)) {
            $ordre->setRecommandations($data['recommandations']);
        }

        if (array_key_exists('garantie', $data)) {
            $ordre->setGarantie($data['garantie']);
        }

        if (array_key_exists('kilometrage_restitution', $data) && $data['kilometrage_restitution'] !== '') {
            $ordre->setKilometrageRestitution((int) $data['kilometrage_restitution']);
        }

        if (array_key_exists('prochaine_revision_km', $data) && $data['prochaine_revision_km'] !== '') {
            $ordre->setProchaineRevisionKm((int) $data['prochaine_revision_km']);
        }

        if (array_key_exists('prochaine_revision_date', $data) && $data['prochaine_revision_date']) {
            try {
                $ordre->setProchaineRevisionDate(new \DateTime($data['prochaine_revision_date']));
            } catch (\Throwable) {
                // ignore invalid date
            }
        }

        $this->syncLegacyWorkshopState($ordre);

        $this->em->flush();

        $this->auditService->log(
            'mecanicien_save_rapport',
            'OrdreReparation',
            $ordre->getId(),
            sprintf('Rapport OR #%d mis à jour par mécanicien #%d', $ordre->getId(), $mecanicien->getId()),
        );

        return $this->json([
            'id' => $ordre->getId(),
            'mechanic_notes' => $ordre->getMechanicNotes(),
            'mechanic_checkup' => $ordre->getMechanicCheckup(),
            'travaux_realises' => $ordre->getTravauxRealises(),
            'alertes' => $ordre->getAlertes(),
            'recommandations' => $ordre->getRecommandations(),
            'garantie' => $ordre->getGarantie(),
            'mechanic_notes_updated_at' => $ordre->getMechanicNotesUpdatedAt()?->format('Y-m-d H:i:s'),
            'mechanic_checkup_updated_at' => $ordre->getMechanicCheckupUpdatedAt()?->format('Y-m-d H:i:s'),
        ]);
    }

    #[Route('/me/sign/{orId}', methods: ['POST'])]
    public function signMecanicien(int $orId, Request $request): JsonResponse
    {
        $mecanicien = $this->getCurrentMecanicien();
        if (!$mecanicien) {
            return $this->json(['error' => 'MECANICIEN_NOT_LINKED'], Response::HTTP_FORBIDDEN);
        }

        $ordre = $this->em->getRepository(OrdreReparation::class)->find($orId);
        if (!$ordre) {
            return $this->json(['error' => 'OR not found'], Response::HTTP_NOT_FOUND);
        }

        $rdv = $ordre->getRendezVous();
        if ($rdv->getMecanicien()?->getId() !== $mecanicien->getId()) {
            return $this->json(['error' => 'Non autorisé sur cet OR'], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $signatureData = $data['signature'] ?? null;

        if (!$signatureData || !str_starts_with($signatureData, 'data:image/')) {
            return $this->json(['error' => 'Signature invalide'], Response::HTTP_BAD_REQUEST);
        }

        $policy = new \App\Service\OrdreReparationPolicy();
        if (!$policy->canSignMecanicien($ordre)) {
            return $this->json([
                'error' => 'Signature mécanicien impossible pour cet OR',
                'statut' => $ordre->getStatut(),
            ], Response::HTTP_CONFLICT);
        }

        $policy->signMecanicien($ordre, $signatureData, $this->getUser()?->getId());

        // Link essai routier to OR if exists
        $essai = $this->findLatestEssai($rdv);
        if ($essai) {
            $ordre->setEssaiRoutier($essai);
        }

        $this->em->flush();

        $this->auditService->log(
            'mecanicien_sign_or',
            'OrdreReparation',
            $ordre->getId(),
            sprintf('OR #%d signé par mécanicien #%d', $ordre->getId(), $mecanicien->getId()),
        );

        $client = $rdv->getClient();
        if ($client?->getTelephone()) {
            $vehicule = $rdv->getVehicule();
            $motoLabel = $vehicule
                ? trim(($vehicule->getMarque() ?? '') . ' ' . ($vehicule->getModele() ?? ''))
                : 'votre moto';
            $this->notificationDispatcher->sendFromTemplate(
                'travaux_termines',
                'sms',
                $rdv->getAtelierId(),
                $client->getTelephone(),
                [
                    'client_prenom' => $client->getPrenom(),
                    'moto'          => $motoLabel ?: 'votre moto',
                    'numero_or'     => $ordre->getNumeroOr(),
                ],
                'RendezVous',
                $rdv->getId(),
            );
        }

        return $this->json([
            'success' => true,
            'message' => 'Intervention signée',
            'statut' => $ordre->getStatut(),
        ]);
    }

    #[Route('/me/demande-complementaire', methods: ['POST'])]
    public function createDemandeComplementaire(Request $request): JsonResponse
    {
        $mecanicien = $this->getCurrentMecanicien();
        if (!$mecanicien) {
            return $this->json(['error' => 'MECANICIEN_NOT_LINKED'], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $rdvId = isset($data['rdv_id']) ? (int) $data['rdv_id'] : 0;
        if ($rdvId <= 0) {
            return $this->json(['error' => 'rdv_id requis'], Response::HTTP_BAD_REQUEST);
        }

        $rdv = $this->em->getRepository(RendezVous::class)->find($rdvId);
        if (!$rdv) {
            return $this->json(['error' => 'RDV not found'], Response::HTTP_NOT_FOUND);
        }

        if ($rdv->getMecanicien()?->getId() !== $mecanicien->getId()) {
            return $this->json(['error' => 'Non autorisé sur ce RDV'], Response::HTTP_FORBIDDEN);
        }

        $description = isset($data['description']) ? trim((string) $data['description']) : '';
        if ($description === '') {
            return $this->json(['error' => 'Description requise'], Response::HTTP_BAD_REQUEST);
        }

        $demande = new \App\Entity\DemandeTravauxSupp();
        $demande->setRendezVous($rdv);
        $demande->setDescription($description);
        $demande->setStatut(\App\Entity\DemandeTravauxSupp::STATUT_EN_ATTENTE_VALIDATION);

        if (isset($data['prix_estime']) && is_numeric($data['prix_estime'])) {
            $demande->setPrixEstime(number_format((float) $data['prix_estime'], 2, '.', ''));
        }
        if (isset($data['temps_estime']) && is_numeric($data['temps_estime'])) {
            $demande->setTempsEstime((int) $data['temps_estime']);
        }
        if (isset($data['urgence']) && in_array((string) $data['urgence'], ['normal', 'urgent'], true)) {
            $demande->setUrgence((string) $data['urgence']);
        }

        $this->em->persist($demande);

        // Notification
        $client = $rdv->getClient();
        $clientNom = $client ? ($client->getPrenom() . ' ' . $client->getNom()) : 'Client';
        $vehicule = $rdv->getVehicule();
        $vehiculePlaque = $vehicule?->getPlaque() ?? '';

        $notif = new \App\Entity\Notification();
        $notif->setAtelierId($rdv->getAtelierId());
        $notif->setType('demande_complementaire');
        $notif->setSeverity($demande->getUrgence() === 'urgent' ? 'critical' : 'warning');
        $notif->setTitle('Demande travaux complémentaires');
        $notif->setMessage(sprintf(
            'Nouvelle demande travaux supplémentaires — %s (%s) — %s€ TTC',
            $clientNom,
            $vehiculePlaque,
            $demande->getPrixEstime() ?? '0.00',
        ));
        $notif->setRelatedEntityType('DemandeTravauxSupp');
        $notif->setTargetRoles(['ROLE_ADMIN', 'ROLE_RECEPTIONNAIRE']);
        $notif->setTargetRole('ROLE_RECEPTIONNAIRE');
        $notif->setPriority($demande->getUrgence() === 'urgent' ? 'high' : 'normal');
        $this->em->persist($notif);

        $this->em->flush();

        $notif->setRelatedEntityId($demande->getId());
        $this->em->flush();

        // Mercure push
        try {
            $this->mercureNotifier->publishToAtelier($rdv->getAtelierId(), $notif);
        } catch (\Throwable $e) {
            // Mercure failure is non-blocking
        }

        return $this->json([
            'id' => $demande->getId(),
            'statut' => $demande->getStatut(),
            'token' => $demande->getTokenValidation(),
        ], Response::HTTP_CREATED);
    }

    #[Route('/me/essai-routier', methods: ['POST'])]
    public function createEssai(Request $request): JsonResponse
    {
        $mecanicien = $this->getCurrentMecanicien();
        if (!$mecanicien) {
            return $this->json(['error' => 'MECANICIEN_NOT_LINKED'], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $rdvId = isset($data['rdv_id']) ? (int) $data['rdv_id'] : 0;
        if ($rdvId <= 0) {
            return $this->json(['error' => 'rdv_id requis'], Response::HTTP_BAD_REQUEST);
        }

        $rdv = $this->em->getRepository(RendezVous::class)->find($rdvId);
        if (!$rdv) {
            return $this->json(['error' => 'RDV not found'], Response::HTTP_NOT_FOUND);
        }

        if ($rdv->getMecanicien()?->getId() !== $mecanicien->getId()) {
            return $this->json(['error' => 'Non autorisé sur ce RDV'], Response::HTTP_FORBIDDEN);
        }

        if (!in_array($rdv->getStatut(), ['en_cours', 'en_pause', 'termine'], true)) {
            return $this->json([
                'error' => sprintf("Essai routier impossible depuis le statut '%s'", $rdv->getStatut()),
            ], Response::HTTP_CONFLICT);
        }

        $essai = $this->findLatestEssai($rdv);
        if (!$essai) {
            $essai = new EssaiRoutier();
            $essai->setRendezVous($rdv);
            $essai->setAtelierId($rdv->getAtelierId());
            $essai->setKmDebut($rdv->getKilometrage());
            $essai->setMecanicienId($this->getUser()?->getId());
            $this->em->persist($essai);
        }

        if ($essai->getMecanicienId() === null) {
            $essai->setMecanicienId($this->getUser()?->getId());
        }

        if (isset($data['km_debut'])) {
            $essai->setKmDebut((int) $data['km_debut']);
        }
        if (isset($data['km_fin'])) {
            $essai->setKmFin((int) $data['km_fin']);
        }
        if (isset($data['dureeMinutes'])) {
            $essai->setDureeMinutes((int) $data['dureeMinutes']);
        }
        if (isset($data['duree_minutes'])) {
            $essai->setDureeMinutes((int) $data['duree_minutes']);
        }

        if (isset($data['checkpoints']) && is_array($data['checkpoints'])) {
            $essai->setCheckpoints($data['checkpoints']);
        } elseif (isset($data['pointsControle']) && is_array($data['pointsControle'])) {
            $essai->setPointsControle($data['pointsControle']);
        }

        if (isset($data['observations'])) {
            $essai->setObservations((string) $data['observations']);
        }
        if (isset($data['anomalies'])) {
            $essai->setAnomalies((string) $data['anomalies']);
        }

        if (isset($data['actions_correctives'])) {
            $essai->setActionsCorrectives((string) $data['actions_correctives']);
        }
        if (isset($data['actionsCorrectives'])) {
            $essai->setActionsCorrectives((string) $data['actionsCorrectives']);
        }

        if ($essai->getKmDebut() !== null && $essai->getKmFin() !== null) {
            $essai->setDistance((string) ($essai->getKmFin() - $essai->getKmDebut()));
        }
        $essai->setRealiseAt(new \DateTime());

        if (($data['valider'] ?? false) === true) {
            if ($essai->getKmDebut() === null) {
                return $this->json(['error' => 'km_debut obligatoire pour valider'], Response::HTTP_BAD_REQUEST);
            }

            if ($essai->getKmFin() === null) {
                return $this->json(['error' => 'km_fin obligatoire pour valider'], Response::HTTP_BAD_REQUEST);
            }

            if ($essai->getKmFin() <= $essai->getKmDebut()) {
                return $this->json(['error' => 'km_fin doit être supérieur à km_debut'], Response::HTTP_BAD_REQUEST);
            }

            if (($essai->getDureeMinutes() ?? 0) <= 0) {
                $computedDuration = max(5, (int) ceil(max(0, ($essai->getKmFin() ?? 0) - ($essai->getKmDebut() ?? 0)) * 2));
                $essai->setDureeMinutes($computedDuration > 0 ? $computedDuration : 10);
            }

            $checkpoints = $essai->getCheckpoints();
            $doneCount = count(array_filter($checkpoints, fn (array $checkpoint) => $this->checkpointStatus($checkpoint) !== null));
            if ($doneCount < 5) {
                return $this->json([
                    'error' => 'Au moins 5 checkpoints doivent être renseignés pour valider.',
                ], Response::HTTP_BAD_REQUEST);
            }

            $hasAnomalie = !empty(array_filter($checkpoints, fn (array $checkpoint) => $this->checkpointStatus($checkpoint) === 'nok'));
            $essai->setStatut($hasAnomalie ? 'anomalie_detectee' : 'valide');
            $essai->setValidatedAt(new \DateTime());
        }

        $this->em->flush();

        $this->auditService->log(
            'mecanicien_create_essai',
            'EssaiRoutier',
            $essai->getId(),
            sprintf('Essai routier #%d RDV #%d — statut: %s', $essai->getId(), $rdv->getId(), $essai->getStatut()),
        );

        return $this->json([
            'id' => $essai->getId(),
            'rdv_id' => $rdv->getId(),
            'km_debut' => $essai->getKmDebut(),
            'km_fin' => $essai->getKmFin(),
            'checkpoints' => $essai->getCheckpoints(),
            'observations' => $essai->getObservations(),
            'statut' => $essai->getStatut(),
            'valide' => $essai->isValide(),
            'validated_at' => $essai->getValidatedAt()?->format('Y-m-d H:i:s'),
        ]);
    }

    private function flattenRdvForMecanicien(RendezVous $rdv): array
    {
        $client = $rdv->getClient();
        $vehicule = $rdv->getVehicule();
        $pont = $rdv->getPont();
        $ordre = $this->findInitialOrdre($rdv);
        $essai = $this->findLatestEssai($rdv);

        return [
            'id' => $rdv->getId(),
            'date_rdv' => $rdv->getDateRdv()->format('Y-m-d'),
            'heure_debut' => $rdv->getHeureRdv()->format('H:i'),
            'heure_rdv' => $rdv->getHeureRdv()->format('H:i:s'),
            'type_intervention' => $rdv->getTypeIntervention(),
            'commentaire_client' => $rdv->getCommentaire(),
            'statut' => $rdv->getStatut(),
            'status' => $rdv->getStatut(),
            'temps_estime' => $rdv->getTempsEstime(),
            'temps_effectif_minutes' => $rdv->getTempsEffectifMinutes(),
            'heure_debut_travail' => $rdv->getHeureDebutTravail()?->format('Y-m-d H:i:s'),
            'heure_fin_travail' => $rdv->getHeureFinTravail()?->format('Y-m-d H:i:s'),
            'client_nom' => $client ? trim(($client->getPrenom() ?? '') . ' ' . ($client->getNom() ?? '')) : null,
            'client_telephone' => $client?->getTelephone(),
            'vehicule_info' => $vehicule ? trim(($vehicule->getMarque() ?? '') . ' ' . ($vehicule->getModele() ?? '')) : null,
            'vehicule_plaque' => $vehicule?->getPlaque(),
            'vehicule_type' => $vehicule?->getTypeMoto(),
            'km_reception' => $rdv->getKilometrage(),
            'pont_nom' => $pont?->getNom(),
            'or_id' => $ordre?->getId(),
            'or_signe' => $ordre?->isSigned(),
            'or_statut' => $ordre?->getStatut(),
            'or_mechanic_notes' => $ordre?->getMechanicNotes(),
            'or_mechanic_checkup' => $ordre?->getMechanicCheckup(),
            'or_travaux_realises' => $ordre?->getTravauxRealises(),
            'or_alertes' => $ordre?->getAlertes(),
            'or_recommandations' => $ordre?->getRecommandations(),
            'or_garantie' => $ordre?->getGarantie(),
            'or_kilometrage_restitution' => $ordre?->getKilometrageRestitution(),
            'or_prochaine_revision_km' => $ordre?->getProchaineRevisionKm(),
            'or_prochaine_revision_date' => $ordre?->getProchaineRevisionDate()?->format('Y-m-d'),
            'is_signed_by_both' => (
                $ordre?->getSignatureMecanicien() !== null &&
                $ordre?->getSignatureClientRestitution() !== null
            ),
            'etat_reception' => $this->buildReceptionState($ordre, $rdv),
            'essai_routier_id' => $essai?->getId(),
            'essai_routier_statut' => $essai?->getStatut(),
            'essai_routier_valide' => $essai?->isValide() ?? false,
            'token_suivi' => $rdv->getTokenSuivi(),
            'demandes_travaux_supp' => array_map(fn(\App\Entity\DemandeTravauxSupp $d) => [
                'id' => $d->getId(),
                'description' => $d->getDescription(),
                'urgence' => $d->getUrgence(),
                'prix_estime' => $d->getPrixEstime(),
                'temps_estime' => $d->getTempsEstime(),
                'statut' => $d->getStatut(),
                'created_at' => $d->getCreatedAt()->format('c'),
            ], $rdv->getDemandesTravauxSupp()->toArray()),
        ];
    }

    private function buildReceptionState(?OrdreReparation $ordre, RendezVous $rdv): ?array
    {
        $raw = $ordre?->getEtatVehicule() ?? $rdv->getEtatVehicule();
        if ($raw === null || trim($raw) === '') {
            return null;
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : null;
    }

    private function checkpointStatus(array $checkpoint): ?string
    {
        if (isset($checkpoint['statut']) && in_array($checkpoint['statut'], ['ok', 'nok', 'na'], true)) {
            return $checkpoint['statut'];
        }

        if (array_key_exists('ok', $checkpoint)) {
            return $checkpoint['ok'] === true ? 'ok' : ($checkpoint['ok'] === false ? 'nok' : null);
        }

        return null;
    }

    private function findInitialOrdre(RendezVous $rdv): ?OrdreReparation
    {
        return $this->em->getRepository(OrdreReparation::class)->findOneBy(
            ['rendezVous' => $rdv, 'typeOr' => 'initial'],
            ['id' => 'DESC'],
        ) ?? $this->em->getRepository(OrdreReparation::class)->findOneBy(
            ['rendezVous' => $rdv],
            ['id' => 'DESC'],
        );
    }

    private function findLatestEssai(RendezVous $rdv): ?EssaiRoutier
    {
        return $rdv->getEssaiRoutier()
            ?? $this->em->getRepository(EssaiRoutier::class)->findOneBy(
                ['rendezVous' => $rdv],
                ['id' => 'DESC'],
            );
    }

    private function syncLegacyWorkshopState(OrdreReparation $ordre): void
    {
        $decoded = [];
        $raw = $ordre->getEtatVehicule();
        if ($raw !== null && trim($raw) !== '') {
            $decoded = json_decode($raw, true);
            if (!is_array($decoded)) {
                $decoded = [];
            }
        }

        $decoded['mechanic_notes'] = $ordre->getMechanicNotes();
        $decoded['mechanic_checkup'] = $ordre->getMechanicCheckup();
        $decoded['last_mechanic_update_at'] = (new \DateTimeImmutable())->format('c');

        $ordre->setEtatVehicule(json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}