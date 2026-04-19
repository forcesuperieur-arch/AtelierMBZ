<?php

namespace App\Service;

use App\Entity\CategorieMoto;
use App\Entity\ModeleMoto;
use App\Entity\MotoTechnicalSpec;
use Doctrine\ORM\EntityManagerInterface;

class NgkMotoCatalogImporter
{
    public function __construct(
        private EntityManagerInterface $em,
        private string $projectDir,
    ) {
    }

    public function importFromDefaultFile(): array
    {
        $candidates = [
            $this->projectDir . '/var/ngk_sparkplugs.xlsx',
            $this->projectDir . '/var/imports/ngk_sparkplugs.xlsx',
            $this->projectDir . '/docs/ngk_sparkplugs.xlsx',
            dirname($this->projectDir) . '/docs/ngk_sparkplugs.xlsx',
        ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $this->importFromXlsx($candidate);
            }
        }

        return $this->importFromXlsx($candidates[0]);
    }

    public function importFromXlsx(string $filePath): array
    {
        $rows = $this->loadRowsFromXlsx($filePath);
        $preparedRows = $this->prepareCatalogRows($rows);

        return $this->importPreparedRows($preparedRows);
    }

    public function prepareCatalogRows(array $rows): array
    {
        $grouped = [];

        foreach ($rows as $row) {
            $marque = mb_strtoupper($this->normalizeText($row['marque'] ?? $row['Brand'] ?? ''));
            $modeleBase = $this->normalizeText($row['modele'] ?? $row['Model'] ?? '');
            $designation = $this->normalizeText($row['designation'] ?? $row['Name'] ?? '');
            $cylindree = $this->extractInt($row['cylindree'] ?? $row['CC'] ?? null);
            $anneeDebut = $this->extractYear($row['annee_debut'] ?? $row['From'] ?? null);
            $anneeFin = $this->extractYear($row['annee_fin'] ?? $row['To'] ?? null);
            $sparkPlug = $this->normalizeText($row['sparkplug'] ?? $row['Sparkplug'] ?? '');

            if ($marque === '' || $modeleBase === '') {
                continue;
            }

            $modele = trim($modeleBase . ($designation !== '' ? ' - ' . $designation : ''));
            $key = implode('|', [
                $marque,
                mb_strtoupper($modele),
                (string) ($cylindree ?? 0),
                (string) ($anneeDebut ?? 0),
                (string) ($anneeFin ?? 0),
            ]);

            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'marque' => $marque,
                    'modele' => $modele,
                    'designation' => $designation,
                    'cylindree' => $cylindree,
                    'cylindree_min' => $cylindree,
                    'cylindree_max' => $cylindree,
                    'annee_debut' => $anneeDebut,
                    'annee_fin' => $anneeFin,
                    'periode_label' => $this->buildPeriodLabel($anneeDebut, $anneeFin),
                    'categorie' => $this->inferCategory($modele, $designation, $cylindree),
                    'bougies' => [],
                ];
            }

            if ($sparkPlug !== '') {
                $grouped[$key]['bougies'][$sparkPlug] = true;
            }
        }

        $prepared = array_map(function (array $item): array {
            $item['bougies'] = array_values(array_keys($item['bougies']));
            sort($item['bougies']);

            return $item;
        }, array_values($grouped));

        usort($prepared, function (array $left, array $right): int {
            return [
                $left['marque'],
                $left['cylindree'] ?? 0,
                $left['modele'],
                $left['annee_debut'] ?? 0,
                $left['annee_fin'] ?? 9999,
            ] <=> [
                $right['marque'],
                $right['cylindree'] ?? 0,
                $right['modele'],
                $right['annee_debut'] ?? 0,
                $right['annee_fin'] ?? 9999,
            ];
        });

        return $prepared;
    }

    public function importPreparedRows(array $rows): array
    {
        $this->ensureCategories();

        $categories = [];
        foreach ($this->em->getRepository(CategorieMoto::class)->findAll() as $category) {
            $categories[$category->getNom()] = $category;
        }

        $modeleRepo = $this->em->getRepository(ModeleMoto::class);
        $specRepo = $this->em->getRepository(MotoTechnicalSpec::class);

        $modelIndex = [];
        foreach ($modeleRepo->findAll() as $existingModele) {
            $modelIndex[$this->buildModelSignature(
                $existingModele->getMarque(),
                $existingModele->getModele(),
                $existingModele->getCylindreeMin(),
                $existingModele->getCylindreeMax(),
                $existingModele->getAnneeDebut(),
                $existingModele->getAnneeFin(),
            )] = $existingModele;
        }

        $specIndex = [];
        foreach ($specRepo->findAll() as $existingSpec) {
            $specIndex[$this->buildSpecSignature($existingSpec)] = $existingSpec;
        }

        $created = 0;
        $updated = 0;
        $specsCreated = 0;
        $specsUpdated = 0;

        foreach ($rows as $row) {
            $category = $categories[$row['categorie']] ?? $categories['Roadster'] ?? null;
            if (!$category instanceof CategorieMoto) {
                continue;
            }

            $signature = $this->buildModelSignature(
                $row['marque'],
                $row['modele'],
                $row['cylindree_min'],
                $row['cylindree_max'],
                $row['annee_debut'],
                $row['annee_fin'],
            );

            $modele = $modelIndex[$signature] ?? null;

            if (!$modele instanceof ModeleMoto) {
                $modele = new ModeleMoto();
                $modele->setMarque($row['marque']);
                $modele->setModele($row['modele']);
                $modele->setCylindreeMin($row['cylindree_min']);
                $modele->setCylindreeMax($row['cylindree_max']);
                $modele->setAnneeDebut($row['annee_debut']);
                $modele->setAnneeFin($row['annee_fin']);
                $modele->setCategorie($category);
                $this->em->persist($modele);
                $modelIndex[$signature] = $modele;
                $created++;
            } else {
                $modele->setCategorie($category);
                $updated++;
            }

            $anneeDebut = $row['annee_debut'] ?? 1900;
            $specSignature = $this->buildSpecSignatureFromRow($row);
            $spec = $specIndex[$specSignature] ?? null;

            if (!$spec instanceof MotoTechnicalSpec) {
                $spec = new MotoTechnicalSpec();
                $spec->setModele($modele);
                $spec->setSource('ngk');
                $spec->setAnneeDebut($anneeDebut);
                $spec->setAnneeFin($row['annee_fin']);
                $spec->setVariante($row['designation'] ?: null);
                $this->em->persist($spec);
                $specIndex[$specSignature] = $spec;
                $specsCreated++;
            } else {
                $specsUpdated++;
            }

            $general = $spec->getGeneral();
            $general['periode_label'] = $row['periode_label'];
            $general['categorie'] = $row['categorie'];
            $general['cylindree'] = $row['cylindree'];
            $spec->setGeneralJson((string) json_encode($general, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            $entretien = $spec->getEntretien();
            $entretien['spark_plugs'] = $row['bougies'];
            $entretien['spark_plug_count'] = count($row['bougies']);
            $spec->setEntretienJson((string) json_encode($entretien, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $spec->setNotes($row['bougies'] ? 'Bougies NGK : ' . implode(', ', $row['bougies']) : null);
        }

        $this->em->flush();

        return [
            'rows' => count($rows),
            'created' => $created,
            'updated' => $updated,
            'specs_created' => $specsCreated,
            'specs_updated' => $specsUpdated,
        ];
    }

    private function ensureCategories(): void
    {
        $defaults = ['Roadster', 'Sportive', 'Trail', 'Touring', 'Custom', 'Scooter', 'Enduro', 'Supermotard', 'Vintage', 'Électrique'];

        foreach ($defaults as $name) {
            $exists = $this->em->getRepository(CategorieMoto::class)->findOneBy(['nom' => $name]);
            if ($exists instanceof CategorieMoto) {
                continue;
            }

            $category = new CategorieMoto();
            $category->setNom($name);
            $this->em->persist($category);
        }

        $this->em->flush();
    }

    private function loadRowsFromXlsx(string $filePath): array
    {
        if (!is_file($filePath)) {
            throw new \RuntimeException(sprintf('Fichier NGK introuvable: %s', $filePath));
        }

        $zip = new \ZipArchive();
        if ($zip->open($filePath) !== true) {
            throw new \RuntimeException('Impossible d’ouvrir le fichier NGK.');
        }

        $namespace = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
        $sharedStrings = [];
        $sharedContent = $zip->getFromName('xl/sharedStrings.xml');
        if (is_string($sharedContent)) {
            $sharedDom = new \DOMDocument();
            $sharedDom->loadXML($sharedContent);
            $sharedXpath = new \DOMXPath($sharedDom);
            $sharedXpath->registerNamespace('a', $namespace);

            foreach ($sharedXpath->query('//a:si') ?: [] as $node) {
                $parts = [];
                foreach ($sharedXpath->query('.//a:t', $node) ?: [] as $textNode) {
                    $parts[] = $textNode->textContent;
                }
                $sharedStrings[] = trim(implode('', $parts));
            }
        }

        $sheetContent = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        if (!is_string($sheetContent)) {
            throw new \RuntimeException('Feuille NGK introuvable dans le fichier.');
        }

        $sheetDom = new \DOMDocument();
        $sheetDom->loadXML($sheetContent);
        $xpath = new \DOMXPath($sheetDom);
        $xpath->registerNamespace('a', $namespace);
        $rows = [];

        foreach ($xpath->query('//a:sheetData/a:row') ?: [] as $rowNode) {
            $row = [];
            foreach ($xpath->query('a:c', $rowNode) ?: [] as $cell) {
                $ref = $cell->attributes?->getNamedItem('r')?->nodeValue ?? '';
                $column = preg_replace('/\d+/', '', $ref) ?: '';
                $raw = trim((string) $xpath->evaluate('string(a:v)', $cell));
                $type = $cell->attributes?->getNamedItem('t')?->nodeValue ?? '';
                $value = $type === 's' ? ($sharedStrings[(int) $raw] ?? '') : $raw;
                $row[$column] = trim((string) $value);
            }
            if ($row !== []) {
                $rows[] = $row;
            }
        }

        if (count($rows) < 2) {
            return [];
        }

        $headers = array_values($rows[0]);
        $payload = [];
        foreach (array_slice($rows, 1) as $row) {
            $values = array_values($row);
            $payload[] = array_combine($headers, array_pad($values, count($headers), '')) ?: [];
        }

        return $payload;
    }

    private function buildModelSignature(string $marque, string $modele, ?int $cylindreeMin, ?int $cylindreeMax, ?int $anneeDebut, ?int $anneeFin): string
    {
        return implode('|', [
            mb_strtoupper(trim($marque)),
            mb_strtoupper(trim($modele)),
            (string) ($cylindreeMin ?? 0),
            (string) ($cylindreeMax ?? 0),
            (string) ($anneeDebut ?? 0),
            (string) ($anneeFin ?? 0),
        ]);
    }

    private function buildSpecSignature(MotoTechnicalSpec $spec): string
    {
        $modele = $spec->getModele();

        return implode('|', [
            $this->buildModelSignature(
                $modele->getMarque(),
                $modele->getModele(),
                $modele->getCylindreeMin(),
                $modele->getCylindreeMax(),
                $modele->getAnneeDebut(),
                $modele->getAnneeFin(),
            ),
            (string) ($spec->getSource() ?? 'ngk'),
            (string) $spec->getAnneeDebut(),
            (string) ($spec->getAnneeFin() ?? 0),
        ]);
    }

    private function buildSpecSignatureFromRow(array $row): string
    {
        return implode('|', [
            $this->buildModelSignature(
                $row['marque'],
                $row['modele'],
                $row['cylindree_min'],
                $row['cylindree_max'],
                $row['annee_debut'],
                $row['annee_fin'],
            ),
            'ngk',
            (string) ($row['annee_debut'] ?? 1900),
            (string) ($row['annee_fin'] ?? 0),
        ]);
    }

    private function normalizeText(mixed $value): string
    {
        return trim(preg_replace('/\s+/', ' ', (string) $value) ?: '');
    }

    private function extractInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (preg_match('/(\d{2,5})/', (string) $value, $matches) === 1) {
            return (int) $matches[1];
        }

        return null;
    }

    private function extractYear(mixed $value): ?int
    {
        $year = $this->extractInt($value);
        if ($year === null || $year < 1900 || $year > 2100) {
            return null;
        }

        return $year;
    }

    private function buildPeriodLabel(?int $anneeDebut, ?int $anneeFin): string
    {
        if ($anneeDebut && $anneeFin) {
            return sprintf('%d-%d', $anneeDebut, $anneeFin);
        }

        if ($anneeDebut) {
            return sprintf('%d+', $anneeDebut);
        }

        return 'Période inconnue';
    }

    private function inferCategory(string $modele, string $designation, ?int $cylindree): string
    {
        $haystack = mb_strtolower(trim($modele . ' ' . $designation));

        $rules = [
            'Scooter' => ['scoot', 'xmax', 'tmax', 'forza', 'pcx', 'burgman', 'vespa', 'mp3', 'downtown', 'citystar'],
            'Trail' => ['adventure', 'africa twin', 'transalp', 'tenere', 'gs', 'v-strom', 'versys', 'multistrada', 'tracer'],
            'Sportive' => ['r1', 'r6', 'r7', 'cbr', 'gsx-r', 'zx-', 'ninja', 'panigale', 'rc ', 'supersport'],
            'Custom' => ['custom', 'shadow', 'intruder', 'boulevard', 'rebel', 'vulcan', 'sportster', 'softail', 'fat boy'],
            'Touring' => ['touring', 'gold wing', 'rt', 'lt', 'electra glide', 'road glide', 'k 1600'],
            'Enduro' => ['enduro', 'cross', 'wr', 'yz', 'crf', 'exc', 'sx', 'rm-z', 'kx'],
            'Supermotard' => ['supermot', 'smc', 'hypermotard', 'dr-z sm'],
            'Électrique' => ['electric', 'electrique', 'zero'],
        ];

        foreach ($rules as $category => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($haystack, $keyword)) {
                    return $category;
                }
            }
        }

        if (($cylindree ?? 0) <= 125 && str_contains($haystack, 'city')) {
            return 'Scooter';
        }

        return 'Roadster';
    }
}
