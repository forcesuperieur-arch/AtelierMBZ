<?php

namespace App\Service;

use App\Entity\RdvStatutHistorique;
use App\Entity\RendezVous;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Détecte les motos immobilisées à l'atelier depuis plus d'un seuil d'heures
 * OUVRÉES (les jours de fermeture — week-end, fériés, fermetures exceptionnelles —
 * ne comptent pas). Sert l'alerte « moto en atelier depuis plus de 72h ouvré ».
 *
 * Choix métier (cmoreau) :
 * - Départ du compteur = arrivée PHYSIQUE de la moto : première entrée d'historique
 *   sur un statut « en atelier » (réception, mais aussi gardiennage direct, etc.),
 *   à défaut la date du RDV — robuste même si le RDV a été repoussé.
 * - « 72h ouvré » = chrono 24h/24, mais gelé les jours où l'atelier est fermé.
 * - Statuts « en atelier » = moto physiquement immobilisée (voir STATUTS_EN_ATELIER).
 *
 * La connaissance « ce jour est-il fermé ? » (fériés, fermeture hebdo, fermetures
 * exceptionnelles) est déléguée à {@see JoursOuvresService}, source unique du calendrier.
 */
class SejourAtelierService
{
    public const SEUIL_HEURES_DEFAUT = 72;

    /**
     * Places du workflow où la moto est physiquement présente et immobilisée.
     * Exclut en_attente/reserve/confirme (pas encore arrivée) et
     * termine, restitue, facture, paye, annule, no_show (repartie ou clôturée).
     */
    public const STATUTS_EN_ATELIER = [
        'reception',
        'en_cours',
        'en_pause',
        'en_attente_pieces',
        'en_attente_reprise',
        'en_gardiennage',
    ];

    public function __construct(
        private EntityManagerInterface $em,
        private JoursOuvresService $joursOuvres,
        private ReglesAtelier $regles,
    ) {}

    /** Seuil d'alerte réglé en back-office pour cet atelier (heures ouvrées). */
    public function seuilPourAtelier(?int $atelierId): int
    {
        return $this->regles->seuilSejourHeures($atelierId);
    }

    /** L'alerte automatique (cloche + e-mail) est-elle activée pour cet atelier ? */
    public function alerteActivePourAtelier(?int $atelierId): bool
    {
        return $this->regles->alerteSejourActive($atelierId);
    }

    /** Délai avant de re-signaler une moto déjà alertée. */
    public function rappelAlerteHeures(?int $atelierId): int
    {
        return $this->regles->rappelAlerteHeures($atelierId);
    }

    /**
     * Heures écoulées entre $debut et $fin en ignorant les journées fermées.
     * Fonction PURE (le calendrier est injecté par $estFerme) → testable unitairement.
     *
     * @param callable(\DateTimeImmutable): bool $estFerme reçoit le jour à évaluer
     */
    public function heuresHorsFermeture(\DateTimeInterface $debut, \DateTimeInterface $fin, callable $estFerme): float
    {
        if ($fin <= $debut) {
            return 0.0;
        }

        $cursor = \DateTimeImmutable::createFromInterface($debut);
        $finImm = \DateTimeImmutable::createFromInterface($fin);
        $totalSeconds = 0;

        // Garde-fou dur contre une boucle infinie (≈ 5 ans de jours).
        $maxIterations = 366 * 5;
        $i = 0;

        while ($cursor < $finImm && $i++ < $maxIterations) {
            $minuitSuivant = $cursor->setTime(0, 0)->modify('+1 day');
            $segmentFin = $minuitSuivant < $finImm ? $minuitSuivant : $finImm;

            if (!$estFerme($cursor)) {
                $totalSeconds += $segmentFin->getTimestamp() - $cursor->getTimestamp();
            }

            $cursor = $minuitSuivant;
        }

        return $totalSeconds / 3600.0;
    }

    /**
     * Heures ouvrées écoulées pour un atelier donné (calendrier réel de l'atelier).
     */
    public function heuresOuvreesEcoulees(\DateTimeInterface $debut, \DateTimeInterface $fin, int $atelierId): float
    {
        return $this->heuresHorsFermeture(
            $debut,
            $fin,
            fn (\DateTimeImmutable $jour) => $this->joursOuvres->estJourFerme(
                \DateTime::createFromInterface($jour),
                $atelierId,
            ),
        );
    }

    /**
     * Date d'arrivée physique de la moto à l'atelier.
     */
    public function dateArriveeAtelier(RendezVous $rdv): \DateTimeInterface
    {
        return $this->choisirDateArrivee(
            $this->premierPassage($rdv, ['reception']),
            $this->premierPassage($rdv, self::STATUTS_EN_ATELIER),
            new \DateTimeImmutable(
                $rdv->getDateRdv()->format('Y-m-d') . ' ' . $rdv->getHeureRdv()->format('H:i:s')
            ),
        );
    }

