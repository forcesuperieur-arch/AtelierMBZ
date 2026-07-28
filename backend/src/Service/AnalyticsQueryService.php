<?php

namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;

/**
 * Moteur de requête de l'Explorateur.
 *
 * Un seul point d'entrée pour interroger la table de faits `analytics_rdv_facts`
 * avec : 0 à 2 axes, plusieurs mesures, une pile de filtres cumulables, et un
 * mode détail qui rend les rendez-vous derrière n'importe quelle case.
 *
 * SÉCURITÉ — le principe de base : **rien de ce que le navigateur envoie
 * n'entre dans le SQL**. Les axes, les mesures et les opérateurs sont des CLÉS
 * qui désignent des fragments SQL écrits ici ; une clé inconnue est refusée.
 * Les valeurs de filtre, elles, sont toujours passées en paramètres liés.
 * L'atelier est imposé par le serveur : jamais négociable depuis la requête.
 */
class AnalyticsQueryService
{
    /** Nombre de lignes d'agrégat renvoyées au maximum (au-delà : « autres »). */
    private const LIMITE_AGREGAT = 200;

    /** Nombre de rendez-vous renvoyés en mode détail. */
    private const LIMITE_DETAIL = 300;

    public function __construct(private EntityManagerInterface $em) {}

    /**
     * Axes d'analyse : clé publique → expression SQL + libellé.
     * `sql` sert au GROUP BY et au SELECT ; `tri` indique le tri naturel de
     * l'axe (une saisonnalité se lit dans l'ordre du calendrier, pas par volume).
     */
    public function axes(): array
    {
        return [
            'type_intervention' => ['sql' => "COALESCE(NULLIF(f.type_intervention, ''), 'Non précisé')", 'libelle' => "Type d'intervention", 'tri' => 'mesure'],
            'mecanicien_nom' => ['sql' => "COALESCE(NULLIF(f.mecanicien_nom, ''), 'Non affecté')", 'libelle' => 'Mécanicien', 'tri' => 'mesure'],
            'statut_rdv' => ['sql' => 'f.statut_rdv', 'libelle' => 'Statut du rendez-vous', 'tri' => 'mesure'],
            'pont_nom' => ['sql' => "COALESCE(NULLIF(f.pont_nom, ''), 'Sans pont')", 'libelle' => 'Pont', 'tri' => 'mesure'],
            'client_segment' => ['sql' => "COALESCE(NULLIF(f.client_segment, ''), 'Inconnu')", 'libelle' => 'Segment client', 'tri' => 'mesure'],
            'vehicule_marque' => ['sql' => "COALESCE(NULLIF(f.vehicule_marque, ''), 'Non renseignée')", 'libelle' => 'Marque de la moto', 'tri' => 'mesure'],
            'origine' => ['sql' => "COALESCE(NULLIF(f.origine, ''), 'inconnu')", 'libelle' => 'Origine du rendez-vous', 'tri' => 'mesure'],
            'litige' => ['sql' => "CASE WHEN f.litige_signale THEN 'Litige signalé' ELSE 'Sans litige' END", 'libelle' => 'Litige', 'tri' => 'mesure'],
            'travaux_comp' => ['sql' => "CASE WHEN f.has_travaux_complementaires THEN 'Avec travaux supp.' ELSE 'Sans travaux supp.' END", 'libelle' => 'Travaux supplémentaires', 'tri' => 'mesure'],
            // Axes de temps — indispensables pour la charge et la saisonnalité.
            'mois' => ['sql' => "to_char(f.date_rdv, 'YYYY-MM')", 'libelle' => 'Mois', 'tri' => 'axe'],
            'semaine' => ['sql' => "to_char(f.date_rdv, 'IYYY-\"S\"IW')", 'libelle' => 'Semaine', 'tri' => 'axe'],
            'jour_semaine' => ['sql' => "to_char(f.date_rdv, 'ID') || ' ' || CASE to_char(f.date_rdv, 'ID') WHEN '1' THEN 'lundi' WHEN '2' THEN 'mardi' WHEN '3' THEN 'mercredi' WHEN '4' THEN 'jeudi' WHEN '5' THEN 'vendredi' WHEN '6' THEN 'samedi' ELSE 'dimanche' END", 'libelle' => 'Jour de la semaine', 'tri' => 'axe'],
            'jour' => ['sql' => "to_char(f.date_rdv, 'YYYY-MM-DD')", 'libelle' => 'Jour', 'tri' => 'axe'],
            'heure' => ['sql' => "lpad(EXTRACT(HOUR FROM f.heure_rdv)::text, 2, '0') || ' h'", 'libelle' => 'Heure du rendez-vous', 'tri' => 'axe'],
        ];
    }

