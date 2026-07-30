<?php

namespace App\Controller;

use App\Entity\Atelier;
use App\Entity\Client;
use App\Entity\ConfigAtelier;
use App\Entity\DemandeTravauxSupp;
use App\Entity\EtatDesLieux;
use App\Entity\Notification;
use App\Entity\OrdreReparation;
use App\Entity\PhotoIntervention;
use App\Entity\Pont;
use App\Entity\Prestation;
use App\Entity\RdvStatutHistorique;
use App\Entity\RendezVous;
use App\Entity\Vehicule;
use App\Service\DemandeTravauxSuppDecisionService;
use App\Service\EtatDesLieuxDocumentService;
use App\Service\MercureNotifier;
use App\Service\NotificationDispatcher;
use App\Service\PdfService;
use App\Service\SlotService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/client')]
class ClientEspaceController extends AbstractController
{
    /** Statuts pour lesquels une demande d'annulation a encore du sens */
    private const CANCELLABLE_STATUTS = ['en_attente', 'reserve', 'confirme'];

    public function __construct(
        private EntityManagerInterface $em,
        private PdfService $pdfService,
        private MercureNotifier $mercureNotifier,
        private DemandeTravauxSuppDecisionService $decisionService,
        private EtatDesLieuxDocumentService $etatDesLieuxDocumentService,
        private SlotService $slotService,
        private RateLimiterFactory $publicBookingLimiter,
        private NotificationDispatcher $notificationDispatcher,
    ) {}

    #[Route('/me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        $client = $this->getCurrentClient();
        if (!$client) {
            return $this->json(['error' => 'Non authentifié'], Response::HTTP_UNAUTHORIZED);
        }

