<?php

namespace App\Controller;

use App\Entity\Client;
use App\Entity\OrdreReparation;
use App\Entity\RendezVous;
use App\Entity\Vehicule;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/client')]
class ClientEspaceController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em) {}

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
            'ordres_reparation' => array_map(fn($o) => [
                'id' => $o->getId(),
                'numero_or' => $o->getNumeroOr(),
                'type_or' => $o->getTypeOr(),
                'travaux' => $o->getTravaux(),
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
