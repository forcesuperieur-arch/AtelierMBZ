<?php

namespace App\Service;

use App\Entity\GrilleTarifaire;
use App\Entity\OrdreReparation;
use App\Entity\Prestation;
use App\Entity\Vehicule;
use App\Enum\ModeTarification;
use Doctrine\ORM\EntityManagerInterface;

class PrestationCatalogService
{
    public function __construct(
        private EntityManagerInterface $em,
        private PricingService $pricingService,
    ) {}

    /**
     * Get prestations applicable to a vehicle, with prices pre-calculated.
     *
     * @return array<array{prestation: Prestation, prix_ht: string, prix_ttc: string, temps_minutes: int}>
     */
    public function getApplicablePrestations(Vehicule $vehicule): array
    {
        $categorie = $vehicule->getCategorie();
        $categorieId = $categorie?->getId();
        $typeMoto = $vehicule->getTypeMoto();
        $cylindree = $vehicule->getCylindree() ? (int) $vehicule->getCylindree() : null;
        $atelierId = $vehicule->getAtelierId();

        $prestations = $this->em->getRepository(Prestation::class)->findBy(['isActive' => 1]);

        $results = [];
        foreach ($prestations as $prestation) {
            if (!$this->isApplicableToVehicle($prestation, $typeMoto, $cylindree, $categorieId)) {
                continue;
            }

            $pricing = $this->calculatePrice($prestation, $vehicule, $atelierId);

            $results[] = [
                'prestation' => $prestation,
                ...$pricing,
            ];
        }

        return $results;
    }

    /**
     * Calculate the price for a prestation applied to a specific vehicle.
     */
    public function calculatePrice(Prestation $prestation, Vehicule $vehicule, ?int $atelierId = null): array
    {
        $categorieId = $vehicule->getCategorie()?->getId();
        $mode = $prestation->getModeTarification();

        // First check for specific grid entry for this category
        if ($categorieId) {
            $grille = $this->em->getRepository(GrilleTarifaire::class)->findOneBy([
                'prestation' => $prestation,
                'categorieMoto' => $categorieId,
                'isActive' => 1,
            ]);

            if ($grille) {
                return [
                    'prix_ht' => $grille->getPrixHt(),
                    'prix_ttc' => $grille->getPrixTtc(),
                    'temps_minutes' => $grille->getTempsMinutes(),
                    'source' => 'grille_categorie',
                    'mode' => $mode->value,
                ];
            }
        }

        // For hourly mode, compute from time and hourly rate
        if ($mode === ModeTarification::HORAIRE) {
            $tauxType = $prestation->getTauxHoraireApplique();
            $mo = $this->pricingService->calculateMoPrice(
                $prestation->getTempsEstimeMinutes(),
                $tauxType,
                $atelierId,
            );

            return [
                'prix_ht' => (string) $mo['prix_ht'],
                'prix_ttc' => (string) $mo['prix_ttc'],
                'temps_minutes' => $prestation->getTempsEstimeMinutes(),
                'source' => 'horaire',
                'mode' => $mode->value,
            ];
        }

        // For sur_devis, return 0 (price to be determined)
        if ($mode === ModeTarification::SUR_DEVIS) {
            return [
                'prix_ht' => '0.00',
                'prix_ttc' => '0.00',
                'temps_minutes' => $prestation->getTempsEstimeMinutes(),
                'source' => 'sur_devis',
                'mode' => $mode->value,
            ];
        }

        // Default: forfait — use base prestation price
        return [
            'prix_ht' => $prestation->getPrixBaseHt(),
            'prix_ttc' => $prestation->getPrixBaseTtc(),
            'temps_minutes' => $prestation->getTempsEstimeMinutes(),
            'source' => 'forfait_base',
            'mode' => $mode->value,
        ];
    }

    /**
     * Validate a prestation can be added to an OR.
     *
     * @throws \DomainException if incompatible
     */
    public function validateAddition(Prestation $prestation, OrdreReparation $or): void
    {
        if (!$prestation->getIsActive()) {
            throw new \DomainException('Cette prestation est désactivée.');
        }

        $vehicule = $or->getRendezVous()?->getVehicule();
        if (!$vehicule) {
            return; // no vehicle to check against
        }

        $categorieId = $vehicule->getCategorie()?->getId();
        $typeMoto = $vehicule->getTypeMoto();
        $cylindree = $vehicule->getCylindree() ? (int) $vehicule->getCylindree() : null;

        if (!$this->isApplicableToVehicle($prestation, $typeMoto, $cylindree, $categorieId)) {
            throw new \DomainException(sprintf(
                'La prestation "%s" n\'est pas applicable à ce véhicule.',
                $prestation->getLibelle(),
            ));
        }
    }

    /**
     * Check if a prestation applies to a vehicle based on type, cylindree range, and category grid.
     */
    private function isApplicableToVehicle(
        Prestation $prestation,
        ?string $typeMoto,
        ?int $cylindree,
        ?int $categorieId,
    ): bool {
        // Type vehicle filter
        $typePresta = $prestation->getTypeVehicule();
        if ($typePresta !== 'tous' && $typeMoto && $typeMoto !== $typePresta) {
            return false;
        }

        // Cylindree range filter
        $minCyl = $prestation->getCylindreeMin();
        $maxCyl = $prestation->getCylindreeMax();
        if ($cylindree !== null) {
            if ($minCyl !== null && $cylindree < $minCyl) {
                return false;
            }
            if ($maxCyl !== null && $cylindree > $maxCyl) {
                return false;
            }
        }

        return true;
    }
}
