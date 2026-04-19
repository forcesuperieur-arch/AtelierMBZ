<?php
namespace App\Controller;

use App\Entity\Vehicule;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class VehiculeLookupController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    #[Route('/api/vehicule/{query}', methods: ['GET'], priority: 20)]
    public function show(string $query): JsonResponse
    {
        $normalized = $this->normalizeVehicleQuery($query);

        if ($normalized === '') {
            return $this->json(['error' => 'Vehicle query is required'], 400);
        }

        $vehicule = $this->findByNormalizedPlate($normalized, $query);

        if (!$vehicule) {
            $term = '%' . mb_strtolower(trim($query)) . '%';
            $vehicule = $this->em->getRepository(Vehicule::class)
                ->createQueryBuilder('v')
                ->where('LOWER(v.plaque) LIKE :term OR LOWER(v.marque) LIKE :term OR LOWER(v.modele) LIKE :term')
                ->setParameter('term', $term)
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();
        }

        if (!$vehicule) {
            return $this->json([
                'found' => false,
                'query' => $query,
            ]);
        }

        return $this->json([
            'found' => true,
            'id' => $vehicule->getId(),
            'plaque' => $vehicule->getPlaque(),
            'marque' => $vehicule->getMarque(),
            'modele' => $vehicule->getModele(),
            'annee' => $vehicule->getAnnee(),
            'cylindree' => $vehicule->getCylindree(),
            'type_moto' => $vehicule->getTypeMoto(),
            'client_id' => $vehicule->getClient()?->getId(),
        ]);
    }

    private function findByNormalizedPlate(string $normalized, string $query): ?Vehicule
    {
        $variants = $this->buildPlateVariants($normalized, $query);

        $candidates = $this->em->getRepository(Vehicule::class)
            ->createQueryBuilder('v')
            ->where('UPPER(v.plaque) IN (:variants)')
            ->setParameter('variants', $variants)
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();

        foreach ($candidates as $candidate) {
            if (!$candidate instanceof Vehicule) {
                continue;
            }

            if ($this->normalizeVehicleQuery((string) $candidate->getPlaque()) === $normalized) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function buildPlateVariants(string $normalized, string $query): array
    {
        $variants = [
            mb_strtoupper(trim($query)),
            $normalized,
        ];

        if (strlen($normalized) === 7) {
            $variants[] = sprintf('%s-%s-%s', substr($normalized, 0, 2), substr($normalized, 2, 3), substr($normalized, 5, 2));
            $variants[] = sprintf('%s %s %s', substr($normalized, 0, 2), substr($normalized, 2, 3), substr($normalized, 5, 2));
        }

        return array_values(array_unique(array_filter($variants)));
    }

    private function normalizeVehicleQuery(string $query): string
    {
        return mb_strtoupper((string) preg_replace('/[^A-Z0-9]/i', '', $query));
    }
}
