<?php
namespace App\Controller;

use App\Entity\ModeleMoto;
use App\Service\NgkMotoCatalogImporter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/motos')]
class MotosLookupController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private NgkMotoCatalogImporter $importer,
    ) {
    }

    private function ensureCatalogLoaded(): void
    {
        if ($this->em->getRepository(ModeleMoto::class)->count([]) > 0) {
            return;
        }

        try {
            $this->importer->importFromDefaultFile();
        } catch (\Throwable) {
            // L’autocomplétion reste vide si le fichier NGK est indisponible.
        }
    }

    #[Route('/marques', methods: ['GET'])]
    public function marques(): JsonResponse
    {
        $this->ensureCatalogLoaded();

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
        $this->ensureCatalogLoaded();

        $marque = trim((string) $request->query->get('marque', ''));
        $query = trim((string) $request->query->get('query', ''));
        $limit = max(1, min(20, (int) $request->query->get('limit', 10)));

        $qb = $this->em->getRepository(ModeleMoto::class)
            ->createQueryBuilder('m')
            ->leftJoin('m.categorie', 'c')
            ->addSelect('c')
            ->orderBy('m.marque', 'ASC')
            ->addOrderBy('m.cylindreeMin', 'ASC')
            ->addOrderBy('m.modele', 'ASC')
            ->addOrderBy('m.anneeDebut', 'ASC')
            ->setMaxResults($limit);

        if ($marque !== '') {
            $qb->andWhere('LOWER(m.marque) LIKE :marque')
                ->setParameter('marque', '%' . mb_strtolower($marque) . '%');
        }

        if ($query !== '') {
            $qb->andWhere('LOWER(m.modele) LIKE :query OR LOWER(m.marque) LIKE :query')
                ->setParameter('query', '%' . mb_strtolower($query) . '%');
        }

        $items = array_map(static fn(ModeleMoto $modele) => [
            'id' => $modele->getId(),
            'marque' => $modele->getMarque(),
            'modele' => $modele->getModele(),
            'categorie_id' => $modele->getCategorie()->getId(),
            'categorie_nom' => $modele->getCategorie()->getNom(),
            'cylindree' => $modele->getCylindreeDisplay(),
            'cylindree_display' => $modele->getCylindreeDisplay(),
            'cylindree_min' => $modele->getCylindreeMin(),
            'cylindree_max' => $modele->getCylindreeMax(),
            'annee_debut' => $modele->getAnneeDebut(),
            'annee_fin' => $modele->getAnneeFin(),
            'annees_display' => match (true) {
                $modele->getAnneeDebut() !== null && $modele->getAnneeFin() !== null => sprintf('%d-%d', $modele->getAnneeDebut(), $modele->getAnneeFin()),
                $modele->getAnneeDebut() !== null => sprintf('%d+', $modele->getAnneeDebut()),
                default => null,
            },
        ], $qb->getQuery()->getResult());

        return $this->json($items);
    }
}
