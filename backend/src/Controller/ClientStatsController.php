<?php

namespace App\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class ClientStatsController extends AbstractController
{
    #[Route('/api/clients/stats', methods: ['GET'], priority: 20)]
    public function __invoke(EntityManagerInterface $entityManager): JsonResponse
    {
        $connection = $entityManager->getConnection();

        return $this->json([
            'total' => (int) $connection->fetchOne('SELECT COUNT(*) FROM clients'),
            'avec_rdv' => (int) $connection->fetchOne('SELECT COUNT(DISTINCT client_id) FROM rendez_vous WHERE client_id IS NOT NULL'),
            'vehicules' => (int) $connection->fetchOne('SELECT COUNT(*) FROM vehicules'),
            'ca_total' => (float) $connection->fetchOne('SELECT COALESCE(SUM(total_ttc), 0) FROM factures'),
        ]);
    }
}
