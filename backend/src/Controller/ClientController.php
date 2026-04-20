<?php
namespace App\Controller;

use App\Entity\Client;
use App\Entity\Facture;
use App\Entity\Devis;
use App\Entity\RappelEmail;
use App\Entity\RendezVous;
use App\Entity\Vehicule;
use App\Service\AuditService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class ClientController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private AuditService $audit,
    ) {}

    #[Route('/api/clients', methods: ['GET'], priority: 20)]
    public function list(Request $request): JsonResponse
    {
        $qb = $this->em->getRepository(Client::class)
            ->createQueryBuilder('c')
            ->leftJoin('c.vehicules', 'v')
            ->addSelect('v')
            ->orderBy('c.nom', 'ASC')
            ->addOrderBy('c.prenom', 'ASC');

        $search = trim((string) $request->query->get('search', ''));

        if ($search !== '') {
            $tokens = preg_split('/\s+/', mb_strtolower($search), -1, PREG_SPLIT_NO_EMPTY) ?: [];

            foreach ($tokens as $index => $token) {
                $param = 'term_' . $index;
                $qb
                    ->andWhere("(LOWER(c.nom) LIKE :$param OR LOWER(c.prenom) LIKE :$param OR LOWER(COALESCE(c.telephone, '')) LIKE :$param OR LOWER(COALESCE(c.email, '')) LIKE :$param)")
                    ->setParameter($param, '%' . $token . '%');
            }
        } else {
            foreach (['nom', 'prenom', 'telephone', 'email'] as $field) {
                $value = trim((string) $request->query->get($field, ''));
                if ($value === '') {
                    continue;
                }

                $qb
                    ->andWhere(sprintf("LOWER(COALESCE(c.%s, '')) LIKE :%s", $field, $field))
                    ->setParameter($field, '%' . mb_strtolower($value) . '%');
            }
        }

        $total = (int) (clone $qb)
            ->select('COUNT(DISTINCT c.id)')
            ->resetDQLPart('orderBy')
            ->getQuery()
            ->getSingleScalarResult();

        $page = max(1, (int) $request->query->get('page', 1));
        $limit = max(1, min(200, (int) $request->query->get('limit', 50)));

        $clients = $qb
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        $items = array_map(function (Client $client): array {
            $vehicules = array_map(fn($vehicule) => [
                'id' => $vehicule->getId(),
                'plaque' => $vehicule->getPlaque(),
                'marque' => $vehicule->getMarque(),
                'modele' => $vehicule->getModele(),
                'annee' => $vehicule->getAnnee(),
            ], $client->getVehicules()->toArray());

            return [
                '@id' => '/api/clients/' . $client->getId(),
                '@type' => 'Client',
                'id' => $client->getId(),
                'atelier_id' => $client->getAtelierId(),
                'nom' => $client->getNom(),
                'prenom' => $client->getPrenom(),
                'telephone' => $client->getTelephone(),
                'email' => $client->getEmail(),
                'adresse' => $client->getAdresse(),
                'created_at' => $client->getCreatedAt()->format(DATE_ATOM),
                'vehicules' => $vehicules,
                'vehicules_count' => count($vehicules),
                'rdv_count' => $client->getRendezVous()->count(),
            ];
        }, $clients);

        return $this->json([
            '@context' => '/api/contexts/Client',
            '@id' => '/api/clients',
            '@type' => 'Collection',
            'totalItems' => $total,
            'hydra:totalItems' => $total,
            'member' => $items,
            'hydra:member' => $items,
            'items' => $items,
            'page' => $page,
            'pages' => max(1, (int) ceil($total / $limit)),
        ]);
    }

    /**
     * RGPD: Anonymize a client (Art. 17 - Right to erasure).
     * Replaces PII with generic values, breaks vehicle link, preserves invoices via snapshots.
     */
    #[Route('/api/clients/{id}/anonymize', methods: ['POST'], priority: 30)]
    public function anonymize(int $id): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $client = $this->em->getRepository(Client::class)->find($id);
        if (!$client) {
            return $this->json(['error' => 'Client not found'], Response::HTTP_NOT_FOUND);
        }
        if ($client->getIsAnonymized()) {
            return $this->json(['error' => 'Client already anonymized'], Response::HTTP_BAD_REQUEST);
        }

        // Check all invoices have snapshots
        $facturesSansSnapshot = $this->em->getRepository(Facture::class)
            ->createQueryBuilder('f')
            ->select('COUNT(f.id)')
            ->where('f.client = :client')
            ->andWhere('f.snapClientNom IS NULL')
            ->setParameter('client', $client)
            ->getQuery()->getSingleScalarResult();

        if ((int) $facturesSansSnapshot > 0) {
            return $this->json([
                'error' => "Impossible d'anonymiser : {$facturesSansSnapshot} facture(s) n'ont pas de snapshot. Exécutez d'abord la commande app:backfill-snapshots.",
            ], Response::HTTP_CONFLICT);
        }

        // Anonymize client PII
        $client->setNom('ANONYME');
        $client->setPrenom('ANONYME');
        $client->setEmail(null);
        $client->setTelephone('0000000000');
        $client->setAdresse(null);
        $client->setNotes(null);
        $client->setConsentDate(null);
        $client->setConsentSource(null);
        $client->setIsAnonymized(true);

        // Anonymize vehicles: break link + mask plate
        foreach ($client->getVehicules() as $vehicule) {
            $vehicule->setPlaque('XX-000-XX');
            $vehicule->setClient(null);
        }

        // Anonymize reminder emails
        $rappels = $this->em->getRepository(RappelEmail::class)->findBy(['client' => $client]);
        foreach ($rappels as $rappel) {
            $rappel->setDestinataire('anonyme@anonyme.local');
            $rappel->setSujet(null);
        }

        $this->em->flush();

        $this->audit->log('rgpd_anonymize', 'client', $id, 'Client anonymized per RGPD request');

        return $this->json(['success' => true, 'message' => 'Client anonymisé avec succès']);
    }

    /**
     * RGPD: Export all client data (Art. 20 - Data portability).
     */
    #[Route('/api/clients/{id}/export', methods: ['GET'], priority: 30)]
    public function export(int $id): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $client = $this->em->getRepository(Client::class)->find($id);
        if (!$client) {
            return $this->json(['error' => 'Client not found'], Response::HTTP_NOT_FOUND);
        }

        $vehicules = array_map(fn(Vehicule $v) => [
            'id' => $v->getId(),
            'plaque' => $v->getPlaque(),
            'marque' => $v->getMarque(),
            'modele' => $v->getModele(),
            'annee' => $v->getAnnee(),
            'cylindree' => $v->getCylindree(),
            'type_moto' => $v->getTypeMoto(),
        ], $client->getVehicules()->toArray());

        $rdvs = array_map(fn(RendezVous $r) => [
            'id' => $r->getId(),
            'date_rdv' => $r->getDateRdv()->format('Y-m-d'),
            'heure_rdv' => $r->getHeureRdv()->format('H:i'),
            'type_intervention' => $r->getTypeIntervention(),
            'statut' => $r->getStatut(),
            'commentaire' => $r->getCommentaire(),
            'prix_estime' => $r->getPrixEstime(),
            'prix_final' => $r->getPrixFinal(),
        ], $client->getRendezVous()->toArray());

        $factures = $this->em->getRepository(Facture::class)->findBy(['client' => $client]);
        $facturesData = array_map(fn(Facture $f) => [
            'id' => $f->getId(),
            'numero' => $f->getNumeroFacture(),
            'total_ttc' => $f->getTotalTtc(),
            'statut' => $f->getStatut(),
            'date_creation' => $f->getDateCreation()->format('Y-m-d'),
        ], $factures);

        $devis = $this->em->getRepository(Devis::class)->findBy(['client' => $client]);
        $devisData = array_map(fn(Devis $d) => [
            'id' => $d->getId(),
            'numero' => $d->getNumeroDevis(),
            'total_ttc' => $d->getTotalTtc(),
            'statut' => $d->getStatut(),
            'date_creation' => $d->getDateCreation()->format('Y-m-d'),
        ], $devis);

        return $this->json([
            'export_date' => (new \DateTime())->format(DATE_ATOM),
            'client' => [
                'id' => $client->getId(),
                'nom' => $client->getNom(),
                'prenom' => $client->getPrenom(),
                'telephone' => $client->getTelephone(),
                'email' => $client->getEmail(),
                'adresse' => $client->getAdresse(),
                'notes' => $client->getNotes(),
                'consent_date' => $client->getConsentDate()?->format(DATE_ATOM),
                'consent_source' => $client->getConsentSource(),
                'created_at' => $client->getCreatedAt()->format(DATE_ATOM),
                'last_activity_at' => $client->getLastActivityAt()?->format(DATE_ATOM),
            ],
            'vehicules' => $vehicules,
            'rendez_vous' => $rdvs,
            'factures' => $facturesData,
            'devis' => $devisData,
        ]);
    }
}
