<?php
namespace App\Controller;

use App\Entity\ModeleMoto;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/motos')]
class MotosLookupController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    #[Route('/marques', methods: ['GET'])]
    public function marques(): JsonResponse
    {
        $rows = $this->em->getRepository(ModeleMoto::class)
            ->createQueryBuilder('m')
            ->select('DISTINCT m.marque AS marque')
            ->where('m.marque IS NOT NULL')
            ->andWhere("m.marque != ''")
            ->orderBy('m.marque', 'ASC')
            ->getQuery()
            ->getArrayResult();

        $marques = array_values(array_filter(array_map(fn(array $row) => $row['marque'] ?? null, $rows)));

        return $this->json(['marques' => $marques]);
    }

    #[Route('/autocomplete', methods: ['GET'])]
    public function autocomplete(Request $request): JsonResponse
    {
        $marque = trim((string) $request->query->get('marque', ''));
        $query = trim((string) $request->query->get('query', ''));
        $limit = max(1, min(20, (int) $request->query->get('limit', 10)));

        $qb = $this->em->getRepository(ModeleMoto::class)
            ->createQueryBuilder('m')
            ->orderBy('m.modele', 'ASC')
            ->setMaxResults($limit);

        if ($marque !== '') {
            $qb->andWhere('LOWER(m.marque) = :marque')
                ->setParameter('marque', mb_strtolower($marque));
        }

        if ($query !== '') {
            $qb->andWhere('LOWER(m.modele) LIKE :query')
                ->setParameter('query', '%' . mb_strtolower($query) . '%');
        }

        $items = array_map(static fn(ModeleMoto $modele) => [
            'id' => $modele->getId(),
            'marque' => $modele->getMarque(),
            'modele' => $modele->getModele(),
            'cylindree' => $modele->getCylindreeDisplay(),
        ], $qb->getQuery()->getResult());

        return $this->json($items);
    }
}
