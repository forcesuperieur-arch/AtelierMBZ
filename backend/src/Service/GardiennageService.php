<?php

namespace App\Service;

use App\Entity\ConfigAtelier;
use App\Entity\RendezVous;
use Doctrine\ORM\EntityManagerInterface;

class GardiennageService
{
    public function __construct(
        private EntityManagerInterface $em,
        private JoursOuvresService $joursOuvres,
    ) {}

    public function peutDeclencher(RendezVous $rdv): bool
    {
        // Must be in termine or en_attente_pieces or en_gardiennage-related state
        if (!in_array($rdv->getStatut(), ['termine', 'en_attente_pieces', 'restitue_partiel'], true)) {
            return false;
        }

        // Already in gardiennage
        if ($rdv->getGardiennageDebutAt()) {
            return false;
        }

        return true;
    }

    public function declencher(RendezVous $rdv, int $userId, string $motif): void
    {
        $rdv->setGardiennageDebutAt(new \DateTime());
        $rdv->setGardiennageDebutPar($userId);
        $rdv->setGardiennageMotif($motif);
        $this->em->flush();
    }

    public function calculerMontant(RendezVous $rdv, \DateTime $dateRestitution): string
    {
        $debut = $rdv->getGardiennageDebutAt();
        if (!$debut) {
            return '0.00';
        }

        $atelierId = $rdv->getAtelierId() ?? 0;
        $joursOuvres = $this->joursOuvres->compterJoursOuvres(
            \DateTime::createFromInterface($debut),
            $dateRestitution,
            $atelierId,
        );

        $config = $this->em->getRepository(ConfigAtelier::class)->findOneBy(['atelierId' => $atelierId]);
        if (!$config) {
            throw new \RuntimeException(sprintf('ConfigAtelier introuvable pour atelier %d — impossible de calculer le gardiennage', $atelierId));
        }
        $tarif = $config->getTarifGardiennageJournalier() ?? '5.00';

        return bcmul((string)$joursOuvres, $tarif, 2);
    }
}
