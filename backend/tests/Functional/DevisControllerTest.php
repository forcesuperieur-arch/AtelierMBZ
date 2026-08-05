<?php

namespace App\Tests\Functional;

use App\Entity\Client;
use App\Entity\Devis;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class DevisControllerTest extends WebTestCase
{
    private function authHeaders(User $user): array
    {
        $jwtManager = static::getContainer()->get(JWTTokenManagerInterface::class);

        return [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $jwtManager->create($user),
        ];
    }

    private function createUser(EntityManagerInterface $em, string $role): User
    {
        $suffix = bin2hex(random_bytes(4));
        $user = (new User())
            ->setUsername($role . '-' . $suffix)
            ->setEmail(sprintf('%s-%s@example.test', $role, $suffix))
            ->setHashedPassword('test')
            ->setRole($role)
            ->setAtelierId(1);

        $em->persist($user);
        $em->flush();

        return $user;
    }

    private function createClientFixture(EntityManagerInterface $em): Client
    {
        $suffix = bin2hex(random_bytes(4));
        $client = (new Client())
            ->setNom('Devis')
            ->setPrenom('Test')
            ->setTelephone('0600000000')
            ->setEmail(sprintf('devis-client-%s@example.test', $suffix));

        $em->persist($client);
        $em->flush();

        return $client;
    }

    public function testCreateComputesTotalsServerSideFromLignes(): void
    {
        $webClient = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $admin = $this->createUser($em, 'admin');
        $devisClient = $this->createClientFixture($em);

        // Volontairement AUCUN total envoyé dans le payload : le bug corrigé faisait que le
        // front pouvait poser des totaux arbitraires, ou (avant ce correctif) que les lignes
        // étaient silencieusement ignorées et les totaux restaient à 0.
        $webClient->request(
            'POST',
            '/api/devis',
            [],
            [],
            $this->authHeaders($admin),
            json_encode([
                'client' => '/api/clients/' . $devisClient->getId(),
                'remise_pourcentage' => 10,
                'lignes' => [
                    ['type' => 'forfait_mo', 'designation' => 'Révision', 'quantite' => 1, 'prix_unitaire_ht' => 100, 'taux_tva' => 20],
                    ['type' => 'piece', 'designation' => 'Plaquettes', 'quantite' => 2, 'prix_unitaire_ht' => 25, 'taux_tva' => 20],
                ],
            ]),
        );

        $this->assertSame(Response::HTTP_CREATED, $webClient->getResponse()->getStatusCode());
        $payload = json_decode($webClient->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertNotEmpty($payload['numero_devis'] ?? null);

        $devis = $em->getRepository(Devis::class)->find($payload['id']);
        $this->assertNotNull($devis);
        $this->assertCount(2, $devis->getLignes(), 'les lignes envoyées doivent être persistées, pas ignorées');

        // HT brut = 100 + (25*2) = 150 ; remise 10% = 15 ; HT net = 135 ; TTC net = 135*1.2 = 162
        $this->assertSame('100.00', $devis->getTotalMoHt());
        $this->assertSame('50.00', $devis->getTotalPiecesHt());
        $this->assertSame('135.00', $devis->getTotalHt());
        $this->assertSame('162.00', $devis->getTotalTtc());
        $this->assertSame('15.00', $devis->getRemiseMontant());
    }

    public function testCreateRejectsDevisWithoutAnyLigne(): void
    {
        $webClient = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $admin = $this->createUser($em, 'admin');
        $devisClient = $this->createClientFixture($em);

        $webClient->request(
            'POST',
            '/api/devis',
            [],
            [],
            $this->authHeaders($admin),
            json_encode(['client' => '/api/clients/' . $devisClient->getId(), 'lignes' => []]),
        );

        $this->assertSame(Response::HTTP_BAD_REQUEST, $webClient->getResponse()->getStatusCode());
    }

    public function testCreateRejectsMissingClient(): void
    {
        $webClient = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $admin = $this->createUser($em, 'admin');

        $webClient->request(
            'POST',
            '/api/devis',
            [],
            [],
            $this->authHeaders($admin),
            json_encode(['lignes' => [['designation' => 'Test', 'prix_unitaire_ht' => 10]]]),
        );

        $this->assertSame(Response::HTTP_BAD_REQUEST, $webClient->getResponse()->getStatusCode());
    }

    public function testAccepterIsForbiddenToNonAdminStaff(): void
    {
        $webClient = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $mecanicien = $this->createUser($em, 'mecanicien');
        $devisClient = $this->createClientFixture($em);

        $devis = new Devis();
        $devis->setClient($devisClient);
        $devis->setStatut('envoye');
        $em->persist($devis);
        $em->flush();

        $webClient->request('POST', '/api/devis/' . $devis->getId() . '/accepter', [], [], $this->authHeaders($mecanicien));

        // Le devis représente une décision CLIENT : un compte staff sans droit admin ne doit
        // pas pouvoir l'accepter/refuser à sa place (contrairement au comportement avant fix).
        $this->assertSame(Response::HTTP_FORBIDDEN, $webClient->getResponse()->getStatusCode());
    }

    public function testEnvoyerRejectsDevisWithoutLignes(): void
    {
        $webClient = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $admin = $this->createUser($em, 'admin');
        $devisClient = $this->createClientFixture($em);

        $devis = new Devis();
        $devis->setClient($devisClient);
        $devis->setStatut('brouillon');
        $em->persist($devis);
        $em->flush();

        $webClient->request('POST', '/api/devis/' . $devis->getId() . '/envoyer', [], [], $this->authHeaders($admin));

        $this->assertSame(Response::HTTP_BAD_REQUEST, $webClient->getResponse()->getStatusCode());
    }

    public function testGenericPatchOnDevisIsNoLongerRoutable(): void
    {
        $webClient = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $admin = $this->createUser($em, 'admin');
        $devisClient = $this->createClientFixture($em);

        $devis = new Devis();
        $devis->setClient($devisClient);
        $devis->setStatut('brouillon');
        $em->persist($devis);
        $em->flush();

        // Avant correctif : PATCH /api/devis/{id} (ApiPlatform générique) permettait de poser
        // n'importe quel statut/montant sans passer par les gardes du contrôleur. L'opération a
        // été retirée : la route ne doit plus accepter cette méthode.
        $webClient->request(
            'PATCH',
            '/api/devis/' . $devis->getId(),
            [],
            [],
            array_merge($this->authHeaders($admin), ['CONTENT_TYPE' => 'application/merge-patch+json']),
            json_encode(['statut' => 'converti']),
        );

        $this->assertGreaterThanOrEqual(400, $webClient->getResponse()->getStatusCode());

        $em->refresh($devis);
        $this->assertSame('brouillon', $devis->getStatut());
    }

    public function testDeleteRefusesAcceptedDevis(): void
    {
        $webClient = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $admin = $this->createUser($em, 'admin');
        $devisClient = $this->createClientFixture($em);

        $devis = new Devis();
        $devis->setClient($devisClient);
        $devis->setStatut('accepte');
        $em->persist($devis);
        $em->flush();
        $id = $devis->getId();

        $webClient->request('DELETE', '/api/devis/' . $id, [], [], $this->authHeaders($admin));

        $this->assertSame(Response::HTTP_BAD_REQUEST, $webClient->getResponse()->getStatusCode());
        $this->assertNotNull($em->getRepository(Devis::class)->find($id));
    }
}
