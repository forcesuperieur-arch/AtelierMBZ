<?php

namespace App\Tests\Unit;

use App\Service\DocumentPreviewFixtures;
use App\Service\PdfTemplateRegistry;
use PHPUnit\Framework\TestCase;

class PdfTemplateRegistryTest extends TestCase
{
    private function templatesDir(): string
    {
        return dirname(__DIR__, 2) . '/templates';
    }

    /**
     * Le régression d'origine : l'administration annonçait un document
     * « rapport_intervention » dont le fichier Twig n'existait pas, si bien que
     * la prévisualisation répondait 500. Aucun test ne vérifiait l'existence du
     * fichier — seulement la présence des métadonnées.
     */
    public function testEveryRegisteredTemplateFileExistsOnDisk(): void
    {
        $registry = new PdfTemplateRegistry();

        foreach ($registry->all() as $meta) {
            $path = $this->templatesDir() . '/' . $meta['template'];

            self::assertFileExists(
                $path,
                sprintf(
                    'Le document « %s » est proposé dans l\'administration mais son template est absent : %s',
                    $meta['code'],
                    $meta['template'],
                ),
            );
        }
    }

    public function testEveryTemplateRenderedByTheApplicationIsListedInAdmin(): void
    {
        $registry = new PdfTemplateRegistry();
        $registered = array_map(
            static fn (array $meta): string => basename($meta['template']),
            $registry->all(),
        );

        $onDisk = [];
        foreach (glob($this->templatesDir() . '/pdf/*.html.twig') ?: [] as $file) {
            $name = basename($file);
            // Les partiels (_layout, _macros) ne sont pas des documents.
            if (!str_starts_with($name, '_')) {
                $onDisk[] = $name;
            }
        }

        sort($registered);
        sort($onDisk);

        self::assertSame(
            $onDisk,
            $registered,
            'Tout template PDF présent sur le disque doit être proposé dans l\'administration, et inversement.',
        );
    }

    public function testMetadataIsCompleteAndCoherent(): void
    {
        $registry = new PdfTemplateRegistry();
        $templates = $registry->all();

        self::assertNotEmpty($templates);

        foreach ($templates as $meta) {
            self::assertNotSame('', trim($meta['label']), sprintf('Libellé manquant pour « %s ».', $meta['code']));
            self::assertNotSame('', trim($meta['description']), sprintf('Description manquante pour « %s ».', $meta['code']));
            self::assertContains($meta['category'], [
                PdfTemplateRegistry::CATEGORY_ATELIER,
                PdfTemplateRegistry::CATEGORY_VO,
                PdfTemplateRegistry::CATEGORY_PILOTAGE,
            ], sprintf('Catégorie inattendue pour « %s ».', $meta['code']));
            self::assertContains($meta['orientation'], [
                PdfTemplateRegistry::PORTRAIT,
                PdfTemplateRegistry::LANDSCAPE,
            ], sprintf('Orientation inattendue pour « %s ».', $meta['code']));
            self::assertIsBool($meta['customisableHeader']);
        }
    }

    /** Onze colonnes : le registre légal est illisible en portrait. */
    public function testLivrePoliceIsRenderedInLandscape(): void
    {
        $registry = new PdfTemplateRegistry();

        self::assertSame(PdfTemplateRegistry::LANDSCAPE, $registry->orientationFor('vo_livre_police'));
    }

    public function testUnknownCodeIsRejected(): void
    {
        $registry = new PdfTemplateRegistry();

        self::assertFalse($registry->has('document_inexistant'));

        $this->expectException(\InvalidArgumentException::class);
        $registry->get('document_inexistant');
    }

    /**
     * Un document sans jeu de données ne peut pas être prévisualisé : la page
     * d'administration afficherait un squelette vide.
     */
    public function testEveryRegisteredDocumentHasPreviewFixtures(): void
    {
        $registry = new PdfTemplateRegistry();
        $fixtures = new DocumentPreviewFixtures();

        foreach ($registry->codes() as $code) {
            self::assertNotEmpty(
                $fixtures->forCode($code),
                sprintf('Aucun jeu de données de prévisualisation pour « %s ».', $code),
            );
        }
    }
}
