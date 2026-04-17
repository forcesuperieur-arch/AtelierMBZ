<?php

namespace App\Tests\Functional;

use App\Entity\Client;
use App\Entity\User;
use App\Entity\VODocument;
use App\Entity\VOFacture;
use App\Entity\VOLivrePolice;
use App\Entity\VOPurchase;
use App\Entity\VODepotVente;
use App\Entity\Vehicule;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class VOControllerTest extends WebTestCase
{
    public function testPurchaseCanBeConfirmedAndSoldThroughWorkflow(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $fixture = $this->createVoFixture($em);

        $client->request(
            'POST',
            '/api/vo/purchases',
            [],
            [],
            $this->authHeaders($fixture['user']),
            json_encode([
                'vehiculeId' => $fixture['purchaseVehicle']->getId(),
                'sellerId' => $fixture['seller']->getId(),
                'expertId' => $fixture['user']->getId(),
                'purchasePrice' => '6500.00',
                'targetSalePrice' => '9200.00',
                'purchaseDate' => '2026-04-17',
                'sellerIdType' => 'carte_identite',
                'sellerIdNumber' => 'CI-ACHAT-' . strtoupper($fixture['suffix']),
                'sellerIdDate' => '2025-09-01',
                'nonGageDate' => '2026-04-16',
                'controleTechniqueOk' => true,
                'notes' => 'Dossier de test achat.',
            ], JSON_THROW_ON_ERROR)
        );

        $this->assertSame(Response::HTTP_CREATED, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
        $creationPayload = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $purchaseId = (int) ($creationPayload['id'] ?? 0);
        $this->assertGreaterThan(0, $purchaseId);

        $purchase = $em->getRepository(VOPurchase::class)->find($purchaseId);
        $this->assertNotNull($purchase);

        $this->attachPurchaseComplianceDocuments($em, $purchase, $fixture['user']);
        $em->flush();

        $client->request(
            'POST',
            '/api/vo/purchases/' . $purchaseId . '/confirm',
            [],
            [],
            $this->authHeaders($fixture['user'])
        );

        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
        $confirmationPayload = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertTrue((bool) ($confirmationPayload['pdfGenerated'] ?? false));
        $this->assertNotNull($confirmationPayload['livrePoliceId'] ?? null);

        $em->clear();

        $purchase = $em->getRepository(VOPurchase::class)->find($purchaseId);
        $this->assertNotNull($purchase);
        $this->assertSame('en_stock', $purchase->getStatus());

        $pvDocument = $em->getRepository(VODocument::class)->findOneBy([
            'voPurchase' => $purchase,
            'type' => VODocument::TYPE_PV_RACHAT,
        ]);
        $this->assertNotNull($pvDocument);

        $livrePoliceEntry = $em->getRepository(VOLivrePolice::class)->findOneBy(['voPurchase' => $purchase]);
        $this->assertNotNull($livrePoliceEntry);
        $this->assertSame('achat', $livrePoliceEntry->getType());
        $this->assertSame('6500.00', $livrePoliceEntry->getPrixAchat());

        $client->request(
            'POST',
            '/api/vo/purchases/' . $purchaseId . '/sell',
            [],
            [],
            $this->authHeaders($fixture['user']),
            json_encode([
                'buyerId' => $fixture['buyer']->getId(),
                'salePrice' => '9200.00',
                'notes' => 'Vente de test VO.',
            ], JSON_THROW_ON_ERROR)
        );

        $this->assertSame(Response::HTTP_CREATED, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
        $salePayload = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertTrue((bool) ($salePayload['pdfGenerated'] ?? false));
        $this->assertNotEmpty($salePayload['invoiceNumber'] ?? null);

        $em->clear();

        $purchase = $em->getRepository(VOPurchase::class)->find($purchaseId);
        $this->assertNotNull($purchase);
        $this->assertSame('vendu', $purchase->getStatus());
        $this->assertNotNull($purchase->getSaleDate());

        $facture = $em->getRepository(VOFacture::class)->findOneBy(['voPurchase' => $purchase]);
        $this->assertNotNull($facture);
        $this->assertSame($fixture['buyer']->getId(), $facture->getClient()->getId());
        $this->assertSame('9200.00', $facture->getTotalTtc());

        $invoiceDocument = $em->getRepository(VODocument::class)->findOneBy([
            'voPurchase' => $purchase,
            'type' => VODocument::TYPE_FACTURE_VO,
        ]);
        $this->assertNotNull($invoiceDocument);

        $livrePoliceEntry = $em->getRepository(VOLivrePolice::class)->findOneBy(['voPurchase' => $purchase]);
        $this->assertNotNull($livrePoliceEntry);
        $this->assertSame('9200.00', $livrePoliceEntry->getPrixVente());
        $this->assertSame($fixture['buyer']->getNom(), $livrePoliceEntry->getAcheteurNom());
    }

    public function testDepotCanBeCreatedAndRestitutedThroughWorkflow(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $fixture = $this->createVoFixture($em);

        $client->request(
            'POST',
            '/api/vo/depots',
            [],
            [],
            $this->authHeaders($fixture['user']),
            json_encode([
                'vehiculeId' => $fixture['depotVehicle']->getId(),
                'deposantId' => $fixture['deposant']->getId(),
                'gestionnaireId' => $fixture['user']->getId(),
                'prixVenteSouhaite' => '11500.00',
                'commissionType' => 'pourcentage',
                'commissionValeur' => '10.00',
                'dateDebut' => '2026-04-17',
                'dureeMandat' => 60,
                'deposantIdType' => 'carte_identite',
                'deposantIdNumber' => 'CI-DEPOT-' . strtoupper($fixture['suffix']),
                'deposantIdDate' => '2025-01-10',
                'conditionsRestitution' => 'Préavis 48h',
                'notes' => 'Dossier de test dépôt.',
            ], JSON_THROW_ON_ERROR)
        );

        $this->assertSame(Response::HTTP_CREATED, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
        $creationPayload = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $depotId = (int) ($creationPayload['id'] ?? 0);
        $this->assertGreaterThan(0, $depotId);

        $em->clear();

        $depot = $em->getRepository(VODepotVente::class)->find($depotId);
        $this->assertNotNull($depot);
        $this->assertSame('actif', $depot->getStatus());

        $contractDocument = $em->getRepository(VODocument::class)->findOneBy([
            'voDepotVente' => $depot,
            'type' => VODocument::TYPE_CONTRAT_DEPOT_VENTE,
        ]);
        $this->assertNotNull($contractDocument);

        $livrePoliceEntry = $em->getRepository(VOLivrePolice::class)->findOneBy(['voDepotVente' => $depot]);
        $this->assertNotNull($livrePoliceEntry);
        $this->assertSame('depot_vente', $livrePoliceEntry->getType());

        $client->request(
            'POST',
            '/api/vo/depots/' . $depotId . '/restituer',
            [],
            [],
            $this->authHeaders($fixture['user']),
            json_encode([
                'notes' => 'Le déposant récupère sa moto.',
            ], JSON_THROW_ON_ERROR)
        );

        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());

        $em->clear();

        $depot = $em->getRepository(VODepotVente::class)->find($depotId);
        $this->assertNotNull($depot);
        $this->assertSame('restitue', $depot->getStatus());
        $this->assertNotNull($depot->getDateFin());
        $this->assertStringContainsString('[RESTITUTION] Le déposant récupère sa moto.', (string) $depot->getNotes());
    }

    /**
     * @return array{user: User, seller: Client, buyer: Client, deposant: Client, purchaseVehicle: Vehicule, depotVehicle: Vehicule, suffix: string}
     */
    private function createVoFixture(EntityManagerInterface $em): array
    {
        $suffix = bin2hex(random_bytes(4));

        $user = (new User())
            ->setUsername('vo-' . $suffix)
            ->setEmail(sprintf('vo-%s@example.test', $suffix))
            ->setHashedPassword('test')
            ->setPrenom('VO')
            ->setNom('Manager')
            ->setRole('vo_manager')
            ->setAtelierId(1);

        $seller = (new Client())
            ->setAtelierId(1)
            ->setNom('Seller-' . $suffix)
            ->setPrenom('Alice')
            ->setTelephone('0600000001')
            ->setEmail(sprintf('seller-%s@example.test', $suffix))
            ->setAdresse('12 rue du vendeur, Paris');

        $buyer = (new Client())
            ->setAtelierId(1)
            ->setNom('Buyer-' . $suffix)
            ->setPrenom('Bob')
            ->setTelephone('0600000002')
            ->setEmail(sprintf('buyer-%s@example.test', $suffix))
            ->setAdresse('34 avenue de l\'acheteur, Lyon');

        $deposant = (new Client())
            ->setAtelierId(1)
            ->setNom('Deposant-' . $suffix)
            ->setPrenom('Chloe')
            ->setTelephone('0600000003')
            ->setEmail(sprintf('deposant-%s@example.test', $suffix))
            ->setAdresse('56 boulevard du dépôt, Nantes');

        $purchaseVehicle = (new Vehicule())
            ->setAtelierId(1)
            ->setClient($seller)
            ->setPlaque('VOA-' . strtoupper(substr($suffix, 0, 4)))
            ->setMarque('Yamaha')
            ->setModele('Tracer 9')
            ->setAnnee(2022)
            ->setCouleur('Bleu')
            ->setMileage(14500)
            ->setVin('VINACHAT' . strtoupper(str_pad(substr($suffix, 0, 9), 9, '0')))
            ->setDatePremiereMiseEnCirculation(new \DateTime('2022-03-10'));

        $depotVehicle = (new Vehicule())
            ->setAtelierId(1)
            ->setClient($deposant)
            ->setPlaque('VOD-' . strtoupper(substr($suffix, 0, 4)))
            ->setMarque('Honda')
            ->setModele('Africa Twin')
            ->setAnnee(2021)
            ->setCouleur('Rouge')
            ->setMileage(22800)
            ->setVin('VINDEPOT' . strtoupper(str_pad(substr(strrev($suffix), 0, 9), 9, '0')))
            ->setDatePremiereMiseEnCirculation(new \DateTime('2021-06-01'));

        $em->persist($user);
        $em->persist($seller);
        $em->persist($buyer);
        $em->persist($deposant);
        $em->persist($purchaseVehicle);
        $em->persist($depotVehicle);
        $em->flush();

        return [
            'user' => $user,
            'seller' => $seller,
            'buyer' => $buyer,
            'deposant' => $deposant,
            'purchaseVehicle' => $purchaseVehicle,
            'depotVehicle' => $depotVehicle,
            'suffix' => $suffix,
        ];
    }

    private function attachPurchaseComplianceDocuments(EntityManagerInterface $em, VOPurchase $purchase, User $user): void
    {
        foreach ([
            VODocument::TYPE_CERFA_CESSION_ACHAT,
            VODocument::TYPE_CARTE_GRISE,
            VODocument::TYPE_NON_GAGE,
            VODocument::TYPE_PIECE_IDENTITE,
        ] as $type) {
            $document = (new VODocument())
                ->setAtelierId($purchase->getAtelierId())
                ->setType($type)
                ->setFilePath('/tmp/' . $type . '-' . $purchase->getId() . '.pdf')
                ->setOriginalFilename($type . '.pdf')
                ->setMimeType('application/pdf')
                ->setVoPurchase($purchase)
                ->setUploadedBy($user);

            $em->persist($document);
        }
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