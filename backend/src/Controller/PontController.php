<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Pont;
use App\Entity\RendezVous;
use App\Service\SlotService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/api/ponts')]
class PontController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private SlotService $slotService,
    ) {}

    /**
     * Unassign a mechanic from a lift.
     * If future appointments exist, returns them + suggested alternative slots.
     * Otherwise, performs the unassignment directly.
     */
    #[Route('/{id}/unassign', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function unassign(int $id): JsonResponse
    {
        $pont = $this->em->getRepository(Pont::class)->find($id);
        if (!$pont) {
            return $this->json(['error' => 'Pont not found'], Response::HTTP_NOT_FOUND);
        }

        $mecanicien = $pont->getMecanicien();
        if (!$mecanicien) {
            return $this->json(['error' => 'No mechanic assigned to this pont'], Response::HTTP_BAD_REQUEST);
        }

        $today = new \DateTimeImmutable('today');
        $rdvRepo = $this->em->getRepository(RendezVous::class);
        $qb = $rdvRepo->createQueryBuilder('r')
            ->where('r.pont = :pont')
            ->andWhere('r.mecanicien = :mecanicien')
            ->andWhere('r.dateRdv >= :today')
            ->andWhere('r.statut NOT IN (:excluded)')
            ->setParameter('pont', $pont)
            ->setParameter('mecanicien', $mecanicien)
            ->setParameter('today', $today->format('Y-m-d'))
            ->setParameter('excluded', ['annule', 'termine'])
            ->orderBy('r.dateRdv', 'ASC')
            ->addOrderBy('r.heureRdv', 'ASC');

        $futureRdvs = $qb->getQuery()->getResult();

        // Suggest alternative slots for the next 7 days
        $suggestedSlots = [];
        if (!empty($futureRdvs)) {
            $endDate = $today->modify('+7 days');
            $available = $this->slotService->getAvailableSlots($today, $endDate, 60, $pont->getAtelierId());
            $count = 0;
            foreach ($available as $date => $slots) {
                foreach ($slots as $slot) {
                    $suggestedSlots[] = [
                        'date' => $date,
                        'heure' => $slot['heure'],
                        'pont_id' => $slot['pont_id'],
                        'mecanicien_id' => $slot['mecanicien_id'],
                    ];
                    $count++;
                    if ($count >= 3) {
                        break 2;
                    }
                }
            }
        }

        if (!empty($futureRdvs)) {
            return $this->json([
                'canUnassign' => false,
                'message' => 'Des rendez-vous futurs sont planifiés pour ce mécanicien sur ce pont.',
                'futureRdvs' => array_map(fn(RendezVous $r) => [
                    'id' => $r->getId(),
                    'date' => $r->getDateRdv()?->format('Y-m-d'),
                    'heure' => $r->getHeureRdv()?->format('H:i'),
                    'client' => $r->getClient() ? $r->getClient()->getPrenom() . ' ' . $r->getClient()->getNom() : null,
                    'statut' => $r->getStatut(),
                ], $futureRdvs),
                'suggestedSlots' => $suggestedSlots,
            ], Response::HTTP_CONFLICT);
        }

        // No future RDVs — proceed with unassignment
        $pont->setMecanicien(null);
        $this->em->persist($pont);
        $this->em->flush();

        return $this->json([
            'success' => true,
            'message' => 'Mécanicien désassigné avec succès.',
            'pont' => [
                'id' => $pont->getId(),
                'nom' => $pont->getNom(),
                'mecanicien' => null,
            ],
        ]);
    }
}