    /**
     * Mesures : clé publique → expression d'agrégat, libellé, unité, et sens
     * favorable (pour colorer un écart sans se tromper de camp).
     */
    public function mesures(): array
    {
        return [
            'count' => ['sql' => 'COUNT(*)', 'libelle' => 'Rendez-vous', 'unite' => 'nombre', 'bon' => 'haut'],
            'clients_uniques' => ['sql' => 'COUNT(DISTINCT f.client_id)', 'libelle' => 'Clients distincts', 'unite' => 'nombre', 'bon' => 'haut'],
            'motos_uniques' => ['sql' => 'COUNT(DISTINCT f.vehicule_id)', 'libelle' => 'Motos distinctes', 'unite' => 'nombre', 'bon' => 'haut'],
            'rdv_par_client' => ['sql' => 'CASE WHEN COUNT(DISTINCT f.client_id) = 0 THEN 0 ELSE ROUND(COUNT(*)::numeric / COUNT(DISTINCT f.client_id), 2) END', 'libelle' => 'RDV par client', 'unite' => 'decimal', 'bon' => 'haut'],

            'temps_estime_total' => ['sql' => 'COALESCE(SUM(f.temps_estime), 0)', 'libelle' => 'Temps estimé (total)', 'unite' => 'minutes', 'bon' => 'neutre'],
            'temps_estime_moyen' => ['sql' => 'ROUND(COALESCE(AVG(f.temps_estime), 0))', 'libelle' => 'Temps estimé (moyen)', 'unite' => 'minutes', 'bon' => 'neutre'],
            'temps_effectif_total' => ['sql' => 'COALESCE(SUM(f.temps_effectif), 0)', 'libelle' => 'Temps pointé (total)', 'unite' => 'minutes', 'bon' => 'neutre'],
            'temps_effectif_moyen' => ['sql' => 'ROUND(COALESCE(AVG(f.temps_effectif), 0))', 'libelle' => 'Temps pointé (moyen)', 'unite' => 'minutes', 'bon' => 'neutre'],
            // L'écart ne se calcule que sur les interventions réellement pointées :
            // mélanger les non-pointées ferait croire à une avance systématique.
            'ecart_moyen' => ['sql' => 'ROUND(COALESCE(AVG(CASE WHEN f.temps_effectif IS NOT NULL AND f.temps_effectif > 0 THEN f.temps_effectif - COALESCE(f.temps_estime, 0) END), 0))', 'libelle' => 'Écart moyen pointé − estimé', 'unite' => 'minutes', 'bon' => 'bas'],
            'taux_depassement' => ['sql' => "ROUND(100.0 * COUNT(*) FILTER (WHERE f.temps_effectif IS NOT NULL AND f.temps_effectif > COALESCE(f.temps_estime, 0)) / NULLIF(COUNT(*) FILTER (WHERE f.temps_effectif IS NOT NULL AND f.temps_effectif > 0), 0), 1)", 'libelle' => 'Part en dépassement', 'unite' => 'pourcent', 'bon' => 'bas'],
            'pointage_renseigne' => ['sql' => "ROUND(100.0 * COUNT(*) FILTER (WHERE f.temps_effectif IS NOT NULL AND f.temps_effectif > 0) / NULLIF(COUNT(*), 0), 1)", 'libelle' => 'Temps effectivement pointé', 'unite' => 'pourcent', 'bon' => 'haut'],

            'delai_prise_en_charge' => ['sql' => 'ROUND(COALESCE(AVG(f.delai_reception_debut), 0))', 'libelle' => 'Délai réception → début', 'unite' => 'minutes', 'bon' => 'bas'],
            'delai_restitution' => ['sql' => 'ROUND(COALESCE(AVG(f.delai_fin_restitution), 0))', 'libelle' => 'Délai fin → restitution', 'unite' => 'minutes', 'bon' => 'bas'],
            'duree_immobilisation' => ['sql' => 'ROUND(COALESCE(AVG(f.delai_total_cycle), 0))', 'libelle' => 'Immobilisation totale', 'unite' => 'minutes', 'bon' => 'bas'],

            'taux_litige' => ['sql' => "ROUND(100.0 * COUNT(*) FILTER (WHERE f.litige_signale) / NULLIF(COUNT(*), 0), 1)", 'libelle' => 'Part avec litige', 'unite' => 'pourcent', 'bon' => 'bas'],
            'taux_travaux_comp' => ['sql' => "ROUND(100.0 * COUNT(*) FILTER (WHERE f.has_travaux_complementaires) / NULLIF(COUNT(*), 0), 1)", 'libelle' => 'Part avec travaux supp.', 'unite' => 'pourcent', 'bon' => 'neutre'],

            'ca_ht' => ['sql' => 'COALESCE(SUM(f.ca_ht), 0)', 'libelle' => 'CA HT', 'unite' => 'euros', 'bon' => 'haut', 'module' => 'facturation'],
            'ca_mo_ht' => ['sql' => 'COALESCE(SUM(f.ca_mo_ht), 0)', 'libelle' => 'CA main-d’œuvre HT', 'unite' => 'euros', 'bon' => 'haut', 'module' => 'facturation'],
            'ca_pieces_ht' => ['sql' => 'COALESCE(SUM(f.ca_pieces_ht), 0)', 'libelle' => 'CA pièces HT', 'unite' => 'euros', 'bon' => 'haut', 'module' => 'facturation'],
            'panier_moyen' => ['sql' => 'ROUND(COALESCE(AVG(NULLIF(f.ca_ht, 0)), 0), 2)', 'libelle' => 'Panier moyen HT', 'unite' => 'euros', 'bon' => 'haut', 'module' => 'facturation'],
        ];
    }

