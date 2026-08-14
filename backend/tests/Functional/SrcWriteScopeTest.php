<?php

namespace App\Tests\Functional;

use App\Entity\Atelier;
use App\Entity\Client;
use App\Entity\ConfigAtelier;
use App\Entity\User;
use App\Service\BookingAtelierAccessService;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lot C5 (recette) : le SRC voit à travers plusieurs ateliers en LECTURE (via le Cockpit),
 * mais ne doit RIEN pouvoir modifier hors de son atelier ACTIF — la lecture cross-atelier
 * (withCrossAtelierRead) n'est jamais appelée par les routes d'écriture génériques.
 */
class SrcWriteScopeTest extends WebTestCase
{
    private function authHeaders(User $user): array
    {
        $jwtManager = static::getContainer()->get(JWTTokenManagerInterface::class);

        return ['CONTENT_TYPE' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer ' . $jwtManager->create($user)];
    }

    public function testServiceClientCannotWriteToClientOfAnotherAtelierWithoutSwitching(): void
    {
        $webClient = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $suffix = bin2hex(random_bytes(4));

        $atelierHome = (new Atelier())->setNom('WriteHome' . $suffix)->setSlug('write-home-' . $suffix)->setActif(true);
        $atelierAutre = (new Atelier())->setNom('WriteAutre' . $suffix)->setSlug('write-autre-' . $suffix)->setActif(true);
        $em->persist($atelierHome);
        $em->persist($atelierAutre);
        $em->flush();

        $config = new ConfigAtelier();
        $config->setAtelierId($atelierAutre->getId());
        $modules = ConfigAtelier::defaultFeatureModules();
        $modules[BookingAtelierAccessService::FEATURE_RDV_SIEGE] = true;
        $config->setFeatureModules($modules);
        $em->persist($config);
        $em->flush();

        $clientAutre = (new Client())->setNom('Proteger' . $suffix)->setPrenom('X')->setTelephone('0600000099');
        $clientAutre->setAtelierId($atelierAutre->getId());
        $em->persist($clientAutre);
        $em->flush();

        $src = (new User())->setUsername('writescope-' . $suffix)->setEmail("writescope-$suffix@example.test")->setHashedPassword('x')->setRole('service_client')->setAtelierId($atelierHome->getId());
        $em->persist($src);
        $em->flush();
        $em->clear();

        $headers = $this->authHeaders($src);

        // Lecture cross-atelier via le Cockpit : le client de l'AUTRE atelier est bien visible
        // (confirme que getAllowedAteliers() l'inclut vraiment, sinon le test suivant serait un
        // faux négatif — "invisible" ne prouverait rien sur l'écriture).
        $webClient->request('GET', '/api/cockpit/clients/' . $clientAutre->getId(), [], [], $headers);
        $this->assertSame(Response::HTTP_OK, $webClient->getResponse()->getStatusCode(), 'prérequis : le client doit être lisible via le Cockpit pour que le test d\'écriture ait un sens');

        // Écriture générique SANS switch d'atelier actif (reste sur son atelier de rattachement) :
        // doit échouer, PAS parce que l'IsGranted bloque (ROLE_USER suffit), mais parce que le
        // filtre tenant normal (1 seul atelier actif) ne trouve pas la ressource hors de son scope.
        $webClient->request('PATCH', '/api/clients/' . $clientAutre->getId(), [], [], array_merge($headers, ['CONTENT_TYPE' => 'application/merge-patch+json']), json_encode(['nom' => 'MODIFIE-' . $suffix]));

        $this->assertNotSame(Response::HTTP_OK, $webClient->getResponse()->getStatusCode(), 'le SRC ne doit jamais pouvoir modifier un client hors de son atelier actif, même s\'il peut le LIRE via le Cockpit');

        // Vérification en SQL brut (hors ORM) : évite toute ambiguïté sur l'état du filtre tenant
        // après la requête HTTP précédente — on veut prouver l'absence de modification, pas re-
        // tester le filtre de lecture.
        $nomActuel = $em->getConnection()->fetchOne('SELECT nom FROM clients WHERE id = ?', [$clientAutre->getId()]);
        $this->assertStringNotContainsString('MODIFIE', (string) $nomActuel, 'le client d\'un autre atelier ne doit pas avoir été modifié');
    }
}
