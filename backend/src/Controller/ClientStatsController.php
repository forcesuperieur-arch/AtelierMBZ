<?php

namespace App\Controller;

use App\Entity\Client;
use App\Entity\Facture;
use App\Entity\RendezVous;
use App\Entity\Vehicule;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class ClientStatsController extends AbstractController
{
    #[Route('/api/clients/stats', methods: ['GET'], priority: 20)]
    public function __invoke(EntityManagerInterface $entityManager): JsonResponse
    {
        $total = (int) $entityManager->getRepository(Client::class)
            ->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $avecRdv = (int) $entityManager->getRepository(RendezVous::class)
            ->createQueryBuilder('r')
            ->select('COUNT(DISTINCT r.client)')
            ->where('r.client IS NOT NULL')
            ->getQuery()
            ->getSingleScalarResult();

        $vehicules = (int) $entityManager->getRepository(Vehicule::class)
            ->createQueryBuilder('v')
            ->select('COUNT(v.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $caTotal = (float) $entityManager->getRepository(Facture::class)
            ->createQueryBuilder('f')
            ->select('COALESCE(SUM(f.totalTtc), 0)')
            ->getQuery()
            ->getSingleScalarResult();

        return $this->json([
            'total' => $total,
            'avec_rdv' => $avecRdv,
            'vehicules' => $vehicules,
            'ca_total' => $caTotal,
        ]);
    }
}
