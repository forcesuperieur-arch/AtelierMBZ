<?php

namespace App\Tests\Functional;

use App\Entity\Client;
use App\Entity\Mecanicien;
use App\Entity\OrdreReparation;
use App\Entity\RendezVous;
use App\Entity\User;
use App\Entity\Vehicule;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class RendezVousControllerTest extends WebTestCase
{
    public function testReceptionnaireCanCancelRdv(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $user = $this->createUser('receptionnaire');
        $rdv = $this->createRdv($em, 'en_attente');

        $client->request(
            'POST',
            '/api/rendez-vous/' . $rdv->getId() . '/transition/annuler',
            [],
            [],
            $this->authHeaders($user),
            json_encode(['motif' => 'client_desiste'], JSON_THROW_ON_ERROR)
        );

        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
        $payload = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('annule', $payload['statut']);
    }

    public function testMecanicienAssignedCanPauseRdv(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $fixture = $this->createMechanicFixture($em, ['statut' => 'en_cours']);

        $client->request(
            'POST',
            '/api/rendez-vous/' . $fixture['rdv']->getId() . '/transition/mettre_en_pause',
            [],
            [],
            $this->authHeaders($fixture['user'])
        );

        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
        $payload = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('en_pause', $payload['statut']);
    }

    public function testMecanicienNotAssignedCannotPauseRdv(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $fixture = $this->createMechanicFixture($em, ['statut' => 'en_cours']);
        $otherUser = $this->createUser('mecanicien');

        $client->request(
            'POST',
            '/api/rendez-vous/' . $fixture['rdv']->getId() . '/transition/mettre_en_pause',
            [],
            [],
            $this->authHeaders($otherUser)
        );

        $this->assertSame(Response::HTTP_FORBIDDEN, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    }

    public function testComptableCanInvoiceRdv(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $user = $this->createUser('comptable');
        $rdv = $this->createRdv($em, 'termine');

        $client->request(
            'POST',
            '/api/rendez-vous/' . $rdv->getId() . '/transition/facturer',
            [],
            [],
            $this->authHeaders($user)
        );

        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
        $payload = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('facture', $payload['statut']);
    }

    public function testAdminCanDoAnyTransition(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $user = $this->createUser('admin');
        $rdv1 = $this->createRdv($em, 'en_attente');
        $rdv2 = $this->createRdv($em, 'en_cours');
        $rdv3 = $this->createRdv($em, 'termine');

        $client->request(
            'POST',
            '/api/rendez-vous/' . $rdv1->getId() . '/transition/annuler',
            [],
            [],
            $this->authHeaders($user),
            json_encode(['motif' => 'client_desiste'], JSON_THROW_ON_ERROR)
        );
        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());

        $client->request(
            'POST',
            '/api/rendez-vous/' . $rdv2->getId() . '/transition/mettre_en_pause',
            [],
            [],
            $this->authHeaders($user)
        );
        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());

        $client->request(
            'POST',
            '/api/rendez-vous/' . $rdv3->getId() . '/transition/facturer',
            [],
            [],
            $this->authHeaders($user)
        );
        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    }

    public function testUserWithoutRoleCannotCancel(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $user = $this->createUser('service_client');
        $rdv = $this->createRdv($em, 'en_attente');

        $client->request(
            'POST',
            '/api/rendez-vous/' . $rdv->getId() . '/transition/annuler',
            [],
            [],
            $this->authHeaders($user),
            json_encode(['motif' => 'client_desiste'], JSON_THROW_ON_ERROR)
        );

        $this->assertSame(Response::HTTP_FORBIDDEN, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    }

    public function testMecanicienCannotCancel(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $fixture = $this->createMechanicFixture($em, ['statut' => 'en_attente']);

        $client->request(
            'POST',
            '/api/rendez-vous/' . $fixture['rdv']->getId() . '/transition/annuler',
            [],
            [],
            $this->authHeaders($fixture['user']),
            json_encode(['motif' => 'client_desiste'], JSON_THROW_ON_ERROR)
        );

        $this->assertSame(Response::HTTP_FORBIDDEN, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    }

    public function testReceptionnaireCannotPause(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $user = $this->createUser('receptionnaire');
        $rdv = $this->createRdv($em, 'en_cours');

        $client->request(
            'POST',
            '/api/rendez-vous/' . $rdv->getId() . '/transition/mettre_en_pause',
            [],
            [],
            $this->authHeaders($user)
        );

        $this->assertSame(Response::HTTP_FORBIDDEN, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    }

    public function testComptableCannotPause(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $user = $this->createUser('comptable');
        $rdv = $this->createRdv($em, 'en_cours');

        $client->request(
            'POST',
            '/api/rendez-vous/' . $rdv->getId() . '/transition/mettre_en_pause',
            [],
            [],
            $this->authHeaders($user)
        );

        $this->assertSame(Response::HTTP_FORBIDDEN, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    }

    public function testReceptionnaireCannotInvoice(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $user = $this->createUser('receptionnaire');
        $rdv = $this->createRdv($em, 'termine');

        $client->request(
            'POST',
            '/api/rendez-vous/' . $rdv->getId() . '/transition/facturer',
            [],
            [],
            $this->authHeaders($user)
        );

        $this->assertSame(Response::HTTP_FORBIDDEN, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    }

    public function testPermissionCheckRunsBeforeBusinessGuard(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $fixture = $this->createMechanicFixture($em, ['statut' => 'reception']);
        $otherUser = $this->createUser('mecanicien');

        // Unassigned mechanic should get 403, not the business guard 400
        $client->request(
            'POST',
            '/api/rendez-vous/' . $fixture['rdv']->getId() . '/transition/start_travail',
            [],
            [],
            $this->authHeaders($otherUser)
        );
        $this->assertSame(Response::HTTP_FORBIDDEN, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());

        // Assigned mechanic should succeed because OR is signed in fixture
        $client->request(
            'POST',
            '/api/rendez-vous/' . $fixture['rdv']->getId() . '/transition/start_travail',
            [],
            [],
            $this->authHeaders($fixture['user'])
        );
        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
        $payload = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('en_cours', $payload['statut']);
    }

    private function createUser(string $role): User
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $suffix = bin2hex(random_bytes(4));

        $user = (new User())
            ->setUsername('rdv-perm-' . $role . '-' . $suffix)
            ->setEmail(sprintf('rdv-perm-%s-%s@example.test', $role, $suffix))
            ->setHashedPassword('test')
            ->setRole($role)
            ->setAtelierId(1);

        $em->persist($user);
        $em->flush();

        return $user;
    }

    private function createRdv(EntityManagerInterface $em, string $statut): RendezVous
    {
        $suffix = bin2hex(random_bytes(4));

        $client = (new Client())
            ->setAtelierId(1)
            ->setNom('Client-' . $suffix)
            ->setPrenom('Test')
            ->setTelephone('0600000000')
            ->setEmail(sprintf('client-rdv-%s@example.test', $suffix));

        $vehicule = (new Vehicule())
            ->setAtelierId(1)
            ->setClient($client)
            ->setPlaque('RDV-' . strtoupper(substr($suffix, 0, 4)))
            ->setMarque('Honda')
            ->setModele('CB500F')
            ->setTypeMoto('Roadster');

        $rdv = (new RendezVous())
            ->setAtelierId(1)
            ->setClient($client)
            ->setVehicule($vehicule)
            ->setDateRdv(new \DateTime('2026-04-29'))
            ->setHeureRdv(new \DateTime('2026-04-29 09:00:00'))
            ->setTypeIntervention('Diagnostic')
            ->setCommentaire('Demande client')
            ->setStatut($statut);

        $em->persist($client);
        $em->persist($vehicule);
        $em->persist($rdv);
        $em->flush();

        return $rdv;
    }

    private function createMechanicFixture(EntityManagerInterface $em, array $rdvOverrides = []): array
    {
        $suffix = bin2hex(random_bytes(4));

        $user = (new User())
            ->setUsername('meca-perm-' . $suffix)
            ->setEmail(sprintf('meca-perm-%s@example.test', $suffix))
            ->setHashedPassword('test')
            ->setRole('mecanicien')
            ->setAtelierId(1);
        $em->persist($user);
        $em->flush();

        $mecanicien = (new Mecanicien())
            ->setAtelierId(1)
            ->setNom('Mechanic')
            ->setPrenom('Test')
            ->setCouleur('#123456')
            ->setIsActive(1)
            ->setUserId($user->getId());

        $client = (new Client())
            ->setAtelierId(1)
            ->setNom('Client-' . $suffix)
            ->setPrenom('Test')
            ->setTelephone('0600000000')
            ->setEmail(sprintf('client-%s@example.test', $suffix));

        $vehicule = (new Vehicule())
            ->setAtelierId(1)
            ->setClient($client)
            ->setPlaque('MC-' . strtoupper(substr($suffix, 0, 4)))
            ->setMarque('BMW')
            ->setModele('R1250GS')
            ->setTypeMoto('Trail');

        $rdv = (new RendezVous())
            ->setClient($client)
            ->setVehicule($vehicule)
            ->setAtelierId(1)
            ->setDateRdv(new \DateTime('today'))
            ->setHeureRdv(new \DateTime('09:00:00'))
            ->setTypeIntervention('Diagnostic')
            ->setCommentaire((string) ($rdvOverrides['commentaire'] ?? 'Problème client'))
            ->setStatut((string) ($rdvOverrides['statut'] ?? 'reception'))
            ->setKilometrage((int) ($rdvOverrides['kilometrage'] ?? 15000))
            ->setMecanicien($mecanicien);

        $ordre = (new OrdreReparation())
            ->setRendezVous($rdv)
            ->setNumeroOr('OR-' . $suffix)
            ->setTypeOr('initial')
            ->setStatut('signe')
            ->setSignatureClient('signed-data');

        $em->persist($mecanicien);
        $em->persist($client);
        $em->persist($vehicule);
        $em->persist($rdv);
        $em->persist($ordre);
        $em->flush();

        return [
            'user' => $user,
            'mecanicien' => $mecanicien,
            'rdv' => $rdv,
            'ordre' => $ordre,
        ];
    }

    private function authHeaders(User $user): array
    {
        $jwtManager = static::getContainer()->get(JWTTokenManagerInterface::class);

        return [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $jwtManager->create($user),
        ];
    }
}
