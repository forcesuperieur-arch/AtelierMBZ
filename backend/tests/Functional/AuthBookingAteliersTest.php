<?php

namespace App\Tests\Functional;

use App\Entity\Atelier;
use App\Entity\ConfigAtelier;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class AuthBookingAteliersTest extends WebTestCase
{
    public function testServiceClientOnlySeesAteliersEnabledForSiegeBooking(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $suffix = bin2hex(random_bytes(4));

        $atelierA = (new Atelier())
            ->setNom('Atelier A ' . $suffix)
            ->setSlug('atelier-a-' . $suffix)
            ->setPlan('starter')
            ->setActif(true);
        $atelierB = (new Atelier())
            ->setNom('Atelier B ' . $suffix)
            ->setSlug('atelier-b-' . $suffix)
            ->setPlan('starter')
            ->setActif(true);
        $atelierC = (new Atelier())
            ->setNom('Atelier C ' . $suffix)
            ->setSlug('atelier-c-' . $suffix)
            ->setPlan('starter')
            ->setActif(true);
        $em->persist($atelierA);
        $em->persist($atelierB);
        $em->persist($atelierC);
        $em->flush();

        $configA = (new ConfigAtelier())
            ->setAtelierId((int) $atelierA->getId())
            ->setFeatureModules([
                ...ConfigAtelier::defaultFeatureModules(),
                'rdv_siege' => false,
            ]);
        $configB = (new ConfigAtelier())
            ->setAtelierId((int) $atelierB->getId())
            ->setFeatureModules([
                ...ConfigAtelier::defaultFeatureModules(),
                'rdv_siege' => true,
            ]);
        $configC = (new ConfigAtelier())
            ->setAtelierId((int) $atelierC->getId())
            ->setFeatureModules([
                ...ConfigAtelier::defaultFeatureModules(),
                'rdv_siege' => false,
            ]);
        $em->persist($configA);
        $em->persist($configB);
        $em->persist($configC);

        $user = (new User())
            ->setUsername('svc-rdv-' . $suffix)
            ->setEmail(sprintf('svc-rdv-%s@example.test', $suffix))
            ->setHashedPassword('test')
            ->setRole('service_client')
            ->setAtelierId((int) $atelierA->getId());
        $em->persist($user);
        $em->flush();

        $client->request('GET', '/api/auth/rdv-ateliers', [], [], $this->authHeaders($user));

        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());
        $payload = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $returnedIds = array_values(array_map('intval', array_column($payload, 'id')));
        $this->assertContains((int) $atelierA->getId(), $returnedIds);
        $this->assertContains((int) $atelierB->getId(), $returnedIds);
        $this->assertNotContains((int) $atelierC->getId(), $returnedIds);

        $this->removeById($em, User::class, (int) $user->getId());
        $this->removeById($em, ConfigAtelier::class, (int) $configA->getId());
        $this->removeById($em, ConfigAtelier::class, (int) $configB->getId());
        $this->removeById($em, ConfigAtelier::class, (int) $configC->getId());
        $this->removeById($em, Atelier::class, (int) $atelierA->getId());
        $this->removeById($em, Atelier::class, (int) $atelierB->getId());
        $this->removeById($em, Atelier::class, (int) $atelierC->getId());
        $em->flush();
    }

    public function testServiceClientCanSwitchOnlyToAllowedAtelier(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $suffix = bin2hex(random_bytes(4));

        $atelierA = (new Atelier())
            ->setNom('Atelier Switch A ' . $suffix)
            ->setSlug('atelier-switch-a-' . $suffix)
            ->setPlan('starter')
            ->setActif(true);
        $atelierB = (new Atelier())
            ->setNom('Atelier Switch B ' . $suffix)
            ->setSlug('atelier-switch-b-' . $suffix)
            ->setPlan('starter')
            ->setActif(true);
        $atelierC = (new Atelier())
            ->setNom('Atelier Switch C ' . $suffix)
            ->setSlug('atelier-switch-c-' . $suffix)
            ->setPlan('starter')
            ->setActif(true);
        $em->persist($atelierA);
        $em->persist($atelierB);
        $em->persist($atelierC);
        $em->flush();

        $configB = (new ConfigAtelier())
            ->setAtelierId((int) $atelierB->getId())
            ->setFeatureModules([
                ...ConfigAtelier::defaultFeatureModules(),
                'rdv_siege' => true,
            ]);
        $em->persist($configB);

        $user = (new User())
            ->setUsername('svc-switch-' . $suffix)
            ->setEmail(sprintf('svc-switch-%s@example.test', $suffix))
            ->setHashedPassword('test')
            ->setRole('service_client')
            ->setAtelierId((int) $atelierA->getId());
        $em->persist($user);
        $em->flush();

        $headers = array_merge($this->authHeaders($user), ['CONTENT_TYPE' => 'application/json']);

        $client->request(
            'POST',
            '/api/auth/switch-atelier',
            [],
            [],
            $headers,
            json_encode(['atelier_id' => (int) $atelierC->getId()], JSON_THROW_ON_ERROR)
        );
        $this->assertSame(Response::HTTP_FORBIDDEN, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());

        $client->request(
            'POST',
            '/api/auth/switch-atelier',
            [],
            [],
            $headers,
            json_encode(['atelier_id' => (int) $atelierB->getId()], JSON_THROW_ON_ERROR)
        );

        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());
        $payload = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame((int) $atelierB->getId(), (int) ($payload['active_atelier_id'] ?? 0));

        $this->removeById($em, User::class, (int) $user->getId());
        $this->removeById($em, ConfigAtelier::class, (int) $configB->getId());
        $this->removeById($em, Atelier::class, (int) $atelierA->getId());
        $this->removeById($em, Atelier::class, (int) $atelierB->getId());
        $this->removeById($em, Atelier::class, (int) $atelierC->getId());
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

    private function removeById(EntityManagerInterface $em, string $className, int $id): void
    {
        $entity = $em->getRepository($className)->find($id);
        if ($entity !== null) {
            $em->remove($entity);
        }
    }
}
