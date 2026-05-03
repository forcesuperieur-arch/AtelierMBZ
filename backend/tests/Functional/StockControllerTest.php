<?php

namespace App\Tests\Functional;

use App\Entity\CommandeFournisseur;
use App\Entity\Fournisseur;
use App\Entity\LigneCommandeFournisseur;
use App\Entity\MouvementStock;
use App\Entity\PieceDetachee;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class StockControllerTest extends WebTestCase
{
    public function testAlertesReturnsLowStockPieces(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $fixture = $this->createStockFixture($em);

        $client->request('GET', '/api/stock/alertes', [], [], $this->authHeaders($fixture['user']));
        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        $payload = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $references = array_column($payload, 'reference');
        $this->assertContains('LOW-STOCK-TEST', $references);
        $this->assertNotContains('OK-STOCK-TEST', $references);
    }

    public function testListMouvementsPaginated(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $fixture = $this->createStockFixture($em);

        $client->request('GET', '/api/stock/mouvements?page=1&limit=10', [], [], $this->authHeaders($fixture['user']));
        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        $payload = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertArrayHasKey('member', $payload);
        $this->assertArrayHasKey('totalItems', $payload);
        $this->assertArrayHasKey('page', $payload);
        $this->assertGreaterThanOrEqual(1, $payload['totalItems']);
    }

    public function testCreateMouvementEntree(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $fixture = $this->createStockFixture($em);
        $piece = $fixture['pieceOk'];
        $oldStock = $piece->getQuantiteStock();

        $client->request(
            'POST',
            '/api/stock/mouvements',
            [],
            [],
            $this->authHeaders($fixture['admin']),
            json_encode([
                'piece_id' => $piece->getId(),
                'type' => MouvementStock::TYPE_ENTREE,
                'quantite' => 5,
                'motif' => 'Test fonctionnel entrée',
            ], JSON_THROW_ON_ERROR)
        );

        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
        $payload = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertTrue($payload['success']);
        $this->assertSame($oldStock + 5, $payload['stock']);
    }

    public function testCreateMouvementAjustement(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $fixture = $this->createStockFixture($em);
        $piece = $fixture['pieceOk'];

        $client->request(
            'POST',
            '/api/stock/mouvements',
            [],
            [],
            $this->authHeaders($fixture['admin']),
            json_encode([
                'piece_id' => $piece->getId(),
                'type' => MouvementStock::TYPE_AJUSTEMENT,
                'quantite' => 42,
                'motif' => 'Ajustement test',
            ], JSON_THROW_ON_ERROR)
        );

        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $payload = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(42, $payload['stock']);
    }

    public function testCreateMouvementRequiresAdmin(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $fixture = $this->createStockFixture($em);

        $client->request(
            'POST',
            '/api/stock/mouvements',
            [],
            [],
            $this->authHeaders($fixture['user']),
            json_encode([
                'piece_id' => $fixture['pieceOk']->getId(),
                'type' => MouvementStock::TYPE_ENTREE,
                'quantite' => 1,
            ], JSON_THROW_ON_ERROR)
        );

        $this->assertSame(Response::HTTP_FORBIDDEN, $client->getResponse()->getStatusCode());
    }

    public function testListCommandes(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $fixture = $this->createStockFixture($em);

        $client->request('GET', '/api/stock/commandes', [], [], $this->authHeaders($fixture['user']));
        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        $payload = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertArrayHasKey('member', $payload);
    }

    public function testCreateCommande(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $fixture = $this->createStockFixture($em);

        $client->request(
            'POST',
            '/api/stock/commandes',
            [],
            [],
            $this->authHeaders($fixture['admin']),
            json_encode([
                'fournisseur_id' => $fixture['fournisseur']->getId(),
                'lignes' => [
                    ['piece_id' => $fixture['pieceOk']->getId(), 'quantite' => 5, 'prix_unitaire_ht' => '10.00'],
                ],
            ], JSON_THROW_ON_ERROR)
        );

        $this->assertSame(Response::HTTP_CREATED, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
        $payload = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertArrayHasKey('numero_commande', $payload);
        $this->assertStringStartsWith('CF-', $payload['numero_commande']);
        $this->assertSame('en_attente', $payload['statut']);
    }

    public function testReceiveCommande(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $fixture = $this->createStockFixture($em);
        $commande = $fixture['commande'];
        $piece = $fixture['pieceLow'];
        $oldStock = $piece->getQuantiteStock();
        $ligne = $commande->getLignes()->first();

        $client->request(
            'POST',
            '/api/stock/commandes/' . $commande->getId() . '/recevoir',
            [],
            [],
            $this->authHeaders($fixture['admin']),
            json_encode([
                'lignes' => [
                    ['ligne_id' => $ligne->getId(), 'quantite_recue' => 3],
                ],
            ], JSON_THROW_ON_ERROR)
        );

        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
        $payload = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertTrue($payload['success']);
        $this->assertSame('recue', $payload['statut']);

        $em->clear();
        $updatedPiece = $em->getRepository(PieceDetachee::class)->find($piece->getId());
        $this->assertSame($oldStock + 3, $updatedPiece->getQuantiteStock());
    }

    public function testAnnulerCommande(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $fixture = $this->createStockFixture($em);
        $commande = $fixture['commande'];

        $client->request(
            'POST',
            '/api/stock/commandes/' . $commande->getId() . '/annuler',
            [],
            [],
            $this->authHeaders($fixture['admin'])
        );

        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $payload = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('annulee', $payload['statut']);
    }

    public function testTogglePiece(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $fixture = $this->createStockFixture($em);
        $piece = $fixture['pieceOk'];
        $oldActive = $piece->getIsActive();

        $client->request(
            'POST',
            '/api/stock/pieces/' . $piece->getId() . '/toggle',
            [],
            [],
            $this->authHeaders($fixture['admin'])
        );

        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $payload = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame($oldActive ? 0 : 1, $payload['is_active']);
    }

    public function testInventaire(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $fixture = $this->createStockFixture($em);

        $client->request('GET', '/api/stock/inventaire', [], [], $this->authHeaders($fixture['user']));
        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        $payload = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertArrayHasKey('total_references', $payload);
        $this->assertArrayHasKey('alertes', $payload);
        $this->assertArrayHasKey('valeur_achat', $payload);
        $this->assertArrayHasKey('pieces', $payload);
        $this->assertGreaterThanOrEqual(1, $payload['total_references']);
    }

    public function testStats(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $fixture = $this->createStockFixture($em);

        $client->request('GET', '/api/stock/stats', [], [], $this->authHeaders($fixture['user']));
        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        $payload = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertArrayHasKey('total_references', $payload);
        $this->assertArrayHasKey('alertes', $payload);
        $this->assertArrayHasKey('valeur_achat', $payload);
        $this->assertArrayHasKey('commandes_en_attente', $payload);
        $this->assertArrayHasKey('mouvements_aujourdhui', $payload);
    }

    public function testListFournisseurs(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $fixture = $this->createStockFixture($em);

        $client->request('GET', '/api/stock/fournisseurs', [], [], $this->authHeaders($fixture['user']));
        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        $payload = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($payload);
        $this->assertNotEmpty($payload);
    }

    public function testCreateFournisseur(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $fixture = $this->createStockFixture($em);

        $client->request(
            'POST',
            '/api/stock/fournisseurs',
            [],
            [],
            $this->authHeaders($fixture['admin']),
            json_encode(['nom' => 'Fournisseur Test Fonctionnel'], JSON_THROW_ON_ERROR)
        );

        $this->assertSame(Response::HTTP_CREATED, $client->getResponse()->getStatusCode());
        $payload = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertArrayHasKey('id', $payload);
        $this->assertSame('Fournisseur Test Fonctionnel', $payload['nom']);
    }

    /**
     * @return array{user: User, admin: User, pieceOk: PieceDetachee, pieceLow: PieceDetachee, fournisseur: Fournisseur, commande: CommandeFournisseur}
     */
    private function createStockFixture(EntityManagerInterface $em): array
    {
        $suffix = bin2hex(random_bytes(4));

        $user = (new User())
            ->setUsername('meca-' . $suffix)
            ->setEmail(sprintf('meca-%s@example.test', $suffix))
            ->setHashedPassword('test')
            ->setRole('mecanicien')
            ->setAtelierId(1);

        $admin = (new User())
            ->setUsername('admin-' . $suffix)
            ->setEmail(sprintf('admin-%s@example.test', $suffix))
            ->setHashedPassword('test')
            ->setRole('admin')
            ->setAtelierId(1);

        $fournisseur = new Fournisseur();
        $fournisseur->setNom('Fournisseur ' . $suffix);
        $fournisseur->setAtelierId(1);
        $fournisseur->setIsActive(1);

        $pieceOk = new PieceDetachee();
        $pieceOk->setReference('OK-STOCK-' . strtoupper($suffix));
        $pieceOk->setNom('Pièce stock OK');
        $pieceOk->setQuantiteStock(20);
        $pieceOk->setQuantiteMinimale(5);
        $pieceOk->setPrixAchatHt('10.00');
        $pieceOk->setPrixVenteHt('18.00');
        $pieceOk->setAtelierId(1);
        $pieceOk->setIsActive(1);

        $pieceLow = new PieceDetachee();
        $pieceLow->setReference('LOW-STOCK-' . strtoupper($suffix));
        $pieceLow->setNom('Pièce stock bas');
        $pieceLow->setQuantiteStock(2);
        $pieceLow->setQuantiteMinimale(5);
        $pieceLow->setPrixAchatHt('15.00');
        $pieceLow->setPrixVenteHt('25.00');
        $pieceLow->setAtelierId(1);
        $pieceLow->setIsActive(1);

        $commande = new CommandeFournisseur();
        $commande->setNumeroCommande('CF-TEST-' . strtoupper($suffix));
        $commande->setFournisseur($fournisseur);
        $commande->setAtelierId(1);
        $commande->setStatut('en_attente');
        $commande->setTotalHt('45.00');
        $commande->setTotalTtc('54.00');

        $ligne = new LigneCommandeFournisseur();
        $ligne->setCommande($commande);
        $ligne->setPiece($pieceLow);
        $ligne->setQuantiteDemandee(5);
        $ligne->setPrixUnitaireHt('9.00');
        $ligne->setAtelierId(1);

        $mouvement = new MouvementStock();
        $mouvement->setPiece($pieceOk);
        $mouvement->setType(MouvementStock::TYPE_ENTREE);
        $mouvement->setQuantite(10);
        $mouvement->setMotif('Fixture test');
        $mouvement->setAtelierId(1);

        $em->persist($user);
        $em->persist($admin);
        $em->persist($fournisseur);
        $em->persist($pieceOk);
        $em->persist($pieceLow);
        $em->persist($commande);
        $em->persist($ligne);
        $em->persist($mouvement);
        $em->flush();

        return [
            'user' => $user,
            'admin' => $admin,
            'pieceOk' => $pieceOk,
            'pieceLow' => $pieceLow,
            'fournisseur' => $fournisseur,
            'commande' => $commande,
        ];
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
