<?php

namespace App\Tests\Unit;

use App\Service\DocumentHeaderRenderer;
use App\Service\DocumentPreviewFixtures;
use App\Service\PdfRenderer;
use App\Service\PdfTemplateRegistry;
use App\Twig\PdfFormatExtension;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

/**
 * Rend chaque document du registre.
 *
 * `strict_variables` est activé volontairement : c'est ce qui transforme un
 * accès à une clé absente en échec de test, au lieu d'un blanc silencieux sur un
 * document remis au client.
 */
class PdfDocumentRenderingTest extends TestCase
{
    private function twig(): Environment
    {
        $twig = new Environment(
            new FilesystemLoader(dirname(__DIR__, 2) . '/templates'),
            ['strict_variables' => true, 'debug' => true, 'cache' => false],
        );
        $twig->addExtension(new PdfFormatExtension());

        return $twig;
    }

    private function headerRenderer(): DocumentHeaderRenderer
    {
        // render() n'interroge pas la base : un stub suffit, le dépôt n'est
        // sollicité que par renderFor().
        return new DocumentHeaderRenderer(
            $this->createStub(EntityManagerInterface::class),
            new PdfTemplateRegistry(),
        );
    }

    /** @return array<string, mixed> */
    private function context(string $code, DocumentPreviewFixtures $fixtures): array
    {
        return [
            'atelier' => null,
            'logo_data_uri' => null,
            'custom_header_html' => null,
            ...$fixtures->forCode($code),
        ];
    }

    public static function documentCodesProvider(): \Generator
    {
        foreach ((new PdfTemplateRegistry())->codes() as $code) {
            yield $code => [$code];
        }
    }

    #[DataProvider('documentCodesProvider')]
    public function testDocumentRendersToHtml(string $code): void
    {
        $registry = new PdfTemplateRegistry();
        $fixtures = new DocumentPreviewFixtures();

        $html = $this->twig()->render(
            $registry->templateFor($code),
            $this->context($code, $fixtures),
        );

        self::assertStringContainsString('<body', $html);
        // Aucun jeton Twig ne doit fuir dans le document final.
        self::assertStringNotContainsString('{{', $html);
    }

    /**
     * La police DejaVu Sans, seule police embarquée, n'a pas de glyphe emoji :
     * dompdf les remplace par des carrés vides sur le document imprimé.
     */
    public function testDocumentsContainNoEmoji(): void
    {
        $registry = new PdfTemplateRegistry();
        $fixtures = new DocumentPreviewFixtures();
        $twig = $this->twig();

        foreach ($registry->all() as $meta) {
            $html = $twig->render($meta['template'], $this->context($meta['code'], $fixtures));

            $found = preg_match(
                '/[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}\x{FE0F}\x{2B00}-\x{2BFF}]/u',
                $html,
                $matches,
            );

            self::assertSame(
                0,
                $found,
                sprintf('Le document « %s » contient un emoji (%s) : il sera rendu en carré vide.', $meta['code'], $matches[0] ?? ''),
            );
        }
    }

    /**
     * Les montants doivent sortir au format français. Un point décimal signale
     * une valeur affichée brute, sans passer par le filtre `eur`.
     */
    public function testInvoiceAmountsUseFrenchFormatting(): void
    {
        $registry = new PdfTemplateRegistry();
        $fixtures = new DocumentPreviewFixtures();

        $html = $this->twig()->render(
            $registry->templateFor('facture'),
            $this->context('facture', $fixtures),
        );

        self::assertStringContainsString('248,40', $html);
        self::assertStringNotContainsString('248.40', $html);
    }

    /**
     * Rendu dompdf complet sur un document représentatif : valide aussi le
     * tampon de pagination, qui ne peut pas être exprimé en CSS.
     */
    public function testOrdreReparationProducesAPaginatedPdf(): void
    {
        $registry = new PdfTemplateRegistry();
        $fixtures = new DocumentPreviewFixtures();

        $html = $this->twig()->render(
            $registry->templateFor('ordre_reparation'),
            $this->context('ordre_reparation', $fixtures),
        );

        $pdf = (new PdfRenderer())->render($html, 'Atelier — OR de test');

        self::assertStringStartsWith('%PDF-', $pdf);
        self::assertGreaterThan(5_000, strlen($pdf));
    }

    /**
     * L'en-tête composé en administration doit remplacer l'en-tête par défaut
     * dans le document réellement produit — sans quoi le designer serait, comme
     * avant, purement décoratif.
     */
    public function testCustomHeaderReplacesDefaultHeader(): void
    {
        $registry = new PdfTemplateRegistry();
        $fixtures = new DocumentPreviewFixtures();
        $headerRenderer = $this->headerRenderer();

        $band = $headerRenderer->render(
            [['type' => 'text', 'x' => 10, 'y' => 5, 'w' => 80, 'h' => 8, 'content' => 'EN-TETE {{atelier_nom}}']],
            ['atelier_nom' => 'Garage Personnalise'],
        );

        $html = $this->twig()->render($registry->templateFor('facture'), [
            ...$this->context('facture', $fixtures),
            'custom_header_html' => $band,
        ]);

        self::assertStringContainsString('EN-TETE Garage Personnalise', $html);
    }

    /** Un jeton inconnu est effacé plutôt qu'imprimé en clair sur la facture. */
    public function testUnknownHeaderTokenIsStripped(): void
    {
        $headerRenderer = $this->headerRenderer();

        $band = $headerRenderer->render(
            [['type' => 'text', 'x' => 0, 'y' => 0, 'w' => 80, 'h' => 8, 'content' => 'A{{jeton_inconnu}}B']],
            ['atelier_nom' => 'Garage'],
        );

        self::assertStringContainsString('AB', $band);
        self::assertStringNotContainsString('jeton_inconnu', $band);
    }
}
