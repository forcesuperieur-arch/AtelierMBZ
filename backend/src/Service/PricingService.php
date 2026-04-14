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
     */
    public function calculateMoPrice(int $minutes, string $tauxType = 'standard', ?int $atelierId = null): array
    {
        $config = $atelierId ? $this->getConfig($atelierId) : null;

        $tauxHoraire = match ($tauxType) {
            'complexe' => $config ? (float) $config->getTauxHoraireMoComplexe() : 85.0,
            'expert' => $config ? (float) $config->getTauxHoraireMoExpert() : 95.0,
            default => $config ? (float) $config->getTauxHoraireMoStandard() : 65.0,
        };

        $forfaitMin = $config ? (float) $config->getForfaitMoMinimum() : 25.0;
        $tvaTaux = $config ? $config->getTvaMoTaux() : 20.0;

        $prixHt = max($forfaitMin, ($minutes / 60) * $tauxHoraire);
        $prixTtc = $prixHt * (1 + $tvaTaux / 100);

        return [
            'prix_ht' => round($prixHt, 2),
            'prix_ttc' => round($prixTtc, 2),
            'taux_horaire' => $tauxHoraire,
            'tva_taux' => $tvaTaux,
        ];
    }

    /**
     * Apply piece margin based on category.
     */
    public function applyPieceMargin(float $prixAchatHt, string $categorie = 'standard', ?int $atelierId = null): array
    {
        $config = $atelierId ? $this->getConfig($atelierId) : null;

        $margePourcent = match ($categorie) {
            'consommable' => $config ? $config->getMargePiecesConsommable() : 50.0,
            'pneumatique' => $config ? $config->getMargePiecesPneumatique() : 25.0,
            default => $config ? $config->getMargePiecesStandard() : 30.0,
        };

        $tvaTaux = $config ? $config->getTvaPiecesTaux() : 20.0;
        $prixVenteHt = $prixAchatHt * (1 + $margePourcent / 100);
        $prixVenteTtc = $prixVenteHt * (1 + $tvaTaux / 100);

        return [
            'prix_achat_ht' => $prixAchatHt,
            'prix_vente_ht' => round($prixVenteHt, 2),
            'prix_vente_ttc' => round($prixVenteTtc, 2),
            'marge_pourcent' => $margePourcent,
            'marge_montant' => round($prixVenteHt - $prixAchatHt, 2),
        ];
    }
}
