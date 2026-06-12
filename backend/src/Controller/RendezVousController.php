<?php
namespace App\Controller;

use App\Entity\Client;
use App\Entity\DemandeTravauxSupp;
use App\Entity\EssaiRoutier;
use App\Entity\Mecanicien;
use App\Entity\OrdreReparation;
use App\Entity\Pont;
use App\Entity\RdvCommande;
use App\Entity\RendezVous;
use App\Entity\Vehicule;
use App\Service\AuditService;
use App\Service\OrdreReparationPolicy;
use App\Service\PhotoService;
use App\Service\RendezVousWorkflowService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Workflow\WorkflowInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class RendezVousController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private WorkflowInterface $rendezVousStateMachine,
        private AuditService $audit,
        private SerializerInterface $serializer,
        private RendezVousWorkflowService $workflowService,
        private PhotoService $photoService,
        private OrdreReparationPolicy $ordreReparationPolicy,
    ) {}

    /**
     * Create a RDV from the frontend form (handles client/vehicule creation).
     */
    #[Route('/api/rendez-vous', methods: ['POST'], priority: 10)]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        // Find or create client
        $client = null;
        if (!empty($data['client_id'])) {
            $client = $this->em->getRepository(Client::class)->find($data['client_id']);
        }
        if (!$client && !empty($data['client_nom'])) {
            $client = new Client();
            $client->setNom($data['client_nom']);
            $client->setPrenom($data['client_prenom'] ?? '');
            $client->setTelephone($data['client_telephone'] ?? '');
            $client->setEmail($data['client_email'] ?? null);
            $this->em->persist($client);
        }
        if (!$client) {
            return $this->json(['error' => 'Client information required'], Response::HTTP_BAD_REQUEST);
        }

        // Find or create vehicule — la fiche n'est réutilisée (et complétée)
        // que si elle appartient au même client : sinon on créerait un RDV sur
        // la moto d'un autre propriétaire et on écraserait sa fiche.
        $vehicule = null;
        if (!empty($data['vehicule_plaque'])) {
            $vehicule = $this->em->getRepository(Vehicule::class)->findOneBy(['plaque' => $data['vehicule_plaque']]);
            if ($vehicule && $vehicule->getClient()?->getTelephone() !== $client->getTelephone()) {
                $vehicule = null;
            }
            if (!$vehicule) {
                $vehicule = new Vehicule();
                $vehicule->setPlaque($data['vehicule_plaque']);
                $vehicule->setClient($client);
            }
            if (!empty($data['vehicule_marque'])) $vehicule->setMarque($data['vehicule_marque']);
            if (!empty($data['vehicule_modele'])) $vehicule->setModele($data['vehicule_modele']);
            if (!empty($data['vehicule_annee'])) $vehicule->setAnnee((int) $data['vehicule_annee']);
            if (!empty($data['vehicule_cylindree'])) $vehicule->setCylindree((string) $data['vehicule_cylindree']);
            if (!empty($data['vehicule_type'])) $vehicule->setTypeMoto((string) $data['vehicule_type']);
            $this->em->persist($vehicule);
        }

        $rdv = new RendezVous();
        $rdv->setClient($client);
        if ($vehicule) $rdv->setVehicule($vehicule);
        $rdv->setDateRdv(new \DateTime($data['date_rdv'] ?? 'today'));
        $rdv->setHeureRdv(new \DateTime($data['heure_debut'] ?? $data['heure_rdv'] ?? '09:00'));
        $rdv->setTypeIntervention($data['type_intervention'] ?? 'entretien');
        $rdv->setCommentaire($data['description_probleme'] ?? $data['commentaire'] ?? null);
        $rdv->setTempsEstime(!empty($data['duree_estimee']) ? (int) $data['duree_estimee'] : (!empty($data['temps_estime']) ? (int) $data['temps_estime'] : null));
        $rdv->setPrixEstime($data['prix_estime'] ?? null);

        if (!empty($data['pont_id'])) {
            $pont = $this->em->getRepository(Pont::class)->find($data['pont_id']);
            if ($pont) {
                $rdv->setPont($pont);
                if ($pont->getMecanicien()) {
                    $rdv->setMecanicien($pont->getMecanicien());
                }
            }
        }
        if (!empty($data['mecanicien_id'])) {
            $meca = $this->em->getRepository(Mecanicien::class)->find($data['mecanicien_id']);
            if ($meca) $rdv->setMecanicien($meca);
        }

        // Contrôle de chevauchement sur le pont (aucun garde-fou auparavant :
        // deux RDV pouvaient se superposer silencieusement). force=true assume.
        if ($rdv->getPont() && empty($data['force'])) {
            $conflit = $this->findPontConflict($rdv);
            if ($conflit) {
                return $this->json([
                    'error' => sprintf(
                        'Le pont %s est déjà occupé sur ce créneau (RDV #%d à %s). Renvoyez avec force=true pour passer outre.',
                        $rdv->getPont()->getNom(),
                        $conflit->getId(),
                        $conflit->getHeureRdv()?->format('H\hi') ?? '?',
                    ),
                    'code' => 'PONT_OCCUPE',
                    'conflict_rdv_id' => $conflit->getId(),
                ], Response::HTTP_CONFLICT);
            }
        }

        $this->em->persist($rdv);
        $this->em->flush();

        // Synchronize commandes
        $this->syncCommandes($rdv, $data['commandes'] ?? []);

        $this->audit->log('create', 'rdv', $rdv->getId(), json_encode([
            'client_id' => $client->getId(),
            'type' => $rdv->getTypeIntervention(),
        ]));

        return $this->json($this->flattenRdv($rdv), Response::HTTP_CREATED);
    }

    /**
     * Apply a workflow transition to a RDV.
     */
    /**
     * Premier RDV actif chevauchant le créneau du RDV donné sur le même pont.
     * Chevauchement sur les durées estimées (défaut 60 min), annulés exclus.
     */
    private function findPontConflict(RendezVous $rdv): ?RendezVous
    {
        if (!$rdv->getPont() || !$rdv->getDateRdv() || !$rdv->getHeureRdv()) {
            return null;
        }

        $startMin = (int) $rdv->getHeureRdv()->format('H') * 60 + (int) $rdv->getHeureRdv()->format('i');
        $endMin = $startMin + max(15, $rdv->getTempsEstime() ?: 60);

        $candidats = $this->em->getRepository(RendezVous::class)->createQueryBuilder('r')
            ->where('r.pont = :pont')
            ->andWhere('r.dateRdv = :date')
            ->andWhere('r.statut != :annule')
            ->setParameter('pont', $rdv->getPont())
            ->setParameter('date', $rdv->getDateRdv()->format('Y-m-d'))
            ->setParameter('annule', 'annule')
            ->getQuery()
            ->getResult();

        foreach ($candidats as $candidat) {
            if ($candidat->getId() === $rdv->getId() || !$candidat->getHeureRdv()) {
                continue;
            }
            $cStart = (int) $candidat->getHeureRdv()->format('H') * 60 + (int) $candidat->getHeureRdv()->format('i');
            $cEnd = $cStart + max(15, $candidat->getTempsEstime() ?: 60);
            if ($startMin < $cEnd && $endMin > $cStart) {
                return $candidat;
            }
        }

        return null;
    }

    /**
     * Accepte la demande d'annulation faite par le client depuis son espace :
     * applique la transition 'annuler' (mêmes gardes/side effects que le
     * workflow standard) puis lève le flag de demande.
     */
    #[Route('/api/rendez-vous/{id}/demande-annulation/accepter', methods: ['POST'])]
    public function accepterDemandeAnnulation(int $id, Request $request): JsonResponse
    {
        $rdv = $this->em->getRepository(RendezVous::class)->find($id);
        if (!$rdv) {
            return $this->json(['error' => 'RDV not found'], Response::HTTP_NOT_FOUND);
        }
        if (!$rdv->getAnnulationDemandeeAt()) {
            return $this->json(['error' => 'Aucune demande d\'annulation en cours.'], Response::HTTP_CONFLICT);
        }

        $response = $this->transition($id, 'annuler', $request);
        if ($response->getStatusCode() === Response::HTTP_OK) {
            $rdv->setAnnulationDemandeeAt(null);
            $this->em->flush();
        }

        return $response;
    }

    /**
     * Refuse la demande d'annulation : le flag est levé, le RDV reste en
     * l'état et le client peut redemander plus tard si besoin.
     */
    #[Route('/api/rendez-vous/{id}/demande-annulation/refuser', methods: ['POST'])]
    public function refuserDemandeAnnulation(int $id): JsonResponse
    {
        $rdv = $this->em->getRepository(RendezVous::class)->find($id);
        if (!$rdv) {
            return $this->json(['error' => 'RDV not found'], Response::HTTP_NOT_FOUND);
        }
        if (!$rdv->getAnnulationDemandeeAt()) {
            return $this->json(['error' => 'Aucune demande d\'annulation en cours.'], Response::HTTP_CONFLICT);
        }

        $rdv->setAnnulationDemandeeAt(null);
        $this->em->flush();

        $this->audit->log('annulation_demande_refusee', 'rdv', $rdv->getId(), json_encode(['statut' => $rdv->getStatut()]));

        return $this->json(['success' => true, 'statut' => $rdv->getStatut()]);
    }

    #[Route('/api/rendez-vous/{id}/transition/{transition}', methods: ['POST'])]
    public function transition(int $id, string $transition, Request $request): JsonResponse
    {
        $rdv = $this->em->getRepository(RendezVous::class)->find($id);
        if (!$rdv) {
            return $this->json(['error' => 'RDV not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $transitionName = $transition;

        if (!$this->rendezVousStateMachine->can($rdv, $transitionName)) {
            $enabledTransitions = array_map(
                fn($t) => $t->getName(),
                $this->rendezVousStateMachine->getEnabledTransitions($rdv)
            );
            return $this->json([
                'error' => "Transition '$transitionName' not allowed from status '{$rdv->getStatut()}'",
                'allowed_transitions' => $enabledTransitions,
            ], Response::HTTP_CONFLICT);
        }

        $missingPhotos = $this->photoService->requirePhotosForTransition($transitionName, $rdv);
        if (!empty($missingPhotos)) {
            return $this->json([
                'error' => 'Photos obligatoires manquantes avant cette transition',
                'missing_photos' => $missingPhotos,
            ], Response::HTTP_BAD_REQUEST);
        }

        if ($transitionName === 'terminer') {
            $pendingDemandes = $this->em->getRepository(DemandeTravauxSupp::class)->count([
                'rendezVous' => $rdv,
                'statut' => 'en_attente_decision_client',
            ]);
            if ($pendingDemandes > 0) {
                return $this->json([
                    'error' => 'Demande complémentaire en attente de décision client',
                ], Response::HTTP_CONFLICT);
            }

            $essai = $this->findLatestEssai($rdv);
            if (!$essai || !$essai->isValide()) {
                return $this->json([
                    'error' => 'Essai routier obligatoire avant clôture. Créez et validez un essai routier pour ce RDV.',
                    'code' => 'ESSAI_ROUTIER_REQUIS',
                ], Response::HTTP_BAD_REQUEST);
            }

            $ordreInitial = $this->findInitialOrdre($rdv);
            if ($essai->getStatut() === 'anomalie_detectee' && trim((string) ($ordreInitial?->getMechanicNotes() ?? '')) === '') {
                return $this->json([
                    'error' => 'Une anomalie a été détectée à l\'essai routier. Renseignez les notes mécanicien avant de terminer.',
                    'code' => 'MECHANIC_NOTES_REQUIRED',
                ], Response::HTTP_BAD_REQUEST);
            }
        }

        if ($transitionName === 'restituer') {
            $ordre = $this->findInitialOrdre($rdv);

            if (!$ordre || !$ordre->isInterventionSigned() || !$ordre->isRestitutionSigned()) {
                return $this->json([
                    'error' => 'Ordre de réparation non signé par le mécanicien et/ou le client restitution',
                ], Response::HTTP_BAD_REQUEST);
            }

            $rapportErrors = [];
            if (empty(trim((string) ($ordre->getTravauxRealises() ?? '')))) {
                $rapportErrors[] = 'Travaux réalisés manquants';
            }
            if (!empty($rapportErrors)) {
                return $this->json([
                    'error' => 'Rapport d\'intervention incomplet',
                    'validation_errors' => $rapportErrors,
                ], Response::HTTP_BAD_REQUEST);
            }
        }

        // Apply additional data based on transition
        if ($transitionName === 'reception' && isset($data['kilometrage'])) {
            $rdv->setKilometrage($data['kilometrage']);
        }
        if ($transitionName === 'reception' && isset($data['etat_vehicule'])) {
            $rdv->setEtatVehicule(is_array($data['etat_vehicule']) ? json_encode($data['etat_vehicule']) : $data['etat_vehicule']);
        }
        if (isset($data['pont_id'])) {
            $pont = $this->em->getRepository(Pont::class)->find($data['pont_id']);
            if ($pont) {
                $rdv->setPont($pont);
                if ($pont->getMecanicien()) {
                    $rdv->setMecanicien($pont->getMecanicien());
                }
            }
        }
        if (isset($data['mecanicien_id'])) {
            $meca = $this->em->getRepository(Mecanicien::class)->find($data['mecanicien_id']);
            if ($meca) { $rdv->setMecanicien($meca); }
        }

        if (in_array($transitionName, ['annuler', 'declarer_no_show', 'no_show'], true)) {
            $defaultMotif = match ($transitionName) {
                'declarer_no_show' => 'client_no_show',
                'no_show' => 'no_show',
                default => null,
            };

            $motif = isset($data['motif']) ? (string) $data['motif'] : $defaultMotif;
            if ($motif !== null && $motif !== '') {
                $rdv->setMotifAnnulation($motif);
                $data['motif'] ??= $motif;
            }

            if (isset($data['commentaire'])) {
                $rdv->setCommentaireAnnulation((string) $data['commentaire']);
            }
        }

        // Block reception without signed OR (client + atelier)
        if ($transitionName === 'reception') {
            $ordreReception = $this->findInitialOrdre($rdv);
            if (!$ordreReception || !$ordreReception->isReceptionSigned()) {
                return $this->json([
                    'error' => 'Signatures client et atelier obligatoires avant validation de la réception. Utilisez le compagnon PDA pour faire signer.',
                ], Response::HTTP_BAD_REQUEST);
            }
        }

        // Start/stop work time tracking + LOT 3 side effects
        if ($transitionName === 'start_travail') {
            $ordreInitial = $this->findInitialOrdre($rdv);

            if (!$ordreInitial || !$ordreInitial->isReceptionSigned()) {
                return $this->json([
                    'error' => 'Ordre de réparation signé (client + atelier) obligatoire avant démarrage',
                ], Response::HTTP_BAD_REQUEST);
            }
        }

        $user = $this->getUser();
        $userId = is_object($user) && method_exists($user, 'getId') ? $user->getId() : null;

        if (in_array($transitionName, ['passer_gardiennage', 'mettre_en_gardiennage'], true)) {
            $rdv->setGardiennageDebutAt(new \DateTime());
            $rdv->setGardiennageDebutPar($userId);
            $rdv->setGardiennageMotif((string) ($data['gardiennage_motif'] ?? $data['motif'] ?? $data['commentaire'] ?? 'Véhicule non récupéré'));
        }

        try {
            $annulation = $this->workflowService->handleTransitionSideEffects($rdv, $transitionName, $data, $userId);
            if ($annulation) {
                $this->em->persist($annulation);
            }
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        $this->rendezVousStateMachine->apply($rdv, $transitionName);

        // Finalize the OR when the RDV reaches completion
        if ($transitionName === 'terminer') {
            $ordre = $this->findInitialOrdre($rdv);
            if ($ordre && $ordre->getStatut() !== 'termine') {
                $this->ordreReparationPolicy->finalize($ordre);
            }
        }

        $this->em->flush();

        $this->audit->log(
            'workflow_transition',
            'rdv',
            $rdv->getId(),
            json_encode(['transition' => $transitionName, 'new_status' => $rdv->getStatut()])
        );

        return $this->json([
            'id' => $rdv->getId(),
            'statut' => $rdv->getStatut(),
            'transitions' => array_map(
                fn($t) => $t->getName(),
                $this->rendezVousStateMachine->getEnabledTransitions($rdv)
            ),
        ]);
    }

    /**
     * Get available transitions for a RDV.
     */
    #[Route('/api/rendez-vous/{id}/transitions', methods: ['GET'])]
    public function getTransitions(int $id): JsonResponse
    {
        $rdv = $this->em->getRepository(RendezVous::class)->find($id);
        if (!$rdv) {
            return $this->json(['error' => 'RDV not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'statut' => $rdv->getStatut(),
            'transitions' => array_map(
                fn($t) => $t->getName(),
                $this->rendezVousStateMachine->getEnabledTransitions($rdv)
            ),
        ]);
    }

    /**
     * Update commandes for a RDV.
     */
    #[Route('/api/rendez-vous/{id}/commandes', methods: ['POST'])]
    public function updateCommandes(int $id, Request $request): JsonResponse
    {
        $rdv = $this->em->getRepository(RendezVous::class)->find($id);
        if (!$rdv) {
            return $this->json(['error' => 'RDV not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $this->syncCommandes($rdv, $data['commandes'] ?? []);

        return $this->json($this->flattenRdv($rdv));
    }

    /**
     * Get mechanic's daily RDVs.
     */
    #[Route('/api/rendez-vous/mecanicien', methods: ['GET'], priority: 10)]
    public function mecanicienRdvs(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Not authenticated'], Response::HTTP_UNAUTHORIZED);
        }

        $date = $request->query->get('date', date('Y-m-d'));

        // Find mecanicien linked to current user
        $mecanicien = $this->em->getRepository(Mecanicien::class)->findOneBy(['userId' => $user->getId()]);
        if (!$mecanicien) {
            return $this->json([]);
        }

        $rdvs = $this->em->getRepository(RendezVous::class)
            ->createQueryBuilder('r')
            ->where('r.mecanicien = :meca')
            ->andWhere('r.dateRdv = :date')
            ->setParameter('meca', $mecanicien)
            ->setParameter('date', new \DateTimeImmutable($date))
            ->orderBy('r.heureRdv', 'ASC')
            ->getQuery()
            ->getResult();

        $data = array_map(fn(RendezVous $r) => $this->flattenRdv($r), $rdvs);
        return $this->json($data);
    }

    private function flattenRdv(RendezVous $r): array
    {
        $client = $r->getClient();
        $vehicule = $r->getVehicule();
        $pont = $r->getPont();
        $orInitial = $this->findInitialOrdre($r);
        $essai = $this->findLatestEssai($r);
        $etatVehiculeReception = $this->decodeJson($r->getEtatVehicule());

        return [
            'id' => $r->getId(),
            'date_rdv' => $r->getDateRdv()->format('Y-m-d'),
            'heure_debut' => $r->getHeureRdv()->format('H:i'),
            'heure_rdv' => $r->getHeureRdv()->format('H:i:s'),
            'type_intervention' => $r->getTypeIntervention(),
            'statut' => $r->getStatut(),
            'status' => $r->getStatut(),
            'commentaire' => $r->getCommentaire(),
            'commentaire_client' => $r->getCommentaire(),
            'description_probleme' => $r->getCommentaire(),
            'temps_estime' => $r->getTempsEstime(),
            'temps_effectif_minutes' => $r->getTempsEffectifMinutes(),
            'heure_debut_travail' => $r->getHeureDebutTravail()?->format('Y-m-d H:i:s'),
            'heure_debut_travaux' => $r->getHeureDebutTravail()?->format('Y-m-d H:i:s'),
            'heure_fin_travail' => $r->getHeureFinTravail()?->format('Y-m-d H:i:s'),
            'gardiennage_debut_at' => $r->getGardiennageDebutAt()?->format('Y-m-d H:i:s'),
            'gardiennage_motif' => $r->getGardiennageMotif(),
            'motif_annulation' => $r->getMotifAnnulation(),
            'commentaire_annulation' => $r->getCommentaireAnnulation(),
            'annulation_demandee_at' => $r->getAnnulationDemandeeAt()?->format('c'),
            'client_nom' => $client ? ($client->getPrenom() . ' ' . $client->getNom()) : null,
            'client_telephone' => $client?->getTelephone(),
            'client_email' => $client?->getEmail(),
            'vehicule_info' => $vehicule ? trim(($vehicule->getMarque() ?? '') . ' ' . ($vehicule->getModele() ?? '')) : null,
            'vehicule_plaque' => $vehicule?->getPlaque(),
            'vehicule_type' => $vehicule?->getTypeMoto(),
            'km_reception' => $r->getKilometrage(),
            'etat_vehicule_reception' => $etatVehiculeReception,
            'pont_nom' => $pont?->getNom(),
            'mecanicien_nom' => $r->getMecanicien() ? ($r->getMecanicien()->getPrenom() . ' ' . $r->getMecanicien()->getNom()) : null,
            'or_id' => $orInitial?->getId(),
            'or_mechanic_notes' => $orInitial?->getMechanicNotes(),
            'or_mechanic_checkup' => $orInitial?->getMechanicCheckup(),
            'essai_routier_id' => $essai?->getId(),
            'essai_routier_statut' => $essai?->getStatut(),
            'essai_routier_valide' => $essai?->isValide() ?? false,
            'token_suivi' => $r->getTokenSuivi(),
            'commandes' => array_values(array_map(
                fn(RdvCommande $c) => $c->getNumero(),
                $r->getCommandes()->toArray()
            )),
        ];
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

    private function decodeJson(?string $payload): ?array
    {
        if ($payload === null || trim($payload) === '') {
            return null;
        }

        $decoded = json_decode($payload, true);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Synchronize RdvCommande collection for a RDV.
     * @param string[] $numeros
     */
    private function syncCommandes(RendezVous $rdv, array $numeros): void
    {
        // Remove existing commandes not in the new list
        foreach ($rdv->getCommandes() as $existing) {
            if (!in_array($existing->getNumero(), $numeros, true)) {
                $rdv->removeCommande($existing);
                $this->em->remove($existing);
            }
        }

        // Add new commandes
        $existingNumeros = array_map(
            fn(RdvCommande $c) => $c->getNumero(),
            $rdv->getCommandes()->toArray()
        );

        foreach ($numeros as $numero) {
            if (!is_string($numero) || trim($numero) === '') {
                continue;
            }
            if (!in_array($numero, $existingNumeros, true)) {
                $cmd = new RdvCommande();
                $cmd->setRendezVous($rdv);
                $cmd->setNumero($numero);
                $this->em->persist($cmd);
                $rdv->addCommande($cmd);
            }
        }

        $this->em->flush();
    }
}
