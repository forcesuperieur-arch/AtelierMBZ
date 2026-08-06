<?php

namespace App\Controller;

use App\Entity\Atelier;
use App\Entity\Client;
use App\Entity\DemandeTravauxSupp;
use App\Entity\Devis;
use App\Entity\EtatDesLieux;
use App\Entity\NotificationLog;
use App\Entity\Reclamation;
use App\Entity\RdvStatutHistorique;
use App\Entity\RendezVous;
use App\Entity\User;
use App\Entity\Vehicule;
use App\Service\AuditService;
use App\Service\BookingAtelierAccessService;
use App\Service\CurrentAtelierResolver;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Cockpit SRC (PILOTE_PLAN.md Lot C2) : recherche universelle + dossier 360° en LECTURE
 * cross-atelier. Réservé à ROLE_SERVICE_CLIENT (pas juste ROLE_USER) : ce n'est pas un écran
 * partagé avec le reste du staff.
 */
#[Route('/api/cockpit')]
#[IsGranted('ROLE_SERVICE_CLIENT')]
class CockpitSrcController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private BookingAtelierAccessService $atelierAccess,
        private AuditService $audit,
        private CurrentAtelierResolver $atelierResolver,
    ) {}

    #[Route('/recherche', methods: ['GET'])]
    public function recherche(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $q = trim((string) $request->query->get('q', ''));
        if (mb_strlen($q) < 2) {
            return $this->json(['clients' => []]);
        }

        $clients = $this->atelierAccess->withCrossAtelierRead(
            $user,
            fn () => $this->searchClients($q),
        );

        $ateliers = $this->atelierNamesById();

        return $this->json([
            'clients' => array_map(function (Client $c) use ($ateliers) {
                $vehicules = $c->getVehicules();
                $dernierRdv = $this->em->getRepository(RendezVous::class)
                    ->createQueryBuilder('r')
                    ->where('r.client = :client')
                    ->setParameter('client', $c)
                    ->orderBy('r.dateRdv', 'DESC')->addOrderBy('r.heureRdv', 'DESC')
                    ->setMaxResults(1)
                    ->getQuery()->getOneOrNullResult();

                return [
                    'id' => $c->getId(),
                    'nom' => $c->getNom(),
                    'prenom' => $c->getPrenom(),
                    'telephone' => $c->getTelephone(),
                    'email' => $c->getEmail(),
                    'atelier_id' => $c->getAtelierId(),
                    'atelier_nom' => $ateliers[$c->getAtelierId()] ?? null,
                    'vehicules' => array_map(fn (Vehicule $v) => [
                        'id' => $v->getId(),
                        'plaque' => $v->getPlaque(),
                        'marque' => $v->getMarque(),
                        'modele' => $v->getModele(),
                    ], $vehicules->toArray()),
                    'dernier_rdv' => $dernierRdv ? [
                        'id' => $dernierRdv->getId(),
                        'date_rdv' => $dernierRdv->getDateRdv()->format('Y-m-d'),
                        'statut' => $dernierRdv->getStatut(),
                    ] : null,
                ];
            }, $clients),
        ]);
    }

    /** @return Client[] */
    private function searchClients(string $q): array
    {
        $qb = $this->em->getRepository(Client::class)->createQueryBuilder('c')
            ->leftJoin('c.vehicules', 'v')
            ->addSelect('v');

        $tokens = preg_split('/\s+/', mb_strtolower($q), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $orX = $qb->expr()->orX();
        foreach ($tokens as $i => $token) {
            $p = 'term' . $i;
            $orX->add("LOWER(c.nom) LIKE :$p")
                ->add("LOWER(c.prenom) LIKE :$p")
                ->add("LOWER(COALESCE(c.telephone, '')) LIKE :$p")
                ->add("LOWER(COALESCE(c.email, '')) LIKE :$p");
            $qb->setParameter($p, '%' . $token . '%');
        }
        // Plaque : comparaison simple (pas de fonction SQL REPLACE en DQL) — matche telle que
        // saisie, y compris partiellement. La recherche par plaque exacte/normalisée existe déjà
        // ailleurs (VehiculeLookupController) si besoin d'aller plus loin.
        $orX->add("UPPER(v.plaque) LIKE :plaque");
        $qb->setParameter('plaque', '%' . mb_strtoupper($q) . '%');

        return $qb->andWhere($orX)->setMaxResults(20)->getQuery()->getResult();
    }

    #[Route('/clients/{id}', methods: ['GET'])]
    public function dossier(int $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $result = $this->atelierAccess->withCrossAtelierRead($user, function () use ($id) {
            $client = $this->em->getRepository(Client::class)->find($id);
            if (!$client) {
                return null;
            }

            $rdvs = $this->em->getRepository(RendezVous::class)
                ->createQueryBuilder('r')
                ->leftJoin('r.vehicule', 'v')->addSelect('v')
                ->where('r.client = :client')
                ->setParameter('client', $client)
                ->orderBy('r.dateRdv', 'DESC')->addOrderBy('r.heureRdv', 'DESC')
                ->setMaxResults(50)
                ->getQuery()->getResult();

            return [
                'id' => $client->getId(),
                'nom' => $client->getNom(),
                'prenom' => $client->getPrenom(),
                'telephone' => $client->getTelephone(),
                'email' => $client->getEmail(),
                'atelier_id' => $client->getAtelierId(),
                'vehicules' => array_map(fn (Vehicule $v) => [
                    'id' => $v->getId(),
                    'plaque' => $v->getPlaque(),
                    'marque' => $v->getMarque(),
                    'modele' => $v->getModele(),
                ], $client->getVehicules()->toArray()),
                'rdvs' => array_map(fn (RendezVous $r) => [
                    'id' => $r->getId(),
                    'date_rdv' => $r->getDateRdv()->format('Y-m-d'),
                    'heure_rdv' => $r->getHeureRdv()->format('H:i'),
                    'statut' => $r->getStatut(),
                    'type_intervention' => $r->getTypeIntervention(),
                    'vehicule' => $r->getVehicule() ? [
                        'plaque' => $r->getVehicule()->getPlaque(),
                        'marque' => $r->getVehicule()->getMarque(),
                        'modele' => $r->getVehicule()->getModele(),
                    ] : null,
                ], $rdvs),
            ];
        });

        if ($result === null) {
            return $this->json(['error' => 'Client introuvable'], Response::HTTP_NOT_FOUND);
        }

        $this->auditCrossAtelierConsultation('client', $id, $result['atelier_id']);

        return $this->json($result);
    }

    #[Route('/rdv/{id}', methods: ['GET'])]
    public function rdvDetail(int $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $result = $this->atelierAccess->withCrossAtelierRead($user, function () use ($id) {
            $rdv = $this->em->getRepository(RendezVous::class)
                ->createQueryBuilder('r')
                ->leftJoin('r.client', 'cl')->addSelect('cl')
                ->leftJoin('r.vehicule', 'v')->addSelect('v')
                ->leftJoin('r.photosIntervention', 'ph')
                ->leftJoin('r.ordresReparation', 'o')
                ->where('r.id = :id')
                ->setParameter('id', $id)
                ->getQuery()->getOneOrNullResult();

            if (!$rdv) {
                return null;
            }

            $timeline = [['statut' => 'cree', 'transition' => null, 'date' => $rdv->getCreatedAt()->format('c')]];
            foreach ($this->em->getRepository(RdvStatutHistorique::class)->findBy(['rendezVous' => $rdv], ['createdAt' => 'ASC', 'id' => 'ASC']) as $h) {
                $timeline[] = ['statut' => $h->getStatut(), 'transition' => $h->getTransition(), 'date' => $h->getCreatedAt()->format('c')];
            }

            $etatDesLieux = $this->em->getRepository(EtatDesLieux::class)->findOneBy(['rendezVous' => $rdv]);
            $etatDesLieuxPayload = null;
            if ($etatDesLieux && $etatDesLieux->getSignedHash() !== null) {
                $etatDesLieuxPayload = [
                    'signe' => true,
                    'signed_at' => $etatDesLieux->getSignedAt()?->format('c'),
                    'kilometrage' => $etatDesLieux->getKilometrage(),
                ];
            }

            $notifications = $this->em->getRepository(NotificationLog::class)
                ->createQueryBuilder('n')
                ->where('n.relatedEntityType = :type')
                ->andWhere('n.relatedEntityId = :id')
                ->setParameter('type', 'RendezVous')
                ->setParameter('id', $rdv->getId())
                ->orderBy('n.sentAt', 'DESC')
                ->getQuery()->getResult();

            return [
                'id' => $rdv->getId(),
                'atelier_id' => $rdv->getAtelierId(),
                'date_rdv' => $rdv->getDateRdv()->format('Y-m-d'),
                'heure_rdv' => $rdv->getHeureRdv()->format('H:i'),
                'statut' => $rdv->getStatut(),
                'type_intervention' => $rdv->getTypeIntervention(),
                'commentaire' => $rdv->getCommentaire(),
                'prix_estime' => $rdv->getPrixEstime(),
                'client' => $rdv->getClient() ? [
                    'id' => $rdv->getClient()->getId(),
                    'nom' => $rdv->getClient()->getNom(),
                    'prenom' => $rdv->getClient()->getPrenom(),
                    'telephone' => $rdv->getClient()->getTelephone(),
                ] : null,
                'vehicule' => $rdv->getVehicule() ? [
                    'plaque' => $rdv->getVehicule()->getPlaque(),
                    'marque' => $rdv->getVehicule()->getMarque(),
                    'modele' => $rdv->getVehicule()->getModele(),
                ] : null,
                'ordres_reparation' => array_map(fn ($o) => [
                    'id' => $o->getId(),
                    'numero_or' => $o->getNumeroOr(),
                    'statut' => $o->getStatut(),
                    'pdf_disponible' => $o->getFinalHash() !== null,
                ], $rdv->getOrdresReparation()->toArray()),
                'photos_count' => $rdv->getPhotosIntervention()->count(),
                'etat_des_lieux' => $etatDesLieuxPayload,
                'timeline' => $timeline,
                'notifications' => array_map(fn (NotificationLog $n) => [
                    'channel' => $n->getChannel(),
                    'template_code' => $n->getTemplateCode(),
                    'to' => $n->getToRecipient(),
                    'status' => $n->getStatus(),
                    'sent_at' => $n->getSentAt()->format('c'),
                ], $notifications),
                'demandes_travaux' => array_map(fn (DemandeTravauxSupp $d) => [
                    'id' => $d->getId(),
                    'statut' => $d->getStatut(),
                    'description' => $d->getDescription(),
                ], $this->em->getRepository(DemandeTravauxSupp::class)->findBy(['rendezVous' => $rdv], ['createdAt' => 'DESC'])),
            ];
        });

        if ($result === null) {
            return $this->json(['error' => 'Rendez-vous introuvable'], Response::HTTP_NOT_FOUND);
        }

        $this->auditCrossAtelierConsultation('rendez_vous', $id, $result['atelier_id']);

        return $this->json($result);
    }

    /**
     * Lot C4 — file de travail : demandes d'annulation en attente, cross-atelier, les plus
     * anciennes en premier (ancienneté = priorité).
     */
    #[Route('/file/annulations', methods: ['GET'])]
    public function fileAnnulations(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $ateliers = $this->atelierNamesById();

        $rdvs = $this->atelierAccess->withCrossAtelierRead($user, fn () => $this->em->getRepository(RendezVous::class)
            ->createQueryBuilder('r')
            ->leftJoin('r.client', 'cl')->addSelect('cl')
            ->leftJoin('r.vehicule', 'v')->addSelect('v')
            ->where('r.annulationDemandeeAt IS NOT NULL')
            ->orderBy('r.annulationDemandeeAt', 'ASC')
            ->setMaxResults(100)
            ->getQuery()->getResult());

        return $this->json(['annulations' => array_map(fn (RendezVous $r) => [
            'id' => $r->getId(),
            'atelier_nom' => $ateliers[$r->getAtelierId()] ?? null,
            'demandee_at' => $r->getAnnulationDemandeeAt()?->format('c'),
            'motif' => $r->getMotifAnnulation(),
            'commentaire' => $r->getCommentaireAnnulation(),
            'date_rdv' => $r->getDateRdv()->format('Y-m-d'),
            'client' => $r->getClient() ? ['id' => $r->getClient()->getId(), 'nom' => $r->getClient()->getNom(), 'prenom' => $r->getClient()->getPrenom(), 'telephone' => $r->getClient()->getTelephone()] : null,
            'vehicule' => $r->getVehicule() ? ['plaque' => $r->getVehicule()->getPlaque()] : null,
        ], $rdvs)]);
    }

    /**
     * Lot C4 — relances : travaux supplémentaires ET devis envoyés sans décision client,
     * cross-atelier, triés par ancienneté d'attente.
     */
    #[Route('/file/relances', methods: ['GET'])]
    public function fileRelances(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $ateliers = $this->atelierNamesById();

        [$demandes, $devisEnvoyes] = $this->atelierAccess->withCrossAtelierRead($user, fn () => [
            $this->em->getRepository(DemandeTravauxSupp::class)
                ->createQueryBuilder('d')
                ->leftJoin('d.rendezVous', 'r')->addSelect('r')
                ->leftJoin('r.client', 'cl')->addSelect('cl')
                ->where('d.statut = :statut')
                ->setParameter('statut', DemandeTravauxSupp::STATUT_EN_ATTENTE_DECISION_CLIENT)
                ->orderBy('d.sentAt', 'ASC')
                ->setMaxResults(100)
                ->getQuery()->getResult(),
            $this->em->getRepository(Devis::class)
                ->createQueryBuilder('dv')
                ->leftJoin('dv.client', 'cl')->addSelect('cl')
                ->where('dv.statut = :statut')
                ->setParameter('statut', 'envoye')
                ->orderBy('dv.updatedAt', 'ASC')
                ->setMaxResults(100)
                ->getQuery()->getResult(),
        ]);

        return $this->json([
            'travaux_supp' => array_map(fn (DemandeTravauxSupp $d) => [
                'id' => $d->getId(),
                'rendez_vous_id' => $d->getRendezVous()?->getId(),
                'atelier_nom' => $ateliers[$d->getAtelierId()] ?? null,
                'client' => $d->getRendezVous()?->getClient() ? ['nom' => $d->getRendezVous()->getClient()->getNom(), 'prenom' => $d->getRendezVous()->getClient()->getPrenom(), 'telephone' => $d->getRendezVous()->getClient()->getTelephone()] : null,
                'description' => $d->getDescription(),
                'sent_at' => $d->getSentAt()?->format('c'),
            ], $demandes),
            'devis' => array_map(fn (Devis $d) => [
                'id' => $d->getId(),
                'numero_devis' => $d->getNumeroDevis(),
                'atelier_nom' => $ateliers[$d->getAtelierId()] ?? null,
                'client' => $d->getClient() ? ['nom' => $d->getClient()->getNom(), 'prenom' => $d->getClient()->getPrenom(), 'telephone' => $d->getClient()->getTelephone()] : null,
                'total_ttc' => $d->getTotalTtc(),
                'envoye_depuis' => $d->getUpdatedAt()->format('c'),
            ], $devisEnvoyes),
        ]);
    }

    /** Lot C4 — cahier de bord des réclamations, cross-atelier. */
    #[Route('/reclamations', methods: ['GET'])]
    public function listReclamations(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $statutFiltre = $request->query->get('statut');
        $ateliers = $this->atelierNamesById();

        $reclamations = $this->atelierAccess->withCrossAtelierRead($user, function () use ($statutFiltre) {
            $qb = $this->em->getRepository(Reclamation::class)->createQueryBuilder('rc')
                ->leftJoin('rc.client', 'cl')->addSelect('cl')
                ->orderBy('rc.updatedAt', 'DESC');
            if ($statutFiltre && in_array($statutFiltre, Reclamation::STATUTS, true)) {
                $qb->andWhere('rc.statut = :statut')->setParameter('statut', $statutFiltre);
            }

            return $qb->setMaxResults(200)->getQuery()->getResult();
        });

        return $this->json(['reclamations' => array_map(fn (Reclamation $rc) => $this->serializeReclamation($rc, $ateliers), $reclamations)]);
    }

    #[Route('/reclamations', methods: ['POST'])]
    public function createReclamation(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true) ?? [];

        $clientId = is_numeric($data['client_id'] ?? null) ? (int) $data['client_id'] : null;
        $sujet = trim((string) ($data['sujet'] ?? ''));
        if (!$clientId || $sujet === '') {
            return $this->json(['error' => 'client_id et sujet sont requis'], Response::HTTP_BAD_REQUEST);
        }

        $reclamation = $this->atelierAccess->withCrossAtelierRead($user, function () use ($clientId, $sujet, $data, $user) {
            $client = $this->em->getRepository(Client::class)->find($clientId);
            if (!$client) {
                return null;
            }

            $rc = new Reclamation();
            $rc->setClient($client);
            $rc->setAtelierId($client->getAtelierId());
            $rc->setSujet($sujet);
            $rc->setCreatedBy($user->getId());
            if (!empty($data['rendez_vous_id']) && is_numeric($data['rendez_vous_id'])) {
                $rdv = $this->em->getRepository(RendezVous::class)->find((int) $data['rendez_vous_id']);
                if ($rdv) {
                    $rc->setRendezVous($rdv);
                }
            }
            if (!empty($data['note'])) {
                $rc->addNote((string) $data['note'], $user->getId(), $user->getUsername());
            }
            $this->em->persist($rc);
            $this->em->flush();

            return $rc;
        });

        if ($reclamation === null) {
            return $this->json(['error' => 'Client introuvable ou hors périmètre'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->serializeReclamation($reclamation, $this->atelierNamesById()), Response::HTTP_CREATED);
    }

    #[Route('/reclamations/{id}/note', methods: ['POST'])]
    public function addReclamationNote(int $id, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true) ?? [];
        $texte = trim((string) ($data['note'] ?? ''));
        $nouveauStatut = $data['statut'] ?? null;

        $reclamation = $this->atelierAccess->withCrossAtelierRead($user, function () use ($id, $texte, $nouveauStatut, $user) {
            $rc = $this->em->getRepository(Reclamation::class)->find($id);
            if (!$rc) {
                return null;
            }
            if ($texte !== '') {
                $rc->addNote($texte, $user->getId(), $user->getUsername());
            }
            if (is_string($nouveauStatut) && in_array($nouveauStatut, Reclamation::STATUTS, true)) {
                $rc->setStatut($nouveauStatut);
            }
            $this->em->flush();

            return $rc;
        });

        if ($reclamation === null) {
            return $this->json(['error' => 'Réclamation introuvable'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->serializeReclamation($reclamation, $this->atelierNamesById()));
    }

    /** @param array<int, string> $ateliers */
    private function serializeReclamation(Reclamation $rc, array $ateliers): array
    {
        return [
            'id' => $rc->getId(),
            'atelier_nom' => $ateliers[$rc->getAtelierId()] ?? null,
            'statut' => $rc->getStatut(),
            'sujet' => $rc->getSujet(),
            'client' => ['id' => $rc->getClient()->getId(), 'nom' => $rc->getClient()->getNom(), 'prenom' => $rc->getClient()->getPrenom()],
            'rendez_vous_id' => $rc->getRendezVous()?->getId(),
            'notes' => $rc->getNotes(),
            'created_at' => $rc->getCreatedAt()->format('c'),
            'updated_at' => $rc->getUpdatedAt()->format('c'),
        ];
    }

    /**
     * PILOTE_PLAN.md Lot C1 : la lecture cross-atelier du SRC doit être journalisée. On ne
     * trace QUE les consultations qui sortent réellement de son atelier actif — auditer chaque
     * lecture (y compris dans son propre atelier) noierait le journal sans valeur ajoutée.
     */
    private function auditCrossAtelierConsultation(string $entityType, int $entityId, ?int $atelierConsulteId): void
    {
        $atelierActifId = $this->atelierResolver->resolveAtelierId();
        if ($atelierConsulteId !== null && $atelierConsulteId !== $atelierActifId) {
            $this->audit->log('consultation_cross_atelier', $entityType, $entityId, json_encode([
                'atelier_consulte' => $atelierConsulteId,
                'atelier_actif' => $atelierActifId,
            ]));
        }
    }

    /** @return array<int, string> */
    private function atelierNamesById(): array
    {
        $names = [];
        foreach ($this->em->getRepository(Atelier::class)->findAll() as $atelier) {
            $names[$atelier->getId()] = $atelier->getNom();
        }

        return $names;
    }
}