    /** Champs filtrables : clé publique → expression SQL comparable. */
    private function champsFiltrables(): array
    {
        $axes = $this->axes();
        $champs = [];
        foreach ($axes as $cle => $def) {
            $champs[$cle] = $def['sql'];
        }

        return $champs;
    }

    /**
     * Exécute une requête d'exploration.
     *
     * @param array{from:string,to:string,dimensions:array,measures:array,filters:array,mode?:string} $requete
     */
    public function executer(int $atelierId, array $requete): array
    {
        $axes = $this->axes();
        $mesures = $this->mesures();

        [$from, $to] = $this->periode($requete);

        // Axes : deux au maximum. Au-delà, un tableau croisé devient illisible
        // et la requête explose en cardinalité.
        $dimensions = array_values(array_filter(
            (array) ($requete['dimensions'] ?? []),
            static fn ($d) => isset($axes[$d])
        ));
        $dimensions = array_slice(array_unique($dimensions), 0, 2);

        $demandees = array_values(array_filter(
            (array) ($requete['measures'] ?? []),
            static fn ($m) => isset($mesures[$m])
        ));
        if (!$demandees) {
            $demandees = ['count'];
        }
        $demandees = array_slice(array_unique($demandees), 0, 6);

        [$conditions, $params] = $this->construireFiltres($requete['filters'] ?? []);
        $params['atelier'] = $atelierId;
        $params['du'] = $from;
        $params['au'] = $to;

        $where = 'f.atelier_id = :atelier AND f.date_rdv BETWEEN :du AND :au';
        if ($conditions) {
            $where .= ' AND ' . implode(' AND ', $conditions);
        }

        if (($requete['mode'] ?? 'agregat') === 'detail') {
            return [
                'mode' => 'detail',
                'periode' => ['from' => $from, 'to' => $to],
                'rows' => $this->detail($where, $params),
                'univers' => $this->univers($atelierId, $from, $to, $where, $params),
            ];
        }

        return [
            'mode' => 'agregat',
            'periode' => ['from' => $from, 'to' => $to],
            'dimensions' => $dimensions,
            'measures' => $demandees,
            'rows' => $this->agregat($dimensions, $demandees, $where, $params),
            'total' => $this->totalSelection($demandees, $where, $params),
            'univers' => $this->univers($atelierId, $from, $to, $where, $params),
            'facettes' => $this->facettes($where, $params),
        ];
    }

