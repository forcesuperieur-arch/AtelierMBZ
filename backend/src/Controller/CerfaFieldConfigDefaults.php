<?php

namespace App\Controller;

/**
 * Valeurs par défaut des positions CERFA — extraites de CerfaOverlayService.
 * Utilisées pour le reset et pour le seeding initial.
 */
final class CerfaFieldConfigDefaults
{
    /**
     * Retourne les defaults x/y/width/font_size pour un champ donné.
     * @return array{x:string,y:string,width:string,font_size:string}|null
     */
    public static function getDefaults(string $cerfaRef, string $fieldKey): ?array
    {
        $all = self::all();
        return $all[$cerfaRef][$fieldKey] ?? null;
    }

    /**
     * Retourne tous les defaults sous forme cerfa_ref → field_key → [x, y, width, font_size].
     *
     * @return array<string, array<string, array{x:string,y:string,width:string,font_size:string,label:string,field_type:string,description:string|null}>>
     */
    public static function all(): array
    {
        return [
            'cerfa_13751' => [
                // Déclarant professionnel
                'declarant_checkbox'         => ['x' => '60.2',  'y' => '27.5',  'width' => '0',    'font_size' => '0',  'label' => 'Case déclarant pro', 'field_type' => 'checkbox', 'description' => 'Coche "Professionnel"'],
                'declarant_nom'              => ['x' => '36.0',  'y' => '38.4',  'width' => '122.0','font_size' => '9',  'label' => 'Nom / raison sociale déclarant', 'field_type' => 'text', 'description' => null],
                'declarant_siren'            => ['x' => '163.3', 'y' => '38.4',  'width' => '0',    'font_size' => '9',  'label' => 'SIREN déclarant', 'field_type' => 'boxed', 'description' => '9 caractères boxés'],
                'declarant_num_voie'         => ['x' => '35.0',  'y' => '47.3',  'width' => '14.0', 'font_size' => '8',  'label' => 'N° voie déclarant', 'field_type' => 'text', 'description' => null],
                'declarant_ext_voie'         => ['x' => '52.5',  'y' => '47.3',  'width' => '14.0', 'font_size' => '8',  'label' => 'Extension voie déclarant', 'field_type' => 'text', 'description' => null],
                'declarant_type_voie'        => ['x' => '70.5',  'y' => '47.3',  'width' => '24.0', 'font_size' => '8',  'label' => 'Type de voie déclarant', 'field_type' => 'text', 'description' => null],
                'declarant_nom_voie'         => ['x' => '97.8',  'y' => '47.3',  'width' => '101.0','font_size' => '8',  'label' => 'Nom de voie déclarant', 'field_type' => 'text', 'description' => null],
                'declarant_cp'               => ['x' => '29.8',  'y' => '55.8',  'width' => '0',    'font_size' => '8',  'label' => 'Code postal déclarant', 'field_type' => 'boxed', 'description' => '5 caractères boxés'],
                'declarant_ville'            => ['x' => '58.5',  'y' => '55.8',  'width' => '140.0','font_size' => '8',  'label' => 'Ville déclarant', 'field_type' => 'text', 'description' => null],
                'date_acquisition'           => ['x' => '44.0',  'y' => '63.6',  'width' => '0',    'font_size' => '8',  'label' => 'Date acquisition', 'field_type' => 'date', 'description' => 'JJ/MM/AAAA boxés'],
                'vehicle_plaque'             => ['x' => '10.5',  'y' => '75.5',  'width' => '59.0', 'font_size' => '9',  'label' => 'Immatriculation véhicule', 'field_type' => 'text', 'description' => null],
                'vehicle_vin'                => ['x' => '75.0',  'y' => '78.0',  'width' => '60.0', 'font_size' => '8',  'label' => 'VIN', 'field_type' => 'text', 'description' => null],
                'vehicle_marque'             => ['x' => '139.0', 'y' => '78.0',  'width' => '60.0', 'font_size' => '8',  'label' => 'Marque véhicule', 'field_type' => 'text', 'description' => null],
                'vehicle_type_variante'      => ['x' => '10.5',  'y' => '86.6',  'width' => '84.0', 'font_size' => '8',  'label' => 'Type variante', 'field_type' => 'text', 'description' => null],
                'vehicle_denomination'       => ['x' => '99.5',  'y' => '86.6',  'width' => '54.0', 'font_size' => '8',  'label' => 'Dénomination commerciale', 'field_type' => 'text', 'description' => null],
                'vehicle_genre'              => ['x' => '157.0', 'y' => '86.6',  'width' => '42.0', 'font_size' => '8',  'label' => 'Genre national', 'field_type' => 'text', 'description' => null],
                'ville_signature'            => ['x' => '15.0',  'y' => '140.1', 'width' => '70.0', 'font_size' => '8',  'label' => 'Ville (signature)', 'field_type' => 'text', 'description' => null],
                'date_signature'             => ['x' => '97.0',  'y' => '140.1', 'width' => '0',    'font_size' => '8',  'label' => 'Date signature', 'field_type' => 'date', 'description' => null],
                'vendeur_nom'                => ['x' => '36.0',  'y' => '205.1', 'width' => '116.0','font_size' => '9',  'label' => 'Nom vendeur', 'field_type' => 'text', 'description' => null],
                'vendeur_num_voie'           => ['x' => '30.0',  'y' => '215.7', 'width' => '14.0', 'font_size' => '8',  'label' => 'N° voie vendeur', 'field_type' => 'text', 'description' => null],
                'vendeur_ext_voie'           => ['x' => '47.5',  'y' => '215.7', 'width' => '14.0', 'font_size' => '8',  'label' => 'Extension voie vendeur', 'field_type' => 'text', 'description' => null],
                'vendeur_type_voie'          => ['x' => '66.0',  'y' => '215.7', 'width' => '24.0', 'font_size' => '8',  'label' => 'Type de voie vendeur', 'field_type' => 'text', 'description' => null],
                'vendeur_nom_voie'           => ['x' => '93.0',  'y' => '215.7', 'width' => '106.0','font_size' => '8',  'label' => 'Nom de voie vendeur', 'field_type' => 'text', 'description' => null],
                'vendeur_cp'                 => ['x' => '30.0',  'y' => '225.4', 'width' => '0',    'font_size' => '7',  'label' => 'Code postal vendeur', 'field_type' => 'boxed', 'description' => '5 caractères boxés'],
                'vendeur_ville'              => ['x' => '59.0',  'y' => '225.4', 'width' => '139.0','font_size' => '7',  'label' => 'Ville vendeur', 'field_type' => 'text', 'description' => null],
                'date_cession_vendeur'       => ['x' => '132.0', 'y' => '234.5', 'width' => '0',    'font_size' => '8',  'label' => 'Date cession (vendeur)', 'field_type' => 'date', 'description' => null],
            ],

            'cerfa_13757' => [
                // Mandant (acheteur)
                'mandant_nom'                => ['x' => '38.6',  'y' => '46.0',  'width' => '96.3', 'font_size' => '9',  'label' => 'Nom mandant (acheteur)', 'field_type' => 'text', 'description' => null],
                'mandant_num_voie'           => ['x' => '36.5',  'y' => '70.5',  'width' => '14.7', 'font_size' => '8',  'label' => 'N° voie mandant', 'field_type' => 'text', 'description' => null],
                'mandant_ext_voie'           => ['x' => '52.7',  'y' => '70.5',  'width' => '14.7', 'font_size' => '8',  'label' => 'Extension voie mandant', 'field_type' => 'text', 'description' => null],
                'mandant_type_voie'          => ['x' => '69.0',  'y' => '70.5',  'width' => '14.7', 'font_size' => '8',  'label' => 'Type de voie mandant', 'field_type' => 'text', 'description' => null],
                'mandant_nom_voie'           => ['x' => '96.3',  'y' => '70.5',  'width' => '101.2','font_size' => '8',  'label' => 'Nom de voie mandant', 'field_type' => 'text', 'description' => null],
                'mandant_cp'                 => ['x' => '36.7',  'y' => '86.3',  'width' => '24.6', 'font_size' => '8',  'label' => 'Code postal mandant', 'field_type' => 'text', 'description' => null],
                'mandant_ville'              => ['x' => '64.6',  'y' => '86.3',  'width' => '65.3', 'font_size' => '8',  'label' => 'Ville mandant', 'field_type' => 'text', 'description' => null],
                'mandant_pays'               => ['x' => '133.1', 'y' => '86.3',  'width' => '64.6', 'font_size' => '8',  'label' => 'Pays mandant', 'field_type' => 'text', 'description' => null],
                // Mandataire (atelier)
                'mandataire_nom'             => ['x' => '40.1',  'y' => '102.4', 'width' => '96.3', 'font_size' => '9',  'label' => 'Nom mandataire (atelier)', 'field_type' => 'text', 'description' => null],
                'mandataire_siren'           => ['x' => '142.3', 'y' => '102.4', 'width' => '55.8', 'font_size' => '8',  'label' => 'SIREN mandataire', 'field_type' => 'text', 'description' => null],
                'objet_mandat'               => ['x' => '38.2',  'y' => '126.8', 'width' => '119.8','font_size' => '8',  'label' => 'Objet du mandat', 'field_type' => 'text', 'description' => null],
                'vehicle_marque'             => ['x' => '40.5',  'y' => '142.8', 'width' => '101.6','font_size' => '8',  'label' => 'Marque véhicule', 'field_type' => 'text', 'description' => null],
                'vehicle_vin'                => ['x' => '40.1',  'y' => '159.4', 'width' => '0',    'font_size' => '8',  'label' => 'VIN véhicule', 'field_type' => 'boxed', 'description' => '17 caractères boxés'],
                'vehicle_plaque'             => ['x' => '83.3',  'y' => '176.6', 'width' => '79.3', 'font_size' => '8',  'label' => 'Immatriculation véhicule', 'field_type' => 'text', 'description' => null],
                'ville_signature'            => ['x' => '21.0',  'y' => '211.2', 'width' => '68.4', 'font_size' => '8',  'label' => 'Ville (signature)', 'field_type' => 'text', 'description' => null],
                'date_signature_jour'        => ['x' => '99.9',  'y' => '212.0', 'width' => '0',    'font_size' => '8',  'label' => 'Jour signature', 'field_type' => 'boxed', 'description' => '2 caractères'],
                'date_signature_mois'        => ['x' => '111.9', 'y' => '212.0', 'width' => '0',    'font_size' => '8',  'label' => 'Mois signature', 'field_type' => 'boxed', 'description' => '2 caractères'],
                'date_signature_annee'       => ['x' => '124.0', 'y' => '212.0', 'width' => '0',    'font_size' => '8',  'label' => 'Année signature', 'field_type' => 'boxed', 'description' => '4 caractères'],
            ],

            'cerfa_15776' => [
                // Véhicule
                'vehicle_plaque'             => ['x' => '12.0',  'y' => '35.0',  'width' => '44.0', 'font_size' => '7',  'label' => 'Immatriculation', 'field_type' => 'text', 'description' => null],
                'vehicle_vin'                => ['x' => '63.0',  'y' => '35.0',  'width' => '0',    'font_size' => '7',  'label' => 'VIN', 'field_type' => 'boxed', 'description' => '17 caractères boxés'],
                'vehicle_mec'                => ['x' => '155.0', 'y' => '35.0',  'width' => '0',    'font_size' => '7',  'label' => 'Date 1ère mise en circulation', 'field_type' => 'date', 'description' => null],
                'vehicle_marque'             => ['x' => '12.0',  'y' => '44.0',  'width' => '44.0', 'font_size' => '7',  'label' => 'Marque', 'field_type' => 'text', 'description' => null],
                'vehicle_type_variante'      => ['x' => '63.0',  'y' => '44.0',  'width' => '45.0', 'font_size' => '7',  'label' => 'Type variante', 'field_type' => 'text', 'description' => null],
                'vehicle_genre'              => ['x' => '115.0', 'y' => '44.0',  'width' => '38.0', 'font_size' => '7',  'label' => 'Genre national', 'field_type' => 'text', 'description' => null],
                'vehicle_denomination'       => ['x' => '160.0', 'y' => '44.0',  'width' => '37.0', 'font_size' => '7',  'label' => 'Dénomination commerciale', 'field_type' => 'text', 'description' => null],
                'vehicle_kilometrage'        => ['x' => '77.0',  'y' => '53.5',  'width' => '20.0', 'font_size' => '7',  'label' => 'Kilométrage', 'field_type' => 'text', 'description' => null],
                'vehicle_num_formule_cg'     => ['x' => '48.0',  'y' => '64.5',  'width' => '0',    'font_size' => '7',  'label' => 'N° formule carte grise', 'field_type' => 'boxed', 'description' => '11 caractères boxés'],
                // Vendeur
                'vendeur_nom'                => ['x' => '36.0',  'y' => '100.5', 'width' => '103.0','font_size' => '8',  'label' => 'Nom vendeur', 'field_type' => 'text', 'description' => null],
                'vendeur_num_voie'           => ['x' => '39.0',  'y' => '110.5', 'width' => '14.0', 'font_size' => '7',  'label' => 'N° voie vendeur', 'field_type' => 'text', 'description' => null],
                'vendeur_ext_voie'           => ['x' => '56.0',  'y' => '110.5', 'width' => '14.0', 'font_size' => '7',  'label' => 'Extension voie vendeur', 'field_type' => 'text', 'description' => null],
                'vendeur_type_voie'          => ['x' => '68.0',  'y' => '110.5', 'width' => '24.0', 'font_size' => '7',  'label' => 'Type de voie vendeur', 'field_type' => 'text', 'description' => null],
                'vendeur_nom_voie'           => ['x' => '94.0',  'y' => '110.5', 'width' => '103.0','font_size' => '7',  'label' => 'Nom de voie vendeur', 'field_type' => 'text', 'description' => null],
                'vendeur_cp'                 => ['x' => '37.0',  'y' => '119.0', 'width' => '0',    'font_size' => '7',  'label' => 'Code postal vendeur', 'field_type' => 'boxed', 'description' => '5 caractères boxés'],
                'vendeur_ville'              => ['x' => '68.0',  'y' => '119.0', 'width' => '128.0','font_size' => '7',  'label' => 'Ville vendeur', 'field_type' => 'text', 'description' => null],
                'date_cession'               => ['x' => '19.0',  'y' => '136.0', 'width' => '0',    'font_size' => '7',  'label' => 'Date de cession', 'field_type' => 'date', 'description' => null],
                'ville_cession_vendeur'      => ['x' => '17.0',  'y' => '181.8', 'width' => '42.0', 'font_size' => '7',  'label' => 'Ville signature vendeur', 'field_type' => 'text', 'description' => null],
                'date_cession_vendeur'       => ['x' => '69.0',  'y' => '181.8', 'width' => '0',    'font_size' => '7',  'label' => 'Date signature vendeur', 'field_type' => 'date', 'description' => null],
                // Acheteur
                'acheteur_nom'               => ['x' => '36.0',  'y' => '220.5', 'width' => '103.0','font_size' => '8',  'label' => 'Nom acheteur', 'field_type' => 'text', 'description' => null],
                'acheteur_siren'             => ['x' => '147.0', 'y' => '220.5', 'width' => '0',    'font_size' => '7',  'label' => 'SIREN acheteur (si pro)', 'field_type' => 'boxed', 'description' => '9 caractères boxés'],
                'acheteur_num_voie'          => ['x' => '39.0',  'y' => '239.0', 'width' => '14.0', 'font_size' => '7',  'label' => 'N° voie acheteur', 'field_type' => 'text', 'description' => null],
                'acheteur_ext_voie'          => ['x' => '56.0',  'y' => '239.0', 'width' => '14.0', 'font_size' => '7',  'label' => 'Extension voie acheteur', 'field_type' => 'text', 'description' => null],
                'acheteur_type_voie'         => ['x' => '68.0',  'y' => '239.0', 'width' => '24.0', 'font_size' => '7',  'label' => 'Type de voie acheteur', 'field_type' => 'text', 'description' => null],
                'acheteur_nom_voie'          => ['x' => '94.0',  'y' => '239.0', 'width' => '103.0','font_size' => '7',  'label' => 'Nom de voie acheteur', 'field_type' => 'text', 'description' => null],
                'acheteur_cp'                => ['x' => '37.0',  'y' => '247.5', 'width' => '0',    'font_size' => '7',  'label' => 'Code postal acheteur', 'field_type' => 'boxed', 'description' => '5 caractères boxés'],
                'acheteur_ville'             => ['x' => '68.0',  'y' => '247.5', 'width' => '128.0','font_size' => '7',  'label' => 'Ville acheteur', 'field_type' => 'text', 'description' => null],
                'ville_cession_acheteur'     => ['x' => '17.0',  'y' => '268.5', 'width' => '42.0', 'font_size' => '7',  'label' => 'Ville signature acheteur', 'field_type' => 'text', 'description' => null],
                'date_cession_acheteur'      => ['x' => '69.0',  'y' => '268.5', 'width' => '0',    'font_size' => '7',  'label' => 'Date signature acheteur', 'field_type' => 'date', 'description' => null],
            ],
        ];
    }
}
