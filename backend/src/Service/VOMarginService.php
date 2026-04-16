<?php

namespace App\Service;

/**
 * Calculs financiers VO : TVA sur marge (Art. 297 A CGI) et commission dépôt-vente.
 * Tous les calculs utilisent bcmath pour la précision décimale.
 */
class VOMarginService
{
    /**
     * TVA sur marge : la TVA est calculée uniquement sur la plus-value (marge).
     * Formule : TVA = (prixVente - prixAchat) × tauxTVA / (100 + tauxTVA)
     *
     * @param string $purchasePrice Prix d'achat HT
     * @param string $salePrice     Prix de vente TTC (inclut la TVA sur marge)
     * @param string $vatRate       Taux de TVA (défaut 20.0)
     * @return array{margin: string, vat: string, salePriceHt: string, salePriceTtc: string}
     */
    public function calculateMarginVat(string $purchasePrice, string $salePrice, string $vatRate = '20.0'): array
    {
        $margin = bcsub($salePrice, $purchasePrice, 2);

        // Si marge négative ou nulle, pas de TVA
        if (bccomp($margin, '0', 2) <= 0) {
            return [
                'margin' => $margin,
                'vat' => '0.00',
                'sale_price_ht' => $salePrice,
                'sale_price_ttc' => $salePrice,
            ];
        }

        // TVA = marge × taux / (100 + taux) — méthode centésimale
        $divisor = bcadd('100', $vatRate, 4);
        $vat = bcdiv(bcmul($margin, $vatRate, 4), $divisor, 2);
        $salePriceHt = bcsub($salePrice, $vat, 2);

        return [
            'margin' => $margin,
            'vat' => $vat,
            'sale_price_ht' => $salePriceHt,
            'sale_price_ttc' => $salePrice,
        ];
    }

    /**
     * TVA classique sur prix total.
     *
     * @param string $salePriceHt Prix de vente HT
     * @param string $vatRate     Taux de TVA (défaut 20.0)
     * @return array{vat: string, sale_price_ht: string, sale_price_ttc: string}
     */
    public function calculateNormalVat(string $salePriceHt, string $vatRate = '20.0'): array
    {
        $vat = bcdiv(bcmul($salePriceHt, $vatRate, 4), '100', 2);
        $ttc = bcadd($salePriceHt, $vat, 2);

        return [
            'vat' => $vat,
            'sale_price_ht' => $salePriceHt,
            'sale_price_ttc' => $ttc,
        ];
    }

    /**
     * Calcul commission dépôt-vente.
     * En dépôt-vente, la TVA s'applique uniquement sur la commission.
     *
     * @param string $salePrice      Prix de vente effectif
     * @param string $commissionType 'pourcentage' ou 'forfait'
     * @param string $commissionValue Valeur de la commission
     * @param string $vatRate         Taux de TVA
     * @return array{commission_ht: string, commission_vat: string, commission_ttc: string, deposant_net: string}
     */
    public function calculateDepotVenteCommission(
        string $salePrice,
        string $commissionType,
        string $commissionValue,
        string $vatRate = '20.0',
    ): array {
        if ($commissionType === 'forfait') {
            $commissionHt = $commissionValue;
        } else {
            $commissionHt = bcdiv(bcmul($salePrice, $commissionValue, 4), '100', 2);
        }

        $commissionVat = bcdiv(bcmul($commissionHt, $vatRate, 4), '100', 2);
        $commissionTtc = bcadd($commissionHt, $commissionVat, 2);
        $deposantNet = bcsub($salePrice, $commissionTtc, 2);

        return [
            'commission_ht' => $commissionHt,
            'commission_vat' => $commissionVat,
            'commission_ttc' => $commissionTtc,
            'deposant_net' => $deposantNet,
        ];
    }
}
