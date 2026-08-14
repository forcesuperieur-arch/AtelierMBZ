<?php

namespace App\Tests\Functional;

use App\Entity\Atelier;
use App\Entity\AuditLog;
use App\Entity\Client;
use App\Entity\ConfigAtelier;
use App\Entity\RendezVous;
use App\Entity\User;
use App\Entity\Vehicule;
use App\Service\BookingAtelierAccessService;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class CockpitSrcControllerTest extends WebTestCase
{
    private function authHeaders(User $user): array
    {
        $jwtManager = static::getContainer()->get(JWTTokenManagerInterface::class);

        return ['CONTENT_TYPE' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer ' . $jwtManager->create($user)];
    }

    private function makeAtelier(EntityManagerInterface $em, string $suffix, bool $siege = false): Atelier
    {
        $atelier = (new Atelier())->setNom('Cockpit ' . $suffix)->setSlug('cockpit-' . strtolower($suffix))->setActif(true);
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

    public function testRechercheFindsClientAcrossAteliers(): void
    {
        $webClient = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $suffix = bin2hex(random_bytes(4));

        $atelierSrc = $this->makeAtelier($em, 'Src' . $suffix);
        $atelierAutre = $this->makeAtelier($em, 'Autre' . $suffix, siege: true);

        $client = (new Client())->setNom('Dupont' . $suffix)->setPrenom('Marie')->setTelephone('0611223344');
        $client->setAtelierId($atelierAutre->getId());
        $em->persist($client);
        $em->flush();

        $src = (new User())->setUsername('cockpit-' . $suffix)->setEmail("cockpit-$suffix@example.test")->setHashedPassword('x')->setRole('service_client')->setAtelierId($atelierSrc->getId());
        $em->persist($src);
        $em->flush();

        $webClient->request('GET', '/api/cockpit/recherche?q=Dupont' . $suffix, [], [], $this->authHeaders($src));

        $this->assertSame(Response::HTTP_OK, $webClient->getResponse()->getStatusCode());
        $payload = json_decode($webClient->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertCount(1, $payload['clients']);
        $this->assertSame($atelierAutre->getId(), $payload['clients'][0]['atelier_id']);
    }

    public function testNonServiceClientRoleIsForbidden(): void
    {
        $webClient = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $suffix = bin2hex(random_bytes(4));

        $mecanicien = (new User())->setUsername('meca-cockpit-' . $suffix)->setEmail("meca-cockpit-$suffix@example.test")->setHashedPassword('x')->setRole('mecanicien')->setAtelierId(1);
        $em->persist($mecanicien);
        $em->flush();

        $webClient->request('GET', '/api/cockpit/recherche?q=test', [], [], $this->authHeaders($mecanicien));

        $this->assertSame(Response::HTTP_FORBIDDEN, $webClient->getResponse()->getStatusCode());
    }

    public function testDossierCrossAtelierIsAuditedButSameAtelierIsNot(): void
    {
        $webClient = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $suffix = bin2hex(random_bytes(4));

        $atelierSrc = $this->makeAtelier($em, 'Home' . $suffix);
        $atelierAutre = $this->makeAtelier($em, 'Loin' . $suffix, siege: true);

        $clientLoin = (new Client())->setNom('Loin' . $suffix)->setPrenom('X')->setTelephone('0600000001');
        $clientLoin->setAtelierId($atelierAutre->getId());
        $clientHome = (new Client())->setNom('Home' . $suffix)->setPrenom('X')->setTelephone('0600000002');
        $clientHome->setAtelierId($atelierSrc->getId());
        $em->persist($clientLoin);
        $em->persist($clientHome);
        $em->flush();

        $src = (new User())->setUsername('cockpit2-' . $suffix)->setEmail("cockpit2-$suffix@example.test")->setHashedPassword('x')->setRole('service_client')->setAtelierId($atelierSrc->getId());
        $em->persist($src);
        $em->flush();

        $headers = $this->authHeaders($src);

        // Atelier actif = celui du SRC (pas de switch demandé) → consultation de son propre client
        $webClient->request('GET', '/api/cockpit/clients/' . $clientHome->getId(), [], [], $headers);
        $this->assertSame(Response::HTTP_OK, $webClient->getResponse()->getStatusCode());

        $auditHome = $em->getRepository(AuditLog::class)->findOneBy(['action' => 'consultation_cross_atelier', 'entityType' => 'client', 'entityId' => $clientHome->getId()]);
        $this->assertNull($auditHome, 'consulter son propre atelier ne doit pas être journalisé comme cross-atelier');

        $webClient->request('GET', '/api/cockpit/clients/' . $clientLoin->getId(), [], [], $headers);
        $this->assertSame(Response::HTTP_OK, $webClient->getResponse()->getStatusCode());
        $payload = json_decode($webClient->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame($atelierAutre->getId(), $payload['atelier_id']);

        $auditLoin = $em->getRepository(AuditLog::class)->findOneBy(['action' => 'consultation_cross_atelier', 'entityType' => 'client', 'entityId' => $clientLoin->getId()]);
        $this->assertNotNull($auditLoin, 'consulter le dossier d\'un client d\'un AUTRE atelier doit être journalisé');
        $this->assertSame($atelierAutre->getId(), json_decode((string) $auditLoin->getDetails(), true)['atelier_consulte']);
    }

    public function testDossierIsNotAccessibleOutsideAllowedAteliers(): void
    {
        $webClient = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $suffix = bin2hex(random_bytes(4));

        $atelierSrc = $this->makeAtelier($em, 'Scope' . $suffix);
        // Atelier SANS rdv_siege : hors du périmètre autorisé du SRC.
        $atelierHorsScope = $this->makeAtelier($em, 'HorsScope' . $suffix, siege: false);

        $clientHorsScope = (new Client())->setNom('HorsScope' . $suffix)->setPrenom('X')->setTelephone('0600000003');
        $clientHorsScope->setAtelierId($atelierHorsScope->getId());
        $em->persist($clientHorsScope);
        $em->flush();

        $src = (new User())->setUsername('cockpit3-' . $suffix)->setEmail("cockpit3-$suffix@example.test")->setHashedPassword('x')->setRole('service_client')->setAtelierId($atelierSrc->getId());
        $em->persist($src);
        $em->flush();

        // IMPORTANT : vide la identity map Doctrine avant la requête simulée. Sans ce clear(),
        // $clientHorsScope reste en cache mémoire dans CETTE instance d'EntityManager (persist()
        // l'y met inconditionnellement) et un find() ultérieur le retrouve par ce cache SANS
        // repasser par le filtre tenant — un faux négatif de test, pas un vrai contournement
        // possible en production (une requête HTTP réelle démarre toujours avec un EntityManager
        // vierge, donc ce find() interrogerait forcément la base, filtre actif).
        $em->clear();

        $webClient->request('GET', '/api/cockpit/clients/' . $clientHorsScope->getId(), [], [], $this->authHeaders($src));

        $this->assertSame(Response::HTTP_NOT_FOUND, $webClient->getResponse()->getStatusCode(), 'un atelier hors périmètre (pas rdv_siege, pas l\'atelier assigné) doit rester invisible');
    }
}
