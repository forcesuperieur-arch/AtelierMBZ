<?php

namespace App\Service;

use App\Entity\EssaiRoutier;
use App\Entity\OrdreReparation;
use App\Entity\PhotoIntervention;
use App\Entity\RapportIntervention;
use App\Entity\RendezVous;
use Doctrine\ORM\EntityManagerInterface;

class RapportInterventionService
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {}

    public function createDraft(RendezVous $rdv): RapportIntervention
    {
        $rapport = new RapportIntervention();
        $rapport->setRendezVous($rdv);
        $rapport->setAtelierId($rdv->getAtelierId());
        $rapport->setStatut('brouillon');

        $this->prefillFromOR($rapport, $rdv);

        $this->em->persist($rapport);
        $this->em->flush();

        return $rapport;
    }

    public function prefillFromOR(RapportIntervention $rapport, RendezVous $rdv): void
    {
        $or = $this->em->getRepository(OrdreReparation::class)->findOneBy(
            ['rendezVous' => $rdv],
            ['id' => 'DESC'],
        );

        if (!$or) {
            return;
        }

        if ($or->getTravaux()) {
            $rapport->setTravauxRealises($or->getTravaux());
        }
        if (method_exists($rdv, 'getKilometrage') && $rdv->getKilometrage()) {
            $rapport->setKilometrageRestitution($rdv->getKilometrage());
        }
    }

    public function calculateNextRevision(?int $currentKm): array
    {
        $nextKm = $currentKm ? $currentKm + 10000 : null;
        $nextDate = (new \DateTime())->modify('+1 year');

        return [
            'km' => $nextKm,
            'date' => $nextDate,
        ];
    }

    /**
     * Validate completeness before signature.
     * Returns array of errors (empty = valid).
     */
    public function validateCompleteness(RapportIntervention $rapport): array
    {
        $errors = [];

        // Essai routier must be complete
        $essai = $this->resolveEssai($rapport);
        if (!$essai || !$essai->isComplete()) {
            $errors[] = 'Essai routier incomplet ou manquant';
        }

        if ($essai && (($essai->getDureeMinutes() ?? 0) <= 0)) {
            $errors[] = 'Durée d\'essai routier non renseignée';
        }

        // Travaux réalisés >= 20 chars
        if (!$rapport->getTravauxRealises() || mb_strlen(trim($rapport->getTravauxRealises())) < 20) {
            $errors[] = 'Description des travaux réalisés trop courte (min 20 caractères)';
        }

        // Recommandations filled
        if (!$rapport->getRecommandations() || mb_strlen(trim($rapport->getRecommandations())) < 5) {
            $errors[] = 'Recommandations non renseignées';
        }

        // ≥ 3 restitution photos
        $photoCount = $this->em->getRepository(PhotoIntervention::class)->count([
            'rendezVous' => $rapport->getRendezVous(),
            'type' => 'restitution',
        ]);
        if ($photoCount < 3) {
            $errors[] = sprintf('Minimum 3 photos de restitution requises (%d actuellement)', $photoCount);
        }

        // If anomalies in essai, corrective actions required
        if ($essai && $essai->hasAnomalies() && !$essai->getActionsCorrectives()) {
            $errors[] = 'Actions correctives requises suite aux anomalies constatées';
        }

        return $errors;
    }

    public function signByMecanicien(RapportIntervention $rapport, string $signature, int $mecanicienId): void
    {
        $rapport->setSignatureMecanicien($signature);
        $rapport->setSigneMecanicienAt(new \DateTime());
        $rapport->setSigneMecanicienId($mecanicienId);
        $rapport->setStatut('en_validation');
        $rapport->setUpdatedAt(new \DateTime());

        $this->em->flush();
    }

    public function signByClient(RapportIntervention $rapport, string $signature, string $ip): void
    {
        $rapport->setSignatureClient($signature);
        $rapport->setSigneClientAt(new \DateTime());
        $rapport->setSignedIp($ip);

        // Create snapshot
        $snapshot = $this->buildSnapshot($rapport);
        $rapport->setSignedSnapshot($snapshot);
        $rapport->setSignedHash(hash('sha256', json_encode($snapshot)));
        $rapport->setStatut('signe');
        $rapport->setUpdatedAt(new \DateTime());

        $this->em->flush();
    }

    public function rectify(RapportIntervention $original, string $motif, int $userId): RapportIntervention
    {
        $original->setStatut('rectifie');
        $original->setUpdatedAt(new \DateTime());

        $newRapport = new RapportIntervention();
        $newRapport->setRendezVous($original->getRendezVous());
        $newRapport->setAtelierId($original->getAtelierId());
        $newRapport->setStatut('brouillon');
        $newRapport->setTravauxRealises($original->getTravauxRealises());
        $newRapport->setAlertes($original->getAlertes());
        $newRapport->setRecommandations($original->getRecommandations());
        $newRapport->setProchaineRevisionKm($original->getProchaineRevisionKm());
        $newRapport->setProchaineRevisionDate($original->getProchaineRevisionDate());
        $newRapport->setKilometrageRestitution($original->getKilometrageRestitution());
        $newRapport->setGarantie($original->getGarantie());
        $newRapport->setRectifiedFrom($original);
        $newRapport->setMotifRectification($motif);
        $newRapport->setRectifiedBy($userId);
        $newRapport->setRectifiedAt(new \DateTime());

        $this->em->persist($newRapport);
        $this->em->flush();

        return $newRapport;
    }

    private function buildSnapshot(RapportIntervention $rapport): array
    {
        $rdv = $rapport->getRendezVous();

        return [
            'rapport_id' => $rapport->getId(),
            'rdv_id' => $rdv->getId(),
            'travaux_realises' => $rapport->getTravauxRealises(),
            'alertes' => $rapport->getAlertes(),
            'recommandations' => $rapport->getRecommandations(),
            'prochaine_revision_km' => $rapport->getProchaineRevisionKm(),
            'prochaine_revision_date' => $rapport->getProchaineRevisionDate()?->format('Y-m-d'),
            'kilometrage_restitution' => $rapport->getKilometrageRestitution(),
            'garantie' => $rapport->getGarantie(),
            'signe_mecanicien_at' => $rapport->getSigneMecanicienAt()?->format('c'),
            'signe_client_at' => $rapport->getSigneClientAt()?->format('c'),
            'snapshot_at' => (new \DateTime())->format('c'),
        ];
    }

    private function resolveEssai(RapportIntervention $rapport): ?EssaiRoutier
    {
        return $rapport->getEssaiRoutier()
            ?? $this->em->getRepository(EssaiRoutier::class)->findOneBy(
                ['rendezVous' => $rapport->getRendezVous()],
                ['id' => 'DESC'],
            );
    }
}
