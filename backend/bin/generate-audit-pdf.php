<?php
/**
 * Convertit les chapitres Markdown de docs/AUDIT-V1/AUDIT-COMPLET/ en un PDF unique.
 *
 * Usage:
 *   docker compose exec php php bin/generate-audit-pdf.php
 *
 * Sortie:
 *   docs/AUDIT-V1/AUDIT-COMPLET-AtelierMBZ.pdf
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$root      = realpath(__DIR__ . '/..');
$repoRoot  = realpath($root . '/..') ?: $root;
$srcDir    = $argv[1] ?? ($repoRoot . '/docs/AUDIT-V1/AUDIT-COMPLET');
$outFile   = $argv[2] ?? ($repoRoot . '/docs/AUDIT-V1/AUDIT-COMPLET-AtelierMBZ.pdf');

if (!is_dir($srcDir)) {
    fwrite(STDERR, "Repertoire source introuvable: {$srcDir}\n");
    exit(1);
}

$files = glob($srcDir . '/*.md') ?: [];
sort($files);
if (!$files) {
    fwrite(STDERR, "Aucun fichier .md trouve dans {$srcDir}\n");
    exit(1);
}

echo "Lecture de " . count($files) . " fichier(s) Markdown:\n";
$markdown = '';
foreach ($files as $f) {
    echo "  - " . basename($f) . "\n";
    $markdown .= "\n\n" . file_get_contents($f) . "\n\n";
}

$body = markdownToHtml($markdown);

$css = <<<CSS
@page { margin: 18mm 14mm 18mm 14mm; }
body {
  font-family: DejaVu Sans, sans-serif;
  font-size: 9.5pt;
  line-height: 1.4;
  color: #1a1a1a;
}
h1 { font-size: 22pt; color: #111; border-bottom: 2px solid #333; padding-bottom: 6px; margin-top: 24pt; page-break-before: always; }
h1.first { page-break-before: avoid; }
h2 { font-size: 16pt; color: #222; border-bottom: 1px solid #999; padding-bottom: 3px; margin-top: 18pt; }
h3 { font-size: 13pt; color: #333; margin-top: 14pt; }
h4 { font-size: 11pt; color: #444; margin-top: 10pt; }
h5, h6 { font-size: 10pt; color: #555; margin-top: 8pt; }
p { margin: 4pt 0; text-align: justify; }
ul, ol { margin: 4pt 0 4pt 18pt; }
li { margin: 1pt 0; }
code { font-family: DejaVu Sans Mono, monospace; background: #f0f0f0; padding: 1px 3px; font-size: 8.5pt; border-radius: 2px; }
pre { background: #f5f5f5; padding: 6pt 8pt; border-left: 3px solid #888; font-family: DejaVu Sans Mono, monospace; font-size: 8pt; white-space: pre-wrap; word-wrap: break-word; margin: 6pt 0; }
pre code { background: none; padding: 0; }
table { width: 100%; border-collapse: collapse; margin: 8pt 0; font-size: 8.5pt; }
th, td { border: 1px solid #aaa; padding: 3pt 5pt; text-align: left; vertical-align: top; word-wrap: break-word; }
th { background: #e5e5e5; font-weight: bold; }
tr:nth-child(even) td { background: #fafafa; }
hr { border: none; border-top: 1px solid #bbb; margin: 12pt 0; }
a { color: #1864ab; text-decoration: none; }
strong { font-weight: bold; }
em { font-style: italic; }
blockquote { border-left: 3px solid #888; padding-left: 8pt; margin: 6pt 0; color: #555; font-style: italic; }
.cover { text-align: center; padding-top: 60mm; }
.cover h1 { font-size: 36pt; border: none; page-break-before: avoid; }
.cover .subtitle { font-size: 14pt; color: #555; margin-top: 6pt; }
.cover .meta { font-size: 11pt; color: #777; margin-top: 30pt; }
.toc { page-break-after: always; }
.toc h1 { page-break-before: avoid; }
.toc ul { list-style: none; margin-left: 0; }
.toc li { padding: 2pt 0; border-bottom: 1px dotted #ccc; }
CSS;

$cover = <<<HTML
<div class="cover">
  <h1>Audit complet AtelierMBZ</h1>
  <div class="subtitle">Documentation exhaustive du fonctionnement de l'application</div>
  <div class="meta">
    Version 1.0 &mdash; 24 avril 2026<br/>
    Backend Symfony 7.2 + Frontend Nuxt 3<br/>
    Genere automatiquement depuis docs/AUDIT-V1/AUDIT-COMPLET/
  </div>
</div>
HTML;

$html = "<!DOCTYPE html><html lang=\"fr\"><head><meta charset=\"UTF-8\"><title>Audit complet AtelierMBZ</title><style>{$css}</style></head><body>{$cover}{$body}</body></html>";

echo "Generation PDF (DomPDF)...\n";

$options = new Options();
$options->set('isRemoteEnabled', false);
$options->set('isHtml5ParserEnabled', true);
$options->set('defaultFont', 'DejaVu Sans');

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

if (!is_dir(dirname($outFile))) {
    mkdir(dirname($outFile), 0755, true);
}
file_put_contents($outFile, $dompdf->output());

$kb = round(filesize($outFile) / 1024, 1);
echo "PDF genere: {$outFile} ({$kb} KB)\n";

// =============================================================================
// Mini parser Markdown -> HTML (suffisant pour les rapports d'audit)
// =============================================================================

function markdownToHtml(string $md): string
{
    // Normalise EOL
    $md = str_replace(["\r\n", "\r"], "\n", $md);
    $lines = explode("\n", $md);
    $out = [];
    $i = 0;
    $n = count($lines);
    $firstH1 = true;

    while ($i < $n) {
        $line = $lines[$i];

        // Code fence
        if (preg_match('/^```(\w*)\s*$/', $line, $m)) {
            $lang = $m[1] ?? '';
            $i++;
            $code = [];
            while ($i < $n && !preg_match('/^```\s*$/', $lines[$i])) {
                $code[] = $lines[$i];
                $i++;
            }
            $i++;
            $out[] = '<pre><code>' . htmlspecialchars(implode("\n", $code), ENT_QUOTES, 'UTF-8') . '</code></pre>';
            continue;
        }

        // Horizontal rule
        if (preg_match('/^(\-{3,}|\*{3,}|_{3,})\s*$/', $line)) {
            $out[] = '<hr/>';
            $i++;
            continue;
        }

        // Headings
        if (preg_match('/^(#{1,6})\s+(.+)$/', $line, $m)) {
            $level = strlen($m[1]);
            $text  = inlineMarkdown(trim($m[2]));
            $cls   = '';
            if ($level === 1 && $firstH1) {
                $cls = ' class="first"';
                $firstH1 = false;
            }
            $out[] = "<h{$level}{$cls}>{$text}</h{$level}>";
            $i++;
            continue;
        }

        // Table (line containing | and next line is separator)
        if (str_contains($line, '|') && $i + 1 < $n && preg_match('/^\s*\|?[\s\-:|]+\|[\s\-:|]+$/', $lines[$i + 1])) {
            $headers = parseTableRow($line);
            $i += 2;
            $rows = [];
            while ($i < $n && str_contains($lines[$i], '|') && trim($lines[$i]) !== '') {
                $rows[] = parseTableRow($lines[$i]);
                $i++;
            }
            $html = '<table><thead><tr>';
            foreach ($headers as $h) {
                $html .= '<th>' . inlineMarkdown($h) . '</th>';
            }
            $html .= '</tr></thead><tbody>';
            foreach ($rows as $row) {
                $html .= '<tr>';
                foreach ($row as $c) {
                    $html .= '<td>' . inlineMarkdown($c) . '</td>';
                }
                $html .= '</tr>';
            }
            $html .= '</tbody></table>';
            $out[] = $html;
            continue;
        }

        // Unordered list
        if (preg_match('/^(\s*)[\-\*\+]\s+(.+)$/', $line)) {
            $listHtml = parseList($lines, $i, $n, false);
            $out[] = $listHtml;
            continue;
        }

        // Ordered list
        if (preg_match('/^(\s*)\d+\.\s+(.+)$/', $line)) {
            $listHtml = parseList($lines, $i, $n, true);
            $out[] = $listHtml;
            continue;
        }

        // Blockquote
        if (preg_match('/^>\s?(.*)$/', $line, $m)) {
            $bq = [$m[1]];
            $i++;
            while ($i < $n && preg_match('/^>\s?(.*)$/', $lines[$i], $m2)) {
                $bq[] = $m2[1];
                $i++;
            }
            $out[] = '<blockquote>' . inlineMarkdown(implode(' ', $bq)) . '</blockquote>';
            continue;
        }

        // Empty line
        if (trim($line) === '') {
            $i++;
            continue;
        }

        // Paragraph (fold consecutive non-empty non-special lines)
        $para = [$line];
        $i++;
        while ($i < $n && trim($lines[$i]) !== '' && !isBlockStart($lines[$i], $lines[$i + 1] ?? '')) {
            $para[] = $lines[$i];
            $i++;
        }
        $out[] = '<p>' . inlineMarkdown(implode(' ', array_map('trim', $para))) . '</p>';
    }

    return implode("\n", $out);
}

function isBlockStart(string $line, string $next): bool
{
    if (preg_match('/^#{1,6}\s+/', $line)) return true;
    if (preg_match('/^```/', $line)) return true;
    if (preg_match('/^(\-{3,}|\*{3,}|_{3,})\s*$/', $line)) return true;
    if (preg_match('/^(\s*)[\-\*\+]\s+/', $line)) return true;
    if (preg_match('/^(\s*)\d+\.\s+/', $line)) return true;
    if (preg_match('/^>/', $line)) return true;
    if (str_contains($line, '|') && preg_match('/^\s*\|?[\s\-:|]+\|[\s\-:|]+$/', $next)) return true;
    return false;
}

function parseTableRow(string $line): array
{
    $line = trim($line);
    $line = trim($line, '|');
    return array_map('trim', explode('|', $line));
}

function parseList(array $lines, int &$i, int $n, bool $ordered): string
{
    $tag = $ordered ? 'ol' : 'ul';
    $items = [];
    $pattern = $ordered ? '/^\s*\d+\.\s+(.+)$/' : '/^\s*[\-\*\+]\s+(.+)$/';
    while ($i < $n && preg_match($pattern, $lines[$i], $m)) {
        $items[] = '<li>' . inlineMarkdown($m[1]) . '</li>';
        $i++;
    }
    return "<{$tag}>" . implode('', $items) . "</{$tag}>";
}

function inlineMarkdown(string $text): string
{
    // Echappement HTML basique sauf pour les patterns markdown
    $text = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    // Code inline (avant le reste)
    $text = preg_replace_callback('/`([^`]+)`/', static fn($m) => '<code>' . $m[1] . '</code>', $text);
    // Bold + italique combinés
    $text = preg_replace('/\*\*\*(.+?)\*\*\*/', '<strong><em>$1</em></strong>', $text);
    $text = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text);
    $text = preg_replace('/(?<![\w\*])\*([^\*\n]+?)\*(?![\w\*])/', '<em>$1</em>', $text);
    // Liens [text](url)
    $text = preg_replace_callback(
        '/\[([^\]]+)\]\(([^)]+)\)/',
        static function ($m) {
            $href = htmlspecialchars_decode($m[2]);
            return '<a href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '">' . $m[1] . '</a>';
        },
        $text
    );
    // Strikethrough ~~text~~
    $text = preg_replace('/~~(.+?)~~/', '<del>$1</del>', $text);
    return $text;
}