        return $this->json([
            'id' => $client->getId(),
            'nom' => $client->getNom(),
            'prenom' => $client->getPrenom(),
            'email' => $client->getEmail(),
            'telephone' => $client->getTelephone(),
            'adresse' => $client->getAdresse(),
            'segment' => $client->getSegment(),
            'created_at' => $client->getCreatedAt()->format('c'),
            'consent_date' => $client->getConsentDate()?->format('c'),
            'last_activity_at' => $client->getLastActivityAt()?->format('c'),
            'vehicules' => array_map(fn(Vehicule $v) => [
                'id' => $v->getId(),
                'plaque' => $v->getPlaque(),
                'marque' => $v->getMarque(),
                'modele' => $v->getModele(),
                'type_moto' => $v->getTypeMoto(),
                'cylindree' => $v->getCylindree(),
                'annee' => $v->getAnnee(),
            ], $client->getVehicules()->toArray()),
        ]);
    }

    #[Route('/me', methods: ['PATCH'])]
    public function updateMe(Request $request): JsonResponse
    {
        $client = $this->getCurrentClient();
        if (!$client) {
            return $this->json(['error' => 'Non authentifié'], Response::HTTP_UNAUTHORIZED);
        }

        $data = json_decode($request->getContent(), true) ?? [];

        if (array_key_exists('nom', $data)) $client->setNom($data['nom']);
        if (array_key_exists('prenom', $data)) $client->setPrenom($data['prenom']);
        if (array_key_exists('telephone', $data)) $client->setTelephone($data['telephone']);
        if (array_key_exists('email', $data)) $client->setEmail($data['email'] ?: null);
        if (array_key_exists('adresse', $data)) $client->setAdresse($data['adresse'] ?: null);

        $this->em->flush();

        return $this->json([
            'id' => $client->getId(),
            'nom' => $client->getNom(),
            'prenom' => $client->getPrenom(),
            'email' => $client->getEmail(),
            'telephone' => $client->getTelephone(),
            'adresse' => $client->getAdresse(),
        ]);
    }

    #[Route('/rdvs/{id}', methods: ['GET'])]
    public function rdvDetail(int $id): JsonResponse
    {
        $client = $this->getCurrentClient();
        if (!$client) {
            return $this->json(['error' => 'Non authentifié'], Response::HTTP_UNAUTHORIZED);
        }

        $rdv = $this->em->getRepository(RendezVous::class)
            ->createQueryBuilder('r')
            ->leftJoin('r.vehicule', 'v')
            ->leftJoin('r.photosIntervention', 'ph')
            ->leftJoin('r.ordresReparation', 'o')
            ->leftJoin('r.commandes', 'c')
            ->where('r.id = :id')
            ->andWhere('r.client = :client')
            ->setParameter('id', $id)
            ->setParameter('client', $client)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$rdv) {
            return $this->json(['error' => 'Rendez-vous introuvable'], Response::HTTP_NOT_FOUND);
        }

        // Timeline : création du dossier + chaque transition de workflow tracée
        $timeline = [[
            'statut' => 'cree',
            'transition' => null,
            'date' => $rdv->getCreatedAt()->format('c'),
        ]];
        $historique = $this->em->getRepository(RdvStatutHistorique::class)
            ->findBy(['rendezVous' => $rdv], ['createdAt' => 'ASC', 'id' => 'ASC']);
        foreach ($historique as $h) {
            $timeline[] = [
                'statut' => $h->getStatut(),
                'transition' => $h->getTransition(),
                'date' => $h->getCreatedAt()->format('c'),
            ];
        }

        $demandes = $this->em->getRepository(DemandeTravauxSupp::class)
            ->findBy(['rendezVous' => $rdv], ['createdAt' => 'DESC']);

        // État des lieux d'entrée : visible dès signature (gate signedHash,
        // PAS le statut 'termine' — écart voulu avec le gate des OR)
        $etatDesLieux = $this->em->getRepository(EtatDesLieux::class)->findOneBy(['rendezVous' => $rdv]);
        $etatDesLieuxPayload = null;
        if ($etatDesLieux && $etatDesLieux->getSignedHash() !== null) {
            $etatDesLieuxPayload = [
                'signe' => true,
                'signed_at' => $etatDesLieux->getSignedAt()?->format('c'),
                'kilometrage' => $etatDesLieux->getKilometrage(),
                'niveau_carburant' => $etatDesLieux->getNiveauCarburant(),
                'observations' => $etatDesLieux->getObservations(),
                'pdf_disponible' => true,
            ];
        }

        return $this->json([
            'id' => $rdv->getId(),
            'date_heure' => (new \DateTime($rdv->getDateRdv()->format('Y-m-d') . ' ' . $rdv->getHeureRdv()->format('H:i:s')))->format('c'),
            'statut' => $rdv->getStatut(),
            'type_intervention' => $rdv->getTypeIntervention(),
            'prestations_snapshot' => $rdv->getPrestationsSnapshot(),
            'prix_estime' => $rdv->getPrixEstime(),
            'commentaire' => $rdv->getCommentaire(),
            'vehicule' => $rdv->getVehicule() ? [
                'id' => $rdv->getVehicule()->getId(),
                'plaque' => $rdv->getVehicule()->getPlaque(),
                'marque' => $rdv->getVehicule()->getMarque(),
                'modele' => $rdv->getVehicule()->getModele(),
            ] : null,
            'annulation_demandee_at' => $rdv->getAnnulationDemandeeAt()?->format('c'),
            'annulation_possible' => $this->isAnnulationPossible($rdv),
            'ordres_reparation' => array_map(fn($o) => [
                'id' => $o->getId(),
                'numero_or' => $o->getNumeroOr(),
                'type_or' => $o->getTypeOr(),
                'travaux' => $o->getTravaux(),
                'montant_estime' => $o->getMontantEstime(),
                'statut' => $o->getStatut(),
                // Le PDF (archive immuable) est exposé au client dès que l'OR est
                // scellé à la restitution (final_hash présent) — même gate que
                // OrdreReparationPdfController.
                'pdf_disponible' => $o->getFinalHash() !== null,
            ], $rdv->getOrdresReparation()->toArray()),
            'photos' => array_map(fn($ph) => [
                'id' => $ph->getId(),
                'filename' => $ph->getFilename(),
                'description' => $ph->getDescription(),
                // Servie par l'endpoint authentifié (contrôle d'appartenance),
                // plus de dépendance au chemin public /uploads
                'url' => '/api/client/photos/' . $ph->getId(),
            ], $rdv->getPhotosIntervention()->toArray()),
            'commandes' => array_map(fn($c) => [
                'id' => $c->getId(),
                'numero' => $c->getNumero(),
            ], $rdv->getCommandes()->toArray()),
            'etat_des_lieux' => $etatDesLieuxPayload,
            'timeline' => $timeline,
            'demandes_travaux' => array_map(fn(DemandeTravauxSupp $d) => [
                'id' => $d->getId(),
                'statut' => $d->getStatut(),
                'description' => $d->getDescription(),
                'prestations' => $d->getPrestationsChoisies(),
                'prix_estime' => $d->getPrixEstime(),
                'temps_estime' => $d->getTempsEstime(),
                'urgence' => $d->getUrgence(),
                'decision' => $d->getDecisionClient(),
                'decision_at' => $d->getDecisionClientAt()?->format('c'),
                'created_at' => $d->getCreatedAt()->format('c'),
                // Seules les demandes envoyées attendent une décision en ligne
                'decision_possible' => $d->getStatut() === DemandeTravauxSupp::STATUT_EN_ATTENTE_DECISION_CLIENT,
                // Accord donné par téléphone : le bloc bascule en mode
                // « confirmer et signer » (pas de refus possible)
                'confirmation_telephone' => $d->isEnAttenteConfirmationTelephone(),
                'accord_telephone_at' => $d->getDecisionCanal() === DemandeTravauxSupp::CANAL_STAFF_TELEPHONE
                    ? $d->getDecisionClientAt()?->format('c')
                    : null,
                'signed_at' => $d->getSignedAt()?->format('c'),
            ], array_values(array_filter(
                $demandes,
                // Les brouillons non validés par le réceptionniste restent invisibles
                fn(DemandeTravauxSupp $d) => !in_array($d->getStatut(), [
                    DemandeTravauxSupp::STATUT_EN_ATTENTE,
                    DemandeTravauxSupp::STATUT_EN_ATTENTE_VALIDATION,
                ], true),
            ))),
        ]);
    }

    /**
     * Photo d'intervention servie au client propriétaire du RDV uniquement.
     * Remplace l'accès par chemin public /uploads/photos côté portail.
     */
    #[Route('/photos/{id}', methods: ['GET'])]
    public function photo(int $id): Response
    {
        $client = $this->getCurrentClient();
        if (!$client) {
            return $this->json(['error' => 'Non authentifié'], Response::HTTP_UNAUTHORIZED);
        }

        $photo = $this->em->getRepository(PhotoIntervention::class)->find($id);
        if (!$photo || $photo->getRendezVous()?->getClient()?->getId() !== $client->getId()) {
            return $this->json(['error' => 'Photo introuvable'], Response::HTTP_NOT_FOUND);
        }

        $safeFilename = basename($photo->getFilename());
        $photoDir = realpath($this->getParameter('kernel.project_dir') . '/var/photos');
        $realPath = $photoDir ? realpath($photoDir . '/' . $safeFilename) : false;

        if ($realPath === false || !str_starts_with($realPath, $photoDir . '/') || !is_file($realPath)) {
            return $this->json(['error' => 'Photo introuvable'], Response::HTTP_NOT_FOUND);
        }

        $response = new BinaryFileResponse($realPath);
        $response->headers->set('Cache-Control', 'private, max-age=3600');
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, $safeFilename);
        return $response;
    }

    /**
     * Décision du client connecté sur une demande de travaux supplémentaires.
     * Même logique (signature, OR complémentaire, notification staff) que la
     * page publique tokenisée — via DemandeTravauxSuppDecisionService.
     */
    #[Route('/demandes-travaux-supp/{id}/decision', methods: ['POST'])]
    public function decisionDemandeTravaux(int $id, Request $request): JsonResponse
    {
        $client = $this->getCurrentClient();
        if (!$client) {
            return $this->json(['error' => 'Non authentifié'], Response::HTTP_UNAUTHORIZED);
        }

        $demande = $this->em->getRepository(DemandeTravauxSupp::class)->find($id);
        if (!$demande || $demande->getRendezVous()->getClient()?->getId() !== $client->getId()) {
            return $this->json(['error' => 'Demande introuvable'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true) ?? [];

        $result = $this->decisionService->decide(
            $demande,
            $data['decision'] ?? null,
            $data['signature'] ?? null,
            $request,
            'client_portail', // KPI pilote : décision via le portail client connecté
        );

        if (isset($result['error'])) {
            return $this->json(['error' => $result['error']], $result['status']);
        }

        return $this->json([
            'id' => $demande->getId(),
            'decision' => $demande->getDecisionClient(),
            'statut' => $demande->getStatut(),
            'or_complementaire_id' => $demande->getOrComplementaire()?->getId(),
        ]);
    }

    #[Route('/rdvs/{rdvId}/or/{orId}/pdf', methods: ['GET'])]
    public function downloadOrPdf(int $rdvId, int $orId): Response
    {
        $client = $this->getCurrentClient();
        if (!$client) {
            return $this->json(['error' => 'Non authentifié'], Response::HTTP_UNAUTHORIZED);
        }

        $rdv = $this->em->getRepository(RendezVous::class)->findOneBy(['id' => $rdvId, 'client' => $client]);
        if (!$rdv) {
            return $this->json(['error' => 'Rendez-vous introuvable'], Response::HTTP_NOT_FOUND);
        }

        $or = null;
        foreach ($rdv->getOrdresReparation() as $candidate) {
            if ($candidate->getId() === $orId) {
                $or = $candidate;
                break;
            }
        }

        // Seul le document scellé à la restitution (empreinte finale) est communicable.
        if (!$or || $or->getFinalHash() === null) {
            return $this->json(['error' => 'Document non disponible'], Response::HTTP_NOT_FOUND);
        }

        // Le PDF est archivé immuable à la restitution (OrdreReparationPolicy).
        // Pas de régénération ici : un re-rendu depuis l'entité vivante pourrait
        // différer du document signé (notes mécano modifiables après coup).
        $pdfPath = $this->pdfService->getArchivedOrPdfPath($or);
        if ($pdfPath === null) {
            return $this->json([
                'error' => 'Document momentanément indisponible. Contactez votre atelier.',
            ], Response::HTTP_NOT_FOUND);
        }

        $response = new BinaryFileResponse($pdfPath);
        $response->headers->set('Content-Type', 'application/pdf');
        $response->setContentDisposition('attachment', 'OR-' . $or->getNumeroOr() . '.pdf');

        return $response;
    }

    /**
     * PDF de l'état des lieux d'entrée pour le client propriétaire du RDV.
     * Même pattern que downloadOrPdf : double contrôle (flag payload + re-check
     * ici), gate = signedHash !== null (dès signature, pas d'attente 'termine'),
     * document archivé à la signature — JAMAIS régénéré.
     */
    #[Route('/rdvs/{rdvId}/etat-des-lieux/pdf', methods: ['GET'])]
    public function downloadEtatDesLieuxPdf(int $rdvId): Response
    {
        $client = $this->getCurrentClient();
        if (!$client) {
            return $this->json(['error' => 'Non authentifié'], Response::HTTP_UNAUTHORIZED);
        }

        $rdv = $this->em->getRepository(RendezVous::class)->findOneBy(['id' => $rdvId, 'client' => $client]);
        if (!$rdv) {
            return $this->json(['error' => 'Rendez-vous introuvable'], Response::HTTP_NOT_FOUND);
        }

        $etatDesLieux = $this->em->getRepository(EtatDesLieux::class)->findOneBy(['rendezVous' => $rdv]);

        // Seul le document signé et figé (hash) est communicable au client
        if (!$etatDesLieux || $etatDesLieux->getSignedHash() === null) {
            return $this->json(['error' => 'Document non disponible'], Response::HTTP_NOT_FOUND);
        }

        // Containment realpath géré par le service (archive hors webroot)
        $pdfPath = $this->etatDesLieuxDocumentService->getArchivedPdfPath($etatDesLieux);
        if ($pdfPath === null) {
            return $this->json([
                'error' => 'Document momentanément indisponible. Contactez votre atelier.',
            ], Response::HTTP_NOT_FOUND);
        }

        $response = new BinaryFileResponse($pdfPath);
        $response->headers->set('Content-Type', 'application/pdf');
        $response->setContentDisposition('attachment', sprintf('etat-des-lieux-rdv-%d.pdf', $rdv->getId()));

        return $response;
    }

    #[Route('/rdvs/{id}/demande-annulation', methods: ['POST'])]
    public function demandeAnnulation(int $id): JsonResponse
    {
        $client = $this->getCurrentClient();
        if (!$client) {
            return $this->json(['error' => 'Non authentifié'], Response::HTTP_UNAUTHORIZED);
        }

        $rdv = $this->em->getRepository(RendezVous::class)->findOneBy(['id' => $id, 'client' => $client]);
        if (!$rdv) {
            return $this->json(['error' => 'Rendez-vous introuvable'], Response::HTTP_NOT_FOUND);
        }

        if ($rdv->getAnnulationDemandeeAt()) {
            return $this->json(['error' => 'Une demande d\'annulation est déjà en cours pour ce rendez-vous.'], Response::HTTP_CONFLICT);
        }

        if (!$this->isAnnulationPossible($rdv)) {
            return $this->json(['error' => 'Ce rendez-vous ne peut plus faire l\'objet d\'une demande d\'annulation en ligne. Contactez directement l\'atelier.'], Response::HTTP_CONFLICT);
        }

        // Sans atelier rattaché, la notification serait invisible côté staff
        // (filtre atelierId) : on refuse plutôt que de perdre la demande.
        if (!$rdv->getAtelierId()) {
            return $this->json([
                'error' => 'Ce rendez-vous ne peut pas être annulé en ligne. Contactez directement l\'atelier.',
            ], Response::HTTP_CONFLICT);
        }

        $rdv->setAnnulationDemandeeAt(new \DateTime());

        $notif = new Notification();
        $notif->setAtelierId($rdv->getAtelierId());
        $notif->setType('rdv_annulation_demandee');
        $notif->setSeverity('warning');
        $notif->setTitle('Demande d\'annulation client');
        $notif->setMessage(sprintf(
            '%s %s demande l\'annulation de son RDV du %s à %s (%s)',
            $client->getPrenom(),
            $client->getNom(),
            $rdv->getDateRdv()->format('d/m/Y'),
            $rdv->getHeureRdv()->format('H\hi'),
            $rdv->getTypeIntervention() ?? 'intervention',
        ));
        $notif->setRelatedEntityType('RendezVous');
        $notif->setRelatedEntityId($rdv->getId());
        $notif->setTargetRoles(['ROLE_ADMIN', 'ROLE_RECEPTIONNAIRE']);
        $notif->setTargetRole('ROLE_RECEPTIONNAIRE');
        $notif->setPriority('high');
        $this->em->persist($notif);

        $this->em->flush();

        try {
            $this->mercureNotifier->publishToAtelier($rdv->getAtelierId(), $notif);
        } catch (\Throwable) {
            // Mercure indisponible : la notification reste visible dans la cloche
        }

        return $this->json([
            'message' => 'Votre demande d\'annulation a été transmise à l\'atelier. Vous serez recontacté pour confirmation.',
            'annulation_demandee_at' => $rdv->getAnnulationDemandeeAt()->format('c'),
        ]);
    }

    private function isAnnulationPossible(RendezVous $rdv): bool
    {
        if (!in_array($rdv->getStatut(), self::CANCELLABLE_STATUTS, true)) {
            return false;
        }

        $rdvDateTime = new \DateTime($rdv->getDateRdv()->format('Y-m-d') . ' ' . $rdv->getHeureRdv()->format('H:i:s'));

        return $rdvDateTime > new \DateTime();
    }

    #[Route('/rdvs', methods: ['GET'])]
    public function rdvs(): JsonResponse
    {
        $client = $this->getCurrentClient();
        if (!$client) {
            return $this->json(['error' => 'Non authentifié'], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $rdvs = $this->em->getRepository(RendezVous::class)
                ->createQueryBuilder('r')
                ->where('r.client = :client')
                ->setParameter('client', $client)
                ->orderBy('r.dateRdv', 'DESC')
                ->getQuery()
                ->getResult();

            $items = array_map(fn(RendezVous $r) => [
                'id' => $r->getId(),
                'date_heure' => (new \DateTime($r->getDateRdv()->format('Y-m-d') . ' ' . $r->getHeureRdv()->format('H:i:s')))->format('c'),
                'statut' => $r->getStatut(),
                'type_intervention' => $r->getTypeIntervention(),
                'vehicule_plaque' => $r->getVehicule()?->getPlaque(),
                'vehicule_info' => trim(($r->getVehicule()?->getMarque() ?? '') . ' ' . ($r->getVehicule()?->getModele() ?? '')),
                'pont' => $r->getPont()?->getNom(),
                'prix_total' => $r->getPrixFinal(),
                'annulation_demandee_at' => $r->getAnnulationDemandeeAt()?->format('c'),
                'annulation_possible' => $this->isAnnulationPossible($r),
            ], $rdvs);

            return $this->json($items);
        } catch (\Throwable $e) {
            return $this->json(['error' => $e::class, 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/historique', methods: ['GET'])]
    public function historique(): JsonResponse
    {
        try {
            $client = $this->getCurrentClient();
            if (!$client) {
                return $this->json(['error' => 'Non authentifié'], Response::HTTP_UNAUTHORIZED);
            }

            $ors = $this->em->getRepository(OrdreReparation::class)
                ->createQueryBuilder('o')
                ->join('o.rendezVous', 'r')
                ->where('r.client = :client')
                ->andWhere('o.signedAt IS NOT NULL')
                ->setParameter('client', $client)
                ->orderBy('o.signedAt', 'DESC')
                ->getQuery()
                ->getResult();

            $items = array_map(fn(OrdreReparation $o) => [
                'id' => $o->getId(),
                'rdv_id' => $o->getRendezVous()->getId(),
                'numero_or' => $o->getNumeroOr(),
                'type_or' => $o->getTypeOr(),
                // Ce qui a été RÉELLEMENT fait prime sur ce qui était prévu à l'origine.
                'travaux' => $o->getTravauxRealises() ?: $o->getTravaux(),
                'signed_at' => $o->getSignedAt()?->format('c'),
                'vehicule_plaque' => $o->getRendezVous()->getVehicule()?->getPlaque(),
                'vehicule_info' => trim(($o->getRendezVous()->getVehicule()?->getMarque() ?? '') . ' ' . ($o->getRendezVous()->getVehicule()?->getModele() ?? '')),
                // Même verrou que la fiche RDV : PDF exposé seulement une fois
                // l'OR scellé à la restitution (final_hash présent).
                'pdf_disponible' => $o->getFinalHash() !== null,
            ], $ors);

            return $this->json($items);
        } catch (\Throwable $e) {
            return $this->json(['error' => $e::class, 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/archive', methods: ['POST'])]
    public function archive(): JsonResponse
    {
        $client = $this->getCurrentClient();
        if (!$client) {
            return $this->json(['error' => 'Non authentifié'], Response::HTTP_UNAUTHORIZED);
        }

        $client->setIsAnonymized(true);
        $client->setNom('ANONYME');
        $client->setPrenom('ANONYME');
        $client->setEmail(null);
        $client->setTelephone('0000000000');
        $client->setAdresse(null);
        $this->em->flush();

        return $this->json(['message' => 'Profil archivé avec succès']);
    }

    #[Route('/vehicules', methods: ['GET'])]
    public function vehicules(): JsonResponse
    {
        $client = $this->getCurrentClient();
        if (!$client) {
            return $this->json(['error' => 'Non authentifié'], Response::HTTP_UNAUTHORIZED);
        }

        $items = array_map(function (Vehicule $v) {
            $vidange = $this->prochaineVidange($v);

            return [
                'id' => $v->getId(),
                'plaque' => $v->getPlaque(),
                'marque' => $v->getMarque(),
                'modele' => $v->getModele(),
                'type_moto' => $v->getTypeMoto(),
                'cylindree' => $v->getCylindree(),
                'annee' => $v->getAnnee(),
                'kilometrage' => $v->getKilometrage(),
                'notes' => $v->getNotes(),
                'prochaine_vidange' => $vidange,
            ];
        }, $client->getVehicules()->toArray());

        return $this->json($items);
    }

    /**
     * Dernier OR signé de ce véhicule portant une suggestion de prochaine vidange
     * (km et/ou date, saisis par le mécanicien à la restitution — voir
     * ConfigAtelier::vidangeIntervalleKm/Mois pour la valeur suggérée par défaut).
     * Due dès que l'un des deux seuils est atteint (km déclaré par le client
     * en priorité, date toujours vérifiée), comme sur une notice constructeur.
     */
    private function prochaineVidange(Vehicule $v): ?array
    {
        $ordre = $this->em->getRepository(OrdreReparation::class)
            ->createQueryBuilder('o')
            ->join('o.rendezVous', 'r')
            ->where('r.vehicule = :vehicule')
            ->andWhere('o.signedAt IS NOT NULL')
            ->andWhere('o.prochaineRevisionKm IS NOT NULL OR o.prochaineRevisionDate IS NOT NULL')
            ->setParameter('vehicule', $v)
            ->orderBy('o.signedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$ordre) {
            return null;
        }

        $km = $ordre->getProchaineRevisionKm();
        $date = $ordre->getProchaineRevisionDate();
        $dueParKm = $km !== null && $v->getKilometrage() !== null && $v->getKilometrage() >= $km;
        $dueParDate = $date !== null && $date <= new \DateTime();

        return [
            'km' => $km,
            'date' => $date?->format('c'),
            'due' => $dueParKm || $dueParDate,
        ];
    }

    /**
     * Ajoute une moto au parc du client connecté. Contrairement au booking
     * public, pas de recherche par plaque tous clients confondus : une
     * plaque déjà associée à CE client renvoie la fiche existante (idempotent),
     * déjà associée à un AUTRE client est refusée (pas de rattachement erroné).
     */
    #[Route('/vehicules', methods: ['POST'])]
    public function createVehicule(Request $request): JsonResponse
    {
        $client = $this->getCurrentClient();
        if (!$client) {
            return $this->json(['error' => 'Non authentifié'], Response::HTTP_UNAUTHORIZED);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        if (empty($data['marque']) || empty($data['modele'])) {
            return $this->json(['error' => 'Marque et modèle sont requis'], Response::HTTP_BAD_REQUEST);
        }

        $plaque = !empty($data['plaque']) ? strtoupper(trim((string) $data['plaque'])) : null;
        if ($plaque) {
            $existing = $this->em->getRepository(Vehicule::class)->findOneBy(['plaque' => $plaque]);
            if ($existing) {
                if ($existing->getClient()?->getId() !== $client->getId()) {
                    return $this->json(['error' => 'Cette plaque est déjà enregistrée sur un autre compte.'], Response::HTTP_CONFLICT);
                }
                // Déjà dans le parc de ce client : idempotent, pas de doublon.
                return $this->json(['id' => $existing->getId()]);
            }
        }

        $vehicule = new Vehicule();
        $vehicule->setClient($client);
        $vehicule->setAtelierId($client->getAtelierId());
        $vehicule->setMarque((string) $data['marque']);
        $vehicule->setModele((string) $data['modele']);
        if ($plaque) {
            $vehicule->setPlaque($plaque);
        }
        if (!empty($data['annee'])) {
            $vehicule->setAnnee((int) $data['annee']);
        }
        if (!empty($data['cylindree'])) {
            $vehicule->setCylindree((string) $data['cylindree']);
        }
        if (!empty($data['type_moto'])) {
            $vehicule->setTypeMoto((string) $data['type_moto']);
        }

        $this->em->persist($vehicule);
        $this->em->flush();

        return $this->json([
            'id' => $vehicule->getId(),
            'plaque' => $vehicule->getPlaque(),
            'marque' => $vehicule->getMarque(),
            'modele' => $vehicule->getModele(),
            'type_moto' => $vehicule->getTypeMoto(),
            'cylindree' => $vehicule->getCylindree(),
            'annee' => $vehicule->getAnnee(),
            'kilometrage' => $vehicule->getKilometrage(),
            'notes' => $vehicule->getNotes(),
            'prochaine_vidange' => null,
        ], Response::HTTP_CREATED);
    }

    #[Route('/vehicules/{id}', methods: ['PATCH'])]
    public function updateVehicule(int $id, Request $request): JsonResponse
    {
        $client = $this->getCurrentClient();
        if (!$client) {
            return $this->json(['error' => 'Non authentifié'], Response::HTTP_UNAUTHORIZED);
        }

        $vehicule = $this->em->getRepository(Vehicule::class)->find($id);
        if (!$vehicule || $vehicule->getClient()?->getId() !== $client->getId()) {
            return $this->json(['error' => 'Véhicule introuvable'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        if (array_key_exists('kilometrage', $data)) {
            $vehicule->setKilometrage((int) $data['kilometrage']);
        }
        if (array_key_exists('notes', $data)) {
            $vehicule->setNotes($data['notes'] ?: null);
        }

        $this->em->flush();

        return $this->json([
            'id' => $vehicule->getId(),
            'plaque' => $vehicule->getPlaque(),
            'kilometrage' => $vehicule->getKilometrage(),
            'notes' => $vehicule->getNotes(),
        ]);
    }

    /**
     * Catalogue des prestations actives de l'atelier du client connecté
     * (même logique que PublicBookingController::prestations, scopée sur
     * le client authentifié au lieu d'un atelier_id soumis par le visiteur).
     */
    #[Route('/prestations', methods: ['GET'])]
    public function prestations(): JsonResponse
    {
        $client = $this->getCurrentClient();
        if (!$client) {
            return $this->json(['error' => 'Non authentifié'], Response::HTTP_UNAUTHORIZED);
        }

        $limiter = $this->publicBookingLimiter->create('client:' . $client->getId());
        if (!$limiter->consume()->isAccepted()) {
            return $this->json(['error' => 'Too many requests'], Response::HTTP_TOO_MANY_REQUESTS);
        }

        $atelierId = $client->getAtelierId();
        if (!$atelierId) {
            return $this->json(['error' => 'Aucun atelier associé à ce compte'], Response::HTTP_BAD_REQUEST);
        }

        $prestations = $this->em->getRepository(Prestation::class)
            ->createQueryBuilder('p')
            ->where('p.atelierId = :atelier')
            ->andWhere('p.isActive = 1')
            ->setParameter('atelier', $atelierId)
            ->orderBy('p.categorie', 'ASC')
            ->addOrderBy('p.nom', 'ASC')
            ->getQuery()
            ->getResult();

        return $this->json(array_map(static fn (Prestation $p): array => [
            'id' => $p->getId(),
            'code' => $p->getCode(),
            'nom' => $p->getNom(),
            'description' => $p->getDescription(),
            'categorie' => $p->getCategorie(),
            'type_vehicule' => $p->getTypeVehicule(),
            'cylindree_min' => $p->getCylindreeMin(),
            'cylindree_max' => $p->getCylindreeMax(),
            'type_tarif' => $p->getTypeTarif(),
            'prix_base_ht' => (float) $p->getPrixBaseHt(),
            'prix_base_ttc' => (float) $p->getPrixBaseTtc(),
            'temps_estime_minutes' => $p->getTempsEstimeMinutes(),
        ], $prestations));
    }

    /**
     * Créneaux disponibles pour l'atelier du client connecté (même moteur
     * que le booking public, `SlotService::getAvailableSlots`).
     */
    #[Route('/slots', methods: ['GET'])]
    public function slots(Request $request): JsonResponse
    {
        $client = $this->getCurrentClient();
        if (!$client) {
            return $this->json(['error' => 'Non authentifié'], Response::HTTP_UNAUTHORIZED);
        }

        $limiter = $this->publicBookingLimiter->create('client:' . $client->getId());
        if (!$limiter->consume()->isAccepted()) {
            return $this->json(['error' => 'Too many requests'], Response::HTTP_TOO_MANY_REQUESTS);
        }

        $atelierId = $client->getAtelierId();
        if (!$atelierId) {
            return $this->json(['error' => 'Aucun atelier associé à ce compte'], Response::HTTP_BAD_REQUEST);
        }

        $dateDebut = $request->query->get('date_debut', (new \DateTime())->format('Y-m-d'));
        $dateFin = $request->query->get('date_fin', (new \DateTime('+14 days'))->format('Y-m-d'));
        $tempsMinutes = (int) $request->query->get('temps_minutes', 60);

        return $this->json([
            'bookingEnabled' => $this->isBookingEnabled($atelierId),
            'slots' => $this->slotService->getAvailableSlots(
                new \DateTime($dateDebut),
                new \DateTime($dateFin),
                $tempsMinutes,
                $atelierId,
            ),
        ]);
    }

    /**
     * Création d'un RDV par le client connecté. Contrairement au booking
     * public (PublicBookingController::createBooking), le RDV est rattaché
     * DIRECTEMENT au client de la session — pas de recherche/création par
     * téléphone, qui exposerait à un rattachement erroné (doublon de fiche)
     * si le téléphone du compte diffère de celui saisi.
     */
    #[Route('/rdvs', methods: ['POST'])]
    public function createRdv(Request $request): JsonResponse
    {
        $client = $this->getCurrentClient();
        if (!$client) {
            return $this->json(['error' => 'Non authentifié'], Response::HTTP_UNAUTHORIZED);
        }

        $limiter = $this->publicBookingLimiter->create('client:' . $client->getId());
        if (!$limiter->consume()->isAccepted()) {
            return $this->json(['error' => 'Too many requests'], Response::HTTP_TOO_MANY_REQUESTS);
        }

        $atelierId = $client->getAtelierId();
        if (!$atelierId) {
            return $this->json(['error' => 'Aucun atelier associé à ce compte'], Response::HTTP_BAD_REQUEST);
        }

        $data = json_decode($request->getContent(), true) ?? [];

        $required = ['date_rdv', 'heure_rdv', 'type_intervention'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return $this->json(['error' => "Field '$field' is required"], Response::HTTP_BAD_REQUEST);
            }
        }

        if (!$this->isBookingEnabled($atelierId)) {
            return $this->json([
                'error' => 'La réservation en ligne n\'est pas disponible pour cet atelier.',
            ], Response::HTTP_FORBIDDEN);
        }

        $vehicule = null;
        if (!empty($data['vehicule_id'])) {
            $vehicule = $this->em->getRepository(Vehicule::class)->find((int) $data['vehicule_id']);
            if (!$vehicule || $vehicule->getClient()?->getId() !== $client->getId()) {
                return $this->json(['error' => 'Véhicule introuvable'], Response::HTTP_NOT_FOUND);
            }
        }

        $tempsEstime = max(15, (int) ($data['duree_estimee'] ?? 60));
        $targetDate = new \DateTime($data['date_rdv']);

        // Même verrou transactionnel anti-course que le booking public : deux
        // réservations simultanées sur le même atelier/jour sont sérialisées.
        $connection = $this->em->getConnection();
        $connection->beginTransaction();
        try {
            $connection->executeStatement(
                'SELECT pg_advisory_xact_lock(hashtext(:lockKey))',
                ['lockKey' => sprintf('public-booking:%d:%s', $atelierId, $targetDate->format('Y-m-d'))]
            );

            $response = $this->doCreateRdv($data, $client, $vehicule, $atelierId, $tempsEstime, $targetDate, $request);
            $connection->commit();

            return $response;
        } catch (\Throwable $e) {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }
            throw $e;
        }
    }

    private function doCreateRdv(array $data, Client $client, ?Vehicule $vehicule, int $atelierId, int $tempsEstime, \DateTime $targetDate, Request $request): JsonResponse
    {
        $availableSlots = $this->slotService->getSlotsForDay($targetDate, $tempsEstime, $atelierId);
        $matchingSlots = array_values(array_filter($availableSlots, static fn(array $slot) => ($slot['heure'] ?? null) === ($data['heure_rdv'] ?? null)));

        if (empty($matchingSlots)) {
            return $this->json([
                'error' => 'Le créneau sélectionné n’est plus disponible. Merci d’en choisir un autre.',
            ], Response::HTTP_CONFLICT);
        }

        $selectedSlot = null;
        if (!empty($data['pont_id'])) {
            foreach ($matchingSlots as $slot) {
                if ((int) ($slot['pont_id'] ?? 0) === (int) $data['pont_id']) {
                    $selectedSlot = $slot;
                    break;
                }
            }
        }
        $selectedSlot ??= $matchingSlots[0];

        $client->touchActivity();

        $rdv = new RendezVous();
        $rdv->setClient($client);
        $rdv->setVehicule($vehicule);
        $rdv->setDateRdv(new \DateTime($data['date_rdv']));
        $rdv->setHeureRdv(new \DateTime($data['heure_rdv']));
        $rdv->setTypeIntervention($data['type_intervention']);
        $rdv->setCommentaire($data['commentaire'] ?? null);
        $rdv->setTempsEstime($tempsEstime);

        // Prestations réservées : figer le snapshot + RECALCULER le total serveur
        // (même règle anti-falsification que le booking public).
        $prestationsInput = $data['prestations'] ?? null;
        if (is_array($prestationsInput) && $prestationsInput !== []) {
            $norm = RendezVous::normalizePrestationsInput($prestationsInput);
            $rdv->setPrestationsSnapshot($norm['snapshot']);
            $rdv->setPrixEstime(number_format($norm['total'], 2, '.', ''));
        }
        $rdv->setStatut('en_attente');
        $rdv->setAtelierId($atelierId);
        $rdv->setOrigine('web'); // KPI pilote : même bucket « en ligne » que le booking public

        if (!empty($selectedSlot['pont_id'])) {
            $pont = $this->em->getRepository(Pont::class)->find((int) $selectedSlot['pont_id']);
            if ($pont) {
                $rdv->setPont($pont);
                if ($pont->getMecanicien()) {
                    $rdv->setMecanicien($pont->getMecanicien());
                }
            }
        }

        $this->em->persist($rdv);
        $this->em->flush();

        try {
            $this->sendBookingConfirmation($rdv, $client, $request);
        } catch (\Throwable) {
            // l'email échoue silencieusement, le RDV est créé
        }

        return $this->json([
            'id' => $rdv->getId(),
            'message' => 'Rendez-vous enregistré.',
            'date' => $rdv->getDateRdv()->format('Y-m-d'),
            'heure' => $rdv->getHeureRdv()->format('H:i'),
            'heure_fin' => $selectedSlot['heure_fin'] ?? null,
            'pause_appliquee' => (bool) ($selectedSlot['pause_appliquee'] ?? false),
        ], Response::HTTP_CREATED);
    }

    private function sendBookingConfirmation(RendezVous $rdv, Client $client, Request $request): void
    {
        $to = $client->getEmail();
        $atelierId = $rdv->getAtelierId();
        if (!$to || !$atelierId) {
            return;
        }

        $baseUrl = rtrim($_ENV['PUBLIC_URL'] ?? $request->getSchemeAndHttpHost(), '/');
        $atelier = $this->em->getRepository(Atelier::class)->find($atelierId);
        $atelierNom = $atelier?->getNom() ?? 'votre atelier';

        $this->notificationDispatcher->sendFromTemplate(
            'booking_accuse',
            'email',
            $atelierId,
            $to,
            [
                'client_prenom' => htmlspecialchars($client->getPrenom() ?? ''),
                'atelier_nom' => htmlspecialchars($atelierNom),
                'date_rdv' => $rdv->getDateRdv()->format('d/m/Y'),
                'heure_rdv' => $rdv->getHeureRdv()->format('H\hi'),
                'type_intervention' => htmlspecialchars($rdv->getTypeIntervention() ?? ''),
                // Client déjà connu du portail : lien direct vers sa fiche RDV
                // (pas de lien de suivi public ni de bloc d'activation).
                'suivi_url' => htmlspecialchars($baseUrl . '/client/rdvs/' . $rdv->getId()),
                'activation_bloc' => '',
            ],
            'RendezVous',
            $rdv->getId(),
        );
    }

    private function isBookingEnabled(int $atelierId): bool
    {
        $config = $this->em->getRepository(ConfigAtelier::class)
            ->createQueryBuilder('c')
            ->where('c.atelierId = :atelier')
            ->setParameter('atelier', $atelierId)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$config) {
            return false;
        }

        $modules = $config->getFeatureModules();
        return !empty($modules['public_booking']);
    }

    private function getCurrentClient(): ?Client
    {
        $token = $this->container->get('security.token_storage')->getToken();
        $user = $token?->getUser();
        if (!$user instanceof \App\Security\ClientUserAdapter) {
            return null;
        }
        return $user->getClient();
    }
}
