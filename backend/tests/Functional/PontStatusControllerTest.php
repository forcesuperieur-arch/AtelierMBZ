<?php

namespace App\Tests\Functional;

use App\Entity\Pont;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class PontStatusControllerTest extends WebTestCase
{
    public function testStatusFallsBackToConfiguredPontsWhenNoActivePontExists(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $suffix = bin2hex(random_bytes(4));

        $user = (new User())
            ->setUsername('pont-status-' . $suffix)
            ->setEmail(sprintf('pont-status-%s@example.test', $suffix))
            ->setHashedPassword('test')
            ->setRole('admin')
            ->setAtelierId(2);
        $em->persist($user);

        $pont = (new Pont())
            ->setAtelierId(2)
            ->setNom('Pont fallback ' . $suffix)
            ->setTypePont('moto')
            ->setCapaciteKg(500)
            ->setOrdreAffichage(99)
            ->setIsActive(0);
        $em->persist($pont);
        $em->flush();

        $client->request('GET', '/api/ponts/status', [], [], $this->authHeaders($user));

        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        $payload = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($payload);
        $this->assertNotEmpty($payload);
        $this->assertContains($pont->getId(), array_map(static fn (array $item): int => (int) ($item['id'] ?? 0), $payload));

        $em->remove($pont);
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
