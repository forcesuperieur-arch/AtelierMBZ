<?php
namespace App\Service;

use App\Entity\ConfigAtelier;
use App\Entity\GrilleTarifaire;
use App\Entity\Prestation;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Calculates prices, margins, and tariffs based on workshop configuration.
 */
class PricingService
{
    public function __construct(private EntityManagerInterface $em) {}

    /**
     * Get the ConfigAtelier for a given atelier (cached per request).
     */
    public function getConfig(int $atelierId): ?ConfigAtelier
    {
        return $this->em->getRepository(ConfigAtelier::class)
            ->findOneBy(['atelierId' => $atelierId]);
    }

    /**
     * Calculate price for a prestation, optionally adjusted by moto category.
     */
    public function calculatePrestationPrice(
        Prestation $prestation,
        ?int $categorieMotoId = null,
        ?int $atelierId = null,
    ): array {
        // Check for specific grid entry
        if ($categorieMotoId) {
            $grille = $this->em->getRepository(GrilleTarifaire::class)->findOneBy([
                'prestation' => $prestation,
                'categorieMoto' => $categorieMotoId,
                'isActive' => 1,
            ]);

            if ($grille) {
                return [
                    'prix_ht' => $grille->getPrixHt(),
                    'prix_ttc' => $grille->getPrixTtc(),
                    'temps_minutes' => $grille->getTempsMinutes(),
                    'delai_jours' => $grille->getDelaiJours(),
                    'source' => 'grille_tarifaire',
                ];
            }
        }

        // Fallback to prestation base price
        return [
            'prix_ht' => $prestation->getPrixBaseHt(),
            'prix_ttc' => $prestation->getPrixBaseTtc(),
            'temps_minutes' => $prestation->getTempsEstimeMinutes(),
            'delai_jours' => $prestation->getDelaiInterventionJours(),
            'source' => 'prestation_base',
        ];
    }

    /**
     * Calculate MO (main d'oeuvre) price from minutes and rate.
     *
     * @throws \RuntimeException if no ConfigAtelier found for the given atelierId
     */
    public function calculateMoPrice(int $minutes, string $tauxType = 'standard', ?int $atelierId = null): array
    {
        $config = $atelierId ? $this->getConfig($atelierId) : null;
        if (!$config) {
            throw new \RuntimeException(sprintf('ConfigAtelier introuvable pour atelier %d — impossible de calculer le prix MO', $atelierId ?? 0));
        }

        $tauxHoraire = match ($tauxType) {
            'complexe' => $config->getTauxHoraireMoComplexe() ?? '85.00',
            'expert' => $config->getTauxHoraireMoExpert() ?? '95.00',
            default => $config->getTauxHoraireMoStandard() ?? '65.00',
        };

        $forfaitMin = $config->getForfaitMoMinimum() ?? '25.00';
        $tvaTaux = (string) ($config->getTvaMoTaux() ?? '20.00');

        // prixHt = max(forfaitMin, (minutes / 60) * tauxHoraire)
        $heures = bcdiv((string) $minutes, '60', 6);
        $prixCalcule = bcmul($heures, $tauxHoraire, 2);
        $prixHt = (bccomp($forfaitMin, $prixCalcule, 2) > 0) ? $forfaitMin : $prixCalcule;

        // prixTtc = prixHt * (1 + tvaTaux / 100)
        $tvaMultiplier = bcadd('1', bcdiv($tvaTaux, '100', 6), 6);
        $prixTtc = bcmul($prixHt, $tvaMultiplier, 2);

        return [
            'prix_ht' => $prixHt,
            'prix_ttc' => $prixTtc,
            'taux_horaire' => $tauxHoraire,
            'tva_taux' => $tvaTaux,
        ];
    }

    /**
     * Apply piece margin based on category.
     *
     * @throws \RuntimeException if no ConfigAtelier found for the given atelierId
     */
    public function applyPieceMargin(string $prixAchatHt, string $categorie = 'standard', ?int $atelierId = null): array
    {
        $config = $atelierId ? $this->getConfig($atelierId) : null;
        if (!$config) {
            throw new \RuntimeException(sprintf('ConfigAtelier introuvable pour atelier %d — impossible de calculer la marge pièce', $atelierId ?? 0));
        }

        $margePourcent = match ($categorie) {
            'consommable' => (string) ($config->getMargePiecesConsommable() ?? '50.00'),
            'pneumatique' => (string) ($config->getMargePiecesPneumatique() ?? '25.00'),
            default => (string) ($config->getMargePiecesStandard() ?? '30.00'),
        };

        $tvaTaux = (string) ($config->getTvaPiecesTaux() ?? '20.00');

        // prixVenteHt = prixAchatHt * (1 + margePourcent / 100)
        $margeMultiplier = bcadd('1', bcdiv($margePourcent, '100', 6), 6);
        $prixVenteHt = bcmul($prixAchatHt, $margeMultiplier, 2);

        // prixVenteTtc = prixVenteHt * (1 + tvaTaux / 100)
        $tvaMultiplier = bcadd('1', bcdiv($tvaTaux, '100', 6), 6);
        $prixVenteTtc = bcmul($prixVenteHt, $tvaMultiplier, 2);

        // margeMontant = prixVenteHt - prixAchatHt
        $margeMontant = bcsub($prixVenteHt, $prixAchatHt, 2);

        return [
            'prix_achat_ht' => $prixAchatHt,
            'prix_vente_ht' => $prixVenteHt,
            'prix_vente_ttc' => $prixVenteTtc,
            'marge_pourcent' => $margePourcent,
            'marge_montant' => $margeMontant,
        ];
    }
}
