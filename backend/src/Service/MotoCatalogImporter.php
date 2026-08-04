<?php

namespace App\Service;

use App\Entity\CategorieMoto;
use App\Entity\ModeleMoto;
use App\Entity\MotoTechnicalSpec;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Importe le référentiel motos + pièces de référence à partir des exports
 * fournisseur DC-AFAM (all_applications.xlsx, part_applications.xlsx).
 *
 * Format source : une ligne = une pièce compatible avec une moto+année donnée
 * (colonnes Bike * + Part *). part_applications.xlsx a les mêmes colonnes Bike
 * mais pas de détail produit (Item/Item type/Item subtype uniquement) : il ne
 * sert qu'à repêcher des motos absentes d'all_applications.xlsx.
 */
class MotoCatalogImporter
{
    private const SOURCE = 'dc_afam';

    /** Catégories de pièces gérées comme références commerciales (marque + réf + désignation). */
    private const REFERENCE_CATEGORIES = ['Spark plugs', 'Batteries', 'Filters'];

    private const SPROCKET_SUBTYPES = ['Front sprocket', 'Alternative front sprocket', 'Rear sprocket', 'Alternative rear sprocket'];
    private const CHAIN_SUBTYPES = ['Chain', 'Alternative chain'];

    /** Taxonomie déjà utilisée par l'atelier : on ne matche que sur ces catégories, jamais de nouvelle création. */
    private const CANONICAL_CATEGORIES = ['Roadster', 'Sportive', 'Trail', 'Touring', 'Custom', 'Scooter', 'Enduro', 'Supermotard', 'Vintage', 'Électrique'];

    /** "Bike type" DC-AFAM qui ne correspond à aucune moto (quad, motoneige, jet-ski) : exclus du catalogue. */
    private const NON_MOTO_BIKE_TYPES = ['ATV And Quad', 'Snow', 'Watercraft'];

    private const KEYWORD_RULES = [
        'Scooter' => ['scoot', 'xmax', 'tmax', 'forza', 'pcx', 'burgman', 'vespa', 'mp3', 'downtown', 'citystar'],
        'Trail' => ['adventure', 'africa twin', 'transalp', 'tenere', 'gs', 'v-strom', 'versys', 'multistrada', 'tracer'],
        'Sportive' => ['r1', 'r6', 'r7', 'cbr', 'gsx-r', 'zx-', 'ninja', 'panigale', 'rc ', 'supersport'],
        'Custom' => ['custom', 'shadow', 'intruder', 'boulevard', 'rebel', 'vulcan', 'sportster', 'softail', 'fat boy'],
        'Touring' => ['touring', 'gold wing', 'rt', 'lt', 'electra glide', 'road glide', 'k 1600'],
        'Enduro' => ['enduro', 'cross', 'wr', 'yz', 'crf', 'exc', 'sx', 'rm-z', 'kx'],
        'Supermotard' => ['supermot', 'smc', 'hypermotard', 'dr-z sm'],
        'Électrique' => ['electric', 'electrique', 'zero'],
    ];

    /** Repli par "Bike type" DC-AFAM quand le nom du modèle ne matche aucun mot-clé. */
    private const BIKE_TYPE_FALLBACK = [
        'Scooter' => 'Scooter',
        'Cross' => 'Enduro',
        'Enduro' => 'Enduro',
        'Off-Road' => 'Enduro',
        'Trial' => 'Enduro',
        'Adventure' => 'Trail',
        'Dual Sport' => 'Trail',
    ];

    public function __construct(
        private EntityManagerInterface $em,
        private string $projectDir,
    ) {
    }

    /**
     * @param ?callable(string): void $progress
     */
    public function importFromDefaultFiles(?callable $progress = null): array
    {
        $dir = $this->projectDir . '/var/imports';

        return $this->import($dir . '/all_applications.xlsx', $dir . '/part_applications.xlsx', $progress);
    }

