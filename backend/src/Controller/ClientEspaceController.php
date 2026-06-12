<?php

namespace App\Controller;

use App\Entity\Client;
use App\Entity\Notification;
use App\Entity\OrdreReparation;
use App\Entity\RendezVous;
use App\Entity\Vehicule;
use App\Service\MercureNotifier;
use App\Service\PdfService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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

        return $this->json([
            'id' => $rdv->getId(),
            'date_heure' => (new \DateTime($rdv->getDateRdv()->format('Y-m-d') . ' ' . $rdv->getHeureRdv()->format('H:i:s')))->format('c'),
            'statut' => $rdv->getStatut(),
            'type_intervention' => $rdv->getTypeIntervention(),
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
                'statut' => $o->getStatut(),
                // Le PDF n'est exposé au client qu'une fois l'OR finalisé et figé
                'pdf_disponible' => $o->getStatut() === 'termine' && $o->getSignedHash() !== null,
            ], $rdv->getOrdresReparation()->toArray()),
            'photos' => array_map(fn($ph) => [
                'id' => $ph->getId(),
                'filename' => $ph->getFilename(),
                'description' => $ph->getDescription(),
            ], $rdv->getPhotosIntervention()->toArray()),
            'commandes' => array_map(fn($c) => [
                'id' => $c->getId(),
                'numero' => $c->getNumero(),
            ], $rdv->getCommandes()->toArray()),
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

        // Seul le document finalisé et figé (hash) est communicable au client
        if (!$or || $or->getStatut() !== 'termine' || $or->getSignedHash() === null) {
            return $this->json(['error' => 'Document non disponible'], Response::HTTP_NOT_FOUND);
        }

        // Le PDF est généré et figé à la finalisation (OrdreReparationPolicy).
        // Pas de régénération ici : un re-rendu depuis l'entité vivante pourrait
        // différer du document signé (notes mécano modifiables après coup).
        $pdfPath = $this->pdfService->getOrPdfPath($or);
        if (!is_file($pdfPath) || !is_readable($pdfPath)) {
            return $this->json([
                'error' => 'Document momentanément indisponible. Contactez votre atelier.',
            ], Response::HTTP_NOT_FOUND);
        }

        $response = new BinaryFileResponse($pdfPath);
        $response->headers->set('Content-Type', 'application/pdf');
        $response->setContentDisposition('attachment', 'OR-' . $or->getNumeroOr() . '.pdf');

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
                'numero_or' => $o->getNumeroOr(),
                'type_or' => $o->getTypeOr(),
                'travaux' => $o->getTravaux(),
                'signed_at' => $o->getSignedAt()?->format('c'),
                'vehicule_plaque' => $o->getRendezVous()->getVehicule()?->getPlaque(),
                'vehicule_info' => trim(($o->getRendezVous()->getVehicule()?->getMarque() ?? '') . ' ' . ($o->getRendezVous()->getVehicule()?->getModele() ?? '')),
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

        $items = array_map(fn(Vehicule $v) => [
            'id' => $v->getId(),
            'plaque' => $v->getPlaque(),
            'marque' => $v->getMarque(),
            'modele' => $v->getModele(),
            'type_moto' => $v->getTypeMoto(),
            'cylindree' => $v->getCylindree(),
            'annee' => $v->getAnnee(),
            'kilometrage' => $v->getKilometrage(),
        ], $client->getVehicules()->toArray());

        return $this->json($items);
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
        ]);
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
