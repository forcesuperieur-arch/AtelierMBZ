<?php

namespace App\Service;

/**
 * Inventaire des documents PDF de l'application.
 *
 * Source unique de vérité, partagée par :
 *  - l'écran d'administration « Templates documents » (liste + prévisualisation) ;
 *  - le designer d'en-tête (codes autorisés) ;
 *  - PdfService (chemin du template, orientation du papier) ;
 *  - le test de fumée qui rend chaque document enregistré.
 *
 * Historiquement la liste était recopiée dans le contrôleur d'administration et
 * dans DocumentLayout::CODES. Les deux avaient divergé du disque : l'admin
 * proposait « rapport_intervention » sans template (prévisualisation en erreur
 * 500), et l'état des lieux comme le rapport analytique étaient générés par
 * l'application sans jamais apparaître dans l'administration.
 */
final class PdfTemplateRegistry
{
    public const PORTRAIT = 'portrait';
    public const LANDSCAPE = 'landscape';

    public const CATEGORY_ATELIER = 'atelier';
    public const CATEGORY_VO = 'vo';
    public const CATEGORY_PILOTAGE = 'pilotage';

    /**
     * @var array<string, array{
     *     label: string,
     *     template: string,
     *     category: string,
     *     description: string,
     *     orientation: string,
     *     customisableHeader: bool,
     * }>
     */
    private const TEMPLATES = [
        'ordre_reparation' => [
            'label' => 'Ordre de réparation',
            'template' => 'pdf/ordre_reparation.html.twig',
            'category' => self::CATEGORY_ATELIER,
            'description' => "Document unique en trois volets : réception, intervention, restitution.",
            'orientation' => self::PORTRAIT,
            'customisableHeader' => true,
        ],
        'facture' => [
            'label' => 'Facture atelier',
            'template' => 'pdf/facture.html.twig',
            'category' => self::CATEGORY_ATELIER,
            'description' => 'Facture client avec lignes, TVA, totaux et mentions légales.',
            'orientation' => self::PORTRAIT,
            'customisableHeader' => true,
        ],
        'devis' => [
            'label' => 'Devis',
            'template' => 'pdf/devis.html.twig',
            'category' => self::CATEGORY_ATELIER,
            'description' => 'Devis avant travaux avec validité, acompte et bon pour accord.',
            'orientation' => self::PORTRAIT,
            'customisableHeader' => true,
        ],
        'rapport_intervention' => [
            'label' => "Rapport d'intervention",
            'template' => 'pdf/rapport_intervention.html.twig',
            'category' => self::CATEGORY_ATELIER,
            'description' => "Compte-rendu remis au client, avec essai routier et signatures.",
            'orientation' => self::PORTRAIT,
            'customisableHeader' => true,
        ],
        'etat_des_lieux' => [
            'label' => "État des lieux d'entrée",
            'template' => 'pdf/etat_des_lieux.html.twig',
            'category' => self::CATEGORY_ATELIER,
            'description' => "Constat signé à la prise en charge : kilométrage, carburant, photos.",
            'orientation' => self::PORTRAIT,
            'customisableHeader' => true,
        ],
        'historique_entretien' => [
            'label' => 'Historique entretien',
            'template' => 'pdf/historique_entretien.html.twig',
            'category' => self::CATEGORY_ATELIER,
            'description' => 'Synthèse des interventions passées sur le véhicule.',
            'orientation' => self::PORTRAIT,
            'customisableHeader' => true,
        ],
        'vo_pv_rachat' => [
            'label' => 'PV de rachat',
            'template' => 'pdf/vo_pv_rachat.html.twig',
            'category' => self::CATEGORY_VO,
            'description' => 'Procès-verbal de rachat VO avec identité du vendeur.',
            'orientation' => self::PORTRAIT,
            'customisableHeader' => true,
        ],
        'vo_facture' => [
            'label' => 'Facture VO',
            'template' => 'pdf/vo_facture.html.twig',
            'category' => self::CATEGORY_VO,
            'description' => 'Facture de vente VO avec régime de TVA et garanties légales.',
            'orientation' => self::PORTRAIT,
            'customisableHeader' => true,
        ],
        'vo_contrat_depot_vente' => [
            'label' => 'Contrat dépôt-vente',
            'template' => 'pdf/vo_contrat_depot_vente.html.twig',
            'category' => self::CATEGORY_VO,
            'description' => 'Mandat de dépôt-vente avec prix cible, commission et clauses.',
            'orientation' => self::PORTRAIT,
            'customisableHeader' => true,
        ],
        'vo_livre_police' => [
            'label' => 'Livre de police',
            'template' => 'pdf/vo_livre_police.html.twig',
            'category' => self::CATEGORY_VO,
            // Onze colonnes : illisible en portrait, le registre est rendu en paysage.
            'description' => 'Extrait du registre légal achat/vente VO (paysage).',
            'orientation' => self::LANDSCAPE,
            'customisableHeader' => true,
        ],
        'vo_da_siv' => [
            'label' => 'Préparation DA SIV',
            'template' => 'pdf/vo_da_siv.html.twig',
            'category' => self::CATEGORY_VO,
            'description' => "Préparation administrative de la déclaration d'achat.",
            'orientation' => self::PORTRAIT,
            'customisableHeader' => true,
        ],
        'vo_mandat_immatriculation' => [
            'label' => "Mandat d'immatriculation",
            'template' => 'pdf/vo_mandat_immatriculation.html.twig',
            'category' => self::CATEGORY_VO,
            'description' => 'Support prérempli pour les formalités SIV côté acheteur.',
            'orientation' => self::PORTRAIT,
            'customisableHeader' => true,
        ],
        'vo_remise_en_etat' => [
            'label' => 'Remise en état VO',
            'template' => 'pdf/vo_remise_en_etat.html.twig',
            'category' => self::CATEGORY_VO,
            'description' => 'Synthèse FRE avec coûts estimés, pièces et arbitrage.',
            'orientation' => self::PORTRAIT,
            'customisableHeader' => true,
        ],
        'dashboard_report' => [
            'label' => 'Rapport analytique',
            'template' => 'pdf/dashboard_report.html.twig',
            'category' => self::CATEGORY_PILOTAGE,
            'description' => "Export du tableau de bord : KPI, tendance, top prestations.",
            'orientation' => self::PORTRAIT,
            'customisableHeader' => false,
        ],
    ];