    /**
     * Règle de choix de la date d'arrivée (pure, donc testable) :
     *
     * 1. Une réception tracée fait foi : c'est le moment où la moto est arrivée.
     * 2. Sinon (dossier legacy, seed, moto entrée sans passer par `reception`), on
     *    retient la date la PLUS ANCIENNE entre le premier événement d'atelier connu
     *    et la date du RDV. Sans cette borne, un simple changement de statut du jour
     *    (mise en pause…) deviendrait « l'arrivée » et remettrait le compteur à zéro.
     */
    public function choisirDateArrivee(
        ?\DateTimeInterface $receptionTracee,
        ?\DateTimeInterface $premierEvenementAtelier,
        \DateTimeInterface $dateRdv,
    ): \DateTimeInterface {
        if ($receptionTracee !== null) {
            return $receptionTracee;
        }

        if ($premierEvenementAtelier === null) {
            return $dateRdv;
        }

        return $premierEvenementAtelier < $dateRdv ? $premierEvenementAtelier : $dateRdv;
    }

    /**
     * Date du premier passage du RDV par l'un des statuts donnés, d'après l'historique.
     *
     * @param list<string> $statuts
     */
    private function premierPassage(RendezVous $rdv, array $statuts): ?\DateTimeInterface
    {
        $histo = $this->em->getRepository(RdvStatutHistorique::class)->createQueryBuilder('h')
            ->where('h.rendezVous = :rdv')
            ->andWhere('h.statut IN (:statuts)')
            ->setParameter('rdv', $rdv)
            ->setParameter('statuts', $statuts)
            ->orderBy('h.createdAt', 'ASC')
            ->addOrderBy('h.id', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $histo instanceof RdvStatutHistorique ? $histo->getCreatedAt() : null;
    }

    /**
     * Motos actuellement en atelier dont l'ancienneté ouvrée dépasse le seuil.
     * En HTTP le TenantFilter restreint automatiquement à l'atelier courant ;
     * en CLI (pas de filtre) on obtient tous les ateliers (à grouper par l'appelant).
     *
     * @return list<array<string, mixed>> trié du plus ancien au plus récent
     */
    public function motosEnDepassement(?int $seuilHeures = null, ?int $atelierId = null): array
    {
        return array_values(array_filter(
            $this->motosEnAtelier($seuilHeures, $atelierId),
            static fn (array $moto) => $moto['en_depassement'],
        ));
    }

    /**
     * TOUTES les motos actuellement présentes à l'atelier, de la plus ancienne à la
     * plus récente, chacune avec son ancienneté ouvrée et un drapeau de dépassement.
     * Alimente l'onglet de suivi « En atelier ».
     *
     * @return list<array<string, mixed>>
     */
    public function motosEnAtelier(?int $seuilHeures = null, ?int $atelierId = null): array
    {
        $qb = $this->em->getRepository(RendezVous::class)->createQueryBuilder('r')
            ->where('r.statut IN (:statuts)')
            ->setParameter('statuts', self::STATUTS_EN_ATELIER);
        if ($atelierId !== null) {
            $qb->andWhere('r.atelierId = :at')->setParameter('at', $atelierId);
        }

        $now = new \DateTimeImmutable();
        $result = [];

        foreach ($qb->getQuery()->getResult() as $rdv) {
            /** @var RendezVous $rdv */
            $debut = $this->dateArriveeAtelier($rdv);
            $heures = $this->heuresOuvreesEcoulees($debut, $now, $rdv->getAtelierId() ?? 0);
            // Sans seuil imposé par l'appelant, chaque atelier applique le sien
            // (réglage back-office), ce qui compte en CLI multi-ateliers.
            $seuil = $seuilHeures ?? $this->seuilPourAtelier($rdv->getAtelierId());

            $client = $rdv->getClient();
            $vehicule = $rdv->getVehicule();
            $mecanicien = $rdv->getMecanicien();

            $result[] = [
                'rdv_id' => $rdv->getId(),
                'atelier_id' => $rdv->getAtelierId(),
                'statut' => $rdv->getStatut(),
                'recu_le' => $debut->format(\DateTimeInterface::ATOM),
                'date_rdv' => $rdv->getDateRdv()->format('Y-m-d'),
                'heure_rdv' => $rdv->getHeureRdv()->format('H:i'),
                'type_intervention' => $rdv->getTypeIntervention(),
                'pont_nom' => $rdv->getPont()?->getNom(),
                'heures_ouvrees' => round($heures, 1),
                'jours_ouvres' => round($heures / 24, 1),
                'seuil_heures' => $seuil,
                'en_depassement' => $heures > $seuil,
                'client_nom' => $client ? trim($client->getPrenom() . ' ' . $client->getNom()) : null,
                'client_telephone' => $client?->getTelephone(),
                'vehicule' => $vehicule
                    ? trim(($vehicule->getMarque() ?? '') . ' ' . ($vehicule->getModele() ?? ''))
                    : null,
                'plaque' => $vehicule?->getPlaque(),
                'mecanicien' => $mecanicien
                    ? trim($mecanicien->getPrenom() . ' ' . $mecanicien->getNom())
                    : null,
            ];
        }

        usort($result, static fn ($a, $b) => $b['heures_ouvrees'] <=> $a['heures_ouvrees']);

        return $result;
    }
}
