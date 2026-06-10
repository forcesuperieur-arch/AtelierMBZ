<?php

namespace App\Service;

use App\Entity\AnalyticsRdvFact;
use App\Entity\AnalyticsDailySnapshot;
use App\Entity\AnalyticsClientFact;
use App\Entity\RendezVous;
use App\Entity\Facture;
use App\Entity\OrdreReparation;
use App\Entity\Client;
use App\Entity\Devis;
use Doctrine\ORM\EntityManagerInterface;

class AnalyticsSyncService
{
    public function __construct(private EntityManagerInterface $em) {}

    public function syncRdv(int $rdvId): void
    {
        $rdv = $this->em->getRepository(RendezVous::class)->find($rdvId);
        if (!$rdv) return;

        $atelierId = $rdv->getAtelierId();
        if (!$atelierId) return;

        $fact = $this->em->getRepository(AnalyticsRdvFact::class)
            ->findOneBy(['rdvId' => $rdvId]);

        if (!$fact) {
            $fact = new AnalyticsRdvFact();
            $fact->setRdvId($rdvId);
            $fact->setAtelierId($atelierId);
            $this->em->persist($fact);
        }

        $fact->setDateRdv($rdv->getDateRdv());
        $fact->setHeureRdv($rdv->getHeureRdv() ? \DateTime::createFromFormat('H:i:s', $rdv->getHeureRdv()->format('H:i:s')) : null);
        $fact->setTypeIntervention($rdv->getTypeIntervention());
        $fact->setStatutRdv($rdv->getStatut());
        $fact->setTempsEstime($rdv->getTempsEstime());
        $fact->setTempsEffectif($rdv->getTempsEffectifMinutes());
        $fact->setPrixEstime($rdv->getPrixEstime() !== null ? (string) $rdv->getPrixEstime() : null);
        $fact->setPrixFinal($rdv->getPrixFinal() !== null ? (string) $rdv->getPrixFinal() : null);

        // Client
        $client = $rdv->getClient();
        if ($client) {
            $fact->setClientId($client->getId());
            $fact->setClientSegment($this->resolveClientSegment($client, $atelierId));
        }

        // Véhicule
        $vehicule = $rdv->getVehicule();
        if ($vehicule) {
            $fact->setVehiculeId($vehicule->getId());
            $fact->setVehiculeMarque($vehicule->getMarque());
            if ($vehicule->getAnnee()) {
                $fact->setVehiculeAgeAnnees((int) date('Y') - $vehicule->getAnnee());
            }
        }

        // Mécanicien
        $meca = $rdv->getMecanicien();
        if ($meca) {
            $fact->setMecanicienId($meca->getId());
            $fact->setMecanicienNom($meca->getPrenom() . ' ' . $meca->getNom());
        }

        // Pont
        $pont = $rdv->getPont();
        if ($pont) {
            $fact->setPontId($pont->getId());
            $fact->setPontNom($pont->getNom());
        }

        // Facture liée
        $facture = $this->em->getRepository(Facture::class)
            ->findOneBy(['rendezVous' => $rdv]);
        if ($facture) {
            $fact->setCaHt((string) $facture->getTotalHt());
            $fact->setCaMoHt((string) $facture->getTotalMoHt());
            $fact->setCaPiecesHt((string) $facture->getTotalPiecesHt());
            $fact->setIsFacture(true);
            $fact->setIsPaye(in_array($facture->getStatut(), ['payee', 'payee_partiellement']));
        } else {
            $fact->setCaHt(null);
            $fact->setCaMoHt(null);
            $fact->setCaPiecesHt(null);
            $fact->setIsFacture(false);
            $fact->setIsPaye(false);
        }

        // Délais
        $fact->setDelaiReceptionDebut($this->computeMinutesDiff($rdv->getHeureDebutTravail(), $rdv->getHeureDebutTravail())); // placeholder
        $fact->setDelaiFinRestitution($this->computeMinutesDiff($rdv->getHeureFinTravail(), $rdv->getHeureFinTravail()));
        $fact->setDelaiTotalCycle($this->computeCycleMinutes($rdv));

        // Devis accepté ?
        $devis = $this->em->getRepository(Devis::class)
            ->findOneBy(['rendezVousId' => $rdv->getId()]);
        $fact->setIsDevisAccepte($devis && in_array($devis->getStatut(), ['accepte', 'transforme']));

        // Travaux complémentaires
        $or = $this->em->getRepository(OrdreReparation::class)
            ->findOneBy(['rendezVous' => $rdv]);
        $fact->setHasTravauxComplementaires(false); // TODO: implement if OR has complementary works flag

        $fact->setSyncedAt(new \DateTime());
        $this->em->flush();

        // Cascade
        $this->syncDailySnapshot($atelierId, $rdv->getDateRdv());
        if ($client) {
            $this->syncClientFact($atelierId, $client->getId(), (int) $rdv->getDateRdv()->format('Y'), (int) $rdv->getDateRdv()->format('n'));
        }
    }

