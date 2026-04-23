<?php

namespace App\Service;

use App\Entity\ConfigAtelier;
use App\Entity\RendezVous;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class GardiennageService
{
    public function __construct(
        private EntityManagerInterface $em,
        private JoursOuvresService $joursOuvres,
        private NotificationDispatcher $dispatcher,
        private LoggerInterface $logger,
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

        $this->notifierEntreeGardiennage($rdv);
    }

    /**
     * Envoie un email immédiat au client à l'entrée en gardiennage.
     * Choix produit : email seul (pas de SMS) — un client qui ne vient pas
     * récupérer sa moto lit peu ses SMS, et le SMS coûte de l'argent.
     * Idempotent : silently ignore si pas d'email client ou si dispatcher échoue.
     */
    public function notifierEntreeGardiennage(RendezVous $rdv): void
    {
        $client = $rdv->getClient();
        if (!$client || !$client->getEmail()) {
            return;
        }

        $atelierId = $rdv->getAtelierId() ?? 0;
        $config = $this->em->getRepository(ConfigAtelier::class)->findOneBy(['atelierId' => $atelierId]);
        $tarif = $config?->getTarifGardiennageJournalier() ?? '5.00';

        $vehicule = $rdv->getVehicule();
        $vars = [
            'client_prenom' => $client->getPrenom() ?? '',
            'plaque' => $vehicule?->getPlaque() ?? '',
            'reference_rdv' => (string) $rdv->getId(),
            'tarif_journalier' => $tarif,
        ];

        try {
            $this->dispatcher->sendFromTemplate(
                'gardiennage_debut',
                'email',
                $atelierId,
                $client->getEmail(),
                $vars,
                'RendezVous',
                $rdv->getId(),
            );
        } catch (\Throwable $e) {
            // Ne bloque jamais le passage en gardiennage si l'envoi email échoue.
            $this->logger->warning('Échec envoi email gardiennage_debut: {error}', [
                'error' => $e->getMessage(),
                'rdv_id' => $rdv->getId(),
            ]);
        }
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