    /** Période demandée, bornée à des dates valides (défaut : 90 derniers jours). */
    private function periode(array $requete): array
    {
        $aujourdhui = new \DateTimeImmutable('today');
        try {
            $from = isset($requete['from']) && $requete['from']
                ? new \DateTimeImmutable((string) $requete['from'])
                : $aujourdhui->modify('-89 days');
            $to = isset($requete['to']) && $requete['to']
                ? new \DateTimeImmutable((string) $requete['to'])
                : $aujourdhui;
        } catch (\Throwable) {
            $from = $aujourdhui->modify('-89 days');
            $to = $aujourdhui;
        }
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        return [$from->format('Y-m-d'), $to->format('Y-m-d')];
    }

    /**
     * Traduit la pile de filtres en conditions SQL paramétrées.
     * Un filtre = un champ du catalogue + une liste de valeurs (inclusion), ou
     * une exclusion. C'est ce qui rend les sélections cumulables : chaque clic
     * ajoute une condition, la retirer remet l'univers en place.
     */
    private function construireFiltres(mixed $filtres): array
    {
        $champs = $this->champsFiltrables();
        $conditions = [];
        $params = [];
        $i = 0;

        foreach ((array) $filtres as $filtre) {
            $champ = (string) ($filtre['field'] ?? '');
            if (!isset($champs[$champ])) {
                continue;
            }
            $valeurs = array_values(array_filter(
                (array) ($filtre['values'] ?? []),
                static fn ($v) => $v !== null && $v !== ''
            ));
            if (!$valeurs) {
                continue;
            }
            // Garde-fou : une liste de valeurs démesurée n'a aucun usage légitime.
            $valeurs = array_slice(array_map('strval', $valeurs), 0, 200);

            $exclusion = ($filtre['op'] ?? 'in') === 'not_in';
            $cle = 'f' . $i++;
            $conditions[] = sprintf(
                '%s %s (:%s)',
                $champs[$champ],
                $exclusion ? 'NOT IN' : 'IN',
                $cle
            );
            $params[$cle] = $valeurs;
        }

        return [$conditions, $params];
    }

    /** Types Doctrine des paramètres tableau (nécessaire pour les clauses IN). */
    private function typesParams(array $params): array
    {
        $types = [];
        foreach ($params as $cle => $valeur) {
            if (is_array($valeur)) {
                $types[$cle] = \Doctrine\DBAL\ArrayParameterType::STRING;
            }
        }

        return $types;
    }

    private function agregat(array $dimensions, array $demandees, string $where, array $params): array
    {
        $axes = $this->axes();
        $mesures = $this->mesures();
        $conn = $this->em->getConnection();

        $select = [];
        $groupBy = [];
        foreach ($dimensions as $index => $cle) {
            $select[] = sprintf('%s AS d%d', $axes[$cle]['sql'], $index);
            $groupBy[] = $axes[$cle]['sql'];
        }
        foreach ($demandees as $cle) {
            $select[] = sprintf('%s AS %s', $mesures[$cle]['sql'], $cle);
        }
        // Toujours renvoyer le nombre de RDV : c'est le repère qui permet de
        // juger si une moyenne repose sur 2 dossiers ou sur 200.
        if (!in_array('count', $demandees, true)) {
            $select[] = 'COUNT(*) AS count';
        }

        // Tri : par l'axe quand il est chronologique, par la mesure sinon.
        $premiere = $demandees[0];
        $triAxe = $dimensions && ($axes[$dimensions[0]]['tri'] ?? 'mesure') === 'axe';
        $orderBy = $dimensions
            ? ($triAxe ? 'd0 ASC' : sprintf('%s DESC NULLS LAST', $premiere))
            : '1';

        $sql = sprintf(
            'SELECT %s FROM analytics_rdv_facts f WHERE %s %s ORDER BY %s LIMIT %d',
            implode(', ', $select),
            $where,
            $groupBy ? 'GROUP BY ' . implode(', ', $groupBy) : '',
            $orderBy,
            self::LIMITE_AGREGAT
        );

        return $conn->fetchAllAssociative($sql, $params, $this->typesParams($params));
    }

