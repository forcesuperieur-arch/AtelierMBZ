<?php
namespace App\Controller;

use App\Entity\Pont;
use App\Entity\RendezVous;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/ponts')]
class PontStatusController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {}

    #[Route('/status', methods: ['GET'], priority: 10)]
    public function status(): JsonResponse
    {
        $pontRepository = $this->em->getRepository(Pont::class);
        $ponts = $pontRepository->findBy([], ['ordreAffichage' => 'ASC', 'id' => 'ASC']);

        $result = [];
        foreach ($ponts as $pont) {
            $currentRdv = $this->em->getRepository(RendezVous::class)
                ->createQueryBuilder('r')
                ->where('r.pont = :pont')
                ->andWhere('r.statut IN (:statuts)')
                ->setParameter('pont', $pont)
                ->setParameter('statuts', ['en_cours', 'reception'])
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();

            $pontData = [
                'id' => $pont->getId(),
                'nom' => $pont->getNom(),
                'type_pont' => $pont->getTypePont(),
                'capacite_kg' => $pont->getCapaciteKg(),
                'is_active' => $pont->getIsActive(),
                'mecanicien' => null,
                'current_rdv' => null,
                'next_count' => 0,
            ];

            if ($pont->getMecanicien()) {
                $meca = $pont->getMecanicien();
                $pontData['mecanicien'] = [
                    'id' => $meca->getId(),
                    'nom' => $meca->getNom(),
                    'prenom' => $meca->getPrenom(),
                ];
            }

            // Count today's remaining RDVs for this pont
            $today = (new \DateTime())->format('Y-m-d');
            $pontData['next_count'] = (int) $this->em->getRepository(RendezVous::class)
                ->createQueryBuilder('r')
                ->select('COUNT(r.id)')
                ->where('r.pont = :pont')
                ->andWhere('r.dateRdv = :today')
                ->andWhere('r.statut NOT IN (:done)')
                ->setParameter('pont', $pont)
                ->setParameter('today', $today)
                ->setParameter('done', ['termine', 'restitue', 'annule', 'facture'])
                ->getQuery()->getSingleScalarResult();

            if ($currentRdv) {
                $elapsed = null;
                if ($currentRdv->getHeureDebutTravail()) {
                    $diff = $currentRdv->getHeureDebutTravail()->diff(new \DateTime());
                    $elapsed = $diff->h * 60 + $diff->i;
                }

                $clientName = '';
                if ($currentRdv->getClient()) {
                    $clientName = $currentRdv->getClient()->getPrenom() . ' ' . $currentRdv->getClient()->getNom();
                }

                $vehiculeInfo = '';
                if ($currentRdv->getVehicule()) {
                    $v = $currentRdv->getVehicule();
                    $vehiculeInfo = trim(($v->getMarque() ?? '') . ' ' . ($v->getModele() ?? ''));
                }

                $pontData['current_rdv'] = [
                    'id' => $currentRdv->getId(),
                    'status' => $currentRdv->getStatut(),
                    'type_intervention' => $currentRdv->getTypeIntervention(),
                    'client_nom' => $clientName,
                    'vehicule_info' => $vehiculeInfo,
                    'vehicule_plaque' => $currentRdv->getVehicule()?->getPlaque(),
                    'temps_estime' => $currentRdv->getTempsEstime(),
                    'temps_ecoule_minutes' => $elapsed,
                ];
            }

            $result[] = $pontData;
        }

        return $this->json($result);
    }
}