    /**
     * @return list<array{code: string, label: string, template: string, category: string, description: string, orientation: string, customisableHeader: bool}>
     */
    public function all(): array
    {
        $result = [];
        foreach (self::TEMPLATES as $code => $meta) {
            $result[] = ['code' => $code, ...$meta];
        }

        return $result;
    }

    /** @return list<string> */
    public function codes(): array
    {
        return array_keys(self::TEMPLATES);
    }

    public function has(string $code): bool
    {
        return isset(self::TEMPLATES[$code]);
    }

    /**
     * @return array{label: string, template: string, category: string, description: string, orientation: string, customisableHeader: bool}
     */
    public function get(string $code): array
    {
        if (!isset(self::TEMPLATES[$code])) {
            throw new \InvalidArgumentException(sprintf('Document PDF inconnu : « %s ».', $code));
        }

        return self::TEMPLATES[$code];
    }

    public function templateFor(string $code): string
    {
        return $this->get($code)['template'];
    }

    public function labelFor(string $code): string
    {
        return $this->get($code)['label'];
    }

    public function orientationFor(string $code): string
    {
        return $this->get($code)['orientation'];
    }

    /** Les codes dont l'en-tête peut être recomposé dans le designer admin. */
    public function customisableHeaderCodes(): array
    {
        return array_keys(array_filter(
            self::TEMPLATES,
            static fn (array $meta): bool => $meta['customisableHeader'],
        ));
    }
}
