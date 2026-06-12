<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Client;
use App\Entity\RendezVous;
use App\Service\OrdreReparationPolicy;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Public appointment tracking endpoint (no auth required).
 * Finds an active appointment by client email + phone.
 */
#[Route('/api/public')]
class PublicSuiviController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private RateLimiterFactory $publicBookingLimiter,
        private OrdreReparationPolicy $ordreReparationPolicy,
    ) {}

    #[Route('/suivi', methods: ['POST'])]
    public function suivi(Request $request): JsonResponse
    {
        $limiter = $this->publicBookingLimiter->create($request->getClientIp());
        if (!$limiter->consume()->isAccepted()) {
            return $this->json(['error' => 'Too many requests'], Response::HTTP_TOO_MANY_REQUESTS);
        }

        $data = json_decode($request->getContent(), true);
        $email = strtolower(trim($data['email'] ?? ''));
        $telephone = preg_replace('/[\s\-\.]+/', '', $data['telephone'] ?? '');

        if (empty($email) || empty($telephone)) {
            return $this->json(['error' => 'email and telephone are required'], Response::HTTP_BAD_REQUEST);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->json(['error' => 'Invalid email format'], Response::HTTP_BAD_REQUEST);
        }

        // Find client by email and phone
        $client = $this->em->getRepository(Client::class)
            ->createQueryBuilder('c')
            ->where('LOWER(c.email) = :email')
            ->andWhere('c.telephone = :telephone')
            ->setParameter('email', $email)
            ->setParameter('telephone', $telephone)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$client) {
            return $this->json(['error' => 'Aucun rendez-vous trouvé pour ces coordonnées.'], Response::HTTP_NOT_FOUND);
        }

        // Find active RDV for this client
        $rdv = $this->em->getRepository(RendezVous::class)
            ->createQueryBuilder('r')
            ->where('r.client = :client')
            ->andWhere('r.statut NOT IN (:excluded)')
            ->setParameter('client', $client)
            ->setParameter('excluded', ['termine', 'annule'])
            ->orderBy('r.dateRdv', 'DESC')
            ->addOrderBy('r.heureRdv', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$rdv) {
            return $this->json(['error' => 'Aucun rendez-vous actif trouvé pour ce client.'], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'rdv' => [
                'id' => $rdv->getId(),
                'date' => $rdv->getDateRdv()?->format('Y-m-d'),
                'heure' => $rdv->getHeureRdv()?->format('H:i'),
                'statut' => $rdv->getStatut(),
                'type_intervention' => $rdv->getTypeIntervention(),
                'temps_estime' => $rdv->getTempsEstime(),
                'pont' => $rdv->getPont()?->getNom(),
                'mecanicien' => $rdv->getMecanicien()
                    ? $rdv->getMecanicien()->getPrenom() . ' ' . $rdv->getMecanicien()->getNom()
                    : null,
            ],
            'client' => [
                'nom' => $client->getNom(),
                'prenom' => $client->getPrenom(),
                'telephone' => $client->getTelephone(),
                'email' => $client->getEmail(),
            ],
        ]);
    }

    /**
     * Public endpoint to retrieve restitution data for a given tracking token.
     * Returns RDV + OR info so the client can review and sign before pickup.
     */
    #[Route('/restitution/{token}', methods: ['GET'])]
    public function restitutionData(string $token): JsonResponse
    {
        $rdv = $this->em->getRepository(RendezVous::class)
            ->createQueryBuilder('r')
            ->leftJoin('r.ordresReparation', 'ordre')
            ->addSelect('ordre')
            ->leftJoin('r.client', 'c')
            ->addSelect('c')
            ->leftJoin('r.vehicule', 'v')
            ->addSelect('v')
            ->where('r.tokenSuivi = :token')
            ->setParameter('token', $token)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$rdv) {
            return $this->json(['error' => 'Lien de restitution invalide.'], Response::HTTP_NOT_FOUND);
        }

        $ordre = $rdv->getOrdresReparation()->first() ?: null;

        // Only allow restitution when work is done (mechanic signed)
        if (!$ordre || !$this->ordreReparationPolicy->canSignRestitution($ordre)) {
            return $this->json([
                'error' => 'Véhicule non prêt pour restitution.',
                'code' => 'NOT_READY',
            ], Response::HTTP_BAD_REQUEST);
        }

        return $this->json([
            'rdv' => [
                'id' => $rdv->getId(),
                'date' => $rdv->getDateRdv()?->format('Y-m-d'),
                'heure' => $rdv->getHeureRdv()?->format('H:i'),
                'statut' => $rdv->getStatut(),
                'type_intervention' => $rdv->getTypeIntervention(),
                'pont' => $rdv->getPont()?->getNom(),
                'mecanicien' => $rdv->getMecanicien()
                    ? $rdv->getMecanicien()->getPrenom() . ' ' . $rdv->getMecanicien()->getNom()
                    : null,
            ],
            'client' => [
                'nom' => $rdv->getClient()?->getNom(),
                'prenom' => $rdv->getClient()?->getPrenom(),
                'telephone' => $rdv->getClient()?->getTelephone(),
            ],
            'vehicule' => [
                'marque' => $rdv->getVehicule()?->getMarque(),
                'modele' => $rdv->getVehicule()?->getModele(),
                'plaque' => $rdv->getVehicule()?->getPlaque(),
            ],
            'ordre' => [
                'id' => $ordre->getId(),
                'travaux_realises' => $ordre->getTravauxRealises(),
                'alertes' => $ordre->getAlertes(),
                'recommandations' => $ordre->getRecommandations(),
                'garantie' => $ordre->getGarantie(),
                'kilometrage_restitution' => $ordre->getKilometrageRestitution(),
                'prochaine_revision_km' => $ordre->getProchaineRevisionKm(),
                'prochaine_revision_date' => $ordre->getProchaineRevisionDate()?->format('Y-m-d'),
                'signature_mecanicien' => $ordre->getSignatureMecanicien() !== null,
                'signature_client_restitution' => $ordre->getSignatureClientRestitution() !== null,
            ],
        ]);
    }

    /**
     * Public endpoint to sign the restitution (client signature at pickup).
     */
    #[Route('/restitution/{token}/sign', methods: ['POST'])]
    public function signRestitution(string $token, Request $request): JsonResponse
    {
        $limiter = $this->publicBookingLimiter->create($request->getClientIp());
        if (!$limiter->consume()->isAccepted()) {
            return $this->json(['error' => 'Too many requests'], Response::HTTP_TOO_MANY_REQUESTS);
        }

        $rdv = $this->em->getRepository(RendezVous::class)
            ->createQueryBuilder('r')
            ->leftJoin('r.ordresReparation', 'ordre')
            ->addSelect('ordre')
            ->where('r.tokenSuivi = :token')
            ->setParameter('token', $token)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$rdv) {
            return $this->json(['error' => 'Lien de restitution invalide.'], Response::HTTP_NOT_FOUND);
        }

        $ordre = $rdv->getOrdresReparation()->first() ?: null;

        if (!$ordre || !$this->ordreReparationPolicy->canSignRestitution($ordre)) {
            return $this->json([
                'error' => 'Restitution non autorisée.',
                'code' => 'NOT_READY',
            ], Response::HTTP_BAD_REQUEST);
        }

        $data = json_decode($request->getContent(), true);
        $signature = trim((string) ($data['signature'] ?? ''));

        if (empty($signature) || !str_starts_with($signature, 'data:image')) {
            return $this->json(['error' => 'Signature invalide.'], Response::HTTP_BAD_REQUEST);
        }

        $this->ordreReparationPolicy->signRestitution($ordre, $signature);
        $this->em->flush();

        return $this->json([
            'success' => true,
            'message' => 'Restitution signée avec succès.',
            'statut' => $ordre->getStatut(),
        ]);
    }
}
