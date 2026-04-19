<?php

namespace App\Tests\Functional;

use App\Entity\User;
use App\Entity\Vehicule;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class VehiculeLookupControllerTest extends WebTestCase
{
    public function testLookupReturnsEmptyPayloadInsteadOfErrorForUnknownPlate(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $suffix = bin2hex(random_bytes(4));

        $user = (new User())
            ->setUsername('vehicule-missing-' . $suffix)
            ->setEmail(sprintf('vehicule-missing-%s@example.test', $suffix))
            ->setHashedPassword('test')
            ->setRole('admin')
            ->setAtelierId(3);
        $em->persist($user);
        $em->flush();

        $client->request('GET', '/api/vehicule/ZZ-999-AA', [], [], $this->authHeaders($user));

        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());
        $payload = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertFalse($payload['found'] ?? true);

        $em->remove($user);
        $em->flush();
    }

    public function testLookupFindsVehicleByNormalizedPlateWithoutServerError(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $suffix = bin2hex(random_bytes(4));

        $user = (new User())
            ->setUsername('vehicule-lookup-' . $suffix)
            ->setEmail(sprintf('vehicule-lookup-%s@example.test', $suffix))
            ->setHashedPassword('test')
            ->setRole('admin')
            ->setAtelierId(3);
        $em->persist($user);

        $vehicule = (new Vehicule())
            ->setAtelierId(3)
            ->setPlaque('ZZ999AA')
            ->setMarque('Honda')
            ->setModele('CB500');
        $em->persist($vehicule);
        $em->flush();

        $client->request('GET', '/api/vehicule/ZZ-999-AA', [], [], $this->authHeaders($user));

        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());

        $payload = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('ZZ999AA', $payload['plaque'] ?? null);
        $this->assertSame('Honda', $payload['marque'] ?? null);

        $em->remove($vehicule);
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
