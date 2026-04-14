<?php
namespace App\Controller;

use App\Entity\PieceDetachee;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api/stock')]
class StockController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private SerializerInterface $serializer,
    ) {}

    #[Route('/alertes', methods: ['GET'], priority: 10)]
    public function alertes(): JsonResponse
    {
        $pieces = $this->em->getRepository(PieceDetachee::class)
            ->createQueryBuilder('p')
            ->where('p.quantiteStock <= p.quantiteMinimale')
            ->andWhere('p.isActive = 1')
            ->orderBy('p.quantiteStock', 'ASC')
            ->getQuery()
            ->getResult();

        $data = json_decode($this->serializer->serialize($pieces, 'json', ['groups' => ['piece:read']]), true);
        return $this->json($data);
    }
}
