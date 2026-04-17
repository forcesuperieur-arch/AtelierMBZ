<?php
namespace App\Controller;

use App\Entity\AnnulationRdv;
use App\Entity\Client;
use App\Entity\DemandeTravauxSupp;
use App\Entity\Mecanicien;
use App\Entity\OrdreReparation;
use App\Entity\Pont;
use App\Entity\RapportIntervention;
use App\Entity\RendezVous;
use App\Entity\Vehicule;
use App\Service\AuditService;
use App\Service\PhotoService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Workflow\WorkflowInterface;

class RendezVousController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private WorkflowInterface $rendezVousStateMachine,
        private AuditService $audit,
        private SerializerInterface $serializer,
        private PhotoService $photoService,
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

        // Find or create vehicule
        $vehicule = null;
        if (!empty($data['vehicule_plaque'])) {
            $vehicule = $this->em->getRepository(Vehicule::class)->findOneBy(['plaque' => $data['vehicule_plaque']]);
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

        $this->em->persist($rdv);
        $this->em->flush();

        $this->audit->log('create', 'rdv', $rdv->getId(), json_encode([
            'client_id' => $client->getId(),
            'type' => $rdv->getTypeIntervention(),
        ]));

        return $this->json($this->flattenRdv($rdv), Response::HTTP_CREATED);
    }

    /**
     * Apply a workflow transition to a RDV.
     */
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

        // LOT 2 — Block transitions without required photos
        $missingPhotos = $this->photoService->requirePhotosForTransition($transitionName, $rdv);
        if (!empty($missingPhotos)) {
            return $this->json([
                'error' => sprintf(
                    'Photos manquantes : %d photo(s) de type "%s" requises (%d/%d)',
                    $missingPhotos['missing'],
                    $missingPhotos['type'],
                    $missingPhotos['current'],
                    $missingPhotos['required']
                ),
                'missing_photos' => $missingPhotos,
            ], Response::HTTP_BAD_REQUEST);
        }

        // Block reception without signed OR
        if ($transitionName === 'reception') {
            $ordreReception = $this->em->getRepository(OrdreReparation::class)->findOneBy(
                ['rendezVous' => $rdv],
                ['id' => 'DESC']
            );
            if (!$ordreReception || !$ordreReception->getSignatureClient()) {
                return $this->json([
                    'error' => 'Signature client obligatoire avant validation de la réception. Utilisez le compagnon PDA pour faire signer.',
                ], Response::HTTP_BAD_REQUEST);
            }
        }

        // Start/stop work time tracking
        if ($transitionName === 'start_travail') {
            $ordreInitial = $this->em->getRepository(OrdreReparation::class)->findOneBy([
                'rendezVous' => $rdv,
            ], ['id' => 'DESC']);

            if (!$ordreInitial || !$ordreInitial->getSignatureClient()) {
                return $this->json([
                    'error' => 'Ordre de réparation signé obligatoire avant démarrage',
                ], Response::HTTP_BAD_REQUEST);
            }

            $rdv->setHeureDebutTravail(new \DateTime());
        }
        if (in_array($transitionName, ['terminer', 'pause'])) {
            // LOT 4.3 — Block terminer if a demande complémentaire is pending
            if ($transitionName === 'terminer') {
                $pendingDemande = $this->em->getRepository(DemandeTravauxSupp::class)->findOneBy([
                    'rendezVous' => $rdv,
                    'statut' => 'en_attente_decision_client',
                ]);
                if ($pendingDemande) {
                    return $this->json([
                        'error' => 'Demande complémentaire en attente de décision client',
                        'demande_id' => $pendingDemande->getId(),
                    ], Response::HTTP_CONFLICT);
                }
            }

            $rdv->setHeureFinTravail(new \DateTime());
            if ($rdv->getHeureDebutTravail()) {
                $diff = $rdv->getHeureDebutTravail()->diff(new \DateTime());
                $minutes = $diff->h * 60 + $diff->i;
                $rdv->setTempsEffectifMinutes(($rdv->getTempsEffectifMinutes() ?? 0) + $minutes);
            }
        }

        // LOT 8 — Block restituer unless rapport signed by both
        if ($transitionName === 'restituer') {
            $rapport = $this->em->getRepository(RapportIntervention::class)->findOneBy(
                ['rendezVous' => $rdv],
                ['id' => 'DESC'],
            );
            if (!$rapport || !$rapport->isSignedByBoth()) {
                return $this->json([
                    'error' => 'Rapport d\'intervention signé par le mécanicien ET le client obligatoire avant restitution',
                ], Response::HTTP_BAD_REQUEST);
            }
        }

        // LOT 3 — Require motif for annulation/no_show
        if (in_array($transitionName, ['annuler', 'declarer_no_show'])) {
            $motif = $data['motif'] ?? null;
            if (!$motif || !in_array($motif, AnnulationRdv::MOTIFS, true)) {
                return $this->json([
                    'error' => 'Motif obligatoire pour annulation',
                    'allowed_motifs' => AnnulationRdv::MOTIFS,
                ], Response::HTTP_BAD_REQUEST);
            }

            $annulation = new AnnulationRdv();
            $annulation->setRendezVous($rdv);
            $annulation->setMotif($motif);
            $annulation->setCommentaire($data['commentaire'] ?? null);
            $annulation->setStatutAvantAnnulation($rdv->getStatut());
            $annulation->setHeureRdvOriginal($rdv->getHeureRdv());
            $annulation->setAtelierId($rdv->getAtelierId());
            if ($user = $this->getUser()) {
                $annulation->setAnnulePar($user->getId());
            }
            $this->em->persist($annulation);
        }

        $this->rendezVousStateMachine->apply($rdv, $transitionName);
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
            ->setParameter('date', $date)
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

        return [
            'id' => $r->getId(),
            'date_rdv' => $r->getDateRdv()->format('Y-m-d'),
            'heure_debut' => $r->getHeureRdv()->format('H:i'),
            'type_intervention' => $r->getTypeIntervention(),
            'statut' => $r->getStatut(),
            'status' => $r->getStatut(),
            'commentaire' => $r->getCommentaire(),
            'description_probleme' => $r->getCommentaire(),
            'temps_estime' => $r->getTempsEstime(),
            'temps_effectif_minutes' => $r->getTempsEffectifMinutes(),
            'heure_debut_travail' => $r->getHeureDebutTravail()?->format('Y-m-d H:i:s'),
            'client_nom' => $client ? ($client->getPrenom() . ' ' . $client->getNom()) : null,
            'client_telephone' => $client?->getTelephone(),
            'client_email' => $client?->getEmail(),
            'vehicule_info' => $vehicule ? trim(($vehicule->getMarque() ?? '') . ' ' . ($vehicule->getModele() ?? '')) : null,
            'vehicule_plaque' => $vehicule?->getPlaque(),
            'pont_nom' => $pont?->getNom(),
            'mecanicien_nom' => $r->getMecanicien() ? ($r->getMecanicien()->getPrenom() . ' ' . $r->getMecanicien()->getNom()) : null,
            'token_suivi' => $r->getTokenSuivi(),
        ];
    }
}