    /**
     * @param ?callable(string): void $progress
     */
    public function import(string $allApplicationsPath, ?string $partApplicationsPath = null, ?callable $progress = null): array
    {
        if (!is_file($allApplicationsPath)) {
            throw new \RuntimeException(sprintf('Fichier introuvable : %s', $allApplicationsPath));
        }

        $progress ??= static function (string $msg): void {};

        $progress('Lecture de all_applications.xlsx…');
        $snapshots = [];
        $rowCount = 0;
        foreach ($this->streamXlsxRows($allApplicationsPath) as $row) {
            $key = $this->bikeKey($row);
            if ($key === null) {
                continue;
            }
            $snapshots[$key] = self::mergeRowIntoSnapshot($snapshots[$key] ?? self::emptySnapshot($row), $row);
            if (++$rowCount % 100000 === 0) {
                $progress(sprintf('  … %d lignes lues (%d motos distinctes)', $rowCount, count($snapshots)));
            }
        }
        $progress(sprintf('all_applications.xlsx : %d lignes, %d motos distinctes.', $rowCount, count($snapshots)));

        $recovered = 0;
        if ($partApplicationsPath !== null && is_file($partApplicationsPath)) {
            $progress('Lecture de part_applications.xlsx (complément)…');
            foreach ($this->streamXlsxRows($partApplicationsPath) as $row) {
                $key = $this->bikeKey($row);
                if ($key === null || isset($snapshots[$key])) {
                    continue;
                }
                $snapshots[$key] = self::emptySnapshot($row);
                $recovered++;
            }
            $progress(sprintf('part_applications.xlsx : %d moto(s) supplémentaire(s) repêchée(s) (identité seule, sans pièces détaillées).', $recovered));
        }

        $excluded = 0;
        foreach ($snapshots as $key => $snapshot) {
            $final = self::finalizeSnapshot($snapshot);
            if ($final['categorie'] === null) {
                unset($snapshots[$key]);
                $excluded++;
                continue;
            }
            $snapshots[$key] = $final;
        }
        $progress(sprintf('%d véhicule(s) exclu(s) (quad/motoneige/jet-ski, hors périmètre moto).', $excluded));

        $progress('Regroupement par modèle et détection des générations…');
        $byModel = [];
        foreach ($snapshots as $snapshot) {
            $byModel[$snapshot['marque'] . '|' . $snapshot['modele'] . '|' . $snapshot['cylindree']][] = $snapshot;
        }

        $generationsByModel = [];
        foreach ($byModel as $modelKey => $yearSnapshots) {
            $generationsByModel[$modelKey] = self::groupIntoGenerations($yearSnapshots);
        }

        $progress(sprintf('%d modèles distincts, %d générations au total.', count($byModel), array_sum(array_map('count', $generationsByModel))));

        return $this->persist($byModel, $generationsByModel, $progress);
    }

    // ------------------------------------------------------------------
    // Fusion ligne -> snapshot moto (logique pure, testable)
    // ------------------------------------------------------------------

    public static function emptySnapshot(array $row): array
    {
        return [
            'marque' => self::str($row['Bike brand'] ?? ''),
            'modele' => self::str($row['Bike model'] ?? ''),
            'cylindree' => self::intOrNull($row['Bike cc'] ?? null),
            'annee' => self::intOrNull($row['Bike year'] ?? null),
            'bike_type_raw' => self::str($row['Bike type'] ?? ''),
            'sprockets' => [],
            'chains' => [],
            'bougies' => [],
            'batteries' => [],
            'filtres' => [],
        ];
    }

    public static function mergeRowIntoSnapshot(array $snapshot, array $row): array
    {
        $category = self::str($row['Part category'] ?? '');
        $subtype = self::str($row['Part type'] ?? '');
        $marqueP = self::str($row['Part brand'] ?? '');
        $ref = self::str($row['Part name'] ?? '');
        $designationFr = self::str($row['Part description french'] ?? '');

        if ($category === 'Sprockets' && in_array($subtype, self::SPROCKET_SUBTYPES, true)) {
            $dents = self::extractTeeth($designationFr);
            if ($dents !== null) {
                $snapshot['sprockets'][] = ['subtype' => $subtype, 'dents' => $dents['dents'], 'pas' => $dents['pas']];
            }
        } elseif ($category === 'Chains' && in_array($subtype, self::CHAIN_SUBTYPES, true)) {
            $chaine = self::extractChain($designationFr);
            if ($chaine !== null) {
                $snapshot['chains'][] = ['subtype' => $subtype, 'pas' => $chaine['pas'], 'maillons' => $chaine['maillons']];
            }
        } elseif (in_array($category, self::REFERENCE_CATEGORIES, true) && $marqueP !== '' && $ref !== '') {
            $bucket = match ($category) {
                'Spark plugs' => 'bougies',
                'Batteries' => 'batteries',
                'Filters' => 'filtres',
                default => null,
            };
            if ($bucket !== null) {
                $snapshot[$bucket][$marqueP . '|' . $ref] = [
                    'marque' => $marqueP,
                    'reference' => $ref,
                    'type' => $subtype,
                    'designation' => $designationFr,
                ];
            }
        }

        return $snapshot;
    }