    public function syncDailySnapshot(int $atelierId, \DateTimeInterface $date): void
    {
        $snapshot = $this->em->getRepository(AnalyticsDailySnapshot::class)
            ->findOneBy(['atelierId' => $atelierId, 'snapshotDate' => $date]);

        if (!$snapshot) {
            $snapshot = new AnalyticsDailySnapshot();
            $snapshot->setAtelierId($atelierId);
            $snapshot->setSnapshotDate($date);
            $this->em->persist($snapshot);
        }

        $conn = $this->em->getConnection();
        $d = $date->format('Y-m-d');

        // RDV counts
        $rdvCounts = $conn->fetchAssociative(
            "SELECT COUNT(*) as total,
                    COUNT(*) FILTER (WHERE statut = 'confirme') as confirme,
                    COUNT(*) FILTER (WHERE statut IN ('reception', 'en_cours')) as en_cours,
                    COUNT(*) FILTER (WHERE statut IN ('termine', 'restitue', 'facture', 'paye')) as termine,
                    COUNT(*) FILTER (WHERE statut = 'annule') as annule
             FROM rendez_vous WHERE date_rdv = :d AND atelier_id = :a",
            ['d' => $d, 'a' => $atelierId]
        ) ?: [];

        // OR ouverts
        $orOuverts = (int) $conn->fetchOne(
            "SELECT COUNT(o.id) FROM ordres_reparation o
             INNER JOIN rendez_vous r ON r.id = o.rendez_vous_id
             WHERE r.atelier_id = :a AND o.statut NOT IN ('termine', 'execute', 'rectifie')",
            ['a' => $atelierId]
        );

        // Revenue
        $rev = $conn->fetchAssociative(
            "SELECT COALESCE(SUM(total_ht), 0) as ca_ht, COALESCE(SUM(total_mo_ht), 0) as mo_ht,
                    COALESCE(SUM(total_pieces_ht), 0) as pieces_ht, COUNT(*) as nb_fac,
                    COALESCE(AVG(total_ht), 0) as panier
             FROM factures WHERE DATE(date_creation) = :d AND atelier_id = :a AND statut != 'annulee'",
            ['d' => $d, 'a' => $atelierId]
        ) ?: ['ca_ht' => 0, 'mo_ht' => 0, 'pieces_ht' => 0, 'nb_fac' => 0, 'panier' => 0];

        // Charge
        $charge = $conn->fetchAssociative(
            "SELECT COALESCE(SUM(COALESCE(temps_estime, 60)), 0) as planif,
                    COALESCE(SUM(COALESCE(temps_effectif_minutes, 0)), 0) as eff
             FROM rendez_vous WHERE date_rdv = :d AND atelier_id = :a AND statut != 'annule'",
            ['d' => $d, 'a' => $atelierId]
        ) ?: ['planif' => 0, 'eff' => 0];

        // Ponts
        $pontsTotal = (int) $conn->fetchOne('SELECT COUNT(*) FROM ponts WHERE atelier_id = :a AND is_active = 1', ['a' => $atelierId]);
        $pontsOccupes = (int) $conn->fetchOne(
            "SELECT COUNT(DISTINCT pont_id) FROM rendez_vous
             WHERE date_rdv = :d AND atelier_id = :a AND statut IN ('reception', 'en_cours') AND pont_id IS NOT NULL",
            ['d' => $d, 'a' => $atelierId]
        );

        // Clients
        $clients = $conn->fetchAssociative(
            "SELECT COUNT(DISTINCT client_id) as total FROM rendez_vous WHERE date_rdv = :d AND atelier_id = :a",
            ['d' => $d, 'a' => $atelierId]
        ) ?: ['total' => 0];

        $clientsRecurrents = (int) $conn->fetchOne(
            "SELECT COUNT(DISTINCT client_id) FROM rendez_vous
             WHERE date_rdv = :d AND atelier_id = :a
               AND client_id IN (
                 SELECT client_id FROM rendez_vous
                 WHERE date_rdv < :d AND atelier_id = :a AND statut != 'annule'
               )",
            ['d' => $d, 'a' => $atelierId]
        );

        // Devis
        $devis = $conn->fetchAssociative(
            "SELECT COUNT(*) as total, COUNT(*) FILTER (WHERE statut IN ('accepte', 'transforme')) as acceptes
             FROM devis WHERE DATE(date_creation) = :d AND atelier_id = :a",
            ['d' => $d, 'a' => $atelierId]
        ) ?: ['total' => 0, 'acceptes' => 0];

        // Retards
        $retards = (int) $conn->fetchOne(
            "SELECT COUNT(*) FROM rendez_vous r
             WHERE r.date_rdv = :d AND r.atelier_id = :a
               AND r.statut IN ('reception', 'en_cours')
               AND r.heure_debut_travail IS NOT NULL
               AND r.temps_estime IS NOT NULL
               AND EXTRACT(EPOCH FROM (NOW() - r.heure_debut_travail))/60 > r.temps_estime + 15",
            ['d' => $d, 'a' => $atelierId]
        );

        $attenteRest = (int) $conn->fetchOne(
            "SELECT COUNT(*) FROM rendez_vous
             WHERE date_rdv = :d AND atelier_id = :a AND statut = 'termine' AND heure_fin_travail IS NOT NULL",
            ['d' => $d, 'a' => $atelierId]
        );

        $snapshot->setNbRdvTotal((int) ($rdvCounts['total'] ?? 0));
        $snapshot->setNbRdvConfirme((int) ($rdvCounts['confirme'] ?? 0));
        $snapshot->setNbRdvEnCours((int) ($rdvCounts['en_cours'] ?? 0));
        $snapshot->setNbRdvTermine((int) ($rdvCounts['termine'] ?? 0));
        $snapshot->setNbRdvAnnule((int) ($rdvCounts['annule'] ?? 0));
        $snapshot->setNbOrOuverts($orOuverts);
        $snapshot->setCaDuJourHt((string) $rev['ca_ht']);
        $snapshot->setCaMoHt((string) $rev['mo_ht']);
        $snapshot->setCaPiecesHt((string) $rev['pieces_ht']);
        $snapshot->setNbFactures((int) $rev['nb_fac']);
        $snapshot->setPanierMoyen((string) $rev['panier']);
        $snapshot->setChargePlanifieeMinutes((int) $charge['planif']);
        $snapshot->setChargeEffectiveMinutes((int) $charge['eff']);
        $snapshot->setOccupationPontsPct($pontsTotal > 0 ? min(100, round($pontsOccupes / $pontsTotal * 100)) : 0);
        $snapshot->setNbClientsNouveaux((int) ($clients['total'] ?? 0) - $clientsRecurrents);
        $snapshot->setNbClientsRecurrents($clientsRecurrents);
        $snapshot->setNbDevis((int) $devis['total']);
        $snapshot->setNbDevisAcceptes((int) $devis['acceptes']);
        $snapshot->setNbRetardsDepassement($retards);
        $snapshot->setNbAttenteRestitution($attenteRest);
        $snapshot->setSyncedAt(new \DateTime());

        $this->em->flush();
    }

