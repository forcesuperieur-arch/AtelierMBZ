<?php

namespace App\Tests\Functional;

use App\Entity\Atelier;
use App\Entity\Client;
use App\Entity\ConfigAtelier;
use App\Entity\User;
use App\Service\BookingAtelierAccessService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Socle sécurité du Cockpit SRC (PILOTE_PLAN.md Lot C1) : un compte service_client doit voir
 * À TRAVERS plusieurs ateliers en lecture, UNIQUEMENT via BookingAtelierAccessService::
 * withCrossAtelierRead() — jamais par défaut, jamais pour un autre rôle.
 */
class CrossAtelierReadTest extends KernelTestCase
{
    private function makeAtelier(EntityManagerInterface $em, string $suffix, bool $siege = false): Atelier
    {
        $atelier = (new Atelier())->setNom('Atelier ' . $suffix)->setSlug('atelier-' . strtolower($suffix) . '-' . $suffix)->setActif(true);
        $em->persist($atelier);
        $em->flush();

        if ($siege) {
            $config = new ConfigAtelier();
            $config->setAtelierId($atelier->getId());
            $modules = ConfigAtelier::defaultFeatureModules();
            $modules[BookingAtelierAccessService::FEATURE_RDV_SIEGE] = true;
            $config->setFeatureModules($modules);
            $em->persist($config);
            $em->flush();
        }

        return $atelier;
    }

    private function makeClient(EntityManagerInterface $em, string $nom, int $atelierId): Client
    {
        $client = (new Client())->setNom($nom)->setPrenom('Test')->setTelephone('0600000000');
        $client->setAtelierId($atelierId);
        $em->persist($client);
        $em->flush();

        return $client;
    }

    public function testNormalStaffOnlySeesOwnAtelierEvenInsideWrapper(): void
    {
        self::bootKernel();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $suffix = bin2hex(random_bytes(3));

        $atelier1 = $this->makeAtelier($em, 'A' . $suffix);
        $atelier2 = $this->makeAtelier($em, 'B' . $suffix, siege: true);
        $this->makeClient($em, 'ClientA' . $suffix, $atelier1->getId());
        $this->makeClient($em, 'ClientB' . $suffix, $atelier2->getId());

        $receptionniste = (new User())->setUsername('rec-' . $suffix)->setEmail("rec-$suffix@example.test")->setHashedPassword('x')->setRole('receptionnaire')->setAtelierId($atelier1->getId());
        $em->persist($receptionniste);
        $em->flush();

        $access = static::getContainer()->get(BookingAtelierAccessService::class);
        $filters = $em->getFilters();
        $filters->enable('tenant_filter')->setParameter('atelier_id', $atelier1->getId());

        // Un réceptionniste n'est PAS service_client : withCrossAtelierRead() est un no-op,
        // le scope reste celui déjà actif (atelier 1 seul), même en essayant de l'appeler.
        $countInside = $access->withCrossAtelierRead(
            $receptionniste,
            fn () => count($em->getRepository(Client::class)->findAll()),
        );

        $this->assertSame(1, $countInside, 'un rôle non-SRC ne doit jamais voir un autre atelier, même via withCrossAtelierRead()');
    }

    public function testServiceClientSeesAcrossAllowedAteliersOnlyInsideWrapper(): void
    {
        self::bootKernel();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $suffix = bin2hex(random_bytes(3));

        $atelier1 = $this->makeAtelier($em, 'C' . $suffix);
        $atelier2 = $this->makeAtelier($em, 'D' . $suffix, siege: true);
        $this->makeClient($em, 'ClientC' . $suffix, $atelier1->getId());
        $this->makeClient($em, 'ClientD' . $suffix, $atelier2->getId());

        $src = (new User())->setUsername('src-' . $suffix)->setEmail("src-$suffix@example.test")->setHashedPassword('x')->setRole('service_client')->setAtelierId($atelier1->getId());
        $em->persist($src);
        $em->flush();

        $access = static::getContainer()->get(BookingAtelierAccessService::class);
        $filters = $em->getFilters();
        $filters->enable('tenant_filter')->setParameter('atelier_id', $atelier1->getId());

        // La base de dev partagée contient d'autres ateliers/clients de tests précédents (dont
        // potentiellement d'autres ateliers en rdv_siege) : on ne compte pas un total global,
        // on vérifie la PRÉSENCE de nos 2 fixtures précises, ce qui est robuste à cette pollution.
        $nomsVisibles = fn () => array_map(fn (Client $c) => $c->getNom(), $em->getRepository(Client::class)->findAll());

        $before = $nomsVisibles();
        $this->assertContains('ClientC' . $suffix, $before, 'hors fenêtre : le SRC voit son propre atelier');
        $this->assertNotContains('ClientD' . $suffix, $before, 'hors fenêtre : le SRC ne voit PAS encore l\'autre atelier');

        $inside = $access->withCrossAtelierRead($src, $nomsVisibles);
        $this->assertContains('ClientC' . $suffix, $inside, 'dans la fenêtre : toujours son propre atelier');
        $this->assertContains('ClientD' . $suffix, $inside, 'dans la fenêtre : voit aussi l\'atelier en rdv_siege');

        $after = $nomsVisibles();
        $this->assertContains('ClientC' . $suffix, $after);
        $this->assertNotContains('ClientD' . $suffix, $after, 'la fenêtre cross-atelier se referme : retour au scope à un seul atelier après le callback');
    }

    public function testCrossAtelierWindowClosesEvenIfCallbackThrows(): void
    {
        self::bootKernel();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $suffix = bin2hex(random_bytes(3));

        $atelier1 = $this->makeAtelier($em, 'E' . $suffix);
        $this->makeClient($em, 'ClientE' . $suffix, $atelier1->getId());

        $src = (new User())->setUsername('src2-' . $suffix)->setEmail("src2-$suffix@example.test")->setHashedPassword('x')->setRole('service_client')->setAtelierId($atelier1->getId());
        $em->persist($src);
        $em->flush();

        $access = static::getContainer()->get(BookingAtelierAccessService::class);
        $filters = $em->getFilters();
        $filters->enable('tenant_filter')->setParameter('atelier_id', $atelier1->getId());

        try {
            $access->withCrossAtelierRead($src, function () {
                throw new \RuntimeException('boom');
            });
            $this->fail('l\'exception aurait dû se propager');
        } catch (\RuntimeException) {
            // attendu
        }

        $countAfter = count($em->getRepository(Client::class)->findAll());
        $this->assertSame(1, $countAfter, 'même après une exception dans le callback, la fenêtre cross-atelier doit être refermée');
    }
}