    /** @return array{dents:int,pas:int}|null */
    public static function extractTeeth(string $designationFr): ?array
    {
        if (preg_match('/(\d+)\s*dents,\s*(\d+)/u', $designationFr, $m) === 1) {
            return ['dents' => (int) $m[1], 'pas' => (int) $m[2]];
        }

        return null;
    }

    /** @return array{pas:int,maillons:int}|null */
    public static function extractChain(string $designationFr): ?array
    {
        if (preg_match('/(\d+),\s*(\d+)\s*maillons/u', $designationFr, $m) === 1) {
            return ['pas' => (int) $m[1], 'maillons' => (int) $m[2]];
        }

        return null;
    }

    /**
     * Réduit les listes brutes accumulées en valeurs finales : pignons/chaîne
     * (préférence à la variante non-"Alternative"), pièces de référence dédupliquées.
     */
    public static function finalizeSnapshot(array $snapshot): array
    {
        $pickSprocket = static function (array $sprockets, string $preferred, string $alternative): ?array {
            foreach ($sprockets as $s) {
                if ($s['subtype'] === $preferred) {
                    return $s;
                }
            }
            foreach ($sprockets as $s) {
                if ($s['subtype'] === $alternative) {
                    return $s;
                }
            }
            return null;
        };

        $avant = $pickSprocket($snapshot['sprockets'], 'Front sprocket', 'Alternative front sprocket');
        $arriere = $pickSprocket($snapshot['sprockets'], 'Rear sprocket', 'Alternative rear sprocket');

        $chain = null;
        foreach ($snapshot['chains'] as $c) {
            if ($c['subtype'] === 'Chain') {
                $chain = $c;
                break;
            }
        }
        $chain ??= $snapshot['chains'][0] ?? null;

        return [
            'marque' => $snapshot['marque'],
            'modele' => $snapshot['modele'],
            'cylindree' => $snapshot['cylindree'],
            'annee' => $snapshot['annee'],
            'categorie' => self::mapToCanonicalCategory($snapshot['bike_type_raw'], $snapshot['modele']),
            'transmission' => ($avant || $arriere || $chain) ? [
                'pignon_avant_dents' => $avant['dents'] ?? null,
                'pignon_arriere_dents' => $arriere['dents'] ?? null,
                'chaine_pas' => $chain['pas'] ?? ($avant['pas'] ?? $arriere['pas'] ?? null),
                'chaine_maillons' => $chain['maillons'] ?? null,
            ] : null,
            'bougies' => array_values($snapshot['bougies']),
            'batteries' => array_values($snapshot['batteries']),
            'filtres' => array_values($snapshot['filtres']),
        ];
    }