    public function syncClientFact(int $atelierId, int $clientId, int $annee, int $mois): void
    {
        $fact = $this->em->getRepository(AnalyticsClientFact::class)
            ->findOneBy(['atelierId' => $atelierId, 'clientId' => $clientId, 'periodeAnnee' => $annee, 'periodeMois' => $mois]);

        if (!$fact) {
            $fact = new AnalyticsClientFact();
            $fact->setAtelierId($atelierId);
            $fact->setClientId($clientId);
            $fact->setPeriodeAnnee($annee);
            $fact->setPeriodeMois($mois);
            $this->em->persist($fact);
        }

        $conn = $this->em->getConnection();
        $start = sprintf('%04d-%02d-01', $annee, $mois);
        $end = date('Y-m-t', strtotime($start));

        $stats = $conn->fetchAssociative(
            "SELECT COUNT(*) as nb_rdv,
                    COALESCE(SUM(f.total_ht), 0) as ca_ht,
                    COALESCE(AVG(f.total_ht), 0) as avg_ticket,
                    MAX(r.date_rdv) as dernier_rdv
             FROM rendez_vous r
             LEFT JOIN factures f ON f.rendez_vous_id = r.id AND f.statut != 'annulee'
             WHERE r.atelier_id = :a AND r.client_id = :c
               AND r.date_rdv BETWEEN :s AND :e AND r.statut != 'annule'",
            ['a' => $atelierId, 'c' => $clientId, 's' => $start, 'e' => $end]
        ) ?: ['nb_rdv' => 0, 'ca_ht' => 0, 'avg_ticket' => 0, 'dernier_rdv' => null];

        $vehicules = (int) $conn->fetchOne(
            "SELECT COUNT(DISTINCT vehicule_id) FROM rendez_vous
             WHERE atelier_id = :a AND client_id = :c AND statut != 'annule'",
            ['a' => $atelierId, 'c' => $clientId]
        );

        $fact->setNbRdv((int) $stats['nb_rdv']);
        $fact->setCaTotalHt((string) $stats['ca_ht']);
        $fact->setCaMoyenParRdv((string) $stats['avg_ticket']);
        $fact->setDernierRdvDate($stats['dernier_rdv'] ? new \DateTime($stats['dernier_rdv']) : null);
        if ($stats['dernier_rdv']) {
            $fact->setJoursDepuisDernierRdv((int) (new \DateTime())->diff(new \DateTime($stats['dernier_rdv']))->days);
        }
        $fact->setClientSegment($this->resolveClientSegment($this->em->getRepository(Client::class)->find($clientId), $atelierId));
        $fact->setNbVehicules($vehicules);
        $fact->setPanierMoyen((string) $stats['avg_ticket']);
        $fact->setSyncedAt(new \DateTime());

        $this->em->flush();
    }

