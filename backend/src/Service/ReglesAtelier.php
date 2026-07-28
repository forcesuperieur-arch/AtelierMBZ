<?php

namespace App\Service;

use App\Entity\ConfigAtelier;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Source unique des RÈGLES MÉTIER réglables en back-office.
 *
 * Avant, ces valeurs étaient des constantes dispersées dans les services et les
 * commandes (nombre de photos d'entrée, délai de relance, jours de rappel…) :
 * changer une règle demandait un déploiement. Elles vivent maintenant dans
 * `ConfigAtelier`, et ce service les lit avec un repli sur la valeur historique —
 * de sorte qu'une installation sans configuration se comporte comme avant.
 *
 * Les garde-fous purement techniques ou légaux (tailles d'upload, durées de vie
 * des jetons d'authentification, délais de purge RGPD) restent volontairement en
 * dur : ils ne sont pas du ressort de l'exploitant.
 */
class ReglesAtelier
{
    // Valeurs historiques, appliquées tant que rien n'est réglé.
    public const DEFAUT_MIN_PHOTOS_ENTREE = 4;
    public const DEFAUT_RELANCE_TRAVAUX_DELAI_HEURES = 4;
    public const DEFAUT_RELANCE_HEURE_MIN = 8;
    public const DEFAUT_RELANCE_HEURE_MAX = 19;
    public const DEFAUT_RAPPELS_RDV_JOURS = [1, 3];
    public const DEFAUT_LIEN_PUBLIC_JOURS = 30;
    public const DEFAUT_ESSAI_POINTS_MIN = 5;
    public const DEFAUT_SEUIL_SEJOUR_HEURES = 72;
    public const DEFAUT_RAPPEL_ALERTE_HEURES = 24;

    /** @var array<int, ConfigAtelier|null> */
    private array $cache = [];

    public function __construct(private EntityManagerInterface $em) {}

    public function minPhotosEntree(?int $atelierId = null): int
    {
        return $this->config($atelierId)?->getMinPhotosEntree() ?: self::DEFAUT_MIN_PHOTOS_ENTREE;
    }

    public function relanceTravauxDelaiHeures(?int $atelierId = null): int
    {
        return $this->config($atelierId)?->getRelanceTravauxDelaiHeures() ?: self::DEFAUT_RELANCE_TRAVAUX_DELAI_HEURES;
    }

    /**
     * Fenêtre horaire d'envoi des relances automatiques (pas de SMS à 3 h du matin).
     *
     * @return array{0: int, 1: int} [heure de début, heure de fin]
     */
    public function fenetreEnvoi(?int $atelierId = null): array
    {
        $config = $this->config($atelierId);
        $min = $config?->getRelanceHeureMin() ?? self::DEFAUT_RELANCE_HEURE_MIN;
        $max = $config?->getRelanceHeureMax() ?? self::DEFAUT_RELANCE_HEURE_MAX;

        // Fenêtre incohérente en base : on retombe sur les valeurs historiques
        // plutôt que de ne plus jamais envoyer.
        if ($max <= $min) {
            return [self::DEFAUT_RELANCE_HEURE_MIN, self::DEFAUT_RELANCE_HEURE_MAX];
        }

        return [$min, $max];
    }

    /**
     * Jours avant le rendez-vous où un rappel client est envoyé (ex. [1, 3]).
     *
     * @return list<int> trié, sans doublon
     */
    public function rappelsRdvJours(?int $atelierId = null): array
    {
        $jours = $this->config($atelierId)?->getRappelsRdvJours();
        if (!$jours) {
            return self::DEFAUT_RAPPELS_RDV_JOURS;
        }

        $propres = array_values(array_unique(array_filter(
            array_map(static fn ($j) => (int) $j, $jours),
            static fn (int $j) => $j > 0,
        )));
        sort($propres);

        return $propres ?: self::DEFAUT_RAPPELS_RDV_JOURS;
    }

    /** Durée de validité d'un lien client public après clôture du dossier. */
    public function lienPublicJours(?int $atelierId = null): int
    {
        return $this->config($atelierId)?->getLienPublicJours() ?: self::DEFAUT_LIEN_PUBLIC_JOURS;
    }

    /** Nombre de points de contrôle à renseigner pour valider un essai routier. */
    public function essaiPointsMin(?int $atelierId = null): int
    {
        return $this->config($atelierId)?->getEssaiPointsMin() ?: self::DEFAUT_ESSAI_POINTS_MIN;
    }

    /** Seuil de l'alerte « moto en atelier », en heures ouvrées. */
    public function seuilSejourHeures(?int $atelierId = null): int
    {
        return $this->config($atelierId)?->getSeuilSejourAtelierHeures() ?: self::DEFAUT_SEUIL_SEJOUR_HEURES;
    }

    public function alerteSejourActive(?int $atelierId = null): bool
    {
        return $this->config($atelierId)?->isAlerteSejourAtelierActive() ?? true;
    }

    /** Délai avant de re-signaler une même moto déjà alertée. */
    public function rappelAlerteHeures(?int $atelierId = null): int
    {
        return $this->config($atelierId)?->getRappelAlerteHeures() ?: self::DEFAUT_RAPPEL_ALERTE_HEURES;
    }

    /** Validité d'un devis, en jours (réglage historique déjà présent en base). */
    public function validiteDevisJours(?int $atelierId = null): int
    {
        return $this->config($atelierId)?->getValiditeDevisJours() ?: 30;
    }

    private function config(?int $atelierId): ?ConfigAtelier
    {
        $cle = $atelierId ?? 0;
        if (array_key_exists($cle, $this->cache)) {
            return $this->cache[$cle];
        }

        $repository = $this->em->getRepository(ConfigAtelier::class);
        $config = $repository->findOneBy(['atelierId' => $cle]);

        // Installations mono-atelier : la configuration peut ne porter aucun
        // atelier_id, on prend alors la configuration existante.
        return $this->cache[$cle] = $config ?? $repository->findOneBy([]);
    }
}