    /**
     * Fait correspondre une moto à l'une des catégories déjà utilisées par l'atelier
     * (jamais de nouvelle catégorie) : d'abord par mot-clé dans le nom du modèle
     * (le plus fiable pour les nomenclatures connues), puis par "Bike type" DC-AFAM
     * en repli. Retourne null pour les véhicules qui ne sont pas des motos (quad,
     * motoneige, jet-ski) : ils sont exclus du catalogue.
     */
    public static function mapToCanonicalCategory(string $bikeTypeRaw, string $modele): ?string
    {
        if (in_array($bikeTypeRaw, self::NON_MOTO_BIKE_TYPES, true)) {
            return null;
        }

        $haystack = mb_strtolower($modele);
        foreach (self::KEYWORD_RULES as $category => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($haystack, $keyword)) {
                    return $category;
                }
            }
        }

        return self::BIKE_TYPE_FALLBACK[$bikeTypeRaw] ?? 'Roadster';
    }

    // ------------------------------------------------------------------
    // Regroupement en générations par plage d'années (logique pure, testable)
    // ------------------------------------------------------------------

    /**
     * $snapshots : liste de snapshots finalisés pour un même (marque, modele, cylindree),
     * un par année. Regroupe les années consécutives qui partagent la même signature de
     * transmission en une seule génération (le signal le plus fiable d'un vrai changement
     * mécanique) ; bougies/batteries/filtres sont l'union des références vues sur la plage.
     *
     * @return list<array{annee_debut:int,annee_fin:int,categorie:string,transmission:?array,bougies:array,batteries:array,filtres:array}>
     */
    public static function groupIntoGenerations(array $snapshots): array
    {
        $withYear = array_values(array_filter($snapshots, static fn(array $s) => $s['annee'] !== null));
        usort($withYear, static fn(array $a, array $b) => $a['annee'] <=> $b['annee']);

        $signatureOf = static function (array $s): string {
            $t = $s['transmission'];
            return $t === null ? 'none' : implode('|', [
                $t['pignon_avant_dents'] ?? '',
                $t['pignon_arriere_dents'] ?? '',
                $t['chaine_pas'] ?? '',
                $t['chaine_maillons'] ?? '',
            ]);
        };

        $generations = [];
        $current = null;

        foreach ($withYear as $s) {
            $sig = $signatureOf($s);
            // Une année sans donnée de transmission ('none') ne doit pas fragmenter une
            // génération existante : on la rattache à la génération en cours et, si celle-ci
            // n'avait pas encore de signature connue, on l'établit dès qu'une vraie apparaît.
            $sameGeneration = $current !== null
                && ($current['signature'] === 'none' || $sig === 'none' || $current['signature'] === $sig);

            if ($sameGeneration) {
                $current['annee_fin'] = $s['annee'];
                $current['categorie'] = $s['categorie'];
                if ($current['signature'] === 'none' && $sig !== 'none') {
                    $current['signature'] = $sig;
                    $current['transmission'] = $s['transmission'];
                }
                $current['bougies'] += self::indexByKey($s['bougies']);
                $current['batteries'] += self::indexByKey($s['batteries']);
                $current['filtres'] += self::indexByKey($s['filtres']);
                $generations[array_key_last($generations)] = $current;
                continue;
            }

            $current = [
                'signature' => $sig,
                'annee_debut' => $s['annee'],
                'annee_fin' => $s['annee'],
                'categorie' => $s['categorie'],
                'transmission' => $s['transmission'],
                'bougies' => self::indexByKey($s['bougies']),
                'batteries' => self::indexByKey($s['batteries']),
                'filtres' => self::indexByKey($s['filtres']),
            ];
            $generations[] = $current;
        }

        // Motos sans année connue : une génération unique "période inconnue".
        $withoutYear = array_values(array_filter($snapshots, static fn(array $s) => $s['annee'] === null));
        if ($withoutYear !== [] && $generations === []) {
            $s = $withoutYear[0];
            $generations[] = [
                'signature' => 'none',
                'annee_debut' => null,
                'annee_fin' => null,
                'categorie' => $s['categorie'],
                'transmission' => $s['transmission'],
                'bougies' => self::indexByKey($s['bougies']),
                'batteries' => self::indexByKey($s['batteries']),
                'filtres' => self::indexByKey($s['filtres']),
            ];
        }

        return array_map(static function (array $g): array {
            unset($g['signature']);
            $g['bougies'] = array_values($g['bougies']);
            $g['batteries'] = array_values($g['batteries']);
            $g['filtres'] = array_values($g['filtres']);
            return $g;
        }, $generations);
    }

    private static function indexByKey(array $refs): array
    {
        $out = [];
        foreach ($refs as $r) {
            $out[$r['marque'] . '|' . $r['reference']] = $r;
        }
        return $out;
    }

    // ------------------------------------------------------------------
    // Persistance Doctrine
    // ------------------------------------------------------------------

    private function persist(array $byModel, array $generationsByModel, callable $progress): array
    {
        $categories = [];
        foreach ($this->em->getRepository(CategorieMoto::class)->findAll() as $cat) {
            $categories[$cat->getNom()] = $cat;
        }

        $modelesCreated = 0;
        $specsCreated = 0;
        $skippedUnknownCategory = 0;
        $i = 0;

        foreach ($byModel as $modelKey => $yearSnapshots) {
            $first = $yearSnapshots[0];
            $nomCategorie = $first['categorie'];

            // On ne matche que sur les catégories déjà utilisées par l'atelier : jamais de création.
            if (!isset($categories[$nomCategorie])) {
                $skippedUnknownCategory++;
                $progress(sprintf('  ! catégorie "%s" introuvable, modèle %s %s ignoré', $nomCategorie, $first['marque'], $first['modele']));
                continue;
            }

            $generations = $generationsByModel[$modelKey];
            $anneeDebutGlobal = null;
            $anneeFinGlobal = null;
            foreach ($generations as $g) {
                if ($g['annee_debut'] !== null) {
                    $anneeDebutGlobal = $anneeDebutGlobal === null ? $g['annee_debut'] : min($anneeDebutGlobal, $g['annee_debut']);
                    $anneeFinGlobal = $anneeFinGlobal === null ? $g['annee_fin'] : max($anneeFinGlobal, $g['annee_fin']);
                }
            }

            $modele = new ModeleMoto();
            $modele->setMarque($first['marque']);
            $modele->setModele($first['modele']);
            $modele->setCategorie($categories[$nomCategorie]);
            $modele->setCylindreeMin($first['cylindree']);
            $modele->setCylindreeMax($first['cylindree']);
            $modele->setAnneeDebut($anneeDebutGlobal);
            $modele->setAnneeFin($anneeFinGlobal);
            $this->em->persist($modele);
            $modelesCreated++;

            foreach ($generations as $index => $g) {
                $spec = new MotoTechnicalSpec();
                $spec->setModele($modele);
                $spec->setSource(self::SOURCE);
                $spec->setAnneeDebut($g['annee_debut'] ?? $anneeDebutGlobal ?? 0);
                $spec->setAnneeFin($g['annee_fin']);
                $spec->setVariante(count($generations) > 1 ? sprintf('Génération %d', $index + 1) : null);

                $entretien = [
                    'bougies' => $g['bougies'],
                    'filtres' => $g['filtres'],
                    'transmission' => $g['transmission'],
                ];
                $spec->setEntretienJson((string) json_encode($entretien, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

                $electrique = ['batteries' => $g['batteries']];
                $spec->setSystemesElectriquesJson((string) json_encode($electrique, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

                $this->em->persist($spec);
                $specsCreated++;
            }

            if (++$i % 500 === 0) {
                $this->em->flush();
                $this->em->clear();
                // Recharger les catégories après clear() : les entités détachées ne sont plus valides.
                $categories = [];
                foreach ($this->em->getRepository(CategorieMoto::class)->findAll() as $cat) {
                    $categories[$cat->getNom()] = $cat;
                }
                $progress(sprintf('  … %d/%d modèles importés', $i, count($byModel)));
            }
        }

        $this->em->flush();

        return [
            'modeles' => $modelesCreated,
            'specs' => $specsCreated,
            'categories' => count($categories),
            'modeles_ignores_categorie_inconnue' => $skippedUnknownCategory,
        ];
    }

    // ------------------------------------------------------------------
    // Lecture XLSX en flux (XMLReader sur zip://, mémoire bornée)
    // ------------------------------------------------------------------

    private function bikeKey(array $row): ?string
    {
        $marque = self::str($row['Bike brand'] ?? '');
        $modele = self::str($row['Bike model'] ?? '');
        if ($marque === '' || $modele === '') {
            return null;
        }
        $cc = self::intOrNull($row['Bike cc'] ?? null);
        $annee = self::intOrNull($row['Bike year'] ?? null);

        return implode('|', [$marque, $modele, (string) ($cc ?? ''), (string) ($annee ?? '')]);
    }

    /** @return iterable<array<string,string>> */
    private function streamXlsxRows(string $xlsxPath): iterable
    {
        $shared = $this->loadSharedStrings($xlsxPath);
        $sheetEntry = $this->biggestSheetEntry($xlsxPath);

        $reader = new \XMLReader();
        if (!$reader->open('zip://' . $xlsxPath . '#' . $sheetEntry)) {
            throw new \RuntimeException(sprintf('Impossible de lire %s dans %s', $sheetEntry, $xlsxPath));
        }

        $headers = null;
        while ($reader->read()) {
            if ($reader->nodeType !== \XMLReader::ELEMENT || $reader->localName !== 'row') {
                continue;
            }

            $rowXml = $reader->readOuterXml();
            $values = $this->parseRowXml($rowXml, $shared);

            if ($headers === null) {
                $headers = $values;
                continue;
            }

            $assoc = [];
            foreach ($headers as $idx => $name) {
                if ($name !== '') {
                    $assoc[$name] = $values[$idx] ?? '';
                }
            }
            yield $assoc;
        }

        $reader->close();
    }

    private function parseRowXml(string $rowXml, array $shared): array
    {
        $values = [];
        $cellReader = new \XMLReader();
        $cellReader->xml($rowXml);
        $colIdx = null;
        $type = null;

        while ($cellReader->read()) {
            if ($cellReader->nodeType === \XMLReader::ELEMENT && $cellReader->localName === 'c') {
                $ref = $cellReader->getAttribute('r') ?? '';
                $colIdx = self::colToIndex($ref);
                $type = $cellReader->getAttribute('t');
            } elseif ($cellReader->nodeType === \XMLReader::ELEMENT && $cellReader->localName === 'v') {
                $raw = $cellReader->readString();
                $value = ($type === 's' && $raw !== '') ? ($shared[(int) $raw] ?? '') : $raw;
                if ($colIdx !== null) {
                    $values[$colIdx] = $value;
                }
            }
        }
        $cellReader->close();

        if ($values === []) {
            return [];
        }
        $max = max(array_keys($values));
        $out = [];
        for ($i = 0; $i <= $max; $i++) {
            $out[$i] = $values[$i] ?? '';
        }

        return $out;
    }

    private function loadSharedStrings(string $xlsxPath): array
    {
        $zip = new \ZipArchive();
        if ($zip->open($xlsxPath) !== true) {
            throw new \RuntimeException(sprintf('Impossible d’ouvrir %s', $xlsxPath));
        }
        $hasShared = $zip->locateName('xl/sharedStrings.xml') !== false;
        $zip->close();

        if (!$hasShared) {
            return [];
        }

        $strings = [];
        $reader = new \XMLReader();
        if (!$reader->open('zip://' . $xlsxPath . '#xl/sharedStrings.xml')) {
            throw new \RuntimeException('Impossible de lire sharedStrings.xml');
        }

        $buffer = '';
        while ($reader->read()) {
            if ($reader->nodeType === \XMLReader::ELEMENT && $reader->localName === 'si') {
                $buffer = '';
            } elseif ($reader->nodeType === \XMLReader::ELEMENT && $reader->localName === 't') {
                $buffer .= $reader->readString();
            } elseif ($reader->nodeType === \XMLReader::END_ELEMENT && $reader->localName === 'si') {
                $strings[] = $buffer;
            }
        }
        $reader->close();

        return $strings;
    }

    private function biggestSheetEntry(string $xlsxPath): string
    {
        $zip = new \ZipArchive();
        if ($zip->open($xlsxPath) !== true) {
            throw new \RuntimeException(sprintf('Impossible d’ouvrir %s', $xlsxPath));
        }

        $best = null;
        $bestSize = -1;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            if ($stat === false) {
                continue;
            }
            if (preg_match('#^xl/worksheets/sheet\d+\.xml$#', $stat['name']) === 1 && $stat['size'] > $bestSize) {
                $best = $stat['name'];
                $bestSize = $stat['size'];
            }
        }
        $zip->close();

        if ($best === null) {
            throw new \RuntimeException(sprintf('Aucune feuille trouvée dans %s', $xlsxPath));
        }

        return $best;
    }

    private static function colToIndex(string $cellRef): int
    {
        $letters = preg_replace('/\d+/', '', $cellRef) ?? '';
        $idx = 0;
        foreach (str_split($letters) as $c) {
            $idx = $idx * 26 + (ord(strtoupper($c)) - ord('A') + 1);
        }

        return $idx - 1;
    }

    private static function str(mixed $v): string
    {
        return trim((string) $v);
    }

    private static function intOrNull(mixed $v): ?int
    {
        $v = self::str($v);
        return ($v === '' || !is_numeric($v)) ? null : (int) $v;
    }
}