    private function resolveClientSegment(?Client $client, int $atelierId): ?string
    {
        if (!$client) return null;
        $conn = $this->em->getConnection();
        $nbRdv = (int) $conn->fetchOne(
            "SELECT COUNT(*) FROM rendez_vous WHERE client_id = :c AND atelier_id = :a AND statut != 'annule'",
            ['c' => $client->getId(), 'a' => $atelierId]
        );
        $caTotal = (float) $conn->fetchOne(
            "SELECT COALESCE(SUM(f.total_ht), 0) FROM factures f
             INNER JOIN rendez_vous r ON r.id = f.rendez_vous_id
             WHERE r.client_id = :c AND r.atelier_id = :a AND f.statut != 'annulee'",
            ['c' => $client->getId(), 'a' => $atelierId]
        );

        if ($nbRdv <= 1) return 'nouveau';
        if ($caTotal > 5000 || $nbRdv >= 6) return 'vip';
        if ($nbRdv >= 3) return 'fidele';
        return 'occasionnel';
    }

    private function computeMinutesDiff(?\DateTimeInterface $start, ?\DateTimeInterface $end): ?int
    {
        if (!$start || !$end) return null;
        return (int) (($end->getTimestamp() - $start->getTimestamp()) / 60);
    }

    private function computeCycleMinutes(RendezVous $rdv): ?int
    {
        // Reception (heure_rdv) to restitution (updated_at if statut restitue/paye)
        $start = $rdv->getHeureRdv();
        if (!$start) return null;
        $startDt = new \DateTime($rdv->getDateRdv()->format('Y-m-d') . ' ' . $start->format('H:i:s'));
        $end = null;
        if (in_array($rdv->getStatut(), ['restitue', 'facture', 'paye'])) {
            $end = $rdv->getUpdatedAt();
        }
        if (!$end) return null;
        return (int) (($end->getTimestamp() - $startDt->getTimestamp()) / 60);
    }

    public function rebuildAll(int $atelierId): void
    {
        $conn = $this->em->getConnection();
        $conn->executeStatement('DELETE FROM analytics_rdv_facts WHERE atelier_id = :a', ['a' => $atelierId]);
        $conn->executeStatement('DELETE FROM analytics_daily_snapshots WHERE atelier_id = :a', ['a' => $atelierId]);
        $conn->executeStatement('DELETE FROM analytics_client_facts WHERE atelier_id = :a', ['a' => $atelierId]);

        $rdvIds = $conn->fetchFirstColumn(
            'SELECT id FROM rendez_vous WHERE atelier_id = :a ORDER BY id',
            ['a' => $atelierId]
        );

        foreach ($rdvIds as $id) {
            $this->syncRdv((int) $id);
        }
    }
}
