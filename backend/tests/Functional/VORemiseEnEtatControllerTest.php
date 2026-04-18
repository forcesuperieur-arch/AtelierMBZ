<?php

namespace App\Tests\Functional;

use App\Entity\Client;
use App\Entity\RoleMetier;
use App\Entity\User;
use App\Entity\VODocument;
use App\Entity\VORemiseEnEtat;
use App\Entity\Vehicule;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class VORemiseEnEtatControllerTest extends WebTestCase
{
    public function testCampaignSignatureArchivesSignedDocument(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $fixture = $this->createFixture($em);
        $campaignId = $this->createPurchaseCampaign($client, $fixture['voManager'], $fixture['seller'], $fixture['vehicle']);

        $client->request(
            'POST',
            '/api/vo/remises-en-etat/' . $campaignId . '/sign',
            [],
            [],
            $this->authHeaders($fixture['voManager']),
            json_encode(['signature' => $this->sampleSignature()], JSON_THROW_ON_ERROR)
        );

        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
        $payload = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(VORemiseEnEtat::STATUS_VALIDEE, $payload['status'] ?? null);
        $this->assertTrue((bool) ($payload['document']['signed'] ?? false));
        $this->assertNotEmpty($payload['document']['signedHash'] ?? null);
        $this->assertNotEmpty($payload['document']['archivedDocument']['id'] ?? null);

        $em->clear();

        $campaign = $em->getRepository(VORemiseEnEtat::class)->find($campaignId);
        $this->assertNotNull($campaign);
        $this->assertTrue($campaign->hasSignedDocument());
        $this->assertSame(VORemiseEnEtat::STATUS_VALIDEE, $campaign->getStatus());

        $document = $em->getRepository(VODocument::class)->findOneBy([
            'type' => VODocument::TYPE_REMISE_EN_ETAT,
            'voRemiseEnEtat' => $campaign,
        ]);
        $this->assertNotNull($document);
        $this->assertStringContainsString('signee', $document->getOriginalFilename());
        $this->assertFileExists(static::getContainer()->getParameter('kernel.project_dir') . '/public' . $document->getFilePath());
    }

    public function testResponsableMagasinCannotCloseCampaign(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $fixture = $this->createFixture($em);
        $campaignId = $this->createPurchaseCampaign($client, $fixture['voManager'], $fixture['seller'], $fixture['vehicle']);

        $client->request(
            'PATCH',
            '/api/vo/remises-en-etat/' . $campaignId,
            [],
            [],
            $this->authHeaders($fixture['responsableMagasin']),
            json_encode(['status' => VORemiseEnEtat::STATUS_CLOTUREE], JSON_THROW_ON_ERROR)
        );

        $this->assertSame(Response::HTTP_FORBIDDEN, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());

        $em->clear();

        $campaign = $em->getRepository(VORemiseEnEtat::class)->find($campaignId);
        $this->assertNotNull($campaign);
        $this->assertSame(VORemiseEnEtat::STATUS_A_CHIFFRER, $campaign->getStatus());
        $this->assertNull($campaign->getClosedAt());
    }

    public function testResponsableAtelierCanCloseCampaign(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $fixture = $this->createFixture($em);
        $campaignId = $this->createPurchaseCampaign($client, $fixture['voManager'], $fixture['seller'], $fixture['vehicle']);

        $client->request(
            'PATCH',
            '/api/vo/remises-en-etat/' . $campaignId,
            [],
            [],
            $this->authHeaders($fixture['responsableAtelier']),
            json_encode(['status' => VORemiseEnEtat::STATUS_CLOTUREE], JSON_THROW_ON_ERROR)
        );

        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
        $payload = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(VORemiseEnEtat::STATUS_CLOTUREE, $payload['status'] ?? null);
        $this->assertNotNull($payload['closedAt'] ?? null);

        $em->clear();

        $campaign = $em->getRepository(VORemiseEnEtat::class)->find($campaignId);
        $this->assertNotNull($campaign);
        $this->assertSame(VORemiseEnEtat::STATUS_CLOTUREE, $campaign->getStatus());
        $this->assertNotNull($campaign->getClosedAt());

        $document = $em->getRepository(VODocument::class)->findOneBy([
            'type' => VODocument::TYPE_REMISE_EN_ETAT,
            'voRemiseEnEtat' => $campaign,
        ]);
        $this->assertNotNull($document);
        $this->assertStringContainsString('fallback', $document->getOriginalFilename());
    }

    /**
     * @return array{voManager: User, responsableMagasin: User, responsableAtelier: User, seller: Client, vehicle: Vehicule, suffix: string}
     */
    private function createFixture(EntityManagerInterface $em): array
    {
        $suffix = bin2hex(random_bytes(4));
        $atelierId = 1000 + random_int(1, 2000);

        $roleAtelier = (new RoleMetier())
            ->setAtelierId($atelierId)
            ->setCode('responsable_atelier')
            ->setLibelle('Responsable atelier')
            ->setBaseRole('ROLE_ADMIN');

        $roleMagasin = (new RoleMetier())
            ->setAtelierId($atelierId)
            ->setCode('responsable_magasin')
            ->setLibelle('Responsable magasin')
            ->setBaseRole('ROLE_ADMIN');

        $voManager = (new User())
            ->setUsername('vo-rem-' . $suffix)
            ->setEmail(sprintf('vo-rem-%s@example.test', $suffix))
            ->setHashedPassword('test')
            ->setPrenom('VO')
            ->setNom('Manager')
            ->setRole('vo_manager')
            ->setAtelierId($atelierId);

        $responsableMagasin = (new User())
            ->setUsername('magasin-' . $suffix)
            ->setEmail(sprintf('magasin-%s@example.test', $suffix))
            ->setHashedPassword('test')
            ->setPrenom('Resp')
            ->setNom('Magasin')
            ->setRole('user')
            ->setRoleMetier($roleMagasin)
            ->setAtelierId($atelierId);

        $responsableAtelier = (new User())
            ->setUsername('atelier-' . $suffix)
            ->setEmail(sprintf('atelier-%s@example.test', $suffix))
            ->setHashedPassword('test')
            ->setPrenom('Resp')
            ->setNom('Atelier')
            ->setRole('user')
            ->setRoleMetier($roleAtelier)
            ->setAtelierId($atelierId);

        $seller = (new Client())
            ->setAtelierId($atelierId)
            ->setNom('Seller-' . $suffix)
            ->setPrenom('Alice')
            ->setTelephone('0601000001')
            ->setEmail(sprintf('seller-rem-%s@example.test', $suffix))
            ->setAdresse('12 rue des essais, Paris');

        $vehicle = (new Vehicule())
            ->setAtelierId($atelierId)
            ->setClient($seller)
            ->setPlaque('REM-' . strtoupper(substr($suffix, 0, 4)))
            ->setMarque('Yamaha')
            ->setModele('MT-09')
            ->setAnnee(2023)
            ->setCouleur('Noir')
            ->setMileage(8700)
            ->setVin('VINREM' . strtoupper(str_pad(substr($suffix, 0, 11), 11, '0')))
            ->setDatePremiereMiseEnCirculation(new \DateTime('2023-03-10'));

        $em->persist($roleAtelier);
        $em->persist($roleMagasin);
        $em->persist($voManager);
        $em->persist($responsableMagasin);
        $em->persist($responsableAtelier);
        $em->persist($seller);
        $em->persist($vehicle);
        $em->flush();

        return [
            'voManager' => $voManager,
            'responsableMagasin' => $responsableMagasin,
            'responsableAtelier' => $responsableAtelier,
            'seller' => $seller,
            'vehicle' => $vehicle,
            'suffix' => $suffix,
        ];
    }

    private function createPurchaseCampaign($client, User $user, Client $seller, Vehicule $vehicle): int
    {
        $client->request(
            'POST',
            '/api/vo/purchases',
            [],
            [],
            $this->authHeaders($user),
            json_encode([
                'sellerId' => $seller->getId(),
                'vehiculeId' => $vehicle->getId(),
                'purchasePrice' => '6400.00',
                'targetSalePrice' => '8800.00',
                'purchaseDate' => '2026-04-18',
                'sellerIdType' => 'carte_identite',
                'sellerIdNumber' => 'CI-REM-' . strtoupper(substr((string) $seller->getNom(), -4)),
                'sellerIdDate' => '2025-06-01',
                'nonGageDate' => '2026-04-17',
                'controleTechniqueOk' => true,
                'status' => 'brouillon',
            ], JSON_THROW_ON_ERROR)
        );

        $this->assertSame(Response::HTTP_CREATED, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
        $purchasePayload = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $purchaseId = (int) ($purchasePayload['id'] ?? 0);
        $this->assertGreaterThan(0, $purchaseId);

        $client->request(
            'POST',
            '/api/vo/purchases/' . $purchaseId . '/remises-en-etat',
            [],
            [],
            $this->authHeaders($user),
            json_encode([], JSON_THROW_ON_ERROR)
        );

        $this->assertSame(Response::HTTP_CREATED, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
        $campaignPayload = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        return (int) ($campaignPayload['id'] ?? 0);
    }

    private function sampleSignature(): string
    {
        return 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO9Z0z8AAAAASUVORK5CYII=';
    }

    /**
     * @return array{CONTENT_TYPE: string, HTTP_AUTHORIZATION: string}
     */
    private function authHeaders(User $user): array
    {
        $jwtManager = static::getContainer()->get(JWTTokenManagerInterface::class);

        return [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $jwtManager->create($user),
        ];
    }
}