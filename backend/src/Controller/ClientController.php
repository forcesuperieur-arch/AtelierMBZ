<?php
namespace App\Controller;

use App\Entity\Client;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class ClientController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

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
}
