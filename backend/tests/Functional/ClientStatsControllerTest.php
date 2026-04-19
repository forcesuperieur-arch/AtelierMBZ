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

class ClientStatsControllerTest extends WebTestCase
{
    public function testStatsAreScopedToAuthenticatedAtelier(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $suffix = bin2hex(random_bytes(4));

        $initialTotal = (int) $em->getRepository(Client::class)->count(['atelierId' => 1]);
        $initialVehicules = (int) $em->getRepository(Vehicule::class)->count(['atelierId' => 1]);
        $initialAvecRdv = (int) $em->getRepository(RendezVous::class)
            ->createQueryBuilder('r')
            ->select('COUNT(DISTINCT r.client)')
            ->where('r.atelierId = :atelierId')
            ->andWhere('r.client IS NOT NULL')
            ->setParameter('atelierId', 1)
            ->getQuery()
            ->getSingleScalarResult();

        $user = (new User())
            ->setUsername('admin-clients-' . $suffix)
            ->setEmail(sprintf('admin-clients-%s@example.test', $suffix))
            ->setHashedPassword('test')
            ->setRole('admin')
            ->setAtelierId(1);
        $em->persist($user);

        $clientAtelierOne = (new Client())
            ->setAtelierId(1)
            ->setNom('ClientA-' . $suffix)
            ->setPrenom('One')
            ->setTelephone('0600000001')
            ->setEmail(sprintf('client-a-%s@example.test', $suffix));
        $em->persist($clientAtelierOne);

        $clientAtelierTwo = (new Client())
            ->setAtelierId(2)
            ->setNom('ClientB-' . $suffix)
            ->setPrenom('Two')
            ->setTelephone('0600000002')
            ->setEmail(sprintf('client-b-%s@example.test', $suffix));
        $em->persist($clientAtelierTwo);

        $vehiculeAtelierOne = (new Vehicule())
            ->setAtelierId(1)
            ->setClient($clientAtelierOne)
            ->setPlaque('AA-101-AA')
            ->setMarque('Yamaha')
            ->setModele('MT-07');
        $em->persist($vehiculeAtelierOne);

        $vehiculeAtelierTwo = (new Vehicule())
            ->setAtelierId(2)
            ->setClient($clientAtelierTwo)
            ->setPlaque('AA-202-AA')
            ->setMarque('Honda')
            ->setModele('CB500');
        $em->persist($vehiculeAtelierTwo);

        $rdvAtelierOne = (new RendezVous())
            ->setAtelierId(1)
            ->setClient($clientAtelierOne)
            ->setVehicule($vehiculeAtelierOne)
            ->setDateRdv(new \DateTime('today'))
            ->setHeureRdv(new \DateTime('09:00'))
            ->setTypeIntervention('Révision')
            ->setStatut('confirme');
        $em->persist($rdvAtelierOne);

        $rdvAtelierTwo = (new RendezVous())
            ->setAtelierId(2)
            ->setClient($clientAtelierTwo)
            ->setVehicule($vehiculeAtelierTwo)
            ->setDateRdv(new \DateTime('today'))
            ->setHeureRdv(new \DateTime('10:00'))
            ->setTypeIntervention('Diagnostic')
            ->setStatut('confirme');
        $em->persist($rdvAtelierTwo);

        $em->flush();

        $client->request('GET', '/api/clients/stats', [], [], $this->authHeaders($user));

        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        $payload = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame($initialTotal + 1, $payload['total'] ?? null);
        $this->assertSame($initialAvecRdv + 1, $payload['avec_rdv'] ?? null);
        $this->assertSame($initialVehicules + 1, $payload['vehicules'] ?? null);

        $em->remove($rdvAtelierOne);
        $em->remove($rdvAtelierTwo);
        $em->remove($vehiculeAtelierOne);
        $em->remove($vehiculeAtelierTwo);
        $em->remove($clientAtelierOne);
        $em->remove($clientAtelierTwo);
        $em->remove($user);
        $em->flush();
    }

    /**
     * @return array<string, string>
     */
    private function authHeaders(User $user): array
    {
        $token = static::getContainer()->get(JWTTokenManagerInterface::class)->create($user);

        return [
            'HTTP_Authorization' => 'Bearer ' . $token,
        ];
    }
}
