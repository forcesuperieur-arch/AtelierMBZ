<?php

namespace App\Tests\Functional;

use App\Entity\Client;
use App\Entity\RendezVous;
use App\Entity\User;
use App\Entity\Vehicule;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class RendezVousWorkflowControllerTest extends WebTestCase
{
    public function testInternalCreationReservesSlotImmediately(): void
    {
        $client = static::createClient();

        $user = $this->createUser();

        $client->request(
            'POST',
            '/api/rendez-vous',
            [],
            [],
            $this->authHeaders($user),
            json_encode([
                'client_prenom' => 'Jean',
                'client_nom' => 'Dupont',
                'client_telephone' => '0600000000',
                'client_email' => 'jean.dupont@example.test',
                'vehicule_marque' => 'Yamaha',
                'vehicule_modele' => 'MT-07',
                'vehicule_plaque' => 'AA-123-BB',
                'date_rdv' => '2026-04-28',
                'heure_debut' => '10:00',
                'type_intervention' => 'Révision',
            ], JSON_THROW_ON_ERROR)
        );

        $this->assertSame(Response::HTTP_CREATED, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
        $payload = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('reserve', $payload['statut'] ?? null);
    }

    public function testConfirmRequiresReservedStatus(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $user = $this->createUser();
        $rdv = $this->createRdv($em, 'en_attente');

        $client->request(
            'POST',
            '/api/rendez-vous/' . $rdv->getId() . '/transition/confirmer',
            [],
            [],
            $this->authHeaders($user),
            json_encode([], JSON_THROW_ON_ERROR)
        );

        $this->assertSame(Response::HTTP_CONFLICT, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
        $payload = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('Le créneau doit d\'abord être réservé avant confirmation.', $payload['error'] ?? null);
        $this->assertSame(['reserver', 'annuler'], $payload['allowed_transitions'] ?? null);
    }

    private function createUser(): User
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $suffix = bin2hex(random_bytes(4));

        $user = (new User())
            ->setUsername('rdv-' . $suffix)
            ->setEmail(sprintf('rdv-%s@example.test', $suffix))
            ->setHashedPassword('test')
            ->setRole('admin')
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