    /** Mesures sur l'ensemble de la sélection (ligne « Total » du tableau). */
    private function totalSelection(array $demandees, string $where, array $params): array
    {
        $mesures = $this->mesures();
        $select = [];
        foreach ($demandees as $cle) {
            $select[] = sprintf('%s AS %s', $mesures[$cle]['sql'], $cle);
        }
        $select[] = 'COUNT(*) AS count';

        $ligne = $this->em->getConnection()->fetchAssociative(
            sprintf('SELECT %s FROM analytics_rdv_facts f WHERE %s', implode(', ', $select), $where),
            $params,
            $this->typesParams($params)
        );

        return $ligne ?: [];
    }

    /**
     * Le compteur « X sur Y » : combien de rendez-vous la sélection retient, sur
     * combien la période en contient. C'est ce qui donne le sens de l'échelle à
     * chaque clic.
     */
    private function univers(int $atelierId, string $from, string $to, string $where, array $params): array
    {
        $conn = $this->em->getConnection();

        $selection = (int) $conn->fetchOne(
            sprintf('SELECT COUNT(*) FROM analytics_rdv_facts f WHERE %s', $where),
            $params,
            $this->typesParams($params)
        );
        $periode = (int) $conn->fetchOne(
            'SELECT COUNT(*) FROM analytics_rdv_facts f WHERE f.atelier_id = :atelier AND f.date_rdv BETWEEN :du AND :au',
            ['atelier' => $atelierId, 'du' => $from, 'au' => $to]
        );

        return ['selection' => $selection, 'periode' => $periode];
    }

    /**
     * Valeurs disponibles par axe DANS la sélection courante, avec leur volume.
     * C'est le panneau de filtres : on ne propose que ce qui existe encore, ce
     * qui évite les sélections menant à un écran vide.
     */
    private function facettes(string $where, array $params): array
    {
        $conn = $this->em->getConnection();
        $facettes = [];
        // Seuls les axes catégoriels : proposer 90 jours de dates en cases à
        // cocher n'aurait aucun intérêt.
        $categoriels = ['type_intervention', 'mecanicien_nom', 'statut_rdv', 'pont_nom', 'client_segment', 'vehicule_marque', 'origine', 'litige', 'travaux_comp'];

        foreach ($categoriels as $cle) {
            $sql = $this->axes()[$cle]['sql'];
            $facettes[$cle] = $conn->fetchAllAssociative(
                sprintf(
                    'SELECT %s AS valeur, COUNT(*)::int AS nb FROM analytics_rdv_facts f WHERE %s GROUP BY %s ORDER BY nb DESC LIMIT 30',
                    $sql,
                    $where,
                    $sql
                ),
                $params,
                $this->typesParams($params)
            );
        }

        return $facettes;
    }

    /**
     * Les rendez-vous derrière la sélection. Sans cette descente au détail, un
     * chiffre agrégé reste une affirmation invérifiable.
     */
    private function detail(string $where, array $params): array
    {
        return $this->em->getConnection()->fetchAllAssociative(
            sprintf(
                "SELECT f.rdv_id, f.date_rdv, f.heure_rdv, f.type_intervention, f.statut_rdv,
                        f.mecanicien_nom, f.pont_nom, f.origine, f.litige_signale,
                        f.temps_estime, f.temps_effectif, f.ca_ht,
                        f.delai_total_cycle,
                        NULLIF(TRIM(COALESCE(c.prenom, '') || ' ' || COALESCE(c.nom, '')), '') AS client_nom,
                        c.telephone AS client_telephone,
                        NULLIF(TRIM(COALESCE(v.marque, '') || ' ' || COALESCE(v.modele, '')), '') AS vehicule_info,
                        v.plaque AS vehicule_plaque
                 FROM analytics_rdv_facts f
                 LEFT JOIN clients c ON c.id = f.client_id
                 LEFT JOIN vehicules v ON v.id = f.vehicule_id
                 WHERE %s
                 ORDER BY f.date_rdv DESC, f.heure_rdv DESC
                 LIMIT %d",
                $where,
                self::LIMITE_DETAIL
            ),
            $params,
            $this->typesParams($params)
        );
    }
}
