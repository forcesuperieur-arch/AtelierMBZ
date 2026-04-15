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
        $normalized = strtoupper((string) preg_replace('/[^A-Z0-9]/i', '', $query));

        if ($normalized === '') {
            return $this->json(['error' => 'Vehicle query is required'], 400);
        }

        $vehicule = $this->em->getRepository(Vehicule::class)
            ->createQueryBuilder('v')
            ->where("REPLACE(REPLACE(UPPER(v.plaque), '-', ''), ' ', '') = :normalized")
            ->setParameter('normalized', $normalized)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$vehicule) {
            $term = '%' . strtolower($query) . '%';
            $vehicule = $this->em->getRepository(Vehicule::class)
                ->createQueryBuilder('v')
                ->where("LOWER(COALESCE(v.plaque, '')) LIKE :term OR LOWER(COALESCE(v.marque, '')) LIKE :term OR LOWER(COALESCE(v.modele, '')) LIKE :term")
                ->setParameter('term', $term)
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();
        }

        if (!$vehicule) {
            return $this->json(['error' => 'Vehicle not found'], 404);
        }

        return $this->json([
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
}
